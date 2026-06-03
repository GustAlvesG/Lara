<?php

namespace App\Services;

use Illuminate\Support\Str;

class DocumentationService
{
    /**
     * Caminho base da documentação (pasta docs/ na raiz do projeto).
     */
    public function base(): string
    {
        return base_path('docs');
    }

    /**
     * Monta a árvore de navegação da documentação.
     *
     * Retorna seções, cada uma com seus documentos (slug + título).
     * A seção "Geral" contém os arquivos da raiz de docs/ (ordenados pela
     * numeração do nome) e a seção "Funcionalidades" agrupa docs/funcionalidades/.
     *
     * @return array<int, array{label: string, items: array<int, array{slug: string, title: string}>}>
     */
    public function tree(): array
    {
        return [
            [
                'label' => 'Geral',
                'items' => $this->itemsFromDirectory($this->base(), ''),
            ],
            [
                'label' => 'Funcionalidades',
                'items' => $this->itemsFromDirectory($this->base().'/funcionalidades', 'funcionalidades/'),
            ],
        ];
    }

    /**
     * Resolve um slug em um caminho absoluto seguro de arquivo .md.
     *
     * Protege contra path traversal: o realpath precisa terminar em .md e
     * estar contido na pasta base de documentação.
     */
    public function resolve(string $slug): ?string
    {
        // Remove extensão .md eventualmente enviada e normaliza
        $slug = preg_replace('/\.md$/i', '', $slug);

        // Permite apenas caracteres seguros para nomes de arquivo/pasta
        if (! preg_match('#^[A-Za-z0-9/_-]+$#', $slug)) {
            return null;
        }

        $candidate = $this->base().'/'.$slug.'.md';
        $real = realpath($candidate);

        if ($real === false) {
            return null;
        }

        $base = realpath($this->base());

        if ($base === false || ! str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (! str_ends_with(strtolower($real), '.md')) {
            return null;
        }

        return $real;
    }

    /**
     * Converte o arquivo Markdown em HTML, reescrevendo links internos (*.md)
     * para a rota do leitor.
     */
    public function render(string $path): string
    {
        $markdown = file_get_contents($path);

        $html = Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return $this->rewriteInternalLinks($html, $path);
    }

    /**
     * Deriva o título de um documento a partir do primeiro H1, com fallback
     * para o nome do arquivo "humanizado".
     */
    public function title(string $path): string
    {
        $handle = @fopen($path, 'r');

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (str_starts_with($line, '# ')) {
                    fclose($handle);

                    return trim(substr($line, 2));
                }
            }
            fclose($handle);
        }

        return Str::headline(pathinfo($path, PATHINFO_FILENAME));
    }

    /**
     * Lista os documentos .md de um diretório (não recursivo), prefixando o
     * slug e ordenando pelo nome do arquivo.
     *
     * @return array<int, array{slug: string, title: string}>
     */
    private function itemsFromDirectory(string $directory, string $slugPrefix): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = glob($directory.'/*.md') ?: [];
        sort($files);

        $items = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $items[] = [
                'slug' => $slugPrefix.$name,
                'title' => $this->title($file),
            ];
        }

        return $items;
    }

    /**
     * Reescreve hrefs que apontam para arquivos .md locais para a rota docs.show,
     * resolvendo caminhos relativos a partir da localização do documento atual.
     */
    private function rewriteInternalLinks(string $html, string $currentPath): string
    {
        $currentDir = dirname($currentPath);
        $base = realpath($this->base());

        return preg_replace_callback(
            '/href="([^"]+\.md)(#[^"]*)?"/i',
            function (array $matches) use ($currentDir, $base) {
                $target = $matches[1];
                $anchor = $matches[2] ?? '';

                // Ignora URLs absolutas (http, https, mailto, etc.)
                if (preg_match('#^[a-z]+://#i', $target) || str_starts_with($target, 'mailto:')) {
                    return $matches[0];
                }

                $resolved = realpath($currentDir.'/'.$target);

                if ($resolved === false || $base === false || ! str_starts_with($resolved, $base.DIRECTORY_SEPARATOR)) {
                    return $matches[0];
                }

                // Slug relativo à base, sem extensão
                $slug = substr($resolved, strlen($base) + 1);
                $slug = preg_replace('/\.md$/i', '', $slug);
                $slug = str_replace(DIRECTORY_SEPARATOR, '/', $slug);

                return 'href="'.e(route('docs.show', $slug)).e($anchor).'"';
            },
            $html
        );
    }
}
