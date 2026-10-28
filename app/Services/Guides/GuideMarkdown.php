<?php

namespace App\Services\Guides;

use Illuminate\Support\Str;

/**
 * As marcações próprias dos guias do usuário (docs/guias) e as conversões que
 * a busca e o leitor de documentação precisam fazer do mesmo jeito.
 *
 * Um guia é Markdown comum com duas coisas a mais:
 *
 *   ---
 *   modulo: Ingressos
 *   palavras: ingresso, evento, festa
 *   ---
 *
 * no topo (o módulo como aparece no menu e os sinônimos que as pessoas usam ao
 * perguntar), e, logo abaixo de um título, uma linha
 *
 *   <!-- telas: tickets.checkin.index -->     (ou  <!-- permissoes: x, y -->)
 *
 * com as telas do menu onde aquela tarefa é feita. As duas somem na tela de
 * Documentação: o leitor escapa HTML, então o comentário apareceria como texto.
 */
final class GuideMarkdown
{
    private const ANNOTATION = '/^\s*<!--\s*(telas|permissoes)\s*:\s*(.*?)\s*-->\s*$/u';

    /**
     * @return array{0: array<string, string>, 1: string} [cabeçalho, corpo]
     */
    public static function splitFrontMatter(string $markdown): array
    {
        $markdown = str_replace("\r\n", "\n", $markdown);
        // BOM de editor no Windows quebraria o "---" da primeira linha.
        $markdown = preg_replace('/^\x{FEFF}/u', '', $markdown);

        if (!preg_match('/^---\n(.*?)\n---\n?/s', $markdown, $m)) {
            return [[], $markdown];
        }

        $meta = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (preg_match('/^\s*([a-z_]+)\s*:\s*(.*?)\s*$/u', $line, $kv)) {
                $meta[$kv[1]] = $kv[2];
            }
        }

        return [$meta, substr($markdown, strlen($m[0]))];
    }

    /**
     * A linha é uma anotação? Devolve [tipo, valores].
     *
     * @return array{0: string, 1: list<string>}|null
     */
    public static function annotation(string $line): ?array
    {
        if (!preg_match(self::ANNOTATION, $line, $m)) {
            return null;
        }

        return [$m[1], self::list($m[2])];
    }

    /** Markdown do guia sem o cabeçalho e sem as anotações — o que a tela mostra. */
    public static function forDisplay(string $markdown): string
    {
        [, $body] = self::splitFrontMatter($markdown);

        $lines = array_filter(
            explode("\n", $body),
            fn (string $line) => self::annotation($line) === null
        );

        return implode("\n", $lines);
    }

    /**
     * Texto puro de um trecho de Markdown, para a IA: sem ênfase, sem link
     * (fica o texto dele), tabelas como "célula — célula".
     */
    public static function toPlainText(string $markdown): string
    {
        $lines = [];

        foreach (explode("\n", str_replace("\r\n", "\n", $markdown)) as $line) {
            if (self::annotation($line) !== null) {
                continue;
            }

            $trimmed = trim($line);

            // Linha separadora de tabela: |---|:---:|
            if (preg_match('/^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?$/', $trimmed)) {
                continue;
            }

            if (str_starts_with($trimmed, '|')) {
                $cells = array_map('trim', explode('|', trim($trimmed, '|')));
                $line = implode(' — ', array_filter($cells, fn ($c) => $c !== ''));
            }

            $lines[] = self::inline(preg_replace('/^\s*>\s?/', '', $line));
        }

        $text = implode("\n", $lines);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /** Texto de um título (sem # nem ênfase), base da âncora. */
    public static function headingText(string $markdownHeading): string
    {
        return trim(self::inline(preg_replace('/\s+#+\s*$/', '', $markdownHeading)));
    }

    /**
     * Âncora de um título, repetida com sufixo (-1, -2…) quando o mesmo texto
     * já apareceu no documento. O leitor de documentação e a busca de guias
     * chamam com o mesmo `$seen`, na ordem do documento — é o que garante que
     * o link da busca cai no título certo.
     *
     * @param array<string, int> $seen
     */
    public static function anchor(string $text, array &$seen): string
    {
        $slug = Str::slug($text) ?: 'secao';
        $count = $seen[$slug] ?? 0;
        $seen[$slug] = $count + 1;

        return $count === 0 ? $slug : $slug . '-' . $count;
    }

    /** @return list<string> */
    public static function list(string $value): array
    {
        $value = trim($value, " \t[]");

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
    }

    private static function inline(string $text): string
    {
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text);
        $text = str_replace(['**', '__', '`'], '', $text);
        $text = preg_replace('/(?<![\w*])\*(?!\s)([^*\n]+?)(?<!\s)\*(?![\w*])/u', '$1', $text);

        return $text;
    }
}
