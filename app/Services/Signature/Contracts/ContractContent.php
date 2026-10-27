<?php

namespace App\Services\Signature\Contracts;

use App\Support\Cpf;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Str;

/**
 * A forma do conteúdo de um contrato — a cópia de trabalho, o retrato de cada
 * versão, o modelo do setor.
 *
 *     title, establishment, contractor_term,
 *     parties[]       {uid, role: contratada|fiador, term, kind: pj|pf, gender: m|f,
 *                      name, document, address, details, rep_name, rep_cpf, email, phone}
 *     variables[]     {key, label, type, value}
 *     considerations[] html
 *     clauses[]       {uid, title, items[] {uid, level: 1|2, html}}
 *     signers[]       {uid, party: contratante|contratada|fiador|testemunha,
 *                      source: director|person, area_id, user_id, name, cpf, email, phone, caption}
 *
 * `normalize()` é a única porta de entrada: tudo o que vem do formulário (ou
 * de um modelo, ou da biblioteca) passa por aqui antes de ser gravado. Ela
 * NÃO valida regra de negócio — um contrato pela metade é um rascunho válido.
 * O que falta para ir à Gerência é dito por ContractRenderer::problems().
 *
 * O texto das cláusulas é HTML saneado (a allow-list do InfoClube mais
 * listas); os `uid` são o que liga observação, remissão e comparação de
 * versões a uma cláusula, mesmo depois de ela mudar de posição.
 */
class ContractContent
{
    public const ROLE_CONTRATADA = 'contratada';
    public const ROLE_FIADOR = 'fiador';

    public const PARTY_ROLES = [
        self::ROLE_CONTRATADA => 'Contratada',
        self::ROLE_FIADOR => 'Fiador',
    ];

    public const SIGNER_CONTRATANTE = 'contratante';
    public const SIGNER_CONTRATADA = 'contratada';
    public const SIGNER_FIADOR = 'fiador';
    public const SIGNER_TESTEMUNHA = 'testemunha';

    public const SIGNER_PARTIES = [
        self::SIGNER_CONTRATANTE => 'Pelo clube (Contratante)',
        self::SIGNER_CONTRATADA => 'Pela contratada',
        self::SIGNER_FIADOR => 'Fiador',
        self::SIGNER_TESTEMUNHA => 'Testemunha',
    ];

    /** Tags do texto de cláusula além da allow-list padrão. */
    public const HTML_TAGS = ['ul', 'ol', 'li'];

    public const MAX_CLAUSES = 60;
    public const MAX_ITEMS = 80;
    public const MAX_PARTIES = 10;
    public const MAX_SIGNERS = 20;
    public const MAX_VARIABLES = 60;
    public const MAX_CONSIDERATIONS = 20;

    /** Um contrato vazio, como nasce sem modelo. */
    public static function blank(string $title = 'Contrato de prestação de serviços'): array
    {
        return self::normalize([
            'title' => $title,
            'establishment' => array_key_first(config('signature.contracts.establishments', [])),
            'contractor_term' => 'CONTRATANTE',
            'parties' => [['role' => self::ROLE_CONTRATADA, 'term' => 'CONTRATADA', 'kind' => 'pj']],
            'variables' => [],
            'considerations' => [],
            'clauses' => [
                ['title' => 'Objeto', 'items' => [['level' => 1, 'html' => '']]],
            ],
            'signers' => [
                ['party' => self::SIGNER_CONTRATADA, 'source' => 'person'],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $estabelecimentos = array_keys(config('signature.contracts.establishments', []));
        $estabelecimento = (string) ($input['establishment'] ?? '');

        return [
            'title' => self::line($input['title'] ?? '', 200),
            'establishment' => in_array($estabelecimento, $estabelecimentos, true)
                ? $estabelecimento
                : ($estabelecimentos[0] ?? ''),
            'contractor_term' => self::term($input['contractor_term'] ?? '', 'CONTRATANTE'),
            'parties' => self::parties($input['parties'] ?? []),
            'variables' => self::variables($input['variables'] ?? []),
            'considerations' => self::considerations($input['considerations'] ?? []),
            'clauses' => self::clauses($input['clauses'] ?? []),
            'signers' => self::signers($input['signers'] ?? []),
        ];
    }

    /**
     * Os itens de uma cláusula, normalizados — a biblioteca guarda só isto.
     *
     * @param  mixed  $items
     * @return array<int, array{uid: string, level: int, html: string}>
     */
    public static function items(mixed $items): array
    {
        $saida = [];

        foreach (array_slice(self::list($items), 0, self::MAX_ITEMS) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $saida[] = [
                'uid' => self::uid($item['uid'] ?? null),
                // O primeiro item de uma cláusula é sempre do primeiro nível:
                // um 1.1.1 sem 1.1 antes não tem número.
                'level' => $saida === [] ? 1 : (((int) ($item['level'] ?? 1)) === 2 ? 2 : 1),
                'html' => self::html($item['html'] ?? ''),
            ];
        }

        return $saida;
    }

    /**
     * Cópia de cláusulas com uid NOVO — para o que entra num contrato vindo
     * da biblioteca, de um modelo ou do contrato original (aditivo). Duas
     * cláusulas com o mesmo uid no mesmo contrato confundiriam remissão e
     * observação.
     *
     * As remissões internas (`[[ref:uid]]`) são trocadas junto, para continuar
     * apontando para a cópia.
     *
     * @param  array<int, array<string, mixed>>  $clauses
     * @return array<int, array<string, mixed>>
     */
    public static function freshUids(array $clauses): array
    {
        $mapa = [];

        foreach ($clauses as $clausula) {
            $mapa[$clausula['uid'] ?? ''] = self::uid(null);

            foreach ($clausula['items'] ?? [] as $item) {
                $mapa[$item['uid'] ?? ''] = self::uid(null);
            }
        }

        unset($mapa['']);

        $troca = fn(string $html) => (string) preg_replace_callback(
            '/\[\[\s*ref:([a-z0-9]+)\s*\]\]/i',
            fn(array $m) => '[[ref:' . ($mapa[strtolower($m[1])] ?? $m[1]) . ']]',
            $html,
        );

        return array_map(fn(array $clausula) => [
            'uid' => $mapa[$clausula['uid'] ?? ''] ?? self::uid(null),
            'title' => $clausula['title'] ?? '',
            'items' => array_map(fn(array $item) => [
                'uid' => $mapa[$item['uid'] ?? ''] ?? self::uid(null),
                'level' => $item['level'] ?? 1,
                'html' => $troca((string) ($item['html'] ?? '')),
            ], $clausula['items'] ?? []),
        ], $clauses);
    }

    /**
     * A chave de uma variável a partir do rótulo: "Valor global" → valor_global.
     */
    public static function keyFor(string $label): string
    {
        return mb_substr(Str::slug($label, '_'), 0, 40);
    }

    /** Identificador curto e estável de cláusula, item, parte e signatário. */
    public static function uid(mixed $uid): string
    {
        $uid = strtolower((string) $uid);

        return preg_match('/^[a-z0-9]{6,20}$/', $uid) ? $uid : strtolower(Str::random(10));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function parties(mixed $parties): array
    {
        $saida = [];

        foreach (array_slice(self::list($parties), 0, self::MAX_PARTIES) as $parte) {
            if (!is_array($parte)) {
                continue;
            }

            $papel = ($parte['role'] ?? '') === self::ROLE_FIADOR ? self::ROLE_FIADOR : self::ROLE_CONTRATADA;
            $tipo = ($parte['kind'] ?? '') === 'pf' ? 'pf' : 'pj';

            $saida[] = [
                'uid' => self::uid($parte['uid'] ?? null),
                'role' => $papel,
                'term' => self::term($parte['term'] ?? '', $papel === self::ROLE_FIADOR ? 'FIADOR' : 'CONTRATADA'),
                'kind' => $tipo,
                'gender' => ($parte['gender'] ?? '') === 'f' ? 'f' : 'm',
                'name' => self::line($parte['name'] ?? '', 200),
                // Só letras e números: o CNPJ alfanumérico tem letras na raiz.
                'document' => strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) ($parte['document'] ?? ''))),
                'address' => self::line($parte['address'] ?? '', 300),
                'details' => self::line($parte['details'] ?? '', 500),
                'rep_name' => self::line($parte['rep_name'] ?? '', 200),
                'rep_cpf' => Cpf::digits($parte['rep_cpf'] ?? ''),
                'email' => mb_strtolower(self::line($parte['email'] ?? '', 150)),
                'phone' => self::line($parte['phone'] ?? '', 30),
            ];
        }

        return $saida;
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, value: string}>
     */
    private static function variables(mixed $variables): array
    {
        $saida = [];
        $usadas = [];

        foreach (array_slice(self::list($variables), 0, self::MAX_VARIABLES) as $variavel) {
            if (!is_array($variavel)) {
                continue;
            }

            $rotulo = self::line($variavel['label'] ?? '', 120);
            $chave = self::keyFor((string) (($variavel['key'] ?? '') !== '' ? $variavel['key'] : $rotulo));

            if ($rotulo === '' && $chave === '') {
                continue;
            }

            // Chave repetida ganha sufixo: duas variáveis com a mesma chave
            // fariam o texto mostrar sempre a primeira.
            $base = $chave !== '' ? $chave : 'variavel';
            $chave = $base;

            for ($n = 2; isset($usadas[$chave]) || in_array($chave, ContractRenderer::BUILTIN_KEYS, true); $n++) {
                $chave = $base . '_' . $n;
            }

            $usadas[$chave] = true;

            $tipo = (string) ($variavel['type'] ?? '');

            $saida[] = [
                'key' => $chave,
                'label' => $rotulo !== '' ? $rotulo : $chave,
                'type' => ContractVariables::exists($tipo) ? $tipo : ContractVariables::TEXT,
                'value' => self::line($variavel['value'] ?? '', 500),
            ];
        }

        return $saida;
    }

    /**
     * @return array<int, string>
     */
    private static function considerations(mixed $considerations): array
    {
        $saida = [];

        foreach (array_slice(self::list($considerations), 0, self::MAX_CONSIDERATIONS) as $texto) {
            $html = self::html(is_array($texto) ? ($texto['html'] ?? '') : $texto);

            if ($html !== '') {
                $saida[] = $html;
            }
        }

        return $saida;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function clauses(mixed $clauses): array
    {
        $saida = [];

        foreach (array_slice(self::list($clauses), 0, self::MAX_CLAUSES) as $clausula) {
            if (!is_array($clausula)) {
                continue;
            }

            $saida[] = [
                'uid' => self::uid($clausula['uid'] ?? null),
                'title' => self::line($clausula['title'] ?? '', 200),
                'items' => self::items($clausula['items'] ?? []),
            ];
        }

        return $saida;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function signers(mixed $signers): array
    {
        $saida = [];

        foreach (array_slice(self::list($signers), 0, self::MAX_SIGNERS) as $signer) {
            if (!is_array($signer)) {
                continue;
            }

            $parte = (string) ($signer['party'] ?? '');
            $parte = isset(self::SIGNER_PARTIES[$parte]) ? $parte : self::SIGNER_CONTRATADA;

            // Diretor vem da escolha "área:usuário" do formulário, ou já separado.
            $areaId = (int) ($signer['area_id'] ?? 0);
            $userId = (int) ($signer['user_id'] ?? 0);

            if (preg_match('/^(\d+):(\d+)$/', (string) ($signer['director'] ?? ''), $m)) {
                [$areaId, $userId] = [(int) $m[1], (int) $m[2]];
            }

            $fonte = ($signer['source'] ?? '') === 'director' ? 'director' : 'person';

            $saida[] = [
                'uid' => self::uid($signer['uid'] ?? null),
                'party' => $parte,
                'source' => $fonte,
                'area_id' => $fonte === 'director' && $areaId > 0 ? $areaId : null,
                'user_id' => $fonte === 'director' && $userId > 0 ? $userId : null,
                'name' => self::line($signer['name'] ?? '', 200),
                'cpf' => Cpf::digits($signer['cpf'] ?? ''),
                'email' => mb_strtolower(self::line($signer['email'] ?? '', 150)),
                'phone' => self::line($signer['phone'] ?? '', 30),
                'caption' => self::line($signer['caption'] ?? '', 120),
            ];
        }

        return $saida;
    }

    /** Lista na ordem do formulário (que manda índices soltos, às vezes). */
    private static function list(mixed $valor): array
    {
        return is_array($valor) ? array_values($valor) : [];
    }

    private static function line(mixed $valor, int $max): string
    {
        $texto = is_scalar($valor) ? (string) $valor : '';

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $texto)), 0, $max);
    }

    /** O termo de uma parte: sempre em maiúsculas ("CONTRATADA", "LOCATÁRIA"). */
    private static function term(mixed $valor, string $padrao): string
    {
        $termo = mb_strtoupper(self::line($valor, 40));

        return $termo !== '' ? $termo : $padrao;
    }

    /**
     * Texto de cláusula: saneado e sem parágrafo vazio sobrando. Texto puro
     * (sem tag) vira parágrafo, com as quebras de linha.
     */
    private static function html(mixed $valor): string
    {
        $html = is_scalar($valor) ? trim((string) $valor) : '';

        if ($html === '') {
            return '';
        }

        if (!preg_match('/<[a-z][^>]*>/i', $html)) {
            $html = '<p>' . nl2br(e($html), false) . '</p>';
        }

        $limpo = HtmlSanitizer::clean($html, self::HTML_TAGS);

        // O editor do navegador deixa <p><br></p> e <div><br></div> no fim.
        $limpo = (string) preg_replace('#<(p|div)>\s*(<br\s*/?>)?\s*</\1>#i', '', $limpo);

        return mb_substr(trim($limpo), 0, 20000);
    }
}
