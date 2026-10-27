<?php

namespace App\Services\Signature\Contracts;

use App\Services\Signature\SignatureFieldTypes;
use App\Support\Cpf;
use App\Support\NumeroPorExtenso;
use Illuminate\Support\Str;

/**
 * Monta o texto do contrato a partir do conteúdo (ContractContent): numera
 * as cláusulas, resolve variáveis e remissões, qualifica as partes e diz o
 * que falta.
 *
 * É o lugar ÚNICO que sabe como o contrato fica escrito — a prévia da tela, o
 * documento de assinatura gerado na aprovação e o arquivo Word saem todos
 * dos mesmos `blocks()`. A lição é a do módulo: texto montado em dois lugares
 * diverge.
 *
 * Por que a numeração é calculada e não guardada: inserir, apagar ou mover
 * uma cláusula renumera tudo, e a remissão (`[[ref:uid]]`) aponta para a
 * cláusula, não para o número — o "cláusula penal do item 7.1" que na
 * verdade era o 6.1 deixa de ser possível.
 */
class ContractRenderer
{
    public const MODE_PREVIEW = 'preview';
    public const MODE_DOCUMENT = 'document';

    /** O campo de data do documento de assinatura (automático, por extenso). */
    public const SIGNING_DATE_KEY = 'data_da_assinatura';

    /** Variáveis que o próprio contrato fornece. Nenhuma variável declarada pode usar estas chaves. */
    public const BUILTIN_KEYS = [
        'contratante', 'contratada', 'contratada_nome', 'contratada_documento', 'contratada_endereco',
        'contratada_representante', 'numero', 'clube_nome', 'clube_cnpj', 'clube_endereco', 'presidente',
    ];

    /**
     * Palavras em maiúsculas que parecem termo de parte e não são — não viram
     * aviso de "quis dizer…".
     */
    private const TERM_LOOKALIKE_IGNORE = ['CONTRATO', 'CONTRATOS'];

    private const BUILTIN_LABELS = [
        'contratante' => 'Termo do clube (ex.: CONTRATANTE), em negrito',
        'contratada' => 'Termo da contratada (ex.: CONTRATADA), em negrito',
        'contratada_nome' => 'Nome da contratada',
        'contratada_documento' => 'CNPJ/CPF da contratada',
        'contratada_endereco' => 'Endereço da contratada',
        'contratada_representante' => 'Representante da contratada',
        'numero' => 'Número do contrato (sai na aprovação)',
        'clube_nome' => 'Nome do clube',
        'clube_cnpj' => 'CNPJ do clube (do estabelecimento escolhido)',
        'clube_endereco' => 'Endereço do clube (do estabelecimento escolhido)',
        'presidente' => 'Nome do presidente',
    ];

    public function __construct(private ContractClub $club)
    {
    }

    /**
     * O contrato em HTML.
     *
     * Prévia: com âncoras por cláusula (observações e comparação apontam
     * para elas) e lacunas visíveis. Documento: o corpo do modelo de
     * assinatura de uso único, com `[[data_da_assinatura]]` e os marcadores
     * `[[assinatura:pN]]` — que o módulo de assinatura resolve.
     *
     * @param  self::MODE_*  $mode
     */
    public function html(array $content, ?string $number, string $mode = self::MODE_PREVIEW): string
    {
        $documento = $mode === self::MODE_DOCUMENT;
        $saida = '';

        foreach ($this->blocks($content, $number, $mode) as $bloco) {
            $saida .= match ($bloco['type']) {
                'heading' => '<p style="text-align:right;margin-left:42%;font-weight:bold;">' . e($bloco['text']) . '</p>',
                'number' => '<p><strong>' . e($bloco['text']) . '</strong></p>',
                'html' => $bloco['html'],
                'consideration' => '<div style="margin-left:24px;">'
                    . $this->prefix($bloco['html'], $bloco['label'], false) . '</div>',
                'clause' => ($documento ? '' : '<section class="ct-clause" id="ct-' . $bloco['uid'] . '" data-clause="' . $bloco['uid'] . '">')
                    . '<h3>' . e($bloco['text']) . '</h3>',
                'item' => '<div' . ($documento ? '' : ' class="ct-item" data-item="' . $bloco['uid'] . '"')
                    . ($bloco['level'] === 2 ? ' style="margin-left:22px;"' : '') . '>'
                    . $this->prefix($bloco['html'], $bloco['number'], true) . '</div>',
                'clause_end' => $documento ? '' : '</section>',
                'closing' => '<p style="margin-top:18px;">' . $bloco['html'] . '</p>',
                'signatures' => $this->signaturesHtml($bloco, $documento),
                default => '',
            };
        }

        return $saida;
    }

    /**
     * O contrato em blocos — a estrutura que a prévia, o documento e o Word
     * leem. Texto já resolvido (variáveis e remissões); HTML saneado.
     *
     * @param  self::MODE_*  $mode
     * @return array<int, array<string, mixed>>
     */
    public function blocks(array $content, ?string $number, string $mode = self::MODE_PREVIEW): array
    {
        $documento = $mode === self::MODE_DOCUMENT;
        $ctx = $this->context($content, $number, $documento);
        $resolve = fn(string $html) => $this->resolveTokens($html, $ctx);

        $blocos = [];

        $blocos[] = ['type' => 'heading', 'text' => $this->headingText($content)];

        if ($number !== null || !$documento) {
            $blocos[] = ['type' => 'number', 'text' => $number ?? (config('signature.contracts.number_prefix') . ' - (número dado na aprovação)')];
        }

        foreach ($this->qualification($content, $ctx) as $html) {
            $blocos[] = ['type' => 'html', 'html' => $html];
        }

        $considerandos = $content['considerations'] ?? [];

        if ($considerandos !== []) {
            $blocos[] = ['type' => 'html', 'html' => '<p>Em conjunto denominadas PARTES; CONSIDERANDO QUE:</p>'];

            foreach ($considerandos as $i => $html) {
                $blocos[] = ['type' => 'consideration', 'label' => '(' . $this->roman($i + 1) . ')', 'html' => $resolve($html)];
            }

            $blocos[] = ['type' => 'html', 'html' => '<p>Resolvem as PARTES firmar o presente instrumento, mediante as cláusulas e condições seguintes:</p>'];
        } else {
            $blocos[] = ['type' => 'html', 'html' => '<p>As PARTES têm entre si justo e acordado o presente instrumento, mediante as cláusulas e condições seguintes:</p>'];
        }

        foreach ($content['clauses'] ?? [] as $c => $clausula) {
            $numeroClausula = $c + 1;

            $blocos[] = [
                'type' => 'clause',
                'uid' => $clausula['uid'],
                'text' => 'CLÁUSULA ' . mb_strtoupper(NumeroPorExtenso::ordinalFeminino($numeroClausula))
                    . (trim((string) $clausula['title']) !== '' ? ' – ' . mb_strtoupper((string) $clausula['title']) : ''),
            ];

            foreach ($this->numberedItems($clausula, $numeroClausula) as $item) {
                $blocos[] = [
                    'type' => 'item',
                    'uid' => $item['uid'],
                    'clause_uid' => $clausula['uid'],
                    'number' => $item['number'],
                    'level' => $item['level'],
                    'html' => $resolve($item['html']),
                ];
            }

            $blocos[] = ['type' => 'clause_end', 'uid' => $clausula['uid']];
        }

        $cidade = e((string) config('signature.contracts.city'));

        $blocos[] = [
            'type' => 'closing',
            'html' => $documento
                ? $cidade . ', [[' . self::SIGNING_DATE_KEY . ']].'
                : $cidade . ', <em>data da assinatura (preenchida sozinha quando a primeira pessoa assinar)</em>.',
            'text' => (string) config('signature.contracts.city') . ', ____ de ______________ de ______.',
        ];

        $blocos[] = ['type' => 'signatures', 'entries' => $this->documentSigners($content)];

        return $blocos;
    }

    /**
     * Quem assina o documento gerado, na ordem do papel: o clube (o
     * presidente à frente), a contratada, os fiadores e as testemunhas. Cada
     * um ganha a sua parte (`p1`, `p2`…) no modelo de uso único.
     *
     * O presidente entra sempre, sem ser declarado: é ele quem representa o
     * clube. Declarado de novo, não é repetido.
     *
     * @return array<int, array<string, mixed>>
     */
    public function documentSigners(array $content): array
    {
        $ordem = [
            ContractContent::SIGNER_CONTRATANTE,
            ContractContent::SIGNER_CONTRATADA,
            ContractContent::SIGNER_FIADOR,
            ContractContent::SIGNER_TESTEMUNHA,
        ];

        $presidente = $this->club->president();
        $termoClube = (string) ($content['contractor_term'] ?? 'CONTRATANTE');
        $contratada = $this->mainParty($content);
        $termoFiador = collect($content['parties'] ?? [])->firstWhere('role', ContractContent::ROLE_FIADOR)['term'] ?? 'FIADOR';

        $lista = [];

        if ($presidente) {
            $lista[] = [
                'party' => ContractContent::SIGNER_CONTRATANTE,
                'source' => 'director',
                'area_id' => $presidente['area_id'],
                'user_id' => $presidente['user_id'],
                'name' => $presidente['name'],
                'cpf' => '',
                'email' => '',
                'phone' => '',
                'caption' => '',
                'president' => true,
            ];
        }

        foreach ($ordem as $parte) {
            foreach ($content['signers'] ?? [] as $signer) {
                if ($signer['party'] !== $parte) {
                    continue;
                }

                $ehPresidente = $presidente
                    && $signer['source'] === 'director'
                    && (int) $signer['area_id'] === $presidente['area_id']
                    && (int) $signer['user_id'] === $presidente['user_id'];

                if ($ehPresidente) {
                    continue;
                }

                $lista[] = $signer + ['president' => false];
            }
        }

        $chave = 0;

        return array_map(function (array $signer) use (&$chave, $termoClube, $contratada, $termoFiador) {
            $base = match ($signer['party']) {
                ContractContent::SIGNER_CONTRATANTE => $termoClube,
                ContractContent::SIGNER_CONTRATADA => trim(($contratada['term'] ?? 'CONTRATADA')
                    . (filled($contratada['name'] ?? null) ? ' — ' . $contratada['name'] : '')),
                ContractContent::SIGNER_FIADOR => $termoFiador,
                default => 'Testemunha',
            };

            return $signer + [
                'key' => 'p' . (++$chave),
                'label' => mb_substr($base . (filled($signer['caption']) ? ' — ' . $signer['caption'] : ''), 0, 190),
            ];
        }, $lista);
    }

    /**
     * O número de cada cláusula e item, por uid — para a escolha de remissão
     * na tela e para a comparação de versões.
     *
     * @return array<string, array{number: string, label: string, kind: string}>
     */
    public function outline(array $content): array
    {
        $mapa = [];

        foreach ($content['clauses'] ?? [] as $c => $clausula) {
            $mapa[$clausula['uid']] = [
                'number' => (string) ($c + 1),
                'label' => 'Cláusula ' . ($c + 1) . ' – ' . ($clausula['title'] ?: 'sem título'),
                'kind' => 'clause',
            ];

            foreach ($this->numberedItems($clausula, $c + 1) as $item) {
                $mapa[$item['uid']] = [
                    'number' => $item['number'],
                    'label' => 'Item ' . $item['number'] . ' – ' . Str::limit(trim(strip_tags($item['html'])), 60),
                    'kind' => 'item',
                ];
            }
        }

        return $mapa;
    }

    /**
     * As variáveis que o texto pode usar, para o botão "Inserir variável".
     *
     * @return array<int, array{token: string, label: string}>
     */
    public function availableTokens(array $content): array
    {
        $lista = [];

        foreach (self::BUILTIN_LABELS as $chave => $rotulo) {
            $lista[] = ['token' => '[[' . $chave . ']]', 'label' => $rotulo];
        }

        foreach ($content['variables'] ?? [] as $variavel) {
            $lista[] = ['token' => '[[' . $variavel['key'] . ']]', 'label' => $variavel['label']];
        }

        return $lista;
    }

    /**
     * O que impede o contrato de ir à Gerência (`error`) e o que vale
     * conferir (`warning`). A mesma lista trava o envio e a aprovação.
     *
     * @return array<int, array{level: string, message: string, clause: ?string}>
     */
    public function problems(array $content): array
    {
        $problemas = [];
        $erro = function (string $mensagem, ?string $clausula = null) use (&$problemas) {
            $problemas[] = ['level' => 'error', 'message' => $mensagem, 'clause' => $clausula];
        };
        $aviso = function (string $mensagem, ?string $clausula = null) use (&$problemas) {
            $problemas[] = ['level' => 'warning', 'message' => $mensagem, 'clause' => $clausula];
        };

        if (trim((string) ($content['title'] ?? '')) === '') {
            $erro('Dê um título ao contrato (ex.: Contrato de prestação de serviços).');
        }

        if (!$this->club->establishment($content['establishment'] ?? null)) {
            $erro('Escolha por qual CNPJ do clube o contrato é feito.');
        }

        if (!$this->club->president()) {
            $erro('Não há titular na área "' . config('signature.contracts.president_area')
                . '" (Assinaturas → Diretoria). O presidente representa o clube e assina o contrato.');
        }

        $this->partyProblems($content, $erro, $aviso);
        $this->variableProblems($content, $erro);
        $this->signerProblems($content, $erro, $aviso);

        if (($content['clauses'] ?? []) === []) {
            $erro('O contrato não tem cláusulas.');
        }

        // Variáveis e remissões: o mesmo resolvedor da montagem, recolhendo o que não fecha.
        $ctx = $this->context($content, null, false);
        $termos = $this->terms($content);

        foreach ($content['considerations'] ?? [] as $i => $html) {
            $onde = 'no considerando (' . $this->roman($i + 1) . ')';
            $this->textProblems($html, $ctx, $termos, $onde, null, $erro, $aviso);
        }

        foreach ($content['clauses'] ?? [] as $c => $clausula) {
            if (trim((string) $clausula['title']) === '') {
                $erro('Dê um título à cláusula ' . ($c + 1) . '.', $clausula['uid']);
            }

            if (($clausula['items'] ?? []) === []) {
                $erro('A cláusula ' . ($c + 1) . ' não tem texto.', $clausula['uid']);
            }

            foreach ($this->numberedItems($clausula, $c + 1) as $item) {
                if (trim(strip_tags($item['html'])) === '') {
                    $erro('O item ' . $item['number'] . ' está vazio.', $clausula['uid']);
                    continue;
                }

                $this->textProblems($item['html'], $ctx, $termos, 'no item ' . $item['number'], $clausula['uid'], $erro, $aviso);
            }
        }

        return $problemas;
    }

    /** Há algum problema que trava o envio? */
    public function hasErrors(array $content): bool
    {
        return collect($this->problems($content))->contains('level', 'error');
    }

    /**
     * Os itens de uma cláusula com o número: 1.1, 1.2, 1.2.1…
     *
     * @return array<int, array{uid: string, level: int, html: string, number: string}>
     */
    private function numberedItems(array $clausula, int $numeroClausula): array
    {
        $primeiro = 0;
        $segundo = 0;
        $saida = [];

        foreach ($clausula['items'] ?? [] as $item) {
            if (($item['level'] ?? 1) === 2 && $primeiro > 0) {
                $segundo++;
                $numero = $numeroClausula . '.' . $primeiro . '.' . $segundo;
                $nivel = 2;
            } else {
                $primeiro++;
                $segundo = 0;
                $numero = $numeroClausula . '.' . $primeiro;
                $nivel = 1;
            }

            $saida[] = ['uid' => $item['uid'], 'level' => $nivel, 'html' => (string) $item['html'], 'number' => $numero];
        }

        return $saida;
    }

    /**
     * Tudo o que um `[[marcador]]` pode virar.
     *
     * @return array{values: array<string, array{0: string, 1: ?string}>, refs: array<string, string>, preview: bool}
     */
    private function context(array $content, ?string $number, bool $documento): array
    {
        $clube = $this->club->establishment($content['establishment'] ?? null);
        $presidente = $this->club->president();
        $parte = $this->mainParty($content);

        $negrito = fn(?string $texto) => filled($texto) ? '<strong>' . e($texto) . '</strong>' : '';

        $valores = [
            'contratante' => [$negrito($content['contractor_term'] ?? 'CONTRATANTE'), null],
            'contratada' => [$negrito($parte['term'] ?? null), $parte ? null : 'o contrato não tem contratada'],
            'contratada_nome' => [e($parte['name'] ?? ''), null],
            'contratada_documento' => [e($this->formatDocument($parte['kind'] ?? 'pj', $parte['document'] ?? '')), null],
            'contratada_endereco' => [e($parte['address'] ?? ''), null],
            'contratada_representante' => [e($parte['rep_name'] ?? ''), null],
            'numero' => [$number !== null ? e($number) : '<em>(número dado na aprovação)</em>', null],
            'clube_nome' => [e($clube['name'] ?? ''), null],
            'clube_cnpj' => [e($clube ? $this->formatDocument('pj', $clube['cnpj']) : ''), null],
            'clube_endereco' => [e($clube['address'] ?? ''), null],
            'presidente' => [e($presidente['name'] ?? ''), null],
        ];

        foreach ($content['variables'] ?? [] as $variavel) {
            [$texto, $falha] = ContractVariables::format($variavel['type'], $variavel['value']);

            $valores[$variavel['key']] = [e($texto), $falha];
        }

        $refs = collect($this->outline($content))->map(fn(array $o) => $o['number'])->all();

        return ['values' => $valores, 'refs' => $refs, 'preview' => !$documento];
    }

    /**
     * Troca os marcadores do texto pelo valor. O que não se resolve fica
     * marcado na prévia — e é problema, então nunca chega ao documento.
     *
     * Os colchetes que sobram saem escapados: um `[[` solto no texto não pode
     * virar marcador do módulo de assinatura (ex.: `[[assinatura]]`).
     */
    private function resolveTokens(string $html, array $ctx): string
    {
        $resolvido = (string) preg_replace_callback(
            '/\[\[\s*([^\[\]<>]{1,80}?)\s*\]\]/u',
            function (array $m) use ($ctx) {
                [$tipo, $chave] = $this->parseToken($m[1]);

                $valor = $tipo === 'ref'
                    ? ($ctx['refs'][$chave] ?? null)
                    : (isset($ctx['values'][$chave]) && $ctx['values'][$chave][0] !== '' ? $ctx['values'][$chave][0] : null);

                if ($valor !== null) {
                    return $valor;
                }

                return $ctx['preview']
                    ? '<span class="ct-missing">' . e($m[0]) . '</span>'
                    : '________________';
            },
            $html,
        );

        return str_replace(['[[', ']]'], ['&#91;&#91;', '&#93;&#93;'], $resolvido);
    }

    /**
     * "ref:abc123" → ['ref', 'abc123']; "Valor global" → ['var', 'valor_global'].
     *
     * @return array{0: string, 1: string}
     */
    private function parseToken(string $token): array
    {
        $token = trim(html_entity_decode($token, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (preg_match('/^ref:\s*([a-z0-9]+)$/i', $token, $m)) {
            return ['ref', strtolower($m[1])];
        }

        return ['var', ContractContent::keyFor($token)];
    }

    /**
     * Problemas do texto de um item ou considerando: marcador que não
     * existe, variável sem valor, termo de parte digitado errado, remissão
     * digitada à mão.
     */
    private function textProblems(
        string $html,
        array $ctx,
        array $termos,
        string $onde,
        ?string $clausula,
        callable $erro,
        callable $aviso,
    ): void {
        preg_match_all('/\[\[\s*([^\[\]<>]{1,80}?)\s*\]\]/u', $html, $marcadores);

        foreach (array_unique($marcadores[1]) as $token) {
            [$tipo, $chave] = $this->parseToken($token);

            if ($tipo === 'ref') {
                if (!isset($ctx['refs'][$chave])) {
                    $erro('Remissão ' . $onde . ' aponta para uma cláusula ou item que não existe mais. Refaça a remissão.', $clausula);
                }

                continue;
            }

            if (!isset($ctx['values'][$chave])) {
                $erro('A variável [[' . $token . ']] ' . $onde . ' não existe. Crie a variável ou corrija o nome.', $clausula);
            } elseif ($ctx['values'][$chave][0] === '' && $ctx['values'][$chave][1] === null && !in_array($chave, ['numero'], true)) {
                $erro('A variável [[' . $token . ']] ' . $onde . ' está sem valor.', $clausula);
            }
        }

        $texto = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // "CONTRADATA", "CONTRATADO", "LOCADORA": parecido com o termo de uma parte, mas diferente.
        preg_match_all('/\b\p{Lu}{6,}\b/u', $texto, $palavras);

        foreach (array_unique($palavras[0]) as $palavra) {
            if (in_array($palavra, $termos, true) || in_array($palavra, self::TERM_LOOKALIKE_IGNORE, true)) {
                continue;
            }

            foreach ($termos as $termo) {
                if (levenshtein(Str::ascii($palavra), Str::ascii($termo)) <= 2) {
                    $aviso('"' . $palavra . '" ' . $onde . ': quis dizer ' . $termo . '? Use a variável para não errar o termo.', $clausula);
                    break;
                }
            }
        }

        // "conforme o item 7.1" digitado: não acompanha quando a numeração muda.
        if (preg_match_all('/\b(cl[áa]usula|item|itens|subitem)\s+(\d+(?:\.\d+)*)\b/iu', $texto, $digitadas, PREG_SET_ORDER)) {
            $numeros = array_flip(array_values($ctx['refs']));

            foreach ($digitadas as $m) {
                $aviso(isset($numeros[$m[2]])
                    ? 'Remissão digitada ' . $onde . ' ("' . $m[0] . '"): se a numeração mudar, ela fica errada. Use "Inserir remissão".'
                    : 'Remissão digitada ' . $onde . ' ("' . $m[0] . '") aponta para um número que não existe neste contrato.', $clausula);
            }
        }
    }

    private function partyProblems(array $content, callable $erro, callable $aviso): void
    {
        $partes = $content['parties'] ?? [];

        if (!collect($partes)->contains('role', ContractContent::ROLE_CONTRATADA)) {
            $erro('Informe a contratada (a outra parte do contrato).');
        }

        foreach ($partes as $i => $parte) {
            $quem = ($parte['name'] ?: ContractContent::PARTY_ROLES[$parte['role']] . ' ' . ($i + 1));

            if ($parte['name'] === '') {
                $erro('Falta o nome de ' . mb_strtolower(ContractContent::PARTY_ROLES[$parte['role']]) . ' (parte ' . ($i + 1) . ').');
            }

            $campo = ['type' => $parte['kind'] === 'pf' ? SignatureFieldTypes::CPF : SignatureFieldTypes::CNPJ];
            [, $falha] = SignatureFieldTypes::parse($campo, $parte['document']);

            if ($parte['document'] === '' || $falha !== null) {
                $erro(($parte['kind'] === 'pf' ? 'CPF' : 'CNPJ') . ' de ' . $quem . ($parte['document'] === '' ? ' não informado.' : ' inválido.'));
            }

            if ($parte['address'] === '') {
                $erro('Falta o endereço de ' . $quem . '.');
            }

            if ($parte['kind'] === 'pj') {
                if ($parte['rep_name'] === '') {
                    $erro('Falta o representante de ' . $quem . ' (quem assina pela empresa).');
                }

                [, $falhaCpf] = SignatureFieldTypes::parse(['type' => SignatureFieldTypes::CPF], $parte['rep_cpf']);

                if ($parte['rep_cpf'] === '' || $falhaCpf !== null) {
                    $erro('CPF do representante de ' . $quem . ($parte['rep_cpf'] === '' ? ' não informado.' : ' inválido.'));
                }
            }
        }
    }

    private function variableProblems(array $content, callable $erro): void
    {
        foreach ($content['variables'] ?? [] as $variavel) {
            [$texto, $falha] = ContractVariables::format($variavel['type'], $variavel['value']);

            if ($falha !== null) {
                $erro('Variável "' . $variavel['label'] . '": ' . $falha);
            } elseif ($texto === '') {
                $erro('Variável "' . $variavel['label'] . '" sem valor.');
            }
        }
    }

    private function signerProblems(array $content, callable $erro, callable $aviso): void
    {
        $signers = $content['signers'] ?? [];

        if (!collect($signers)->contains('party', ContractContent::SIGNER_CONTRATADA)) {
            $erro('Informe quem assina pela contratada.');
        }

        if (!collect($signers)->contains('party', ContractContent::SIGNER_TESTEMUNHA)) {
            $aviso('O contrato está sem testemunhas.');
        }

        foreach ($signers as $i => $signer) {
            $quem = 'Signatário ' . ($i + 1) . ($signer['name'] !== '' ? ' (' . $signer['name'] . ')' : '');

            if ($signer['source'] === 'director') {
                if (!$signer['area_id'] || !$signer['user_id']) {
                    $erro($quem . ': escolha o diretor.');
                }

                continue;
            }

            if ($signer['name'] === '') {
                $erro($quem . ': falta o nome.');
            }

            [, $falha] = SignatureFieldTypes::parse(['type' => SignatureFieldTypes::CPF], $signer['cpf']);

            if ($signer['cpf'] === '' || $falha !== null) {
                $erro($quem . ': CPF ' . ($signer['cpf'] === '' ? 'não informado.' : 'inválido.'));
            }

            if ($signer['email'] !== '' && !filter_var($signer['email'], FILTER_VALIDATE_EMAIL)) {
                $erro($quem . ': e-mail inválido.');
            }

            if ($signer['email'] === '' && $signer['party'] !== ContractContent::SIGNER_TESTEMUNHA) {
                $aviso($quem . ': sem e-mail, não dá para mandar o link de assinatura a distância.');
            }
        }
    }

    /**
     * Qualificação das partes: o clube (pelo estabelecimento escolhido,
     * representado pelo presidente) e cada contratada e fiador.
     *
     * @return array<int, string>
     */
    private function qualification(array $content, array $ctx): array
    {
        $clube = $this->club->establishment($content['establishment'] ?? null);
        $presidente = $this->club->president();
        $lacuna = $ctx['preview'] ? '<span class="ct-missing">____</span>' : '________________';
        $ou = fn(?string $valor) => filled($valor) ? e($valor) : $lacuna;

        $paragrafos = ['<p>Pelo presente instrumento particular, as partes a seguir qualificadas:</p>'];

        $paragrafos[] = '<p><strong>' . e($content['contractor_term'] ?? 'CONTRATANTE') . ':</strong> <strong>'
            . $ou($clube['name'] ?? null) . '</strong>, pessoa jurídica de direito privado, com sede à '
            . $ou($clube['address'] ?? null) . ', inscrito no CNPJ sob o nº '
            . $ou($clube ? $this->formatDocument('pj', $clube['cnpj']) : null)
            . (filled($clube['state_registration'] ?? null) ? ', Inscrição Estadual nº ' . e($clube['state_registration']) : '')
            . ', neste ato representado por seu Presidente, ' . $ou($presidente['name'] ?? null) . ';</p>';

        foreach ($content['parties'] ?? [] as $parte) {
            $inicio = '<p><strong>' . e($parte['term']) . ':</strong> <strong>' . $ou($parte['name']) . '</strong>';
            $detalhes = filled($parte['details']) ? ', ' . e($parte['details']) : '';
            $documento = $ou($this->formatDocument($parte['kind'], $parte['document']));

            if ($parte['kind'] === 'pj') {
                $paragrafos[] = $inicio . ', pessoa jurídica de direito privado, com sede à ' . $ou($parte['address'])
                    . ', inscrita no CNPJ sob o nº ' . $documento . $detalhes
                    . ', neste ato representada por ' . $ou($parte['rep_name'])
                    . ', CPF nº ' . $ou($parte['rep_cpf'] !== '' ? Cpf::format($parte['rep_cpf']) : null) . ';</p>';
            } else {
                $paragrafos[] = $inicio . $detalhes . ', residente à ' . $ou($parte['address'])
                    . ', ' . ($parte['gender'] === 'f' ? 'inscrita' : 'inscrito') . ' no CPF sob o nº ' . $documento . ';</p>';
            }
        }

        return $paragrafos;
    }

    /**
     * O título no alto, à direita, como nos contratos do clube: "CONTRATO DE
     * PRESTAÇÃO DE SERVIÇOS, QUE ENTRE SI CELEBRAM CLUBE DOS FUNCIONÁRIOS DA
     * CSN E A.C.S TRADING LTDA."
     */
    private function headingText(array $content): string
    {
        $nomes = collect($content['parties'] ?? [])
            ->where('role', ContractContent::ROLE_CONTRATADA)
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        $outra = $nomes === [] ? '…' : (count($nomes) === 1 ? $nomes[0] : implode(', ', array_slice($nomes, 0, -1)) . ' E ' . end($nomes));

        return mb_strtoupper(rtrim((string) ($content['title'] ?? ''), '. ')
            . ', QUE ENTRE SI CELEBRAM CLUBE DOS FUNCIONÁRIOS DA CSN E ' . $outra) . '.';
    }

    private function signaturesHtml(array $bloco, bool $documento): string
    {
        $entradas = $bloco['entries'];
        $partes = collect($entradas)->reject(fn($s) => $s['party'] === ContractContent::SIGNER_TESTEMUNHA)->values();
        $testemunhas = collect($entradas)->where('party', ContractContent::SIGNER_TESTEMUNHA)->values();

        if ($documento) {
            // Marcadores vizinhos saem lado a lado (SignatureDocumentRenderer::placeSignatures).
            $html = $partes->map(fn($s) => '[[assinatura:' . $s['key'] . ']]')->join('');

            if ($testemunhas->isNotEmpty()) {
                $html .= '<p style="margin-top:22px;"><strong>Testemunhas:</strong></p>'
                    . $testemunhas->map(fn($s) => '[[assinatura:' . $s['key'] . ']]')->join('');
            }

            return $html;
        }

        $linha = fn(array $s) => '<div class="ct-sig"><div class="ct-sig-line"></div><strong>'
            . e($s['name'] !== '' ? $s['name'] : '(nome a informar)') . '</strong><br><span>'
            . e($s['label']) . ($s['source'] === 'director' ? ' (diretoria)' : '') . '</span></div>';

        $html = '<div class="ct-sigs">' . $partes->map($linha)->join('') . '</div>';

        if ($testemunhas->isNotEmpty()) {
            $html .= '<p><strong>Testemunhas:</strong></p><div class="ct-sigs">' . $testemunhas->map($linha)->join('') . '</div>';
        }

        return $html;
    }

    /**
     * Põe o número no começo do texto: "<p><strong>1.1</strong> - O objeto…".
     * Texto que não começa por parágrafo ganha um só para o número.
     */
    private function prefix(string $html, string $rotulo, bool $comHifen): string
    {
        $marca = '<strong>' . e($rotulo) . '</strong>' . ($comHifen ? ' - ' : ' ');

        if (preg_match('/^\s*<p>/i', $html)) {
            return (string) preg_replace('/^\s*<p>/i', '<p>' . $marca, $html, 1);
        }

        return '<p>' . rtrim($marca, ' -') . '</p>' . $html;
    }

    /** A contratada principal (a primeira), ou null. */
    private function mainParty(array $content): ?array
    {
        return collect($content['parties'] ?? [])->firstWhere('role', ContractContent::ROLE_CONTRATADA);
    }

    /** @return array<int, string> os termos das partes, em maiúsculas */
    private function terms(array $content): array
    {
        return collect([$content['contractor_term'] ?? 'CONTRATANTE'])
            ->concat(collect($content['parties'] ?? [])->pluck('term'))
            ->filter()
            ->map(fn($t) => mb_strtoupper((string) $t))
            ->unique()
            ->values()
            ->all();
    }

    private function formatDocument(string $kind, string $document): string
    {
        if ($document === '') {
            return '';
        }

        return SignatureFieldTypes::format(
            ['type' => $kind === 'pf' ? SignatureFieldTypes::CPF : SignatureFieldTypes::CNPJ],
            $document,
        );
    }

    private function roman(int $n): string
    {
        $mapa = ['x' => 10, 'ix' => 9, 'v' => 5, 'iv' => 4, 'i' => 1];
        $saida = '';

        foreach ($mapa as $letra => $valor) {
            while ($n >= $valor) {
                $saida .= $letra;
                $n -= $valor;
            }
        }

        return $saida;
    }
}
