<script>
    // Estado da barra superior.
    //
    // Dois problemas moravam aqui: (1) o dropdown fechava no `mouseleave` sem
    // atraso, e como o painel ficava a alguns pixels do botao, atravessar esse
    // vao com o mouse fechava o menu antes de chegar no item; (2) cada grupo
    // tinha seu proprio `dropOpen`, entao abrir um nao fechava o outro.
    //
    // Agora ha um unico `open` para a barra toda, o fechamento passa por um
    // timer que qualquer `mouseenter` (do botao OU do painel) cancela, e os
    // paineis sao teleportados para o body com posicao fixa — o que tambem
    // permite `overflow-hidden` na fileira sem cortar o menu aberto.
    //
    // ATENCAO ao mexer no `:style` dos paineis: ele PRECISA ser objeto
    // (`{ top: ... }`), nunca string. Com string o Alpine faz
    // `setAttribute('style', ...)` e apaga o `display:none` que o x-show tinha
    // posto; como `pos` e compartilhado, mexer o mouse num grupo reescrevia o
    // style de todos e abria a barra inteira de uma vez. E o x-show usa
    // `once()`: como o valor dos outros continuava `false`, ele nao reagia
    // para esconder de novo, e os paineis ficavam abertos.
    window.topNav = function (navKeys) {
        // Fica fora do objeto reativo de proposito: o Alpine embrulharia o
        // ResizeObserver num proxy, e chamar `observe()` atraves dele nao e
        // garantido em objeto nativo.
        var observer = null;

        return {
            navKeys: navKeys,
            total: navKeys.length,
            visibleCount: navKeys.length,
            widths: [],
            moreWidth: 90,
            measured: false,
            mobileMenu: false,
            open: null,
            closeTimer: null,
            pos: { top: 0, left: 0 },

            init() {
                if (typeof ResizeObserver === 'undefined') {
                    this.measured = true;
                    return;
                }

                observer = new ResizeObserver(function () { this.sync(); }.bind(this));

                this.$nextTick(function () {
                    observer.observe(this.$refs.bar);
                    this.sync();
                }.bind(this));
            },

            // Quantos itens cabem na fileira. As larguras naturais sao medidas
            // uma vez so, na primeira vez que a barra tem largura — depois disso
            // parte dos itens esta escondida e nao daria mais para medir. Como
            // o modo superior nasce em `display:none` quando a pessoa usa o modo
            // lateral, quem dispara essa primeira medicao e o ResizeObserver.
            sync() {
                var bar = this.$refs.bar;

                if (!bar || bar.clientWidth === 0) {
                    return;
                }

                if (!this.measured) {
                    this.widths = Array.prototype.map.call(bar.children, function (el) {
                        return el.getBoundingClientRect().width + 2;
                    });

                    if (!this.widths.length || this.widths.indexOf(2) !== -1) {
                        return;
                    }

                    this.measured = true;
                }

                // A conta corre na ordem visual, nao na do DOM: o `order` do
                // flexbox pode ter mudado a sequencia, e quem sobra para o
                // "Mais" tem que ser o final da fila que a pessoa montou.
                var ordered = this.orderedNavKeys;
                var keys = this.navKeys;
                var widths = this.widths;
                var available = bar.clientWidth;
                var used = 0;
                var count = 0;

                for (var r = 0; r < ordered.length; r++) {
                    var domIndex = keys.indexOf(ordered[r]);
                    var width = domIndex === -1 ? 0 : widths[domIndex];
                    // O ultimo item nao precisa reservar espaco pro "Mais":
                    // se ele coube, nao sobrou nada pra esconder.
                    var reserve = r === ordered.length - 1 ? 0 : this.moreWidth;

                    if (used + width + reserve > available) {
                        break;
                    }

                    used += width;
                    count++;
                }

                this.visibleCount = count;
            },

            show(key, el) {
                clearTimeout(this.closeTimer);

                var r = el.getBoundingClientRect();

                this.pos = {
                    top: r.bottom,
                    left: Math.max(8, Math.min(r.left, window.innerWidth - 248)),
                };

                this.open = key;
            },

            toggle(key, el) {
                if (this.open === key) {
                    this.open = null;
                    return;
                }

                this.show(key, el);
            },

            // O atraso e o que da tempo de atravessar do botao para o painel.
            hide() {
                this.closeTimer = setTimeout(function () { this.open = null; }.bind(this), 200);
            },

            keep() {
                clearTimeout(this.closeTimer);
            },
        };
    };
</script>

<nav
    x-data="topNav(@js(array_column($visibleNavLinks, 'key')))"
    @keydown.escape.window="open = null"
    @nav-order-changed.window="sync()"
    class="sticky top-0 z-40 bg-white dark:bg-gray-800 shadow-lg border-b border-gray-100 dark:border-gray-700"
>
    <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center gap-3">
            {{-- Logo --}}
            <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center">
                <x-application-logo :width="'30px'" :height="'38px'" :color="'#A00001'" class="fill-red-800 dark:fill-white" />
                <span class="ml-2 text-xl font-extrabold text-red-800 dark:text-white">LARA</span>
            </a>

            {{-- Desktop links --}}
            <div class="hidden min-w-0 flex-1 items-center sm:flex">
                <div
                    x-ref="bar"
                    class="flex min-w-0 flex-1 items-center gap-0.5 overflow-hidden transition-opacity duration-150"
                    :class="measured ? 'opacity-100' : 'opacity-0'"
                >
                    @foreach($visibleNavLinks as $link)
                        @php
                            $children = $link['children'] ?? null;
                            $isActive = $children
                                ? collect($children)->contains(fn($c) => request()->routeIs($c['active'] ?? $c['route']))
                                : request()->routeIs($link['active'] ?? $link['route']);
                            $baseClasses     = 'inline-flex items-center whitespace-nowrap px-2.5 py-2 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out rounded-t-md';
                            $activeClasses   = 'border-red-800 dark:border-red-400 text-red-800 dark:text-red-400 bg-red-50/50 dark:bg-red-900/20';
                            $inactiveClasses = 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700';
                            $key = 'g'.$loop->index;
                        @endphp

                        <div class="shrink-0"
                            :class="navRank('{{ $link['key'] }}') >= visibleCount ? 'hidden' : ''"
                            :style="{ order: navRank('{{ $link['key'] }}') }"
                        >
                            @if($children)
                                <button
                                    @click="toggle('{{ $key }}', $event.currentTarget)"
                                    @mouseenter="show('{{ $key }}', $event.currentTarget)"
                                    @mouseleave="hide()"
                                    class="{{ $baseClasses }} {{ $isActive ? $activeClasses : $inactiveClasses }}"
                                >
                                    <svg class="mr-1 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}"></path>
                                    </svg>
                                    {{ __($link['label']) }}
                                    <svg class="ms-1 h-3 w-3 transition-transform duration-200" :class="open === '{{ $key }}' ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>

                                {{-- O painel vai para o body: a fileira e
                                     `overflow-hidden` (para medir sem quebrar o
                                     layout) e cortaria um painel posicionado
                                     dentro dela. O `pt-1.5` e a ponte de hover:
                                     a area clicavel encosta no botao, mesmo com
                                     o cartao desenhado um pouco abaixo. --}}
                                <template x-teleport="body">
                                    <div
                                        x-show="open === '{{ $key }}'"
                                        x-transition.opacity.duration.150ms
                                        @mouseenter="keep()"
                                        @mouseleave="hide()"
                                        @click.outside="open === '{{ $key }}' && (open = null)"
                                        class="fixed z-[60] pt-1.5"
                                        :style="{ top: pos.top + 'px', left: pos.left + 'px' }"
                                        style="display: none;"
                                    >
                                        <div class="w-60 rounded-xl border border-gray-100 bg-white py-2 shadow-xl dark:border-gray-700 dark:bg-gray-800">
                                            <div class="mb-1 border-b border-gray-100 px-4 pb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:border-gray-700">
                                                {{ __($link['label']) }}
                                            </div>
                                            @foreach($children as $child)
                                                @php $childActive = request()->routeIs($child['active'] ?? $child['route']); @endphp
                                                <div class="group flex items-center">
                                                    <a href="{{ route($child['route']) }}"
                                                        class="block min-w-0 flex-1 truncate px-4 py-2 text-sm transition {{ $childActive ? 'bg-red-50 font-semibold text-red-800 dark:bg-red-900/20 dark:text-red-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                                        {{ __($child['label']) }}
                                                    </a>
                                                    <x-nav-fav-star :route-key="$child['route']" class="mr-1.5" />
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </template>
                            @else
                                <div class="group flex items-center">
                                    <a href="{{ route($link['route']) }}"
                                        class="{{ $baseClasses }} {{ $isActive ? $activeClasses : $inactiveClasses }}">
                                        <svg class="mr-1 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}"></path>
                                        </svg>
                                        {{ __($link['label']) }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Excedente: o que nao coube na largura da tela. --}}
                <div class="shrink-0" x-show="visibleCount < total" x-cloak>
                    <button
                        @click="toggle('more', $event.currentTarget)"
                        @mouseenter="show('more', $event.currentTarget)"
                        @mouseleave="hide()"
                        class="inline-flex items-center whitespace-nowrap rounded-t-md border-b-2 border-transparent px-2.5 py-2 text-sm font-medium leading-5 text-gray-500 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                    >
                        Mais
                        <span class="ms-1.5 rounded-full bg-gray-100 px-1.5 text-[11px] font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-300"
                            x-text="total - visibleCount"></span>
                        <svg class="ms-1 h-3 w-3 transition-transform duration-200" :class="open === 'more' ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <template x-teleport="body">
                        <div
                            x-show="open === 'more'"
                            x-transition.opacity.duration.150ms
                            @mouseenter="keep()"
                            @mouseleave="hide()"
                            @click.outside="open === 'more' && (open = null)"
                            class="fixed z-[60] pt-1.5"
                            :style="{ top: pos.top + 'px', left: pos.left + 'px' }"
                            style="display: none;"
                        >
                            <div class="flex max-h-[70vh] w-64 flex-col overflow-y-auto rounded-xl border border-gray-100 bg-white py-2 shadow-xl dark:border-gray-700 dark:bg-gray-800">
                                @foreach($visibleNavLinks as $link)
                                    @php $children = $link['children'] ?? null; @endphp

                                    <div
                                        :class="navRank('{{ $link['key'] }}') < visibleCount ? 'hidden' : ''"
                                        :style="{ order: navRank('{{ $link['key'] }}') }"
                                    >
                                        @if($children)
                                            <div class="mt-1 px-4 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                                {{ __($link['label']) }}
                                            </div>
                                            @foreach($children as $child)
                                                @php $childActive = request()->routeIs($child['active'] ?? $child['route']); @endphp
                                                <div class="group flex items-center">
                                                    <a href="{{ route($child['route']) }}"
                                                        class="block min-w-0 flex-1 truncate px-4 py-2 text-sm transition {{ $childActive ? 'bg-red-50 font-semibold text-red-800 dark:bg-red-900/20 dark:text-red-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                                        {{ __($child['label']) }}
                                                    </a>
                                                    <x-nav-fav-star :route-key="$child['route']" class="mr-1.5" />
                                                </div>
                                            @endforeach
                                        @else
                                            @php $itemActive = request()->routeIs($link['active'] ?? $link['route']); @endphp
                                            <div class="group flex items-center">
                                                <a href="{{ route($link['route']) }}"
                                                    class="flex min-w-0 flex-1 items-center gap-2 truncate px-4 py-2 text-sm transition {{ $itemActive ? 'bg-red-50 font-semibold text-red-800 dark:bg-red-900/20 dark:text-red-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}"></path>
                                                    </svg>
                                                    {{ __($link['label']) }}
                                                </a>
                                                <x-nav-fav-star :route-key="$link['route']" class="mr-1.5" />
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Busca, favoritos e conta --}}
            <div class="hidden shrink-0 items-center gap-2 sm:flex">
                <button type="button" @click="openPalette()"
                    title="Buscar módulo (Ctrl+K)"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-2.5 py-2 text-sm text-gray-400 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-600 dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                    <kbd class="hidden text-[10px] font-medium lg:block">Ctrl K</kbd>
                </button>

                {{-- Favoritos: só existe depois que a pessoa fixa o primeiro. --}}
                <div x-data="{ favOpen: false }" class="relative" x-show="favItems.length" x-cloak>
                    <button type="button" @click="favOpen = !favOpen" @click.outside="favOpen = false"
                        title="Favoritos"
                        class="inline-flex items-center rounded-lg border border-gray-200 p-2 text-amber-400 transition hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11.48 3.5a.56.56 0 011.04 0l2.12 4.7 5.11.6c.47.05.66.64.31.96l-3.8 3.45 1.03 5.05c.09.46-.4.82-.81.59L12 16.3l-4.48 2.55c-.41.23-.9-.13-.81-.59l1.03-5.05-3.8-3.45c-.35-.32-.16-.91.31-.96l5.11-.6 2.12-4.7z" />
                        </svg>
                    </button>

                    <div x-show="favOpen" x-transition
                        class="absolute right-0 z-50 mt-2 w-60 overflow-hidden rounded-xl border border-gray-100 bg-white py-1 shadow-xl dark:border-gray-700 dark:bg-gray-800"
                        style="display: none;">
                        <div class="mb-1 border-b border-gray-100 px-4 pb-1.5 pt-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:border-gray-700">
                            Favoritos
                        </div>
                        <template x-for="item in favItems" :key="item.key">
                            <div class="group flex items-center">
                                <a :href="item.url"
                                    class="flex min-w-0 flex-1 items-center gap-2 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                    </svg>
                                    <span class="truncate" x-text="item.label"></span>
                                </a>
                                <button type="button" @click="toggleFav(item.key)" title="Remover dos favoritos"
                                    class="mr-1.5 shrink-0 rounded-lg p-1.5 text-amber-400 opacity-0 transition group-hover:opacity-100">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M11.48 3.5a.56.56 0 011.04 0l2.12 4.7 5.11.6c.47.05.66.64.31.96l-3.8 3.45 1.03 5.05c.09.46-.4.82-.81.59L12 16.3l-4.48 2.55c-.41.23-.9-.13-.81-.59l1.03-5.05-3.8-3.45c-.35-.32-.16-.91.31-.96l5.11-.6 2.12-4.7z" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Conta --}}
                <div x-data="{ userOpen: false }" class="relative">
                    <button @click="userOpen = !userOpen" @click.outside="userOpen = false"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-red-800 text-xs font-bold text-white">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </span>
                        <span class="hidden max-w-[10rem] truncate lg:block">{{ Auth::user()->name }}</span>
                        <svg class="h-4 w-4 shrink-0" :class="userOpen ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <div x-show="userOpen" x-transition
                        class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800"
                        style="display: none;">
                        <x-nav-account-links />
                    </div>
                </div>
            </div>

            {{-- Hamburger --}}
            <div class="-me-2 ml-auto flex items-center gap-1 sm:hidden">
                <button type="button" @click="openPalette()" title="Buscar módulo"
                    class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-500 dark:text-gray-500 dark:hover:bg-gray-700">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                </button>
                <button @click="mobileMenu = !mobileMenu"
                    class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-500 dark:text-gray-500 dark:hover:bg-gray-700">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': mobileMenu, 'inline-flex': !mobileMenu }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': !mobileMenu, 'inline-flex': mobileMenu }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Responsive menu --}}
    <div :class="{'block': mobileMenu, 'hidden': !mobileMenu}" class="hidden border-t border-gray-100 sm:hidden dark:border-gray-700">
        {{-- Mesmo esquema da lateral: flex-col para o `order` valer aqui
             também, senão a ordem escolhida só existiria no desktop. --}}
        <div class="flex flex-col pb-3 pt-2">
            <template x-if="favItems.length">
                <div class="order-first">
                    <div class="px-4 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Favoritos</div>
                    <template x-for="item in favItems" :key="item.key">
                        <a :href="item.url"
                            class="block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-700"
                            x-text="item.label"></a>
                    </template>
                </div>
            </template>

            @foreach($visibleNavLinks as $link)
                @php $children = $link['children'] ?? null; @endphp
                <div :style="{ order: navRank('{{ $link['key'] }}') }">
                    @if($children)
                        <div class="px-4 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __($link['label']) }}</div>
                        @foreach($children as $child)
                            <x-responsive-nav-link :href="route($child['route'])" :active="request()->routeIs($child['active'] ?? $child['route'])">
                                {{ __($child['label']) }}
                            </x-responsive-nav-link>
                        @endforeach
                    @else
                        <x-responsive-nav-link :href="route($link['route'])" :active="request()->routeIs($link['active'] ?? $link['route'])">
                            {{ __($link['label']) }}
                        </x-responsive-nav-link>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="border-t border-gray-200 pb-1 pt-4 dark:border-gray-600">
            <div class="px-4">
                <div class="text-base font-medium text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">{{ __('Perfil') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('docs.index')">{{ __('Documentação') }}</x-responsive-nav-link>
                @role('admin')
                <x-responsive-nav-link :href="route('users.index')">{{ __('Usuários') }}</x-responsive-nav-link>
                @endrole
                <button type="button" @click="organizerOpen = true; mobileMenu = false"
                    class="block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-700">
                    {{ __('Organizar menu') }}
                </button>
                <x-nav-mode-toggle />
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Sair') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
