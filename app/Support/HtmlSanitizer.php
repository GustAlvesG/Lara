<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Allow-list de HTML para o editor próprio do InfoClube (negrito, itálico,
 * sublinhado, tabela e cor de fundo do texto).
 *
 * Roda no `store()` do InformationController antes de gravar `description`:
 * sem isso, qualquer usuário com acesso ao formulário conseguia gravar HTML
 * arbitrário (script, onerror, etc.) que era reproduzido para todo mundo que
 * abrisse a informação — XSS armazenado. Tags fora da lista são "desembrulhadas"
 * (mantém o texto, descarta só a tag) em vez de apagadas, pra não sumir com
 * conteúdo antigo gravado pelo CKEditor (ex: listas, títulos).
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'span', 'div',
        'table', 'thead', 'tbody', 'tr', 'td', 'th',
    ];

    /**
     * Tags cujo conteúdo é removido junto (nunca só "desembrulhado").
     */
    private const STRIP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form',
        'input', 'button', 'svg', 'link', 'meta', 'noscript',
    ];

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $dom->getElementById('__root__');

        if (!$root) {
            return '';
        }

        self::sanitizeChildren($root, $dom);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function sanitizeChildren(DOMNode $node, DOMDocument $dom): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (!$child instanceof DOMElement) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::STRIP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            // Sanitiza os filhos antes de decidir sobre a própria tag.
            self::sanitizeChildren($child, $dom);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                self::unwrap($child, $node, $dom);
                continue;
            }

            self::sanitizeAttributes($child);
        }
    }

    /**
     * Substitui o elemento pelos próprios filhos (mantém o texto, descarta a tag).
     */
    private static function unwrap(DOMElement $child, DOMNode $parent, DOMDocument $dom): void
    {
        foreach (iterator_to_array($child->childNodes) as $grandchild) {
            $parent->insertBefore($grandchild, $child);
        }

        $parent->removeChild($child);
    }

    private static function sanitizeAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if ($name === 'style' && in_array(strtolower($element->tagName), ['span', 'td', 'th'], true)) {
                $clean = self::cleanStyle($attribute->value);

                if ($clean === '') {
                    $element->removeAttribute('style');
                } else {
                    $element->setAttribute('style', $clean);
                }

                continue;
            }

            $element->removeAttribute($attribute->name);
        }
    }

    /**
     * Só o necessário para "alterar o fundo do texto": mantém apenas
     * background-color com um valor hex/rgb/nome-de-cor simples, descarta o
     * resto (evita url(), expression(), etc. dentro do style).
     */
    private static function cleanStyle(string $style): string
    {
        if (preg_match('/background-color\s*:\s*(#[0-9a-fA-F]{3,8}|rgb\([0-9,\s]+\)|[a-zA-Z]+)\s*;?/', $style, $matches)) {
            return rtrim($matches[0], ';') . ';';
        }

        return '';
    }
}
