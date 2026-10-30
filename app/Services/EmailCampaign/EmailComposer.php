<?php

namespace App\Services\EmailCampaign;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Editor visual do Disparo de E-mails: transforma o texto formatado que a
 * pessoa escreveu (negrito, títulos, listas, link, botão, imagem) num e-mail
 * HTML pronto, no layout do clube (resources/email-campaigns/layout.html).
 *
 * Duas etapas, de propósito separadas:
 *  - sanitize(): o que fica gravado em `body` e volta para o editor — só as
 *    tags e atributos que o editor produz, sem estilo nenhum;
 *  - compose(): o e-mail de verdade — o `body` limpo com o estilo escrito
 *    dentro de cada tag (style="…"), que é o que Gmail e Outlook respeitam,
 *    dentro do layout com cabeçalho e rodapé.
 *
 * As variáveis {{coluna}} passam como texto e são trocadas depois, no envio
 * (CampaignRenderer), como no modo HTML.
 */
final class EmailComposer
{
    private const FONT = 'font-family:Arial,Helvetica,sans-serif;';

    /** Tags que o editor produz. O resto é desembrulhado (fica o texto). */
    private const ALLOWED = ['p', 'br', 'h2', 'h3', 'strong', 'em', 'u', 'a', 'ul', 'ol', 'li', 'img', 'hr'];

    /** Somem com o conteúdo. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'link', 'meta', 'noscript', 'head', 'title'];

    /** O que o navegador gera no contenteditable, no nome que o e-mail usa. */
    private const RENAME = ['div' => 'p', 'h1' => 'h2', 'h4' => 'h3', 'h5' => 'h3', 'h6' => 'h3', 'b' => 'strong', 'i' => 'em'];

    /** Blocos que aceitam alinhamento (centralizar). */
    private const ALIGNABLE = ['p', 'h2', 'h3', 'li'];

    /** Marca do link que vira botão. */
    public const BUTTON_CLASS = 'email-button';

    private const STYLES = [
        'p' => 'margin:0 0 16px;' . self::FONT . 'font-size:16px;line-height:1.6;color:#2b2523;',
        'h2' => 'margin:0 0 14px;' . self::FONT . 'font-size:24px;line-height:1.3;font-weight:bold;color:#2b2523;',
        'h3' => 'margin:0 0 12px;' . self::FONT . 'font-size:19px;line-height:1.35;font-weight:bold;color:#2b2523;',
        'ul' => 'margin:0 0 16px;padding-left:24px;' . self::FONT . 'font-size:16px;line-height:1.6;color:#2b2523;',
        'ol' => 'margin:0 0 16px;padding-left:24px;' . self::FONT . 'font-size:16px;line-height:1.6;color:#2b2523;',
        'li' => 'margin:0 0 6px;',
        'a' => 'color:#a00001;text-decoration:underline;',
        'button' => 'display:inline-block;padding:14px 28px;border-radius:999px;background-color:#a00001;' . self::FONT . 'font-size:15px;font-weight:bold;line-height:1.2;color:#ffffff;text-decoration:none;',
        'img' => 'display:block;width:100%;max-width:536px;height:auto;border:0;margin:0 0 16px;',
        'hr' => 'border:0;border-top:1px solid #ece6e1;margin:24px 0;',
    ];

    /** O texto do editor, limpo, para gravar e reabrir no editor. */
    public function sanitize(?string $body): string
    {
        if ($body === null || trim($body) === '') {
            return '';
        }

        [$dom, $root] = $this->load($body);
        $this->clean($root, $dom);

        return $this->save($dom, $root);
    }

    /**
     * O e-mail completo: o texto limpo, com estilo em cada tag, dentro do
     * layout. `$header` é a faixa de cima (o nome do remetente).
     */
    public function compose(?string $body, ?string $header = null): string
    {
        [$dom, $root] = $this->load($this->sanitize($body) ?: '<p></p>');
        $this->style($root, $dom);

        $header = trim((string) $header) !== '' ? $header : (string) config('mail.from.name');

        return strtr((string) file_get_contents(resource_path('email-campaigns/layout.html')), [
            '%%CABECALHO%%' => e($header),
            '%%CONTEUDO%%' => $this->save($dom, $root),
        ]);
    }

    /** Texto de partida de uma campanha nova no editor visual. */
    public static function starterBody(): string
    {
        return '<h2>Título do e-mail</h2>'
            . '<p>Olá, {{primeiro_nome}}!</p>'
            . '<p>Escreva aqui a mensagem. Use os botões acima para negrito, títulos, listas, links e imagens.</p>'
            . '<p><a class="' . self::BUTTON_CLASS . '" href="https://www.exemplo.com.br">Saiba mais</a></p>'
            . '<p>Até breve,<br>Equipe do Clube</p>';
    }

    /** @return array{0: DOMDocument, 1: DOMElement} */
    private function load(string $html): array
    {
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="__raiz__">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        return [$dom, $dom->getElementById('__raiz__')];
    }

    private function save(DOMDocument $dom, DOMElement $root): string
    {
        $out = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        // O libxml codifica as chaves dentro de href/src: {{email}} viraria %7B%7Bemail%7D%7D.
        return trim(str_ireplace(['%7B%7B', '%7D%7D'], ['{{', '}}'], $out));
    }

    private function clean(DOMNode $node, DOMDocument $dom): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            if (isset(self::RENAME[$tag])) {
                $child = $this->rename($child, self::RENAME[$tag], $dom);
                $tag = self::RENAME[$tag];
            }

            $this->clean($child, $dom);

            if (! in_array($tag, self::ALLOWED, true)) {
                foreach (iterator_to_array($child->childNodes) as $grandchild) {
                    $node->insertBefore($grandchild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            // Parágrafo dentro de parágrafo (div aninhada do contenteditable) vira irmão.
            if (in_array($tag, ['p', 'h2', 'h3'], true) && $node instanceof DOMElement && in_array(strtolower($node->tagName), ['p', 'h2', 'h3'], true)) {
                foreach (iterator_to_array($child->childNodes) as $grandchild) {
                    $node->insertBefore($grandchild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            $this->keepAttributes($child, $tag);

            if ($tag === 'img' && ! $child->hasAttribute('src')) {
                $node->removeChild($child);
            } elseif ($tag === 'a' && ! $child->hasAttribute('href')) {
                foreach (iterator_to_array($child->childNodes) as $grandchild) {
                    $node->insertBefore($grandchild, $child);
                }
                $node->removeChild($child);
            }
        }
    }

    private function keepAttributes(DOMElement $element, string $tag): void
    {
        $align = $this->align($element);
        $href = trim($element->getAttribute('href'));
        $src = trim($element->getAttribute('src'));
        $alt = $element->getAttribute('alt');
        $button = in_array(self::BUTTON_CLASS, preg_split('/\s+/', $element->getAttribute('class')), true);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->name);
        }

        if ($tag === 'a' && $this->safeUrl($href, ['http', 'https', 'mailto', 'tel'])) {
            $element->setAttribute('href', $href);

            if ($button) {
                $element->setAttribute('class', self::BUTTON_CLASS);
            }
        }

        if ($tag === 'img' && $this->safeUrl($src, ['http', 'https'])) {
            $element->setAttribute('src', $src);
            $element->setAttribute('alt', $alt);
        }

        if ($align && in_array($tag, self::ALIGNABLE, true)) {
            $element->setAttribute('style', 'text-align:' . $align . ';');
        }
    }

    /** Só o alinhamento sobrevive ao estilo do editor (centralizar, à direita). */
    private function align(DOMElement $element): ?string
    {
        $value = strtolower($element->getAttribute('align'));

        if (preg_match('/text-align\s*:\s*(left|center|right|justify)/i', $element->getAttribute('style'), $match)) {
            $value = strtolower($match[1]);
        }

        return in_array($value, ['center', 'right', 'justify'], true) ? $value : null;
    }

    /** Link e imagem: endereço absoluto do esquema permitido, ou variável ({{link}}). */
    private function safeUrl(string $url, array $schemes): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '{{')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, $schemes, true);
    }

    private function style(DOMNode $node, DOMDocument $dom): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $this->style($child, $dom);

            $tag = strtolower($child->tagName);
            $key = $tag === 'a' && $child->getAttribute('class') === self::BUTTON_CLASS ? 'button' : $tag;

            if (! isset(self::STYLES[$key])) {
                continue;
            }

            // O alinhamento escolhido vem depois, para vencer o do estilo padrão.
            $child->setAttribute('style', self::STYLES[$key] . $child->getAttribute('style'));
            $child->removeAttribute('class');

            if ($tag === 'img') {
                $child->setAttribute('width', '536');
            }
        }
    }

    private function rename(DOMElement $element, string $tag, DOMDocument $dom): DOMElement
    {
        $renamed = $dom->createElement($tag);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $renamed->setAttribute($attribute->name, $attribute->value);
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            $renamed->appendChild($child);
        }

        $element->parentNode->replaceChild($renamed, $element);

        return $renamed;
    }
}
