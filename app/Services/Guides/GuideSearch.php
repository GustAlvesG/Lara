<?php

namespace App\Services\Guides;

use App\Authorization\Permissions;
use App\Models\User;
use App\View\Navigation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Busca nos guias do usuário, em nome de um funcionário (Lara IA).
 *
 * Os guias não são sigilosos — a tela Documentação abre para qualquer pessoa
 * logada —, então a busca não esconde seção nenhuma. O que ela faz é dizer,
 * em cada seção, se a pessoa ALCANÇA a tarefa (`tem_acesso`), quais telas ela
 * consegue abrir (links) e, quando não alcança, o que precisa pedir. É isso
 * que permite à IA responder "isso é do Financeiro; peça a permissão X ao
 * coordenador" em vez de ensinar um caminho que vai dar em 403.
 *
 * A busca é em PHP, sobre o texto já sem acento: "inscricao" acha
 * "inscrição", coisa que o LIKE do MySQL faz mas o resto não.
 */
class GuideSearch
{
    /**
     * Pesos por campo, por termo. O título de cima (`pai`) pesa pouco: com
     * mais, toda subseção ganharia da própria seção de que faz parte.
     */
    private const WEIGHTS = [
        'titulo' => 4,
        'pai' => 1,
        'modulo' => 2,
        'texto' => 1,
    ];

    /** Bônus para as seções do módulo em que a pessoa está (contexto da tela). */
    private const MODULE_BOOST = 3;

    private const MAX_TEXT = 3000;

    /**
     * Palavras de pergunta de uso que casariam com todo guia — somam às
     * stopwords de config/search.php.
     */
    private const STOPWORDS = ['faco', 'faz', 'consigo', 'posso', 'preciso', 'quero', 'sistema', 'lara', 'tela', 'usar', 'uso'];

    public function __construct(private UserGuides $guides) {}

    /**
     * @param  string[]  $terms
     * @return array{terms: list<string>, data: list<array<string, mixed>>}
     */
    public function search(array $terms, User $user, ?string $modulo = null, int $limit = 6): array
    {
        $stems = $this->stems($terms);

        if ($stems === []) {
            return ['terms' => [], 'data' => []];
        }

        $modulo = $modulo !== null ? $this->fold($modulo) : null;
        $hits = [];

        foreach ($this->guides->sections() as $position => $section) {
            $fields = [
                'titulo' => $this->fold($section['titulo']),
                'pai' => $this->fold((string) $section['pai']),
                'modulo' => $this->fold($section['modulo'] . ' ' . $section['titulo_guia'] . ' ' . $section['palavras']),
                'texto' => $this->fold($section['texto']),
            ];

            $score = 0;
            $matched = 0;

            foreach ($stems as $stem) {
                $termScore = 0;

                foreach (self::WEIGHTS as $field => $weight) {
                    if ($fields[$field] !== '' && str_contains($fields[$field], $stem)) {
                        $termScore += $weight;
                    }
                }

                if ($termScore > 0) {
                    $matched++;
                    $score += $termScore;
                }
            }

            if ($score === 0) {
                continue;
            }

            if ($modulo && $this->fold($section['modulo']) === $modulo) {
                $score += self::MODULE_BOOST;
            }

            $hits[] = compact('section', 'score', 'matched', 'position');
        }

        // Mais pontos; empate: mais termos diferentes; depois a ordem do guia.
        usort($hits, fn ($a, $b) => [$b['score'], $b['matched'], $a['position']] <=> [$a['score'], $a['matched'], $b['position']]);

        $data = array_map(
            fn (array $hit) => $this->present($hit['section'], $hit['score'], $user),
            array_slice($hits, 0, $limit)
        );

        return ['terms' => $stems, 'data' => $data];
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function present(array $section, int $score, User $user): array
    {
        $acesso = $this->access($section, $user);

        $caminho = array_filter([$section['titulo_guia'], $section['pai'], $section['titulo'] === 'Visão geral' ? null : $section['titulo']]);

        return [
            'guia' => $section['titulo_guia'],
            'modulo' => $section['modulo'] ?: null,
            'secao' => implode(' › ', $caminho),
            'texto' => Str::limit($section['texto'], self::MAX_TEXT),
            'url' => route('docs.show', UserGuides::DIRECTORY . '/' . $section['guia']) . '#' . $section['ancora'],
            'tem_acesso' => $acesso['tem_acesso'],
            'telas' => $acesso['telas'],
            'precisa_de' => $acesso['precisa_de'],
            'relevance' => $score,
        ];
    }

    /**
     * Telas e permissões da seção; sem nenhuma, as telas do módulo do guia.
     * Guia de fora do menu (Primeiros passos) vale para todos.
     *
     * @param  array<string, mixed>  $section
     * @return array{tem_acesso: bool, telas: list<array{nome: string, url: string}>, precisa_de: list<string>}
     */
    private function access(array $section, User $user): array
    {
        $destinations = Navigation::destinations();
        $known = fn (string $route) => isset($destinations[$route]) && Route::has($route);

        $routes = array_values(array_filter($section['telas'], $known));
        $permissions = $section['permissoes'];
        $explicit = $routes !== [] || $permissions !== [];

        if (!$explicit) {
            $modulo = $section['modulo'];
            $routes = array_keys(array_filter(
                $destinations,
                fn ($d, $route) => $modulo !== '' && $d['modulo'] === $modulo && $known($route),
                ARRAY_FILTER_USE_BOTH
            ));
        }

        if ($routes === [] && $permissions === []) {
            return ['tem_acesso' => true, 'telas' => [], 'precisa_de' => []];
        }

        $opens = fn (string $route) => ($p = $destinations[$route]['permission']) === null || $user->can($p);

        $reachable = array_values(array_filter($routes, $opens));
        $hasPermission = array_filter($permissions, fn ($p) => $user->can($p)) !== [];
        $temAcesso = $reachable !== [] || $hasPermission;

        // Link só para o que a seção cita e a pessoa abre; o módulo inteiro
        // (seção sem anotação) não vira lista de links.
        $telas = $explicit
            ? array_map(fn ($route) => ['nome' => $destinations[$route]['caminho'], 'url' => route($route)], $reachable)
            : [];

        $precisaDe = [];
        if (!$temAcesso) {
            foreach ($routes as $route) {
                $precisaDe[] = $this->describe($destinations[$route]['caminho'], $destinations[$route]['permission']);
            }
            foreach ($permissions as $permission) {
                $precisaDe[] = $this->describe(null, $permission);
            }
        }

        return ['tem_acesso' => $temAcesso, 'telas' => $telas, 'precisa_de' => array_values(array_unique($precisaDe))];
    }

    /** "Ingressos › Check-in (permissão: Check-in na entrada do evento)". */
    private function describe(?string $caminho, ?string $permission): string
    {
        $label = $permission && in_array($permission, Permissions::all(), true)
            ? Permissions::group($permission) . ' › ' . Permissions::label($permission)
            : null;

        return match (true) {
            $caminho !== null && $label !== null => "{$caminho} (permissão: {$label})",
            $caminho !== null => $caminho,
            $label !== null => "permissão: {$label}",
            default => (string) $permission,
        };
    }

    /**
     * Termos sem acento e reduzidos a um radical simples, para plural e
     * flexão casarem: "inscrições" → "inscric" acha "inscrição"; "eventos" →
     * "even" acha "evento"; "busco" → "busc" acha "buscar". Grosseiro de
     * propósito: o custo de um falso positivo é uma seção a mais na lista,
     * que a IA descarta.
     *
     * @param  string[]  $terms
     * @return list<string>
     */
    private function stems(array $terms): array
    {
        $stems = [];

        foreach ($terms as $term) {
            $folded = $this->fold($term);

            // Menos de 3 letras ("in", de "check-in") casaria com quase tudo.
            if (strlen($folded) < 3 || in_array($folded, self::STOPWORDS, true)) {
                continue;
            }

            $length = strlen($folded);

            $stems[] = match (true) {
                $length >= 7 => substr($folded, 0, max(4, $length - 3)),
                $length >= 5 => substr($folded, 0, -1),
                default => $folded,
            };
        }

        return array_values(array_unique($stems));
    }

    private function fold(string $text): string
    {
        return mb_strtolower(Str::ascii($text));
    }
}
