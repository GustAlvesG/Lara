<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Icon -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />

        @include('partials.theme-script')

        {{-- Modo de navegação antes da primeira pintura (ver app.css,
             .nav-only-*). Lateral e Superior são os menus de antes. --}}
        <script>
            (function () {
                var mode = 'areas';
                try {
                    var saved = localStorage.getItem('laraNavMode');
                    if (saved === 'side' || saved === 'top') {
                        mode = saved;
                    }
                } catch (e) {}
                document.documentElement.dataset.nav = mode;
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|unbounded:500,600,700|jetbrains-mono:500,600&display=swap" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/webcamjs/1.0.25/webcam.min.js"></script>
        {{-- Só nas telas ainda não repaginadas: ver App\View\Components\AppLayout. --}}
        @if ($bootstrapGrid ?? true)
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap-grid.min.css">
        @endif
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- @vite(['resources/sass/app.scss', 'resources/js/app.js']) --}}

        <style>[x-cloak]{display:none!important;}</style>
        {{ $css ?? '' }}
    </head>
    <body class="font-sans antialiased">
        @php
            // O menu sai de App\View\Navigation (permissões, rotas e a página
            // atual); os parciais das três navegações usam estas variáveis.
            $nav = \App\View\Navigation::build(auth()->user(), request());
            $visibleNavLinks = $nav['links'];
            $navGroups = $nav['groups'];
            $navIndex = $nav['index'];
            $navCurrent = $nav['current'];

            // Uma consulta só, para o sino da barra e o flutuante.
            $unreadNotifications = auth()->user()->unreadNotifications()->latest()->limit(8)->get();
        @endphp

        <script>
            // Estado compartilhado das navegações: modo (Módulos, lateral ou
            // superior), sidebar recolhida, favoritos, recentes, o painel de
            // Módulos e a busca (Ctrl+K). Fica aqui, e nao inline no x-data,
            // porque leitura de localStorage precisa de try/catch — em janela
            // anonima o acesso lanca e derrubaria o menu inteiro.
            //
            // Favoritos e ordem do menu sao da CONTA: vem do servidor (prefs)
            // e cada mudanca e gravada la. O localStorage fica so de copia —
            // limpar os dados do navegador nao apaga mais os favoritos.
            window.laraShell = function (navIndex, navGroups, currentKey, prefs) {
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

                // `null` no servidor = a pessoa nunca salvou: vale o que
                // este navegador tiver, e o init() sobe isso uma vez.
                prefs = prefs || {};
                var saved = prefs.saved || {};
                var fromServer = Array.isArray(saved.favorites) || Array.isArray(saved.order);
                var syncTimer = null;

                return {
                    collapsed: window.matchMedia('(max-width: 640px)').matches ? false : read('sidebarCollapsed', 'false') === 'true',
                    mobileOpen: false,
                    // O script do <head> já leu e gravou o modo no <html>.
                    navMode: document.documentElement.dataset.nav || 'areas',
                    navIndex: navIndex,
                    navGroups: navGroups,
                    currentKey: currentKey,
                    favorites: Array.isArray(saved.favorites) ? saved.favorites : readList('navFavorites'),
                    recent: readList('navRecent'),
                    navOrder: Array.isArray(saved.order) ? saved.order : readList('navOrder'),
                    paletteOpen: false,
                    organizerOpen: false,
                    launcherOpen: false,
                    launcherTab: 'todas',
                    dragKey: null,
                    query: '',
                    cursor: 0,

                    // A página aberta vai para o topo dos recentes. Só entra o
                    // que está no menu desta pessoa: tela de detalhe e perfil
                    // não têm chave (currentKey nulo) e não poluem a lista.
                    init() {
                        if (fromServer) {
                            // A copia local acompanha a conta.
                            write('navFavorites', JSON.stringify(this.favorites));
                            write('navOrder', JSON.stringify(this.navOrder));
                        } else if (this.favorites.length || this.navOrder.length) {
                            // Primeira vez com a conta vazia: sobe o que o
                            // navegador ja tinha.
                            this.syncPrefs();
                        }

                        if (!this.currentKey) {
                            return;
                        }

                        var key = this.currentKey;
                        this.recent = [key].concat(this.recent.filter(function (k) { return k !== key; })).slice(0, 8);
                        write('navRecent', JSON.stringify(this.recent));
                    },

                    // Grava favoritos e ordem na conta. Junta mudancas
                    // seguidas (arrastar, varias estrelas) num envio so; se
                    // falhar, a copia local segura ate a proxima mudanca.
                    syncPrefs() {
                        if (!prefs.url) {
                            return;
                        }

                        var self = this;
                        clearTimeout(syncTimer);
                        syncTimer = setTimeout(function () {
                            fetch(prefs.url, {
                                method: 'PUT',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': prefs.token || '',
                                },
                                body: JSON.stringify({ favorites: self.favorites, order: self.navOrder }),
                            }).catch(function () {});
                        }, 400);
                    },

                    toggle() {
                        this.collapsed = !this.collapsed;
                        write('sidebarCollapsed', this.collapsed);
                    },

                    // A chave é nova (laraNavMode, e não a navMode antiga) para
                    // todo mundo começar nas Módulos uma vez; quem preferir o
                    // menu de antes escolhe Lateral ou Superior na conta.
                    setNav(m) {
                        this.navMode = m;
                        write('laraNavMode', m);
                        document.documentElement.dataset.nav = m;
                        this.mobileOpen = false;
                        this.launcherOpen = false;
                        // A barra superior mede a largura ao aparecer.
                        window.dispatchEvent(new CustomEvent('nav-order-changed'));
                    },

                    // ----- Painel de Módulos -----

                    openLauncher(tab) {
                        this.launcherTab = tab || 'todas';
                        this.launcherOpen = true;
                        this.paletteOpen = false;
                    },

                    toggleLauncher(tab) {
                        if (this.launcherOpen) {
                            this.launcherOpen = false;
                            return;
                        }

                        this.openLauncher(tab);
                    },

                    // ----- Recentes -----

                    // Sem a página atual: ela já está na capa e no "você está em".
                    get recentItems() {
                        var index = this.navIndex;
                        var current = this.currentKey;

                        return this.recent
                            .filter(function (key) { return key !== current; })
                            .map(function (key) {
                                return index.find(function (item) { return item.key === key; });
                            })
                            .filter(Boolean)
                            .slice(0, 5);
                    },

                    // ----- Favoritos -----

                    isFav(key) {
                        return this.favorites.indexOf(key) !== -1;
                    },

                    setFavorites(list) {
                        this.favorites = list;
                        write('navFavorites', JSON.stringify(list));
                        this.syncPrefs();
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
                        this.syncPrefs();
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
                        this.launcherOpen = false;
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
            x-data="laraShell(@js($navIndex), @js($navGroups), @js($navCurrent['key'] ?? null), @js(['saved' => auth()->user()->nav_preferences, 'url' => url('/nav-preferences'), 'token' => csrf_token()]))"
            @keydown.window.ctrl.k.prevent="openPalette()"
            @keydown.window.meta.k.prevent="openPalette()"
            @keydown.escape.window="paletteOpen = false; launcherOpen = false"
            class="min-h-screen bg-canvas"
        >
            {{-- Navegação por Módulos (padrão). `contents` para o wrapper não
                 virar o limite do `sticky` da barra. --}}
            <div class="nav-only-areas contents">
                @include('partials.navigation-areas')
            </div>

            {{-- Menus de antes, como opção --}}
            <div class="nav-only-side">
                @include('partials.navigation')
            </div>

            <div class="nav-only-top contents">
                @include('partials.navigation-top')
            </div>

            <!-- Content -->
            <div
                class="nav-tabbar-pad transition-all duration-300 ease-in-out"
                :class="navMode === 'side' ? (collapsed ? 'sm:ml-20' : 'sm:ml-64') : ''"
            >
                {{-- Capa da área da página atual, com as páginas irmãs como
                     abas. Só nas Módulos: nos menus de antes, quem leva às irmãs
                     é o próprio menu. Tela fora do menu não tem capa. --}}
                @if ($cover && $navCurrent && $navCurrent['tabs'])
                    <div class="nav-only-areas">
                        <x-area-cover
                            :area="$navCurrent['group']['area']"
                            :title="__($navCurrent['group']['label'])"
                            :icon="$navCurrent['group']['glyph']"
                            :tabs="$navCurrent['tabs']"
                        >
                            <button type="button" @click="toggleFav(currentKey)" :aria-pressed="isFav(currentKey)"
                                class="inline-flex h-8 items-center gap-[7px] rounded-full border-[1.5px] px-[13px] text-[13px] font-bold transition"
                                style="border-color: rgb(var(--ci) / .35); background-color: rgb(var(--surface) / .55); color: rgb(var(--ci))">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linejoin="round" fill="none"
                                    :fill="isFav(currentKey) ? 'currentColor' : 'none'" aria-hidden="true">
                                    <path d="M11.48 3.5a.56.56 0 011.04 0l2.12 4.7 5.11.6c.47.05.66.64.31.96l-3.8 3.45 1.03 5.05c.09.46-.4.82-.81.59L12 16.3l-4.48 2.55c-.41.23-.9-.13-.81-.59l1.03-5.05-3.8-3.45c-.35-.32-.16-.91.31-.96l5.11-.6z" />
                                </svg>
                                <span x-text="isFav(currentKey) ? 'Favorito' : 'Favoritar'">Favoritar</span>
                            </button>
                            {{ $coverActions ?? '' }}
                        </x-area-cover>
                    </div>
                @endif

                <!-- Page Heading -->
                @if (isset($header))
                    <header>
                        <div class="max-w-7xl mx-auto pt-6 pb-2 px-4 sm:px-6 lg:px-8">
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

            {{-- Nas Módulos o sino fica na barra de cima; nos menus de antes,
                 flutuante no canto. --}}
            <div class="nav-only-side">
                @include('partials.notification-bell', ['placement' => 'floating'])
            </div>
            <div class="nav-only-top">
                @include('partials.notification-bell', ['placement' => 'floating'])
            </div>

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
