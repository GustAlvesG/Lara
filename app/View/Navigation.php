<?php

namespace App\View;

use App\Authorization\Permissions as P;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * O menu do painel, montado uma vez por requisição e consumido pelas três
 * navegações (Módulos, lateral e superior), pela busca (Ctrl+K), pelos
 * favoritos/recentes e pela capa da área.
 *
 * Cada destino declara a permissão do catálogo que a ROTA dele exige
 * (App\Authorization\Permissions) — a mesma, sempre, senão o item vira um link
 * para um 403. Sem `permission` = todo mundo logado. Um grupo some inteiro
 * quando não sobra filho nenhum, e o link do grupo é o primeiro filho que
 * sobrou.
 *
 * `can()` e não o método do model: o layout renderiza em toda tela, e uma
 * consulta ao banco daqui quebraria as telas cujos testes montam o usuário na
 * mão. O acesso é calculado uma vez por requisição (User::access()), então as
 * dezenas de `can()` custam uma consulta só.
 *
 * `area` é a cor da área (App\View\AreaColor) e `glyph` o nome do ícone em
 * <x-icon>; `icon` continua sendo o path SVG que os menus antigos desenham.
 */
final class Navigation
{
    private const ACCOUNT_GROUP = 'Conta';

    /**
     * @return list<array<string, mixed>>
     */
    public static function links(): array
    {
        return [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'area' => 'inicio', 'glyph' => 'home',
                'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6'],
            ['label' => 'InfoClube', 'area' => 'info', 'glyph' => 'info',
                'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                'children' => [
                    ['route' => 'information.index', 'label' => 'Informações', 'active' => 'information.*'],
                    ['route' => 'avisos.index', 'label' => 'Avisos', 'active' => 'avisos.*'],
                ],
            ],
            ['label' => 'SIV', 'area' => 'portaria', 'glyph' => 'car',
                'icon' => 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z',
                'children' => [
                    // O resultado da busca (parking.show) é outra rota, mas é a mesma aba.
                    ['route' => 'parking.search', 'label' => 'Busca', 'permission' => P::SIV_BUSCA, 'active' => ['parking.search', 'parking.show']],
                    ['route' => 'parking-authorizations.index', 'label' => 'Placas Diretoria', 'permission' => P::SIV_PLACAS_DIRETORIA, 'active' => 'parking-authorizations.*'],
                    ['route' => 'fleet.index', 'label' => 'Frota', 'permission' => P::SIV_FROTA],
                    ['route' => 'fleet.trips', 'label' => 'Viagens', 'permission' => P::SIV_VIAGENS],
                    ['route' => 'fleet.vehicles', 'label' => 'Veículos', 'permission' => P::SIV_VEICULOS, 'active' => 'fleet.vehicles*'],
                ],
            ],
            ['route' => 'home-assistant.index', 'label' => 'Home Assistant', 'area' => 'inicio', 'glyph' => 'home',
                'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                'permission' => P::HOME_ASSISTANT,
            ],
            // Pagamentos virou sub-aba de Reservas (a URL continua /payments).
            ['label' => 'Reservas', 'area' => 'reservas', 'glyph' => 'calendar',
                'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                'children' => [
                    ['route' => 'schedule.index', 'label' => 'Novo Agendamento', 'permission' => P::RESERVAS_AGENDAMENTOS],
                    ['route' => 'schedule.list', 'label' => 'Todos os Agendamentos', 'permission' => P::RESERVAS_AGENDAMENTOS],
                    ['route' => 'payment.index', 'label' => 'Pagamentos', 'permission' => P::RESERVAS_PAGAMENTOS, 'active' => 'payment.*'],
                ],
            ],
            // Aguardando Motorista saiu do menu: abre pela tela de Carros de
            // Aplicativo.
            ['label' => 'Externos', 'area' => 'externos', 'glyph' => 'users',
                'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                'children' => [
                    ['route' => 'company.index', 'label' => 'Empresas'],
                    ['route' => 'company.access.monitor', 'label' => 'Monitor de Acesso'],
                    ['route' => 'company.one-off.index', 'label' => 'Liberação Pontual', 'permission' => P::EXTERNOS_LIBERACAO_PONTUAL],
                    ['route' => 'company.access.logs', 'label' => 'Histórico', 'permission' => P::EXTERNOS_HISTORICO],
                    ['route' => 'company.uber.requests', 'label' => 'Carros de Aplicativo', 'permission' => P::EXTERNOS_CARROS_APLICATIVO, 'active' => 'company.uber.*'],
                ],
            ],
            ['route' => 'lara.index', 'label' => 'Lara (IA)', 'area' => 'lara', 'glyph' => 'chat',
                'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z',
                'permission' => P::LARA,
            ],
            ['route' => 'poli-bot.index', 'label' => 'Bot WhatsApp', 'area' => 'lara', 'glyph' => 'chat',
                'icon' => 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z',
                'permission' => P::BOT_WHATSAPP,
                'active' => 'poli-bot.*',
            ],
            // Compras (Questor). Ordens de Compra e Centros de Custo seguem
            // fora do menu enquanto a integração só simula a gravação — as
            // rotas existem e exigem a mesma permissão.
            ['label' => 'Compras', 'area' => 'compras', 'glyph' => 'bag',
                'icon' => 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12A1.125 1.125 0 0 1 19.75 21.75H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z',
                'children' => [
                    // ['route' => 'questor.purchase-orders.index', 'label' => 'Ordens de Compra', 'permission' => P::COMPRAS, 'active' => 'questor.purchase-orders.*'],
                    // ['route' => 'questor.cost-centers.index', 'label' => 'Centros de Custo', 'permission' => P::COMPRAS],
                    ['route' => 'cotacao.mapas.index', 'label' => 'Mapas de Cotação', 'permission' => P::COMPRAS],
                    ['route' => 'cotacao.mapas.previa', 'label' => 'Nova Cotação (buscar SC)', 'permission' => P::COMPRAS],
                ],
            ],
            ['label' => 'Carteirinhas', 'area' => 'cartao', 'glyph' => 'card',
                'icon' => 'M12 4.5v15m7.5-7.5h-15',
                'children' => [
                    ['route' => 'id-cards.issue', 'label' => 'Emitir Carteirinha', 'permission' => P::CARTEIRINHAS],
                    ['route' => 'card-templates.index', 'label' => 'Modelos', 'permission' => P::CARTEIRINHAS, 'active' => 'card-templates.*'],
                ],
            ],
            // Assinatura eletrônica presencial (tablet do balcão). Quem atende
            // e quem escreve o texto dos termos não são necessariamente a
            // mesma pessoa: cada filho tem a sua permissão, e o grupo some
            // para quem não alcança nenhum.
            ['label' => 'Assinaturas', 'area' => 'cartao', 'glyph' => 'pencil',
                'icon' => 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z',
                'children' => [
                    ['route' => 'signature-documents.index', 'label' => 'Documentos', 'permission' => 'acessar-documentos-assinatura', 'active' => 'signature-documents.*'],
                    ['route' => 'signature-reviews.index', 'label' => 'Revisão', 'permission' => 'acessar-revisao-assinatura', 'active' => 'signature-reviews.*'],
                    ['route' => 'signature-templates.index', 'label' => 'Modelos', 'permission' => P::ASSINATURA_MODELOS, 'active' => 'signature-templates.*'],
                    ['route' => 'minor-terms.index', 'label' => 'Termo de Menores', 'permission' => 'acessar-termo-menores', 'active' => 'minor-terms.*'],
                    ['route' => 'signature-guide.index', 'label' => 'Guia', 'permission' => 'acessar-guia-assinatura', 'active' => 'signature-guide.*'],
                ]],
            ['label' => 'Freelancers', 'area' => 'freela', 'glyph' => 'user',
                'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                'children' => [
                    ['route' => 'freelancers.index', 'label' => 'Freelancers', 'permission' => P::FREELANCERS_CADASTRO],
                    ['route' => 'freelancer-functions.index', 'label' => 'Funções', 'permission' => P::FREELANCERS_FUNCOES],
                    ['route' => 'freelancer-services.index', 'label' => 'Serviços / Contratos', 'permission' => P::FREELANCERS_SERVICOS_LISTAR],
                    ['route' => 'kiosk.index', 'label' => 'Assinatura (Tablet)', 'permission' => P::FREELANCERS_ASSINATURA],
                    ['route' => 'freelancer-services.tracking', 'label' => 'Acompanhamento', 'permission' => P::FREELANCERS_ACOMPANHAMENTO],
                    ['route' => 'freelancer-services.finance', 'label' => 'Financeiro', 'permission' => P::FREELANCERS_FINANCEIRO, 'active' => 'freelancer-services.finance*'],
                ],
            ],
            ['label' => 'Placar Clube', 'area' => 'placar', 'glyph' => 'trophy',
                'icon' => 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2',
                'children' => [
                    ['route' => 'placar.equipes.index', 'label' => 'Equipes', 'permission' => P::PLACAR_CADASTRO],
                    ['route' => 'placar.times.index', 'label' => 'Times', 'permission' => P::PLACAR_CADASTRO],
                    ['route' => 'placar.jogadores.index', 'label' => 'Jogadores', 'permission' => P::PLACAR_CADASTRO],
                    ['route' => 'placar.competicoes.index', 'label' => 'Competições', 'permission' => P::PLACAR_CADASTRO],
                    ['route' => 'placar.jogos.index', 'label' => 'Jogos', 'permission' => P::PLACAR_CADASTRO],
                    ['route' => 'placar.scout.jogos', 'label' => 'Súmulas (Scout)', 'permission' => P::PLACAR_SCOUT],
                ],
            ],
            // Replay: as quatro telas são as etapas do mesmo trabalho — definir o
            // formato, desenhar o layout, ligar a câmera e conferir os vídeos.
            ['label' => 'Replay', 'area' => 'placar', 'glyph' => 'monitor',
                'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
                'children' => [
                    ['route' => 'replay.settings.index', 'label' => 'Configuração de Vídeo', 'permission' => P::REPLAY, 'active' => 'replay.settings.*'],
                    ['route' => 'replay.layouts.index', 'label' => 'Layouts de Logomarca', 'permission' => P::REPLAY, 'active' => 'replay.layouts.*'],
                    ['route' => 'replay.cameras.index', 'label' => 'Câmeras', 'permission' => P::REPLAY, 'active' => 'replay.cameras.*'],
                    ['route' => 'replay.videos.index', 'label' => 'Vídeos', 'permission' => P::REPLAY, 'active' => 'replay.videos.*'],
                ],
            ],
            // Banco de Horas: escondido do menu por decisão anterior. Para
            // voltar, é descomentar — a consulta é do Gate `view-comp-time`
            // (RH, coordenador ou quem tem matrícula) e o cadastro é da
            // permissão `banco-horas.admin`.
            // ['label' => 'Banco de Horas', 'area' => 'inicio', 'glyph' => 'clock',
            //     'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            //     'permission' => 'view-comp-time',
            //     'children' => [
            //         ['route' => 'comp-time.index', 'label' => 'Consulta'],
            //         ['route' => 'comp-time.employees.index', 'label' => 'Funcionários', 'permission' => P::BANCO_HORAS_ADMIN],
            //     ],
            // ],
        ];
    }

    /**
     * Atalhos da conta. Também entram na busca: é onde as pessoas se perdem
     * procurando "usuários" e "documentação".
     *
     * @return list<array<string, mixed>>
     */
    public static function accountLinks(): array
    {
        return [
            ['route' => 'profile.edit', 'label' => 'Perfil', 'glyph' => 'user', 'icon' => self::USER_ICON],
            ['route' => 'docs.index', 'label' => 'Documentação', 'glyph' => 'doc', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
            ['route' => 'my-sector.index', 'label' => 'Meu setor', 'glyph' => 'users', 'icon' => self::GROUP_ICON, 'permission' => 'coordinate-sector'],
            ['route' => 'users.index', 'label' => 'Usuários', 'glyph' => 'users', 'icon' => self::GROUP_ICON, 'permission' => P::USUARIOS_GERENCIAR],
            ['route' => 'sectors.index', 'label' => 'Setores', 'glyph' => 'users', 'icon' => self::GROUP_ICON, 'permission' => P::SETORES_GERENCIAR],
        ];
    }

    private const USER_ICON = 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z';

    private const GROUP_ICON = 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z';

    /**
     * @return array{
     *     links: list<array<string, mixed>>,
     *     groups: list<array<string, mixed>>,
     *     index: list<array<string, mixed>>,
     *     current: ?array{group: array<string, mixed>, key: string, label: string, tabs: list<array<string, mixed>>}
     * }
     */
    public static function build(?Authenticatable $user, Request $request): array
    {
        // Route::has em cada destino porque o layout renderiza em TODA tela:
        // um nome de rota que não resolve (tela ainda não mesclada, cache de
        // rotas velho) derrubaria o sistema inteiro com 500, e não só o item.
        $can = fn (?string $permission) => ! $permission || (bool) $user?->can($permission);
        $allowed = fn (array $item) => Route::has($item['route']) && $can($item['permission'] ?? null);

        $links = [];

        foreach (self::links() as $link) {
            if (! $can($link['permission'] ?? null)) {
                continue;
            }

            if (isset($link['children'])) {
                $link['children'] = array_values(array_filter($link['children'], $allowed));

                if ($link['children'] === []) {
                    continue;
                }

                $link['route'] = $link['children'][0]['route'];
            } elseif (! Route::has($link['route'])) {
                continue;
            }

            // Chave estável do grupo: é por ela que a ordem escolhida pela
            // pessoa fica gravada, então não pode ser a posição na lista nem a
            // rota do primeiro filho — as duas mudam quando uma permissão entra
            // ou sai, e a ordem salva apontaria para o grupo errado.
            $link['key'] = Str::slug($link['label']);
            $link['area'] = AreaColor::normalize($link['area'] ?? null);
            $links[] = $link;
        }

        $groups = array_map(fn ($link) => [
            'key' => $link['key'],
            'label' => __($link['label']),
            'icon' => $link['icon'],
            'glyph' => $link['glyph'],
            'area' => $link['area'],
            'url' => route($link['route']),
            'pages' => count($link['children'] ?? [1]),
        ], $links);

        // Índice plano: alimenta a busca (Ctrl+K), os favoritos e os recentes,
        // que precisam de uma lista única de destinos finais. A chave é o nome
        // da rota — é o que fica salvo no localStorage de cada pessoa.
        $index = [];

        foreach ($links as $link) {
            $items = $link['children'] ?? [['route' => $link['route'], 'label' => $link['label']]];

            foreach ($items as $item) {
                $index[$item['route']] ??= self::entry($item, isset($link['children']) ? __($link['label']) : null, $link);
            }
        }

        foreach (array_filter(self::accountLinks(), $allowed) as $item) {
            $index[$item['route']] ??= self::entry($item, self::ACCOUNT_GROUP, ['icon' => $item['icon'], 'area' => 'inicio']);
        }

        return [
            'links' => $links,
            'groups' => $groups,
            'index' => array_values($index),
            'current' => self::current($links, $request),
        ];
    }

    /**
     * A página atual dentro do menu: o grupo dela (para a capa e o "você está
     * em") e as páginas irmãs (as abas). Nulo fora do menu — perfil, telas de
     * detalhe sem padrão `active`, a vitrine de componentes.
     */
    private static function current(array $links, Request $request): ?array
    {
        foreach ($links as $link) {
            $items = $link['children'] ?? [$link];

            foreach ($items as $item) {
                if (! $request->routeIs($item['active'] ?? $item['route'])) {
                    continue;
                }

                $tabs = array_map(fn ($child) => [
                    'label' => __($child['label']),
                    'href' => route($child['route']),
                    'active' => $child['route'] === $item['route'],
                ], $link['children'] ?? []);

                return [
                    'group' => $link,
                    'key' => $item['route'],
                    'label' => __($item['label']),
                    'tabs' => $tabs,
                ];
            }
        }

        return null;
    }

    private static function entry(array $item, ?string $group, array $parent): array
    {
        return [
            'key' => $item['route'],
            'label' => __($item['label']),
            'group' => $group,
            'icon' => $parent['icon'],
            'area' => $parent['area'],
            'areaStyle' => AreaColor::style($parent['area'], paint: false),
            'url' => route($item['route']),
        ];
    }
}
