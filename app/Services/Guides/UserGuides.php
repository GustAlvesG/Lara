<?php

namespace App\Services\Guides;

use Illuminate\Support\Facades\Cache;

/**
 * Os guias do usuário (docs/guias/*.md), quebrados em seções para a busca da
 * Lara (IA). Cada título `##` ou `###` vira uma seção com o texto que vem logo
 * abaixo dele; `####` em diante fica dentro da seção de cima. O texto entre o
 * título do guia (`#`) e o primeiro `##` é a "Visão geral".
 *
 * Uma seção sem `<!-- telas: -->` herda a do `##` acima (o `###` herda do
 * `##`); sem nenhuma, vale o módulo inteiro — quem decide é GuideSearch.
 *
 * O resultado fica em cache até algum arquivo mudar (a chave leva nome e
 * data de cada um), então editar um guia vale na pergunta seguinte, sem
 * deploy de código nem limpeza de cache.
 */
class UserGuides
{
    public const DIRECTORY = 'guias';

    private ?array $memo = null;

    public function __construct(private ?string $path = null)
    {
        $this->path ??= base_path('docs/' . self::DIRECTORY);
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return list<array{guia: string, titulo_guia: string, modulo: string, palavras: string, titulo: string, pai: ?string, ancora: string, texto: string, telas: list<string>, permissoes: list<string>}>
     */
    public function sections(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $files = glob($this->path . '/*.md') ?: [];
        sort($files);

        $stamp = md5($this->path . '|' . implode('|', array_map(
            fn ($file) => basename($file) . ':' . filemtime($file) . ':' . filesize($file),
            $files
        )));

        return $this->memo = Cache::remember('guias:secoes:' . $stamp, now()->addDay(), function () use ($files) {
            $sections = [];

            foreach ($files as $file) {
                array_push($sections, ...$this->parse(pathinfo($file, PATHINFO_FILENAME), (string) file_get_contents($file)));
            }

            return $sections;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parse(string $slug, string $markdown): array
    {
        [$meta, $body] = GuideMarkdown::splitFrontMatter($markdown);

        $modulo = $meta['modulo'] ?? '';
        $palavras = implode(' ', GuideMarkdown::list($meta['palavras'] ?? ''));
        $tituloGuia = $modulo ?: $slug;

        $sections = [];
        $seen = [];
        $current = null;
        $h2 = null;
        $inFence = false;
        $expectAnnotation = false;

        $flush = function () use (&$current, &$sections) {
            if ($current === null) {
                return;
            }

            $current['texto'] = GuideMarkdown::toPlainText(implode("\n", $current['linhas']));
            unset($current['linhas']);

            if ($current['texto'] !== '') {
                $sections[] = $current;
            }

            $current = null;
        };

        foreach (explode("\n", $body) as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line)) {
                $inFence = !$inFence;
                $expectAnnotation = false;
                if ($current !== null) {
                    $current['linhas'][] = $line;
                }
                continue;
            }

            if (!$inFence && preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                $level = strlen($m[1]);
                $text = GuideMarkdown::headingText($m[2]);
                // Toda âncora entra na conta, inclusive as de #### — é assim
                // que o leitor de documentação numera os títulos repetidos.
                $anchor = GuideMarkdown::anchor($text, $seen);

                if ($level >= 4) {
                    if ($current !== null) {
                        $current['linhas'][] = $text;
                    }
                    continue;
                }

                $flush();

                $base = [
                    'guia' => $slug,
                    'modulo' => $modulo,
                    'palavras' => $palavras,
                    'ancora' => $anchor,
                    'linhas' => [],
                ];

                if ($level === 1) {
                    $tituloGuia = $text;
                    $h2 = null;
                    $current = $base + ['titulo' => 'Visão geral', 'pai' => null, 'telas' => [], 'permissoes' => []];
                } elseif ($level === 2) {
                    $h2 = ['titulo' => $text, 'telas' => [], 'permissoes' => []];
                    $current = $base + ['titulo' => $text, 'pai' => null, 'telas' => [], 'permissoes' => []];
                } else {
                    $current = $base + [
                        'titulo' => $text,
                        'pai' => $h2['titulo'] ?? null,
                        'telas' => $h2['telas'] ?? [],
                        'permissoes' => $h2['permissoes'] ?? [],
                    ];
                }

                $current['nivel'] = $level;
                $expectAnnotation = true;
                continue;
            }

            if ($expectAnnotation && trim($line) !== '') {
                $annotation = GuideMarkdown::annotation($line);

                if ($annotation !== null && $current !== null) {
                    [$kind, $values] = $annotation;
                    $current[$kind] = $values;

                    if ($current['nivel'] === 2) {
                        $h2[$kind] = $values;
                    }

                    // Pode haver telas e permissoes, uma por linha.
                    continue;
                }

                $expectAnnotation = false;
            }

            if ($current !== null && GuideMarkdown::annotation($line) === null) {
                $current['linhas'][] = $line;
            }
        }

        $flush();

        return array_map(function (array $section) use ($tituloGuia) {
            unset($section['nivel']);

            return $section + ['titulo_guia' => $tituloGuia];
        }, $sections);
    }
}
