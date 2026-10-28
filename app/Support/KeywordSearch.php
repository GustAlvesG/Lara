<?php

namespace App\Support;

/**
 * Peças comuns das buscas por palavra-chave da API (InfoClube e Avisos):
 * a nota de relevância e a limpeza do HTML gravado pelo editor.
 */
final class KeywordSearch
{
    /**
     * Soma ponderada de "CASE WHEN coluna LIKE termo THEN peso ELSE 0", uma
     * parcela por termo x coluna, mais uma parcela por termo para acerto em
     * tag (vale uma vez só, por mais tags que casem).
     *
     * As colunas de $weights e o $tagExistsSql são constantes do código de
     * quem chama (não entrada do usuário), então a interpolação na SQL é
     * segura; os valores dos termos vão só como bindings. O $tagExistsSql é
     * um SELECT correlacionado com um único `?` para o nome da tag.
     *
     * @param string[] $terms
     * @param array<string, int> $weights
     * @return array{0: string, 1: array<int, string>}
     */
    public static function relevance(array $terms, array $weights, string $tagExistsSql, int $tagWeight): array
    {
        $parts = [];
        $bindings = [];

        foreach ($terms as $term) {
            $like = '%' . $term . '%';

            foreach ($weights as $column => $weight) {
                $parts[] = "(CASE WHEN {$column} LIKE ? THEN {$weight} ELSE 0 END)";
                $bindings[] = $like;
            }

            $parts[] = "(CASE WHEN EXISTS ({$tagExistsSql}) THEN {$tagWeight} ELSE 0 END)";
            $bindings[] = $like;
        }

        return [implode(' + ', $parts), $bindings];
    }

    /**
     * O texto é gravado como HTML (rich text dos editores) — a busca devolve
     * texto puro para o consumidor da API não receber markup. Troca cada tag
     * por um espaço antes de decodificar entidades, senão
     * "<p>Texto</p><p>Mais</p>" viraria "TextoMais" grudado.
     */
    public static function stripHtml(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = preg_replace('/<[^>]*>/', ' ', $html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}
