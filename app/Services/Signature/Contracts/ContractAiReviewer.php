<?php

namespace App\Services\Signature\Contracts;

use App\Exceptions\ClaudeException;
use App\Models\SignatureContract;
use App\Models\SignatureContractAiReview;
use App\Services\Claude\ClaudeClient;
use Illuminate\Support\Str;

/**
 * Revisão automática do contrato pelo Claude (pedido da Gerência, 27/10/2026).
 *
 * Manda o contrato como o gerente lê na prévia — qualificação das partes,
 * considerandos, cláusulas e signatários, com as variáveis resolvidas — e
 * recebe o básico conferido: um resumo das inconsistências e os apontamentos
 * com sugestão de alteração, cada um ligado a uma cláusula.
 *
 * Privacidade (28/10/2026): o trecho com valores, CPF, documentos ou endereço
 * não vai, a menos que o gerente o marque na tela. No lugar dele segue só um
 * aviso (e o título, no caso de cláusula), para a numeração continuar certa.
 *
 * Só orienta: não aprova, não devolve, não muda o texto. A conferência do
 * próprio Lara (ContractRenderer::problems) segue valendo e vai junto no
 * pedido, para o Claude não repetir o que já está apontado.
 *
 * Mesmo texto, mesma revisão: pedir de novo sem o contrato ter mudado
 * devolve a que já existe, sem nova chamada.
 */
class ContractAiReviewer
{
    public const MAX_FINDINGS = 30;

    private const SYSTEM = <<<'TXT'
Você revisa minutas de contrato de um clube social brasileiro antes de a Gerência aprová-las para assinatura.
Seu papel é conferir o básico e apontar inconsistências, com sugestão objetiva de alteração. Você não aprova nem reprova: quem decide é o gerente.

Confira, entre outros:
- partes: nome, CPF/CNPJ, endereço e representante coerentes entre a qualificação, as cláusulas e os signatários;
- objeto claro e compatível com o restante do contrato;
- valores, percentuais e quantidades: número e valor por extenso iguais, somas e parcelas que fecham, forma e prazo de pagamento definidos;
- prazos e datas: vigência, início, fim, renovação e prazos de aviso coerentes entre si;
- remissões internas ("conforme cláusula X", "item Y") que apontam para o lugar certo;
- termos definidos (ex.: CONTRATADA, LOCATÁRIO) usados sempre do mesmo jeito;
- cláusulas contraditórias, duplicadas ou faltando o essencial ao tipo de contrato (multa, rescisão, foro, obrigações das partes);
- lacunas, campos sem preencher e erros de português que mudem o sentido.

Regras da resposta:
- Escreva em português do Brasil, frases curtas, sem juridiquês desnecessário.
- "resumo": 2 a 5 frases com as inconsistências mais importantes. Se não houver nenhuma relevante, diga isso.
- "apontamentos": no máximo 15, do mais grave ao menos grave. Não invente problema para preencher a lista.
- "clausula": o número da cláusula (1, 2, 3…); use 0 para o que está fora das cláusulas (qualificação, considerandos, assinaturas) ou é geral.
- "trecho": onde está, como aparece no texto (ex.: "Item 4.2", "Qualificação da CONTRATADA").
- "sugestao": a alteração proposta; quando for o caso, já com a redação sugerida.
- Não repita o que a conferência automática do sistema já apontou.
- Campos marcados como lacuna na minuta ainda serão preenchidos: só aponte se faltarem informações essenciais.
- Trechos marcados "[não enviado: …]" foram retirados de propósito, por privacidade. Não aponte a falta deles nem suponha o que dizem.
TXT;

    /** O que tira um trecho da análise por padrão (ver SignatureContractAiReview::SENSITIVE_LABELS). */
    private const SENSITIVE = [
        'valores' => '/R\$|\breais\b|\b\d{1,3}(?:\.\d{3})*,\d{2}\b/iu',
        'cpf' => '/\bCPF\b|\b\d{3}\.\d{3}\.\d{3}-\d{2}\b|\b\d{11}\b/u',
        'documentos' => '/\bCNPJ\b|\bRG\b|\bCNH\b|passaporte|carteira de identidade|inscri[çc][ãa]o (?:estadual|municipal)|\b\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}\b|\b\d{14}\b/iu',
        'endereco' => '/\b(?:rua|avenida|alameda|travessa|rodovia|estrada|pra[çc]a|bairro|CEP|residentes?|com sede)\b|\bav\.\s|\b\d{5}-\d{3}\b/iu',
    ];

    public function __construct(
        private ClaudeClient $claude,
        private ContractRenderer $renderer,
        private ContractService $contracts,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->claude->isConfigured();
    }

    /**
     * Analisa o texto atual do contrato e guarda a revisão. Os trechos
     * sensíveis só vão se a chave estiver em $include (marcados na tela).
     *
     * @param  array<int, string>  $include
     * @return array{review: SignatureContractAiReview, reused: bool}
     *
     * @throws ClaudeException
     */
    public function review(SignatureContract $contract, ?int $userId, ?string $userName, array $include = []): array
    {
        $conteudo = $contract->content ?? [];
        $trechos = $this->sections($conteudo, $contract->number);

        // O hash é do texto inteiro (diz se o contrato mudou); o que foi
        // retido entra na comparação para reaproveitar a revisão.
        $hash = hash('sha256', $this->join($trechos));
        $retidos = collect($trechos)
            ->filter(fn(array $t) => $t['reasons'] !== [] && !in_array($t['key'], $include, true))
            ->map(fn(array $t) => ['key' => $t['key'], 'label' => $t['label'], 'reasons' => $t['reasons']])
            ->values()
            ->all();
        $chaves = array_column($retidos, 'key');
        sort($chaves);

        $existente = $contract->aiReviews()->where('content_hash', $hash)->get()
            ->first(fn(SignatureContractAiReview $r) => $r->withheldKeys() === $chaves);

        if ($existente) {
            return ['review' => $existente, 'reused' => true];
        }

        $texto = $this->join($trechos, $chaves);

        // A chamada pode passar do limite padrão do PHP (30 s).
        $tentativas = max(1, (int) config('claude.max_retries', 1) + 1);
        @set_time_limit((int) (config('claude.timeout', 90) * $tentativas) + 30);

        $resposta = $this->claude->structured(
            model: (string) config('claude.contract_review.model'),
            system: self::SYSTEM,
            prompt: $this->prompt($contract, $texto, $retidos),
            schema: $this->schema(),
            maxTokens: (int) config('claude.contract_review.max_tokens', 16000),
            effort: config('claude.contract_review.effort'),
        );

        $dados = $resposta['data'];
        $totalClausulas = count($conteudo['clauses'] ?? []);

        $review = SignatureContractAiReview::create([
            'signature_contract_id' => $contract->id,
            'content_hash' => $hash,
            'model' => mb_substr($resposta['model'], 0, 60),
            'verdict' => isset(SignatureContractAiReview::VERDICT_LABELS[$dados['veredito'] ?? null])
                ? $dados['veredito']
                : SignatureContractAiReview::VERDICT_ADJUST,
            'summary' => mb_substr(trim((string) ($dados['resumo'] ?? '')), 0, 4000),
            'findings' => $this->findings($dados['apontamentos'] ?? [], $totalClausulas),
            'withheld' => $retidos,
            'input_tokens' => $resposta['input_tokens'],
            'output_tokens' => $resposta['output_tokens'],
            'requested_by' => $userId,
            'requested_by_name' => $userName,
        ]);

        $this->contracts->event($contract, 'ai_reviewed', $userId, $userName, [
            'review_id' => $review->id,
            'verdict' => $review->verdict,
            'findings' => count($review->findings),
            'withheld' => count($retidos),
        ]);

        return ['review' => $review, 'reused' => false];
    }

    /** Se a revisão é do texto que está no contrato agora. */
    public function isCurrent(SignatureContractAiReview $review, SignatureContract $contract): bool
    {
        return hash_equals($review->content_hash, hash('sha256', $this->plainText($contract->content ?? [], $contract->number)));
    }

    /**
     * O contrato em texto corrido, como na prévia (variáveis resolvidas,
     * lacunas marcadas, numeração calculada), sem os trechos de $withheld.
     *
     * @param  array<int, string>  $withheld
     */
    public function plainText(array $content, ?string $number, array $withheld = []): string
    {
        return $this->join($this->sections($content, $number), $withheld);
    }

    /**
     * Os trechos que a tela oferece para incluir: os que têm valores, CPF,
     * documentos ou endereço e, por isso, ficam de fora por padrão.
     *
     * @return array<int, array{key: string, label: string, reasons: array<int, string>}>
     */
    public function sensitiveSections(SignatureContract $contract): array
    {
        return collect($this->sections($contract->content ?? [], $contract->number))
            ->filter(fn(array $t) => $t['reasons'] !== [])
            ->map(fn(array $t) => ['key' => $t['key'], 'label' => $t['label'], 'reasons' => $t['reasons']])
            ->values()
            ->all();
    }

    /**
     * O contrato em trechos: cada parágrafo da qualificação, cada
     * considerando, cada cláusula com seus itens e as assinaturas. Título,
     * número e fecho (key nula) vão sempre. "head" segue mesmo quando o
     * trecho é retido (o título da cláusula), "body" só quando vai.
     *
     * @return array<int, array{key: ?string, label: string, head: string, body: array<int, string>, reasons: array<int, string>}>
     */
    private function sections(array $content, ?string $number): array
    {
        $trechos = [];
        $clausulas = array_values($content['clauses'] ?? []);
        $c = 0;
        $atual = null;

        foreach ($this->renderer->blocks($content, $number) as $i => $bloco) {
            switch ($bloco['type']) {
                case 'heading':
                case 'number':
                    $trechos[] = ['key' => null, 'label' => '', 'head' => $bloco['text'], 'body' => []];
                    break;

                case 'closing':
                    $trechos[] = ['key' => null, 'label' => '', 'head' => $this->text($bloco['html']), 'body' => []];
                    break;

                case 'html':
                    $texto = $this->text($bloco['html']);
                    $label = preg_match('/^([^:\n]{2,40}):/u', $texto, $m) ? 'Qualificação – ' . trim($m[1]) : 'Texto de abertura';
                    $trechos[] = ['key' => 'texto-' . $i, 'label' => $label, 'head' => '', 'body' => [$texto]];
                    break;

                case 'consideration':
                    $trechos[] = [
                        'key' => 'considerando-' . $i,
                        'label' => 'Considerando ' . $bloco['label'],
                        'head' => '',
                        'body' => [$bloco['label'] . ' ' . $this->text($bloco['html'])],
                    ];
                    break;

                case 'clause':
                    $titulo = trim((string) ($clausulas[$c]['title'] ?? ''));
                    $c++;
                    $trechos[] = [
                        'key' => 'clausula-' . $bloco['uid'],
                        'label' => 'Cláusula ' . $c . ($titulo !== '' ? ' – ' . $titulo : ''),
                        'head' => "\n" . $bloco['text'],
                        'body' => [],
                    ];
                    $atual = array_key_last($trechos);
                    break;

                case 'item':
                    if ($atual !== null) {
                        $trechos[$atual]['body'][] = ($bloco['level'] === 2 ? '    ' : '') . $bloco['number'] . ' ' . $this->text($bloco['html']);
                    }
                    break;

                case 'clause_end':
                    $atual = null;
                    break;

                case 'signatures':
                    $trechos[] = [
                        'key' => 'assinaturas',
                        'label' => 'Assinaturas',
                        'head' => '',
                        'body' => ["\nASSINATURAS:\n" . collect($bloco['entries'])
                            ->map(fn(array $s) => '- ' . $s['label'] . ': ' . ($s['name'] ?: '(sem nome)'))
                            ->join("\n")],
                    ];
                    break;
            }
        }

        return array_map(function (array $t) {
            $t['reasons'] = $t['key'] === null ? [] : $this->reasons(implode("\n", $t['body']));

            return $t;
        }, $trechos);
    }

    /** @return array<int, string> as chaves de SENSITIVE que aparecem no texto */
    private function reasons(string $texto): array
    {
        return array_keys(array_filter(self::SENSITIVE, fn(string $regex) => preg_match($regex, $texto) === 1));
    }

    /** @param  array<int, string>  $withheld */
    private function join(array $trechos, array $withheld = []): string
    {
        $linhas = [];

        foreach ($trechos as $t) {
            $linhas[] = $t['head'];

            if ($t['key'] !== null && in_array($t['key'], $withheld, true)) {
                $linhas[] = '[não enviado: ' . $this->reasonsText($t['reasons']) . ']';
            } else {
                array_push($linhas, ...$t['body']);
            }
        }

        return trim(implode("\n", array_filter($linhas, fn($l) => $l !== '')));
    }

    /** @param  array<int, string>  $reasons */
    private function reasonsText(array $reasons): string
    {
        return 'contém ' . collect($reasons)->map(fn($r) => SignatureContractAiReview::SENSITIVE_LABELS[$r] ?? $r)->join(', ', ' e ');
    }

    private function prompt(SignatureContract $contract, string $texto, array $retidos = []): string
    {
        $problemas = collect($this->renderer->problems($contract->content ?? []))
            ->map(fn(array $p) => '- ' . $p['message'])
            ->join("\n");

        $anexos = $contract->attachments()->pluck('label')->map(fn($l) => '- ' . $l)->join("\n");

        $fora = collect($retidos)->map(fn(array $r) => '- ' . $r['label'] . ' (' . $this->reasonsText($r['reasons']) . ')')->join("\n");

        return "Revise a minuta abaixo.\n\n"
            . "Tipo: " . ($contract->kind === SignatureContract::KIND_ADDENDUM ? 'aditivo contratual' : 'contrato') . "\n"
            . "Título: {$contract->title}\n"
            . "Setor que redigiu: " . ($contract->sector_name ?? '—') . "\n\n"
            . "Conferência automática do sistema (já apontado, não repita):\n" . ($problemas ?: '- nada apontado') . "\n\n"
            . "Anexos que seguem com o contrato (só os nomes):\n" . ($anexos ?: '- nenhum') . "\n\n"
            . "Trechos retirados por privacidade (não aponte a falta deles):\n" . ($fora ?: '- nenhum') . "\n\n"
            . "<minuta>\n{$texto}\n</minuta>";
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'veredito' => [
                    'type' => 'string',
                    'enum' => array_keys(SignatureContractAiReview::VERDICT_LABELS),
                    'description' => 'sem_ressalvas, ajustes_recomendados ou revisar_antes_de_aprovar (há problema que muda o sentido ou expõe o clube)',
                ],
                'resumo' => ['type' => 'string', 'description' => 'Breve resumo das inconsistências (2 a 5 frases).'],
                'apontamentos' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'clausula' => ['type' => 'integer', 'description' => 'Número da cláusula; 0 = fora das cláusulas ou geral.'],
                            'trecho' => ['type' => 'string'],
                            'gravidade' => ['type' => 'string', 'enum' => SignatureContractAiReview::SEVERITIES],
                            'problema' => ['type' => 'string'],
                            'sugestao' => ['type' => 'string'],
                        ],
                        'required' => ['clausula', 'trecho', 'gravidade', 'problema', 'sugestao'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['veredito', 'resumo', 'apontamentos'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Os apontamentos limpos: limite de quantidade e tamanho, cláusula que
     * existe, gravidade conhecida.
     *
     * @return array<int, array{clausula: int, trecho: string, gravidade: string, problema: string, sugestao: string}>
     */
    private function findings(mixed $lista, int $totalClausulas): array
    {
        if (!is_array($lista)) {
            return [];
        }

        $saida = [];

        foreach (array_slice($lista, 0, self::MAX_FINDINGS) as $a) {
            if (!is_array($a) || trim((string) ($a['problema'] ?? '')) === '') {
                continue;
            }

            $clausula = (int) ($a['clausula'] ?? 0);

            $saida[] = [
                'clausula' => $clausula >= 1 && $clausula <= $totalClausulas ? $clausula : 0,
                'trecho' => Str::limit(trim((string) ($a['trecho'] ?? '')), 150),
                'gravidade' => in_array($a['gravidade'] ?? null, SignatureContractAiReview::SEVERITIES, true) ? $a['gravidade'] : 'media',
                'problema' => Str::limit(trim((string) $a['problema']), 1500),
                'sugestao' => Str::limit(trim((string) ($a['sugestao'] ?? '')), 2000),
            ];
        }

        return $saida;
    }

    private function text(string $html): string
    {
        $html = preg_replace(['/<li[^>]*>/i', '/<br\s*\/?>/i', '/<\/(p|li|div|h\d)>/i'], ["\n- ", "\n", "\n"], $html);

        $texto = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace(["/[ \t]+/u", "/\n{2,}/"], [' ', "\n"], $texto));
    }
}
