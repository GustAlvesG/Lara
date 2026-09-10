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
             | Menu "Compras" (Questor). Montado antes do $navLinks porque as
             | duas metades têm donos diferentes:
             |
             |   Ordens de Compra / Centros de Custo -> permissão `authorize purchase orders`
             |   Mapas de Cotação                    -> vínculo com o setor Contabilidade
             |
             | Os partials do menu só sabem filtrar pela permissão do item PAI,
             | então a filtragem por filho acontece aqui — e o pai só existe se
             | sobrar algum filho. É o mesmo arranjo do menu Freelancers, logo
             | abaixo.
             |
             | `can()` e não o método do model: o layout renderiza em toda tela,
             | e uma consulta ao banco daqui quebraria as telas cujos testes
             | montam o usuário na mão.
             */
            $canAuthorizeOrders = auth()->user()?->can('authorize purchase orders');
            // Só o setor: estar na Contabilidade, em qualquer papel, já mostra a
            // aba. Sem permissão do Spatie no caminho — ver CotacaoMapaPolicy.
            $canCotacao = auth()->user()?->can('acessar-cotacao');

            $comprasChildren = [];

            if ($canAuthorizeOrders) {
                // `active` com curinga porque o detalhe da ordem é outra rota:
                // sem ele, abrir uma ordem apagaria o destaque.
                #$comprasChildren[] = ['route' => 'questor.purchase-orders.index', 'label' => 'Ordens de Compra', 'active' => 'questor.purchase-orders.*'];
                #$comprasChildren[] = ['route' => 'questor.cost-centers.index', 'label' => 'Centros de Custo'];
            }

            if ($canCotacao) {
                $comprasChildren[] = ['route' => 'cotacao.mapas.index', 'label' => 'Mapas de Cotação', 'active' => 'cotacao.mapas.index'];
                $comprasChildren[] = ['route' => 'cotacao.mapas.previa', 'label' => 'Nova Cotação (buscar SC)', 'active' => 'cotacao.mapas.previa'];
            }

            // SIV reúne o que é de portaria e veículo: consulta de placas,
            // placas da diretoria e a quilometragem da frota. Os itens são
            // montados por permissão porque o menu só checa permissão no nível
            // de cima — mesmo padrão de Freelancers e Placar mais abaixo.
            $sivChildren = [];

            if (auth()->user()?->can('search parking')) {
                $sivChildren[] = ['route' => 'parking.search', 'label' => 'Busca'];
                $sivChildren[] = ['route' => 'parking-authorizations.index', 'label' => 'Placas Diretoria'];
            }

            if (auth()->user()?->can('manage fleet')) {
                $sivChildren[] = ['route' => 'fleet.index', 'label' => 'Frota'];
                $sivChildren[] = ['route' => 'fleet.trips', 'label' => 'Viagens'];
                $sivChildren[] = ['route' => 'fleet.vehicles', 'label' => 'Veículos'];
            }

            $navLinks = [
                ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6'],
                ['route' => 'information.index', 'label' => 'InfoClube', 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                    'permission' => 'view information',
                    'children' => [
                        ['route' => 'information.index', 'label' => 'Informações'],
                        ['route' => 'avisos.index', 'label' => 'Avisos'],
                    ],
                ],
                // Some inteiro quando a pessoa não tem nem placas nem frota.
                ...($sivChildren === [] ? [] : [[
                    'route' => $sivChildren[0]['route'],
                    'label' => 'SIV',
                    'icon' => 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z',
                    'children' => $sivChildren,
                ]]),
                ['route' => 'videowall.index', 'label' => 'Smart Panel', 'icon' => 'M9.75 17L9 20l-1-1v-4h-2l-1 1 7-7 7 7-1 1h-2v-4l-1 1h-2v4z',
                    'permission' => 'manage smart panel',
                ],
                ['route' => 'home-assistant.index', 'label' => 'Home Assistant', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                    'permission' => 'manage home assistant',
                ],
                ['route' => 'schedule.index', 'label' => 'Reservas', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                    'permission' => 'view reservations',
                    'children' => [
                        ['route' => 'schedule.index', 'label' => 'Novo Agendamento'],
                        ['route' => 'schedule.list', 'label' => 'Todos os Agendamentos'],
                    ],
                ],
                ['route' => 'payment.index', 'label' => 'Pagamentos', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                    'permission' => 'view payments',
                ],
                ['route' => 'company.index', 'label' => 'Externos', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                    'children' => [
                        ['route' => 'company.index', 'label' => 'Empresas'],
                        ['route' => 'company.access.monitor', 'label' => 'Monitor de Acesso'],
                        ['route' => 'company.access.logs', 'label' => 'Histórico'],
                        ['route' => 'company.uber.requests', 'label' => 'Carros de Aplicativo'],
                    ],
                ],
                ['route' => 'lara.index', 'label' => 'Lara (IA)', 'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z',
                    'permission' => 'use lara chat',
                ],
                // Compras (Questor): Ordens de Compra, Centros de Custo e Mapas
                // de Cotação. Some inteiro quando o usuário não alcança nenhum
                // dos filhos — daí o spread condicional, e não uma `permission`
                // de pai, que só saberia gatilhar por uma das duas regras.
                ...($comprasChildren === [] ? [] : [[
                    'route' => $comprasChildren[0]['route'],
                    'label' => 'Compras',
                    'icon' => 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12A1.125 1.125 0 0 1 19.75 21.75H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z',
                    'children' => $comprasChildren,
                ]]),
                ['route' => 'id-cards.issue', 'label' => 'Carteirinhas', 'icon' => 'M12 4.5v15m7.5-7.5h-15',
                    'permission' => 'manage id cards',
                    'children' => [
                        ['route' => 'id-cards.issue', 'label' => 'Emitir Carteirinha'],
                        ['route' => 'card-templates.index', 'label' => 'Modelos'],
                    ],
                ],
            ];

            // O financeiro tem regra própria — vínculo com o setor Contabilidade
            // ou Gerência: quem só tem isso enxerga o menu Freelancers apenas
            // com a aba Financeiro.
            $canFreelancers = auth()->user()?->can('manage freelancers');
            $canFreelancerPayments = auth()->user()?->can('manage-freelancer-payments');
            // Acompanhamento do trâmite: vínculo com o setor Comercial. Como o
            // Financeiro, é uma entrada que existe sozinha — quem só acompanha
            // enxerga o menu Freelancers apenas com ela.
            $canTrackFreelancers = auth()->user()?->can('track-freelancer-batches');

            if ($canFreelancers || $canFreelancerPayments || $canTrackFreelancers) {
                $freelancerChildren = [];

                if ($canFreelancers) {
                    $freelancerChildren[] = ['route' => 'freelancers.index', 'label' => 'Freelancers'];
                    $freelancerChildren[] = ['route' => 'freelancer-functions.index', 'label' => 'Funções'];
                    $freelancerChildren[] = ['route' => 'freelancer-services.index', 'label' => 'Serviços / Contratos'];
                    $freelancerChildren[] = ['route' => 'kiosk.index', 'label' => 'Assinatura (Tablet)'];
                }

                if ($canTrackFreelancers) {
                    $freelancerChildren[] = ['route' => 'freelancer-services.tracking', 'label' => 'Acompanhamento'];
                }

                if ($canFreelancerPayments) {
                    $freelancerChildren[] = ['route' => 'freelancer-services.finance', 'label' => 'Financeiro'];
                }

                $navLinks[] = [
                    'route' => $freelancerChildren[0]['route'],
                    'label' => 'Freelancers',
                    'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                    'children' => $freelancerChildren,
                ];
            }

            // Placar Clube: cadastro (escreve) e scout (só lê) são Gates
            // separados hoje com a mesma regra de setor (ver AppServiceProvider),
            // por isso os dois grupos aparecem juntos sempre que algum dos
            // dois estiver liberado — quem só acompanha o jogo também precisa
            // achar a súmula no menu.
            $canPlacarCadastro = auth()->user()?->can('manage-placar-cadastro');
            $canPlacarScout = auth()->user()?->can('view-placar-scout');

            if ($canPlacarCadastro || $canPlacarScout) {
                $placarChildren = [];

                if ($canPlacarCadastro) {
                    $placarChildren[] = ['route' => 'placar.equipes.index', 'label' => 'Equipes'];
                    $placarChildren[] = ['route' => 'placar.times.index', 'label' => 'Times'];
                    $placarChildren[] = ['route' => 'placar.jogadores.index', 'label' => 'Jogadores'];
                    $placarChildren[] = ['route' => 'placar.competicoes.index', 'label' => 'Competições'];
                    $placarChildren[] = ['route' => 'placar.jogos.index', 'label' => 'Jogos'];
                }

                if ($canPlacarScout) {
                    $placarChildren[] = ['route' => 'placar.scout.jogos', 'label' => 'Súmulas (Scout)'];
                }

                $navLinks[] = [
                    'route' => $placarChildren[0]['route'],
                    'label' => 'Placar Clube',
                    'icon' => 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2',
                    'children' => $placarChildren,
                ];
            }

            // Banco de Horas. A consulta tem três públicos: o RH (vê todos),
            // o coordenador (vê o próprio setor) e o colaborador com matrícula
            // (vê a própria ficha) — ver CompTimeService::accessFor(). Quem não
            // se encaixa em nenhum dos três não tem o que abrir, e o menu não
            // aparece. A aba de cadastro é só do RH.
            $canManageCompTime = auth()->user()?->can('manage-comp-time');
            $canViewCompTime = auth()->user()?->can('view-comp-time');

            // if ($canViewCompTime) {
            //     $compTimeChildren = [
            //         ['route' => 'comp-time.index', 'label' => 'Consulta'],
            //     ];

            //     // Route::has porque este layout renderiza em TODA tela: um nome de
            //     // rota que não existe (tela ainda não mesclada, cache de rotas
            //     // velho) derruba o sistema inteiro com 500, e não só este item.
            //     if ($canManageCompTime && \Illuminate\Support\Facades\Route::has('comp-time.employees.index')) {
            //         $compTimeChildren[] = ['route' => 'comp-time.employees.index', 'label' => 'Funcionários'];
            //     }

            //     $navLinks[] = [
            //         'route' => 'comp-time.index',
            //         'label' => 'Banco de Horas',
            //         'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            //         'children' => $compTimeChildren,
            //     ];
            // }

            // Permissao do nivel de cima resolvida uma vez so: as duas barras e
            // o indice de busca consomem a mesma lista ja filtrada, em vez de
            // repetir o `can()` em cada partial.
            $visibleNavLinks = array_values(array_filter(
                $navLinks,
                fn ($link) => ! ($link['permission'] ?? null) || auth()->user()?->can($link['permission'])
            ));

            // Chave estável do grupo: é por ela que a ordem escolhida pela
            // pessoa fica gravada, então não pode ser a posição na lista nem a
            // rota do primeiro filho — as duas mudam quando uma permissão entra
            // ou sai, e a ordem salva apontaria para o grupo errado.
            $visibleNavLinks = array_map(
                fn ($link) => $link + ['key' => \Illuminate\Support\Str::slug($link['label'])],
                $visibleNavLinks
            );

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
            // perdem procurando "usuarios" e "documentacao".
            $navIndex['profile.edit'] ??= [
                'key' => 'profile.edit',
                'label' => 'Perfil',
                'group' => 'Conta',
                'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                'url' => route('profile.edit'),
            ];

            $navIndex['docs.index'] ??= [
                'key' => 'docs.index',
                'label' => 'Documentação',
                'group' => 'Conta',
                'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                'url' => route('docs.index'),
            ];

            if (auth()->user()?->hasRole('admin')) {
                $navIndex['users.index'] ??= [
                    'key' => 'users.index',
                    'label' => 'Usuários',
                    'group' => 'Conta',
                    'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
                    'url' => route('users.index'),
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
