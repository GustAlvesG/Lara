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
 * Manda o contrato inteiro — qualificação das partes, considerandos, todas as
 * cláusulas e signatários, já com as variáveis resolvidas, como o gerente lê
 * na prévia — e recebe o básico conferido: um resumo das inconsistências e
 * os apontamentos com sugestão de alteração, cada um ligado a uma cláusula.
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
TXT;

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
     * Analisa o texto atual do contrato e guarda a revisão.
     *
     * @return array{review: SignatureContractAiReview, reused: bool}
     *
     * @throws ClaudeException
     */
    public function review(SignatureContract $contract, ?int $userId, ?string $userName): array
    {
        $conteudo = $contract->content ?? [];
        $texto = $this->plainText($conteudo, $contract->number);
        $hash = hash('sha256', $texto);

        $existente = $contract->aiReviews()->where('content_hash', $hash)->first();

        if ($existente) {
            return ['review' => $existente, 'reused' => true];
        }

        // A chamada pode passar do limite padrão do PHP (30 s).
        $tentativas = max(1, (int) config('claude.max_retries', 1) + 1);
        @set_time_limit((int) (config('claude.timeout', 90) * $tentativas) + 30);

        $resposta = $this->claude->structured(
            model: (string) config('claude.contract_review.model'),
            system: self::SYSTEM,
            prompt: $this->prompt($contract, $texto),
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
            'input_tokens' => $resposta['input_tokens'],
            'output_tokens' => $resposta['output_tokens'],
            'requested_by' => $userId,
            'requested_by_name' => $userName,
        ]);

        $this->contracts->event($contract, 'ai_reviewed', $userId, $userName, [
            'review_id' => $review->id,
            'verdict' => $review->verdict,
            'findings' => count($review->findings),
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
     * lacunas marcadas, numeração calculada).
     */
    public function plainText(array $content, ?string $number): string
    {
        $linhas = [];

        foreach ($this->renderer->blocks($content, $number) as $bloco) {
            $linhas[] = match ($bloco['type']) {
                'heading', 'number' => $bloco['text'],
                'html', 'closing' => $this->text($bloco['html']),
                'consideration' => $bloco['label'] . ' ' . $this->text($bloco['html']),
                'clause' => "\n" . $bloco['text'],
                'item' => ($bloco['level'] === 2 ? '    ' : '') . $bloco['number'] . ' ' . $this->text($bloco['html']),
                'signatures' => "\nASSINATURAS:\n" . collect($bloco['entries'])
                    ->map(fn(array $s) => '- ' . $s['label'] . ': ' . ($s['name'] ?: '(sem nome)'))
                    ->join("\n"),
                default => null,
            };
        }

        return trim(implode("\n", array_filter($linhas, fn($l) => $l !== null && $l !== '')));
    }

    private function prompt(SignatureContract $contract, string $texto): string
    {
        $problemas = collect($this->renderer->problems($contract->content ?? []))
            ->map(fn(array $p) => '- ' . $p['message'])
            ->join("\n");

        $anexos = $contract->attachments()->pluck('label')->map(fn($l) => '- ' . $l)->join("\n");

        return "Revise a minuta abaixo.\n\n"
            . "Tipo: " . ($contract->kind === SignatureContract::KIND_ADDENDUM ? 'aditivo contratual' : 'contrato') . "\n"
            . "Título: {$contract->title}\n"
            . "Setor que redigiu: " . ($contract->sector_name ?? '—') . "\n\n"
            . "Conferência automática do sistema (já apontado, não repita):\n" . ($problemas ?: '- nada apontado') . "\n\n"
            . "Anexos que seguem com o contrato (só os nomes):\n" . ($anexos ?: '- nenhum') . "\n\n"
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
