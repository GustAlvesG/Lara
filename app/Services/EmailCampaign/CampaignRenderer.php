<?php

namespace App\Services\EmailCampaign;

use App\Models\EmailCampaign\EmailCampaign;
use App\Models\EmailCampaign\EmailCampaignRecipient;
use Illuminate\Support\Str;

/**
 * Monta o e-mail de um destinatário: troca as variáveis {{coluna}}, embute as
 * imagens do editor e gera a versão em texto puro (sem ela, o e-mail ganha
 * ponto de spam nos filtros).
 *
 * Variável que a pessoa não tem (coluna vazia ou inexistente) vira texto
 * vazio — nunca sai "{{nome}}" para o destinatário. No HTML o valor é
 * escapado: a lista vem de fora e não pode injetar marcação.
 */
final class CampaignRenderer
{
    public const VARIABLE_PATTERN = '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/';

    public function __construct(private readonly CampaignImageStore $images)
    {
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, html: string, text: string, images: array<string, string>}
     */
    public function render(EmailCampaign $campaign, array $variables, bool $embed = true): array
    {
        $subject = $this->replace($campaign->subject, $variables, escape: false);
        $html = $this->replace((string) $campaign->html, $variables, escape: true);

        $html = $this->withoutScripts($html);
        // A prévia na tela mantém o endereço da rota (a imagem abre logada).
        [$html, $images] = $embed ? $this->embedImages($html) : [$html, []];

        return [
            'subject' => Str::squish($subject),
            'html' => $this->document($html, $subject),
            'text' => $this->text($html),
            'images' => $images,
        ];
    }

    /** @return array<string, string> */
    public static function variablesFor(EmailCampaignRecipient $recipient): array
    {
        $name = trim((string) $recipient->name);

        return array_merge(
            array_map(fn ($value) => (string) $value, $recipient->data ?? []),
            [
                'email' => $recipient->email,
                'nome' => $name,
                'primeiro_nome' => Str::before($name, ' '),
            ],
        );
    }

    /**
     * Valores para a prévia e o e-mail de teste: o primeiro destinatário da
     * lista, ou exemplos quando a lista ainda não subiu.
     *
     * @return array<string, string>
     */
    public function sampleVariables(EmailCampaign $campaign): array
    {
        $first = $campaign->recipients()->orderBy('id')->first();

        if ($first) {
            return self::variablesFor($first);
        }

        $sample = ['email' => 'fulano@exemplo.com.br', 'nome' => 'Fulano de Tal', 'primeiro_nome' => 'Fulano'];

        foreach (array_keys($campaign->columns ?? []) as $key) {
            $sample[$key] ??= '[' . $key . ']';
        }

        return $sample;
    }

    /**
     * Variáveis usadas no texto que a lista não tem — a tela avisa antes do
     * envio, porque elas sairiam vazias.
     *
     * @return list<string>
     */
    public function unknownVariables(EmailCampaign $campaign): array
    {
        preg_match_all(self::VARIABLE_PATTERN, $campaign->subject . ' ' . $campaign->html, $matches);

        $used = array_unique(array_map('strtolower', $matches[1]));

        return array_values(array_diff($used, array_keys($campaign->variables())));
    }

    private function replace(string $content, array $variables, bool $escape): string
    {
        return preg_replace_callback(self::VARIABLE_PATTERN, function (array $match) use ($variables, $escape) {
            $value = (string) ($variables[strtolower($match[1])] ?? '');

            return $escape ? e($value) : $value;
        }, $content);
    }

    /** Script em e-mail não roda em cliente nenhum e é sinal clássico de spam. */
    private function withoutScripts(string $html): string
    {
        return preg_replace('#<script\b[^>]*>.*?</script\s*>#is', '', $html);
    }

    /**
     * Endereço da rota de imagens do editor (com ou sem domínio — o HTML pode
     * ter sido montado em outro ambiente) => `cid:arquivo`.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function embedImages(string $html): array
    {
        $prefix = Str::before(parse_url(route('email-campaigns.images.show', ['file' => 'ARQUIVO'], false), PHP_URL_PATH), 'ARQUIVO');
        $pattern = '#(?:https?://[^"\'\s()<>]*?)?' . preg_quote($prefix, '#') . '(' . CampaignImageStore::NAME_PATTERN . ')#i';
        $images = [];

        $html = preg_replace_callback($pattern, function (array $match) use (&$images) {
            $path = $this->images->path(strtolower($match[1]));

            if (! $path) {
                return $match[0];
            }

            $images[strtolower($match[1])] = $path;

            return 'cid:' . strtolower($match[1]);
        }, $html);

        return [$html, $images];
    }

    /** Fragmento ganha o esqueleto de documento; HTML completo fica como está. */
    private function document(string $html, string $title): string
    {
        if (preg_match('/<html[\s>]/i', $html)) {
            return $html;
        }

        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . e($title) . '</title></head><body>' . $html . '</body></html>';
    }

    private function text(string $html): string
    {
        $text = preg_replace('#<(head|style|title)\b[^>]*>.*?</\1\s*>#is', '', $html);
        $text = preg_replace_callback('#<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>#is', function ($m) {
            $label = trim(strip_tags($m[3]));
            $url = html_entity_decode($m[2]);

            return $label === '' || $label === $url ? $url : "{$label} ({$url})";
        }, $text);
        $text = preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = preg_replace('#</(p|div|tr|h[1-6]|li|table)>#i', "\n\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $text = preg_replace("/ *\n */", "\n", $text);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
