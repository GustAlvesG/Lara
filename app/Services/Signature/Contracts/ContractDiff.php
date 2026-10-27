<?php

namespace App\Services\Signature\Contracts;

/**
 * O que mudou entre duas versões do contrato — para a Gerência não reler o
 * contrato inteiro a cada rodada.
 *
 * Cláusula e item são casados pelo `uid`, não pela posição: mover uma
 * cláusula não a faz parecer apagada e criada de novo. O texto mudado sai
 * com a diferença palavra a palavra (`<del>`/`<ins>`).
 */
class ContractDiff
{
    /** Acima disto (palavras de cada lado), o item sai inteiro como trocado. */
    private const MAX_WORDS = 700;

    /**
     * @return array{
     *     clauses: array<int, array<string, mixed>>,
     *     removed: array<int, string>,
     *     fields: array<int, string>,
     *     changed: bool
     * }
     */
    public function compare(array $antes, array $depois): array
    {
        $anteriores = collect($antes['clauses'] ?? [])->keyBy('uid');
        $clausulas = [];

        foreach ($depois['clauses'] ?? [] as $c => $clausula) {
            $antiga = $anteriores->get($clausula['uid']);
            $itensAntigos = collect($antiga['items'] ?? [])->keyBy('uid');
            $itens = [];

            foreach ($clausula['items'] ?? [] as $item) {
                $velho = $itensAntigos->get($item['uid']);

                if (!$velho) {
                    $itens[] = ['uid' => $item['uid'], 'status' => 'added', 'html' => '<ins>' . e($this->text($item['html'])) . '</ins>'];
                } elseif ($this->text($velho['html']) !== $this->text($item['html']) || ($velho['level'] ?? 1) !== ($item['level'] ?? 1)) {
                    $itens[] = ['uid' => $item['uid'], 'status' => 'changed', 'html' => $this->words($this->text($velho['html']), $this->text($item['html']))];
                }
            }

            $idsAtuais = collect($clausula['items'] ?? [])->pluck('uid')->all();

            foreach ($antiga['items'] ?? [] as $velho) {
                if (!in_array($velho['uid'], $idsAtuais, true)) {
                    $itens[] = ['uid' => $velho['uid'], 'status' => 'removed', 'html' => '<del>' . e($this->text($velho['html'])) . '</del>'];
                }
            }

            $titulo = $antiga && trim((string) $antiga['title']) !== trim((string) $clausula['title'])
                ? $this->words((string) $antiga['title'], (string) $clausula['title'])
                : null;

            $status = !$antiga ? 'added' : (($itens !== [] || $titulo !== null) ? 'changed' : 'same');

            $clausulas[] = [
                'uid' => $clausula['uid'],
                'number' => $c + 1,
                'title' => $clausula['title'],
                'title_diff' => $titulo,
                'status' => $status,
                'items' => $status === 'added' ? [] : $itens,
            ];
        }

        $idsDepois = collect($depois['clauses'] ?? [])->pluck('uid')->all();

        $removidas = collect($antes['clauses'] ?? [])
            ->reject(fn($c) => in_array($c['uid'], $idsDepois, true))
            ->map(fn($c) => (string) ($c['title'] ?: 'Cláusula sem título'))
            ->values()
            ->all();

        $campos = $this->fields($antes, $depois);

        return [
            'clauses' => $clausulas,
            'removed' => $removidas,
            'fields' => $campos,
            'changed' => $campos !== [] || $removidas !== [] || collect($clausulas)->contains(fn($c) => $c['status'] !== 'same'),
        ];
    }

    /**
     * O que mudou fora das cláusulas, numa frase por assunto.
     *
     * @return array<int, string>
     */
    private function fields(array $antes, array $depois): array
    {
        $mudou = [];

        if (($antes['title'] ?? '') !== ($depois['title'] ?? '')) {
            $mudou[] = 'Título: "' . ($antes['title'] ?? '') . '" → "' . ($depois['title'] ?? '') . '"';
        }

        if (($antes['establishment'] ?? '') !== ($depois['establishment'] ?? '')) {
            $mudou[] = 'Estabelecimento (CNPJ do clube) trocado';
        }

        if (($antes['contractor_term'] ?? '') !== ($depois['contractor_term'] ?? '')) {
            $mudou[] = 'Termo do clube: ' . ($antes['contractor_term'] ?? '') . ' → ' . ($depois['contractor_term'] ?? '');
        }

        if ($this->comparable($antes['parties'] ?? []) !== $this->comparable($depois['parties'] ?? [])) {
            $mudou[] = 'Dados das partes (contratada/fiador) alterados';
        }

        $valoresAntes = collect($antes['variables'] ?? [])->mapWithKeys(fn($v) => [$v['key'] => $v]);

        foreach ($depois['variables'] ?? [] as $variavel) {
            $velha = $valoresAntes->get($variavel['key']);

            if (!$velha) {
                $mudou[] = 'Variável nova: ' . $variavel['label'] . ' = ' . $variavel['value'];
            } elseif ($velha['value'] !== $variavel['value'] || $velha['type'] !== $variavel['type']) {
                $mudou[] = 'Variável ' . $variavel['label'] . ': ' . $velha['value'] . ' → ' . $variavel['value'];
            }
        }

        foreach ($valoresAntes as $chave => $velha) {
            if (!collect($depois['variables'] ?? [])->contains('key', $chave)) {
                $mudou[] = 'Variável removida: ' . $velha['label'];
            }
        }

        $texto = fn(array $lista) => array_map(fn($html) => $this->text((string) $html), $lista);

        if ($texto($antes['considerations'] ?? []) !== $texto($depois['considerations'] ?? [])) {
            $mudou[] = 'Considerandos alterados';
        }

        if ($this->comparable($antes['signers'] ?? []) !== $this->comparable($depois['signers'] ?? [])) {
            $mudou[] = 'Signatários alterados';
        }

        return $mudou;
    }

    /** A lista sem os uid, para comparar conteúdo. */
    private function comparable(array $lista): array
    {
        return array_map(function ($linha) {
            unset($linha['uid']);

            return $linha;
        }, $lista);
    }

    /** O texto puro de um trecho de HTML, com o espaço normalizado. */
    private function text(string $html): string
    {
        $texto = str_replace(['</p>', '<br>', '<br/>', '</li>', '<li>'], [' ', ' ', ' ', ' ', ' • '], $html);
        $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    /**
     * Diferença palavra a palavra, pela maior subsequência comum.
     */
    private function words(string $antes, string $depois): string
    {
        $a = $antes === '' ? [] : explode(' ', $antes);
        $b = $depois === '' ? [] : explode(' ', $depois);

        if (count($a) > self::MAX_WORDS || count($b) > self::MAX_WORDS) {
            return '<del>' . e($antes) . '</del> <ins>' . e($depois) . '</ins>';
        }

        $n = count($a);
        $m = count($b);
        $tabela = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $tabela[$i][$j] = $a[$i] === $b[$j]
                    ? $tabela[$i + 1][$j + 1] + 1
                    : max($tabela[$i + 1][$j], $tabela[$i][$j + 1]);
            }
        }

        $saida = [];
        $i = 0;
        $j = 0;

        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $saida[] = e($a[$i]);
                $i++;
                $j++;
            } elseif ($tabela[$i + 1][$j] >= $tabela[$i][$j + 1]) {
                $saida[] = '<del>' . e($a[$i++]) . '</del>';
            } else {
                $saida[] = '<ins>' . e($b[$j++]) . '</ins>';
            }
        }

        while ($i < $n) {
            $saida[] = '<del>' . e($a[$i++]) . '</del>';
        }

        while ($j < $m) {
            $saida[] = '<ins>' . e($b[$j++]) . '</ins>';
        }

        return implode(' ', $saida);
    }
}
