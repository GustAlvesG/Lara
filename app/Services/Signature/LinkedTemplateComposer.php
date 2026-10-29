<?php

namespace App\Services\Signature;

use App\Models\SignatureTemplate;

/**
 * Modelo vinculado: "quem assina X assina também Y" (sentido único).
 *
 * O texto de Y entra NO MESMO documento de X, em página nova, e o traço único
 * de quem assina é carimbado no lugar de assinatura de cada parte. Para todo
 * o resto do módulo, o documento continua tendo UM modelo: este serviço monta
 * em memória um `SignatureTemplate` composto (X + Y + …), e quem lê regra do
 * documento lê dele (`SignatureDocument::effectiveTemplate()`).
 *
 * O vínculo é declarado em X (`linked_templates`, versionado com X), com a
 * correspondência feita no ato de vincular:
 *
 *  - `fields`: campo de Y => campo de X (mesmo tipo) ou null (campo próprio).
 *    Sem entrada, vale a regra padrão: chave igual e tipo igual = mesmo campo.
 *    Campo próprio com chave que já existe ganha sufixo (`nome_2`).
 *  - `parties`: parte de Y (ou `_todos`, o `[[assinatura]]` de Y) => parte de X,
 *    `_todos` (todos que assinam X) ou `_nova` (parte nova, que se soma às de X e
 *    precisa de signatário no documento). Sem entrada: parte com a mesma
 *    chave = a mesma; senão, parte nova; o genérico de Y vai para todos.
 *
 * Y que também exige Z leva Z junto (o composto de Y é o que um documento de
 * Y seria). Cada modelo entra uma vez só: o segundo caminho até ele é
 * ignorado, e é isso que impede ciclo.
 *
 * As regras somam a mais exigente: foto e visto se qualquer um pedir,
 * conferência de identidade mais forte, anexos e itens de revisão unidos,
 * maior prazo de guarda.
 */
class LinkedTemplateComposer
{
    /**
     * Como origem em `parties`, o `[[assinatura]]` (marcador genérico) de Y;
     * como destino, "todos que assinam X". Começa por `_`, que nenhuma chave
     * de parte pode ter (`^[a-z]`).
     */
    public const GENERIC = '_todos';

    /** Destino "parte nova" em `parties`. */
    public const NEW_PARTY = '_nova';

    /**
     * Força da conferência de identidade, para somar a mais exigente. O
     * código no e-mail vence o CPF: prova a posse do e-mail cadastrado, e não
     * só o conhecimento de um número.
     */
    private const IDENTITY_STRENGTH = [
        SignatureTemplate::IDENTITY_NONE => 0,
        SignatureTemplate::IDENTITY_PARTIAL => 1,
        SignatureTemplate::IDENTITY_FULL => 2,
        SignatureTemplate::IDENTITY_EMAIL => 3,
    ];

    /**
     * O modelo composto. Sem vínculo, o próprio modelo.
     *
     * @param  array<int|string, int>|null  $versions  raiz => id da versão usada (o que o documento guardou);
     *                                                 null = versões ativas de agora (documento novo)
     */
    public function compose(SignatureTemplate $template, ?array $versions = null): SignatureTemplate
    {
        if ($template->declaredLinks() === []) {
            return $template;
        }

        $visitados = [$this->rootOf($template) => true];

        return $this->composeTree($template, $versions, $visitados);
    }

    /**
     * Raiz => id da versão de cada modelo que um documento novo de `$template`
     * leva junto. É o que o documento guarda: revisar Y depois não muda o
     * texto de um documento já criado.
     *
     * @return array<int, int>
     */
    public function versionMap(SignatureTemplate $template): array
    {
        return collect($this->compose($template)->composedParts)
            ->mapWithKeys(fn(array $parte) => [$parte['root_id'] => $parte['id']])
            ->all();
    }

    /**
     * Por que um documento novo de `$template` não pode ser criado agora —
     * um modelo exigido sem versão ativa. Vazio = pode.
     *
     * @return array<int, string>
     */
    public function problems(SignatureTemplate $template): array
    {
        return $this->compose($template)->compositionProblems;
    }

    /**
     * As raízes alcançáveis a partir de um modelo pelos vínculos das versões
     * ativas — para recusar ciclo no cadastro.
     *
     * @return array<int, int>
     */
    public function reachableRoots(int $root): array
    {
        $vistos = [];
        $fila = [$root];

        while ($fila !== []) {
            $atual = array_shift($fila);
            $modelo = $this->current($atual);

            foreach ($modelo?->declaredLinks() ?? [] as $vinculo) {
                if (!isset($vistos[$vinculo['root_id']])) {
                    $vistos[$vinculo['root_id']] = true;
                    $fila[] = $vinculo['root_id'];
                }
            }
        }

        return array_keys($vistos);
    }

    /** A versão ativa mais nova de uma raiz. */
    public function current(int $root): ?SignatureTemplate
    {
        return SignatureTemplate::where('root_id', $root)
            ->where('active', true)
            ->where('single_use', false)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @param  array<int|string, int>|null  $versions
     * @param  array<int, bool>  $visitados
     */
    private function composeTree(SignatureTemplate $template, ?array $versions, array &$visitados): SignatureTemplate
    {
        $composto = clone $template;
        $composto->composite = true;
        $composto->composedParts = [];
        $composto->compositionProblems = [];

        // X sem lugar de assinatura ganha o genérico no fim do texto dele — é
        // onde a assinatura sairia se X estivesse sozinho, e não depois de Y.
        $corpo = $this->withSignatureMarker($template);
        $variaveis = $template->declaredVariables();
        $partes = $template->declaredParties();
        $anexos = $template->declaredAttachments();
        $revisao = $template->declaredReviewItems();
        $foto = (bool) $template->requires_photo;
        $visto = (bool) $template->requires_initials;
        $identidade = $template->identity_check ?: SignatureTemplate::IDENTITY_PARTIAL;
        $guarda = $template->retention_months;
        $generico = $template->signature_placeholder ?: '[[assinatura]]';

        foreach ($template->declaredLinks() as $vinculo) {
            $raiz = $vinculo['root_id'];

            if (isset($visitados[$raiz])) {
                continue;
            }

            $visitados[$raiz] = true;

            $versao = $versions !== null
                ? (isset($versions[$raiz]) ? SignatureTemplate::find($versions[$raiz]) : null)
                : $this->current($raiz);

            if (!$versao) {
                // Documento já criado sem este modelo (vínculo acrescentado
                // depois): fica como foi criado. Documento novo: não pode.
                if ($versions === null) {
                    $nome = SignatureTemplate::where('root_id', $raiz)->orderByDesc('version')->value('name');
                    $composto->compositionProblems[] = 'O modelo exigido "' . ($nome ?? '#' . $raiz)
                        . '" está desativado. Reative-o ou tire o vínculo do modelo.';
                }

                continue;
            }

            $filho = $this->composeTree($versao, $versions, $visitados);

            $composto->composedParts[] = [
                'id' => $versao->id,
                'root_id' => $raiz,
                'name' => $versao->name,
                'version' => $versao->version,
            ];
            array_push($composto->composedParts, ...$filho->composedParts);
            array_push($composto->compositionProblems, ...$filho->compositionProblems);

            [$mapaCampos, $variaveis] = $this->mergeFields($variaveis, $filho->declaredVariables(), $vinculo['fields']);
            [$mapaPartes, $partes] = $this->mergeParties($partes, $filho->declaredParties(), $vinculo['parties'], $generico);

            $corpoFilho = $this->rewriteBody($filho, $mapaCampos, $mapaPartes);

            $corpo .= '<div class="linked-part" style="page-break-before: always;">' . $corpoFilho . '</div>';

            $anexos = $this->mergeByKey($anexos, $filho->declaredAttachments(), true);
            $revisao = $this->mergeByKey($revisao, $filho->declaredReviewItems(), false);
            $foto = $foto || (bool) $filho->requires_photo;
            $visto = $visto || (bool) $filho->requires_initials;

            $outra = $filho->identity_check ?: SignatureTemplate::IDENTITY_PARTIAL;
            if ((self::IDENTITY_STRENGTH[$outra] ?? 0) > (self::IDENTITY_STRENGTH[$identidade] ?? 0)) {
                $identidade = $outra;
            }

            if ($filho->retention_months !== null) {
                $guarda = max((int) $guarda, (int) $filho->retention_months);
            }
        }

        $composto->setRawAttributes(array_merge($composto->getAttributes(), [
            'body_html' => $corpo,
            'variables' => json_encode($variaveis, JSON_UNESCAPED_UNICODE),
            'parties' => json_encode($partes, JSON_UNESCAPED_UNICODE),
            'attachments' => json_encode($anexos, JSON_UNESCAPED_UNICODE),
            'review_items' => json_encode($revisao, JSON_UNESCAPED_UNICODE),
            'requires_photo' => $foto,
            'requires_initials' => $visto,
            'identity_check' => $identidade,
            'retention_months' => $guarda,
        ]));

        return $composto;
    }

    /**
     * Junta os campos de Y aos de X.
     *
     * @param  array<int, array<string, mixed>>  $deX
     * @param  array<int, array<string, mixed>>  $deY
     * @param  array<string, ?string>  $correspondencia
     * @return array{0: array<string, string>, 1: array<int, array<string, mixed>>}  chave em Y => chave no composto; campos
     */
    private function mergeFields(array $deX, array $deY, array $correspondencia): array
    {
        $porChave = [];
        foreach ($deX as $i => $campo) {
            $porChave[$campo['key']] = $i;
        }

        $mapa = [];

        foreach ($deY as $campo) {
            $chave = $campo['key'];
            $alvo = array_key_exists($chave, $correspondencia)
                ? $correspondencia[$chave]
                : (isset($porChave[$chave]) ? $chave : null);

            if ($alvo !== null && isset($porChave[$alvo]) && $deX[$porChave[$alvo]]['type'] === $campo['type']) {
                // O mesmo campo: preenchido uma vez, vale nos dois textos. O
                // mais exigente vence.
                $i = $porChave[$alvo];
                $deX[$i]['required'] = $deX[$i]['required'] || $campo['required'];
                $deX[$i]['attendant_only'] = $deX[$i]['attendant_only'] || $campo['attendant_only'];
                $mapa[$chave] = $alvo;

                continue;
            }

            $nova = $this->uniqueKey($chave, array_keys($porChave));
            $campo['key'] = $nova;
            $porChave[$nova] = count($deX);
            $deX[] = $campo;
            $mapa[$chave] = $nova;
        }

        return [$mapa, $deX];
    }

    /**
     * Junta as partes de Y às de X: ligada a uma de X, a todos, ou nova.
     *
     * @param  array<int, array<string, mixed>>  $deX
     * @param  array<int, array<string, mixed>>  $deY
     * @param  array<string, string>  $correspondencia
     * @return array{0: array<string, string>, 1: array<int, array<string, mixed>>}  parte de Y (ou `*`) => marcadores; partes
     */
    private function mergeParties(array $deX, array $deY, array $correspondencia, string $generico): array
    {
        $chavesDeX = array_column($deX, 'key');

        // "Todos que assinam X": os lugares de todas as partes de X e o de
        // quem não tem parte, vizinhos — saem lado a lado.
        $todos = implode('', array_map(fn(string $k) => SignatureTemplate::partyPlaceholder($k), $chavesDeX)) . $generico;

        $mapa = [];

        foreach ($deY as $parte) {
            $chave = $parte['key'];
            $alvo = $correspondencia[$chave] ?? (in_array($chave, $chavesDeX, true) ? $chave : self::NEW_PARTY);

            if ($alvo === self::GENERIC) {
                $mapa[$chave] = $todos;
            } elseif ($alvo !== self::NEW_PARTY && in_array($alvo, $chavesDeX, true)) {
                $mapa[$chave] = SignatureTemplate::partyPlaceholder($alvo);
            } else {
                // Parte nova: soma-se às de X e pede signatário no documento.
                $nova = $this->uniqueKey($chave, array_column($deX, 'key'));
                $deX[] = array_merge($parte, ['key' => $nova]);
                $mapa[$chave] = SignatureTemplate::partyPlaceholder($nova);
            }
        }

        $alvoGenerico = $correspondencia[self::GENERIC] ?? self::GENERIC;
        $mapa[self::GENERIC] = $alvoGenerico !== self::GENERIC && in_array($alvoGenerico, $chavesDeX, true)
            ? SignatureTemplate::partyPlaceholder($alvoGenerico)
            : $todos;

        return [$mapa, $deX];
    }

    /**
     * O texto de Y com as chaves e os lugares de assinatura do composto.
     *
     * O genérico de Y sai primeiro para um marcador provisório: o texto que as
     * partes recebem pode conter o `[[assinatura]]` de X, e uma troca depois
     * da outra o substituiria de novo.
     *
     * @param  array<string, string>  $campos
     * @param  array<string, string>  $partes
     */
    private function rewriteBody(SignatureTemplate $filho, array $campos, array $partes): string
    {
        $generico = $filho->signature_placeholder ?: '[[assinatura]]';
        $provisorio = "\u{E000}generico\u{E000}";

        // Y com lugar de parte e sem o genérico não ganha o genérico: ele viraria
        // "todos que assinam X" e repetiria X dentro de Y.
        $corpo = str_replace($generico, $provisorio, $this->withSignatureMarker($filho));

        $corpo = (string) preg_replace_callback(
            '/\[\[([a-z][a-z0-9_]*)\]\]/',
            fn(array $m) => isset($campos[$m[1]]) ? '[[' . $campos[$m[1]] . ']]' : $m[0],
            $corpo,
        );

        $corpo = (string) preg_replace_callback(
            '/\[\[assinatura:([a-z][a-z0-9_]*)\]\]/',
            fn(array $m) => $partes[$m[1]] ?? $m[0],
            $corpo,
        );

        return str_replace($provisorio, $partes[self::GENERIC], $corpo);
    }

    /**
     * O texto com um lugar de assinatura: sem marcador nenhum, o genérico vai
     * ao fim — onde o documento sozinho poria a assinatura. Texto com lugar
     * de parte já diz onde cada um assina e fica como está (quem não tem
     * parte vai ao fim do documento, como sempre).
     */
    private function withSignatureMarker(SignatureTemplate $template): string
    {
        $corpo = (string) $template->body_html;
        $generico = $template->signature_placeholder ?: '[[assinatura]]';

        if (str_contains($corpo, $generico) || preg_match('/\[\[assinatura:[a-z][a-z0-9_]*\]\]/', $corpo)) {
            return $corpo;
        }

        return $corpo . $generico;
    }

    /**
     * @param  array<int, array<string, mixed>>  $deX
     * @param  array<int, array<string, mixed>>  $deY
     * @return array<int, array<string, mixed>>
     */
    private function mergeByKey(array $deX, array $deY, bool $obrigatorio): array
    {
        $porChave = array_column($deX, null, 'key');

        foreach ($deY as $item) {
            if (isset($porChave[$item['key']])) {
                if ($obrigatorio) {
                    $porChave[$item['key']]['required'] = $porChave[$item['key']]['required'] || $item['required'];
                }

                continue;
            }

            $porChave[$item['key']] = $item;
        }

        return array_values($porChave);
    }

    /** `nome`, ou `nome_2`, `nome_3`… — a primeira livre. */
    private function uniqueKey(string $chave, array $usadas): string
    {
        if (!in_array($chave, $usadas, true)) {
            return $chave;
        }

        $n = 2;
        $base = mb_substr($chave, 0, 55);

        while (in_array($base . '_' . $n, $usadas, true)) {
            $n++;
        }

        return $base . '_' . $n;
    }

    private function rootOf(SignatureTemplate $template): int
    {
        return (int) ($template->root_id ?? $template->id);
    }
}
