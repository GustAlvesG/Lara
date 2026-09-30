<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Icon -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/webcamjs/1.0.25/webcam.min.js"></script>
        <!-- Bootstrap Grid -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap-grid.min.css">
        <script src="https://cdn.tailwindcss.com"></script>
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- @vite(['resources/sass/app.scss', 'resources/js/app.js']) --}}

        <link rel="stylesheet" href="{{ asset('css/style-global.css') }}">
        <style>[x-cloak]{display:none!important;}</style>
        {{ $css ?? '' }}
    </head>
    <body class="font-sans antialiased">
        @php
            /*
             | Menu do painel.
             |
             | Cada destino declara a permissão do catálogo que a ROTA dele
             | exige (App\Authorization\Permissions) — a mesma, sempre, senão o
             | item vira um link para um 403. Sem `permission` = todo mundo
             | logado. Um grupo some inteiro quando não sobra filho nenhum, e o
             | link do grupo é o primeiro filho que sobrou.
             |
             | `can()` e não o método do model: o layout renderiza em toda tela,
             | e uma consulta ao banco daqui quebraria as telas cujos testes
             | montam o usuário na mão. O acesso é calculado uma vez por
             | requisição (User::access()), então as dezenas de `can()` abaixo
             | custam uma consulta só.
             */
            $P = \App\Authorization\Permissions::class;

            $navLinks = [
                ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6'],
                ['label' => 'InfoClube', 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                    'children' => [
                        ['route' => 'information.index', 'label' => 'Informações'],
                        ['route' => 'avisos.index', 'label' => 'Avisos'],
                    ],
                ],
                ['label' => 'SIV', 'icon' => 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z',
                    'children' => [
                        ['route' => 'parking.search', 'label' => 'Busca', 'permission' => $P::SIV_BUSCA],
                        ['route' => 'parking-authorizations.index', 'label' => 'Placas Diretoria', 'permission' => $P::SIV_PLACAS_DIRETORIA, 'active' => 'parking-authorizations.*'],
                        ['route' => 'fleet.index', 'label' => 'Frota', 'permission' => $P::SIV_FROTA],
                        ['route' => 'fleet.trips', 'label' => 'Viagens', 'permission' => $P::SIV_VIAGENS],
                        ['route' => 'fleet.vehicles', 'label' => 'Veículos', 'permission' => $P::SIV_VEICULOS, 'active' => 'fleet.vehicles*'],
                    ],
                ],
                ['route' => 'home-assistant.index', 'label' => 'Home Assistant', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                    'permission' => $P::HOME_ASSISTANT,
                ],
                // Pagamentos virou sub-aba de Reservas (a URL continua /payments).
                ['label' => 'Reservas', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                    'children' => [
                        ['route' => 'schedule.index', 'label' => 'Novo Agendamento', 'permission' => $P::RESERVAS_AGENDAMENTOS],
                        ['route' => 'schedule.list', 'label' => 'Todos os Agendamentos', 'permission' => $P::RESERVAS_AGENDAMENTOS],
                        ['route' => 'payment.index', 'label' => 'Pagamentos', 'permission' => $P::RESERVAS_PAGAMENTOS, 'active' => 'payment.*'],
                    ],
                ],
                // Aguardando Motorista saiu do menu: abre pela tela de Carros de
                // Aplicativo.
                ['label' => 'Externos', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                    'children' => [
                        ['route' => 'company.index', 'label' => 'Empresas'],
                        ['route' => 'company.access.monitor', 'label' => 'Monitor de Acesso'],
                        ['route' => 'company.one-off.index', 'label' => 'Liberação Pontual', 'permission' => $P::EXTERNOS_LIBERACAO_PONTUAL],
                        ['route' => 'company.access.logs', 'label' => 'Histórico', 'permission' => $P::EXTERNOS_HISTORICO],
                        ['route' => 'company.uber.requests', 'label' => 'Carros de Aplicativo', 'permission' => $P::EXTERNOS_CARROS_APLICATIVO, 'active' => 'company.uber.*'],
                    ],
                ],
                ['route' => 'lara.index', 'label' => 'Lara (IA)', 'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z',
                    'permission' => $P::LARA,
                ],
                ['route' => 'poli-bot.index', 'label' => 'Bot WhatsApp',
                    'icon' => 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z',
                    'permission' => $P::BOT_WHATSAPP,
                    'active' => 'poli-bot.*',
                ],
                // Compras (Questor). Ordens de Compra e Centros de Custo seguem
                // fora do menu enquanto a integração só simula a gravação — as
                // rotas existem e exigem a mesma permissão.
                ['label' => 'Compras', 'icon' => 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12A1.125 1.125 0 0 1 19.75 21.75H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z',
                    'children' => [
                        // ['route' => 'questor.purchase-orders.index', 'label' => 'Ordens de Compra', 'permission' => $P::COMPRAS, 'active' => 'questor.purchase-orders.*'],
                        // ['route' => 'questor.cost-centers.index', 'label' => 'Centros de Custo', 'permission' => $P::COMPRAS],
                        ['route' => 'cotacao.mapas.index', 'label' => 'Mapas de Cotação', 'permission' => $P::COMPRAS],
                        ['route' => 'cotacao.mapas.previa', 'label' => 'Nova Cotação (buscar SC)', 'permission' => $P::COMPRAS],
                    ],
                ],
                ['label' => 'Carteirinhas', 'icon' => 'M12 4.5v15m7.5-7.5h-15',
                    'children' => [
                        ['route' => 'id-cards.issue', 'label' => 'Emitir Carteirinha', 'permission' => $P::CARTEIRINHAS],
                        ['route' => 'card-templates.index', 'label' => 'Modelos', 'permission' => $P::CARTEIRINHAS, 'active' => 'card-templates.*'],
                    ],
                ],
                ['label' => 'Freelancers', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                    'children' => [
                        ['route' => 'freelancers.index', 'label' => 'Freelancers', 'permission' => $P::FREELANCERS_CADASTRO],
                        ['route' => 'freelancer-functions.index', 'label' => 'Funções', 'permission' => $P::FREELANCERS_FUNCOES],
                        ['route' => 'freelancer-services.index', 'label' => 'Serviços / Contratos', 'permission' => $P::FREELANCERS_SERVICOS_LISTAR],
                        ['route' => 'kiosk.index', 'label' => 'Assinatura (Tablet)', 'permission' => $P::FREELANCERS_ASSINATURA],
                        ['route' => 'freelancer-services.tracking', 'label' => 'Acompanhamento', 'permission' => $P::FREELANCERS_ACOMPANHAMENTO],
                        ['route' => 'freelancer-services.finance', 'label' => 'Financeiro', 'permission' => $P::FREELANCERS_FINANCEIRO, 'active' => 'freelancer-services.finance*'],
                    ],
                ],
                ['label' => 'Placar Clube', 'icon' => 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2',
                    'children' => [
                        ['route' => 'placar.equipes.index', 'label' => 'Equipes', 'permission' => $P::PLACAR_CADASTRO],
                        ['route' => 'placar.times.index', 'label' => 'Times', 'permission' => $P::PLACAR_CADASTRO],
                        ['route' => 'placar.jogadores.index', 'label' => 'Jogadores', 'permission' => $P::PLACAR_CADASTRO],
                        ['route' => 'placar.competicoes.index', 'label' => 'Competições', 'permission' => $P::PLACAR_CADASTRO],
                        ['route' => 'placar.jogos.index', 'label' => 'Jogos', 'permission' => $P::PLACAR_CADASTRO],
                        ['route' => 'placar.scout.jogos', 'label' => 'Súmulas (Scout)', 'permission' => $P::PLACAR_SCOUT],
                    ],
                ],
                // Banco de Horas: escondido do menu por decisão anterior. Para
                // voltar, é descomentar — a consulta é do Gate `view-comp-time`
                // (RH, coordenador ou quem tem matrícula) e o cadastro é da
                // permissão `banco-horas.admin`.
                // ['label' => 'Banco de Horas', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                //     'permission' => 'view-comp-time',
                //     'children' => [
                //         ['route' => 'comp-time.index', 'label' => 'Consulta'],
                //         ['route' => 'comp-time.employees.index', 'label' => 'Funcionários', 'permission' => $P::BANCO_HORAS_ADMIN],
                //     ],
                // ],
            ];

            // Filtra por permissão (pai e filhos) numa passada só: as duas
            // barras e o índice de busca consomem a mesma lista já filtrada, em
            // vez de repetir o `can()` em cada partial.
            //
            // Route::has em cada destino porque este layout renderiza em TODA
            // tela: um nome de rota que não resolve (tela ainda não mesclada,
            // cache de rotas velho) derrubaria o sistema inteiro com 500, e não
            // só este item.
            $allowed = fn (array $item) => \Illuminate\Support\Facades\Route::has($item['route'])
                && (! ($item['permission'] ?? null) || auth()->user()?->can($item['permission']));

            $visibleNavLinks = [];

            foreach ($navLinks as $link) {
                if (($link['permission'] ?? null) && ! auth()->user()?->can($link['permission'])) {
                    continue;
                }

                if (isset($link['children'])) {
                    $link['children'] = array_values(array_filter($link['children'], $allowed));

                    if ($link['children'] === []) {
                        continue;
                    }

                    $link['route'] = $link['children'][0]['route'];
                } elseif (! \Illuminate\Support\Facades\Route::has($link['route'])) {
                    continue;
                }

                // Chave estável do grupo: é por ela que a ordem escolhida pela
                // pessoa fica gravada, então não pode ser a posição na lista nem
                // a rota do primeiro filho — as duas mudam quando uma permissão
                // entra ou sai, e a ordem salva apontaria para o grupo errado.
                $link['key'] = \Illuminate\Support\Str::slug($link['label']);

                $visibleNavLinks[] = $link;
            }

            $navGroups = array_map(
                fn ($link) => [
                    'key' => $link['key'],
                    'label' => __($link['label']),
                    'icon' => $link['icon'],
                ],
                $visibleNavLinks
            );

            // Indice plano do menu: alimenta a busca (Ctrl+K) e os favoritos,
            // que precisam de uma lista unica de destinos finais. O grupo vira
            // so rotulo, e a chave e o nome da rota — e o que fica salvo no
            // localStorage do usuario.
            $navIndex = [];

            foreach ($visibleNavLinks as $link) {
                $items = $link['children'] ?? [['route' => $link['route'], 'label' => $link['label']]];

                foreach ($items as $item) {
                    $navIndex[$item['route']] ??= [
                        'key' => $item['route'],
                        'label' => __($item['label']),
                        'group' => isset($link['children']) ? __($link['label']) : null,
                        'icon' => $link['icon'],
                        'url' => route($item['route']),
                    ];
                }
            }

            // Atalhos da conta tambem entram na busca: e onde as pessoas se
            // perdem procurando "usuarios" e "documentacao". A lista e a
            // mesma do menu da conta (x-nav-account-links).
            $userIcon = 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z';
            $groupIcon = 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z';

            $accountLinks = [
                ['route' => 'profile.edit', 'label' => 'Perfil', 'icon' => $userIcon],
                ['route' => 'docs.index', 'label' => 'Documentação', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
                ['route' => 'my-sector.index', 'label' => 'Meu setor', 'icon' => $groupIcon, 'permission' => 'coordinate-sector'],
                ['route' => 'users.index', 'label' => 'Usuários', 'icon' => $groupIcon, 'permission' => $P::USUARIOS_GERENCIAR],
                ['route' => 'sectors.index', 'label' => 'Setores', 'icon' => $groupIcon, 'permission' => $P::SETORES_GERENCIAR],
            ];

            foreach (array_filter($accountLinks, $allowed) as $item) {
                $navIndex[$item['route']] ??= [
                    'key' => $item['route'],
                    'label' => $item['label'],
                    'group' => 'Conta',
                    'icon' => $item['icon'],
                    'url' => route($item['route']),
                ];
            }

            $navIndex = array_values($navIndex);
        @endphp

        <script>
            // Estado compartilhado das duas barras: modo de navegacao, sidebar
            // recolhida, favoritos e a busca (Ctrl+K). Fica aqui, e nao inline
            // no x-data, porque leitura de localStorage precisa de try/catch —
            // em janela anonima o acesso lanca e derrubaria o menu inteiro.
            window.laraShell = function (navIndex, navGroups) {
                // Faixa de acentos combinantes do NFD. Montada por codigo de
                // caractere de proposito: escrita literal, seriam bytes
                // invisiveis no blade, que e justamente o que costuma virar
                // mojibake no round-trip de editor.
                var DIACRITICS = new RegExp('[' + String.fromCharCode(0x300) + '-' + String.fromCharCode(0x36f) + ']', 'g');

                function read(key, fallback) {
                    try {
                        var value = localStorage.getItem(key);
                        return value === null ? fallback : value;
                    } catch (e) {
                        return fallback;
                    }
                }

                function write(key, value) {
                    try {
                        localStorage.setItem(key, value);
                    } catch (e) {}
                }

                function readList(key) {
                    var parsed;

                    try {
                        parsed = JSON.parse(read(key, '[]'));
                    } catch (e) {
                        return [];
                    }

                    return Array.isArray(parsed) ? parsed : [];
                }

                return {
                    collapsed: window.matchMedia('(max-width: 640px)').matches ? false : read('sidebarCollapsed', 'false') === 'true',
                    mobileOpen: false,
                    navMode: read('navMode', 'side'),
                    navIndex: navIndex,
                    navGroups: navGroups,
                    favorites: readList('navFavorites'),
                    navOrder: readList('navOrder'),
                    paletteOpen: false,
                    organizerOpen: false,
                    dragKey: null,
                    query: '',
                    cursor: 0,

                    toggle() {
                        this.collapsed = !this.collapsed;
                        write('sidebarCollapsed', this.collapsed);
                    },

                    setNav(m) {
                        this.navMode = m;
                        write('navMode', m);
                        this.mobileOpen = false;
                    },

                    // ----- Favoritos -----

                    isFav(key) {
                        return this.favorites.indexOf(key) !== -1;
                    },

                    setFavorites(list) {
                        this.favorites = list;
                        write('navFavorites', JSON.stringify(list));
                    },

                    toggleFav(key) {
                        this.setFavorites(this.isFav(key)
                            ? this.favorites.filter(function (k) { return k !== key; })
                            : this.favorites.concat([key]));
                    },

                    // Favorito e so a chave da rota: se o item sair do menu
                    // (permissao revogada, rota removida) ele simplesmente nao
                    // e encontrado no indice e some da lista.
                    get favItems() {
                        var index = this.navIndex;

                        return this.favorites
                            .map(function (key) {
                                return index.find(function (item) { return item.key === key; });
                            })
                            .filter(Boolean);
                    },

                    // ----- Ordem dos grupos do menu -----

                    get orderedNavKeys() {
                        var known = this.navGroups.map(function (g) { return g.key; });

                        var ordered = this.navOrder.filter(function (k) {
                            return known.indexOf(k) !== -1;
                        });

                        // Grupo que a pessoa ainda nao viu (permissao nova, item
                        // novo no codigo) entra na posicao padrao dele, e nao
                        // empilhado no fim, onde passaria despercebido.
                        known.forEach(function (key, i) {
                            if (ordered.indexOf(key) === -1) {
                                ordered.splice(Math.min(i, ordered.length), 0, key);
                            }
                        });

                        return ordered;
                    },

                    get orderedNavGroups() {
                        var groups = this.navGroups;

                        return this.orderedNavKeys
                            .map(function (key) {
                                return groups.find(function (g) { return g.key === key; });
                            })
                            .filter(Boolean);
                    },

                    // Vira `order` do flexbox: reordena sem mexer no DOM que o
                    // Blade montou.
                    navRank(key) {
                        var i = this.orderedNavKeys.indexOf(key);

                        return i === -1 ? 99 : i;
                    },

                    setNavOrder(list) {
                        this.navOrder = list;
                        write('navOrder', JSON.stringify(list));
                        // A barra superior recalcula quantos itens cabem, e a
                        // conta depende da ordem.
                        window.dispatchEvent(new CustomEvent('nav-order-changed'));
                    },

                    resetNavOrder() {
                        this.setNavOrder([]);
                    },

                    get navOrderIsCustom() {
                        return this.navOrder.length > 0;
                    },

                    // ----- Reordenacao (setas e arrastar) -----

                    movedList(list, key, delta) {
                        var from = list.indexOf(key);

                        if (from === -1) {
                            return null;
                        }

                        var to = Math.min(list.length - 1, Math.max(0, from + delta));

                        if (to === from) {
                            return null;
                        }

                        var copy = list.slice();
                        copy.splice(to, 0, copy.splice(from, 1)[0]);

                        return copy;
                    },

                    moveNav(key, delta) {
                        var list = this.movedList(this.orderedNavKeys, key, delta);

                        if (list) {
                            this.setNavOrder(list);
                        }
                    },

                    moveFav(key, delta) {
                        var list = this.movedList(this.favorites, key, delta);

                        if (list) {
                            this.setFavorites(list);
                        }
                    },

                    // Arrastar reordena ao passar por cima do vizinho, sem
                    // esperar o soltar — assim a lista mostra o resultado
                    // enquanto a pessoa arrasta.
                    dragOverNav(key) {
                        var list = this.draggedOver(this.orderedNavKeys, key);

                        if (list) {
                            this.setNavOrder(list);
                        }
                    },

                    dragOverFav(key) {
                        var list = this.draggedOver(this.favorites, key);

                        if (list) {
                            this.setFavorites(list);
                        }
                    },

                    draggedOver(list, key) {
                        if (!this.dragKey || this.dragKey === key) {
                            return null;
                        }

                        var from = list.indexOf(this.dragKey);
                        var to = list.indexOf(key);

                        if (from === -1 || to === -1) {
                            return null;
                        }

                        var copy = list.slice();
                        copy.splice(to, 0, copy.splice(from, 1)[0]);

                        return copy;
                    },

                    // Busca sem acento e sem caixa: "servicos" acha
                    // "Serviços / Contratos".
                    fold(text) {
                        return (text || '').toString().normalize('NFD').replace(DIACRITICS, '').toLowerCase();
                    },

                    get results() {
                        var self = this;
                        var query = this.fold(this.query).trim();

                        if (!query) {
                            return this.favItems.length ? this.favItems : this.navIndex.slice(0, 8);
                        }

                        var terms = query.split(/\s+/);

                        return this.navIndex
                            .map(function (item) {
                                var label = self.fold(item.label);
                                var haystack = label + ' ' + self.fold(item.group);
                                var matches = terms.every(function (term) { return haystack.indexOf(term) !== -1; });

                                if (!matches) {
                                    return null;
                                }

                                // Quem comeca com o termo vem antes de quem so
                                // o contem, e o casamento no rotulo ganha do
                                // casamento no nome do grupo.
                                var score = label.indexOf(terms[0]) === 0 ? 0 : (label.indexOf(terms[0]) !== -1 ? 1 : 2);

                                return { item: item, score: score };
                            })
                            .filter(Boolean)
                            .sort(function (a, b) { return a.score - b.score; })
                            .map(function (hit) { return hit.item; })
                            .slice(0, 12);
                    },

                    openPalette() {
                        this.paletteOpen = true;
                        this.query = '';
                        this.cursor = 0;
                        this.mobileOpen = false;
                        this.$nextTick(function () {
                            if (this.$refs.paletteInput) {
                                this.$refs.paletteInput.focus();
                            }
                        }.bind(this));
                    },

                    closePalette() {
                        this.paletteOpen = false;
                    },

                    moveCursor(delta) {
                        var total = this.results.length;

                        if (!total) {
                            return;
                        }

                        this.cursor = (this.cursor + delta + total) % total;
                    },

                    openResult() {
                        var target = this.results[this.cursor];

                        if (target) {
                            window.location.href = target.url;
                        }
                    },
                };
            };
        </script>

        <div
            x-data="laraShell(@js($navIndex), @js($navGroups))"
            @keydown.window.ctrl.k.prevent="openPalette()"
            @keydown.window.meta.k.prevent="openPalette()"
            @keydown.escape.window="paletteOpen = false"
            class="min-h-screen bg-gray-100 dark:bg-gray-900"
        >
            <!-- Lateral navigation -->
            <div x-show="navMode === 'side'" x-cloak>
                @include('partials.navigation')
            </div>

            <!-- Top navigation -->
            <div x-show="navMode === 'top'" x-cloak>
                @include('partials.navigation-top')
            </div>

            <!-- Content -->
            <div
                class="transition-all duration-300 ease-in-out"
                :class="navMode === 'side' ? (collapsed ? 'sm:ml-20' : 'sm:ml-64') : ''"
            >
                <!-- Page Heading -->
                @if (isset($header))
                    <header class="bg-white dark:bg-gray-800 shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>

                @include('partials.footer')
            </div>

            <!-- Floating notification bell (fixed bottom-right, both modes) -->
            @include('partials.notification-bell')

            <!-- Busca de módulos (Ctrl+K), compartilhada pelos dois modos -->
            @include('partials.nav-palette')

            <!-- Organizar menu: ordem dos grupos e dos favoritos -->
            @include('partials.nav-organizer')
        </div>

        {{-- JQUERY --}}
        <script
            src="https://code.jquery.com/jquery-3.7.1.js"
            integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4="
            crossorigin="anonymous"></script>
        {{ $js ?? '' }}

        @auth
        <script>
        (function () {
            const POLL_MS  = 30000;
            const ICON_URL = '{{ asset("favicon.ico") }}';
            let since = new Date().toISOString();

            function requestPermission() {
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission();
                }
            }

            function showNotification(title, body, url) {
                if (!('Notification' in window) || Notification.permission !== 'granted') return;
                const n = new Notification(title, { body: body, icon: ICON_URL });
                n.onclick = function () {
                    window.focus();
                    if (url) window.location.href = url;
                    n.close();
                };
            }

            async function poll() {
                try {
                    const res = await fetch('/notifications/unread-json?since=' + encodeURIComponent(since), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    since = data.checked_at;
                    data.notifications.forEach(function (n) {
                        showNotification(n.title, n.message, n.url);
                    });
                } catch (_) {}
            }

            requestPermission();
            setInterval(poll, POLL_MS);
        })();
        </script>
        @endauth
    </body>
</html>
