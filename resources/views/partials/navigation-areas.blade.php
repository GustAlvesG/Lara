{{--
    Navegação por Módulos — a única do sistema desde 28/10/2026. Refeita em
    10/11/2026 como "ilha":

    - Ilha: barra flutuante, translúcida (fundo a 75% + desfoque forte, para o
      conteúdo aparecer atrás ao rolar), solta das bordas. Leva a cor do
      módulo aberto — o "você está em" é um chip na cor da área e um traço na
      base da ilha acende na mesma cor. Logo, Módulos, você está em, busca
      (Ctrl K), sino e conta.
    - Doca: favoritos e recentes logo abaixo da ilha. Recolhe quando a página
      desce e volta quando sobe (a ilha fica). Recolhida, sai do Tab (inert).
    - Painel de Módulos: todos os módulos de uma vez, fechados e compactos
      (sem rolar). O nome abre a primeira aba; o botão ao lado abre as outras
      abas, um módulo por vez. Favoritos e recentes na coluna ao lado. No
      celular é uma folha que sobe de baixo, com abas.
    - Celular: doca flutuante embaixo, com Módulos no centro.

    O estado (favoritos, recentes, ordem, painel, busca) vem do laraShell
    (layouts/app.blade.php); aqui só o recolher da doca (laraNavBar), que
    também atualiza --split-top para as colunas presas abaixo da barra.
--}}
@php
    $userName = (string) (Auth::user()->name ?? '');
    $nameParts = preg_split('/\s+/', trim($userName), -1, PREG_SPLIT_NO_EMPTY);
    // Primeiro e último nome: "Gustavo Delgado Alves" vira GA.
    $initials = $nameParts
        ? mb_strtoupper(mb_substr($nameParts[0], 0, 1) . (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : ''))
        : '?';

    // A ilha toma a cor do módulo aberto; fora de módulo, a do início (grená).
    $areaStyle = \App\View\AreaColor::style($navCurrent['group']['area'] ?? 'inicio', paint: false);

    $focus = 'focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint';
    $chipClasses = 'inline-flex h-9 shrink-0 items-center gap-2 whitespace-nowrap rounded-full bg-surface pl-2.5 pr-3.5 text-[13px] font-semibold text-ink no-underline shadow-card ring-1 ring-line transition hover:-translate-y-px hover:ring-line-strong motion-reduce:hover:translate-y-0 aria-[current=page]:bg-ink aria-[current=page]:text-canvas aria-[current=page]:ring-transparent ' . $focus;
    $dockLabel = 'inline-flex h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-2 text-xs font-bold uppercase tracking-[0.08em] text-ink-3 transition hover:text-ink ' . $focus;
    $tabClasses = 'inline-flex h-9 min-w-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-full px-2 text-[13px] font-bold text-ink-2 transition aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-card';
    $islandButton = 'grid h-10 w-10 shrink-0 place-items-center rounded-full text-ink-2 transition hover:bg-subtle hover:text-ink ' . $focus;
@endphp

<script>
    // Recolher a doca ao descer a página e trazê-la de volta ao subir. Só a
    // doca se move; a ilha fica. A altura real da barra vira --split-top,
    // que as telas de duas colunas usam para prender a coluna abaixo dela.
    window.laraNavBar = function () {
        return {
            compact: false,
            lastY: 0,

            initBar() {
                this.lastY = window.scrollY;
                this.syncTop();
                window.addEventListener('resize', this.syncTop.bind(this), { passive: true });
            },

            onScroll() {
                var y = window.scrollY;

                if (y < 80) {
                    this.compact = false;
                } else if (y > this.lastY + 8) {
                    this.compact = true;
                } else if (y < this.lastY - 8) {
                    this.compact = false;
                }

                this.lastY = y;
            },

            syncTop() {
                var height = this.$root.offsetHeight;
                document.documentElement.style.setProperty('--split-top', (height + 12) + 'px');
            },
        };
    };
</script>

<header x-data="laraNavBar()" x-init="initBar()" @scroll.window.passive="onScroll()" @transitionend="syncTop()"
    class="pointer-events-none sticky z-40 px-2 pt-2 sm:px-4 sm:pt-3" style="top: env(safe-area-inset-top, 0px); {{ $areaStyle }}">

    {{-- ============================== Ilha ============================== --}}
    {{-- z-10: o backdrop-blur faz da ilha e da doca camadas próprias; sem ele
         a doca (que vem depois) tampa o menu da conta e o do sino. --}}
    <div class="pointer-events-auto relative z-10 mx-auto flex h-[60px] max-w-[1680px] items-center gap-1.5 rounded-[22px] border border-surface/60 bg-surface/75 pl-2 pr-1.5 shadow-[0_14px_34px_-20px_rgb(var(--ink)/.55)] ring-1 ring-line/70 backdrop-blur-2xl backdrop-saturate-[1.8] sm:gap-2 sm:pl-2.5 sm:pr-2">

        <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-1.5 rounded-2xl pr-1 no-underline {{ $focus }}" aria-label="LARA, ir para o início">
            <img src="{{ asset('images/logo_girante.gif') }}" alt="" class="h-[44px] w-auto">
            <span class="hidden font-display text-[17px] font-bold tracking-tight text-ink lg:inline">LARA</span>
        </a>

        <span class="mx-0.5 hidden h-7 w-px shrink-0 bg-line-strong sm:block" aria-hidden="true"></span>

        <button type="button" @click="toggleLauncher('todas')" :aria-expanded="launcherOpen.toString()" aria-expanded="false" aria-controls="lara-launcher"
            class="group inline-flex h-10 shrink-0 items-center gap-2 rounded-full bg-subtle px-3 text-[13.5px] font-bold text-ink transition hover:bg-line active:scale-[.97] aria-expanded:bg-ink aria-expanded:text-canvas sm:pr-4 {{ $focus }}">
            <span class="relative grid h-[18px] w-[18px] place-items-center">
                <x-icon name="grid" class="absolute h-[18px] w-[18px] transition duration-200 group-aria-expanded:rotate-90 group-aria-expanded:scale-0" />
                <x-icon name="x" class="absolute h-[18px] w-[18px] scale-0 transition duration-200 group-aria-expanded:scale-100" />
            </span>
            <span class="hidden sm:inline">Módulos</span>
        </button>

        @if ($navCurrent)
            {{-- Você está em: chip na cor do módulo. --}}
            <nav class="hidden h-10 min-w-0 items-center gap-2 overflow-hidden whitespace-nowrap rounded-full pl-3 pr-4 text-[13.5px] md:flex"
                aria-label="Você está em" style="background-color: rgb(var(--c)); color: rgb(var(--ci))">
                <x-icon :name="$navCurrent['group']['glyph'] ?? 'grid'" class="h-4 w-4 shrink-0" />
                @if (isset($navCurrent['group']['children']))
                    <a href="{{ route($navCurrent['group']['route']) }}" class="font-semibold no-underline opacity-75 transition hover:opacity-100">{{ __($navCurrent['group']['label']) }}</a>
                    <span aria-hidden="true">/</span>
                @endif
                <b class="truncate font-bold">{{ $navCurrent['label'] }}</b>
            </nav>
        @endif

        <button type="button" @click="openPalette()" aria-label="Buscar (Ctrl K)"
            class="group ml-auto flex h-10 w-10 shrink-0 items-center justify-center gap-2 rounded-full text-[13.5px] text-ink-3 transition hover:bg-subtle md:w-[220px] md:justify-start md:bg-subtle md:px-3.5 md:hover:bg-line lg:w-[320px] {{ $focus }}">
            <x-icon name="search" class="h-[18px] w-[18px] transition group-hover:text-ink" />
            <span class="hidden md:inline">Buscar ou ir para…</span>
            <kbd class="ml-auto hidden rounded-md bg-surface px-1.5 py-0.5 font-mono text-[11px] font-semibold text-ink-3 shadow-card lg:inline">Ctrl K</kbd>
        </button>

        @include('partials.notification-bell')

        <div x-data="{ userOpen: false }" class="relative" @keydown.escape.window="userOpen = false">
            <button type="button" @click="userOpen = !userOpen" @click.outside="userOpen = false"
                :aria-expanded="userOpen.toString()" aria-expanded="false" aria-label="Sua conta"
                class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-grena text-[12.5px] font-bold text-white transition hover:ring-4 hover:ring-grena-tint active:scale-95 {{ $focus }}">
                {{ $initials }}
            </button>

            <div x-show="userOpen" x-cloak x-transition.opacity.duration.150ms
                class="absolute right-0 top-full z-50 mt-3 w-[min(18rem,calc(100vw-24px))] overflow-hidden rounded-[20px] border border-line bg-surface p-2 shadow-pop">
                <div class="px-2.5 pb-2 pt-1.5">
                    <b class="block truncate text-ink">{{ $userName }}</b>
                    @if (Auth::user()->email)
                        <small class="block truncate text-xs text-ink-3">{{ Auth::user()->email }}</small>
                    @endif
                </div>
                <x-nav-account-links />
            </div>
        </div>

        {{-- Traço na cor do módulo aberto, na base da ilha. --}}
        <span class="pointer-events-none absolute inset-x-8 -bottom-px h-[2px] rounded-full opacity-80" aria-hidden="true"
            style="background: linear-gradient(90deg, transparent, rgb(var(--ci)) 25%, rgb(var(--ci)) 75%, transparent)"></span>
    </div>

    {{-- ============================== Doca ============================== --}}
    {{-- Favoritos são da conta; recentes, deste navegador — montados no cliente. --}}
    <div class="pointer-events-auto mx-auto max-w-[1680px] overflow-hidden transition-[max-height,opacity,margin] duration-300 ease-out motion-reduce:transition-none"
        :class="compact ? 'mt-0 max-h-0 opacity-0' : 'mt-1.5 max-h-14 opacity-100'" x-effect="$el.inert = compact">
        <nav class="flex h-12 w-fit max-w-full items-center gap-1.5 overflow-x-auto rounded-full bg-surface/70 px-1.5 ring-1 ring-line/70 backdrop-blur-2xl backdrop-saturate-[1.8] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            aria-label="Favoritos e recentes">
            <button type="button" @click="openLauncher('favoritos')" class="{{ $dockLabel }}">
                <x-icon name="star" class="h-3.5 w-3.5 text-star" />Favoritos
            </button>
            <template x-for="item in favItems" :key="'fav-' + item.key">
                <a :href="item.url" :style="item.areaStyle" :aria-current="item.key === currentKey ? 'page' : null" class="{{ $chipClasses }}">
                    <i class="h-2.5 w-2.5 shrink-0 rounded-full ring-2 ring-surface" style="background-color: rgb(var(--ci))"></i>
                    <span x-text="item.label"></span>
                </a>
            </template>
            <span x-show="!favItems.length" class="shrink-0 whitespace-nowrap text-[13px] text-ink-3">Marque páginas com a estrela</span>

            <span class="mx-1.5 h-5 w-px shrink-0 bg-line-strong" aria-hidden="true"></span>

            <button type="button" @click="openLauncher('recentes')" class="{{ $dockLabel }}">
                <x-icon name="history" class="h-3.5 w-3.5" />Recentes
            </button>
            <template x-for="item in recentItems" :key="'rec-' + item.key">
                <a :href="item.url" :style="item.areaStyle" class="{{ $chipClasses }}">
                    <i class="h-2.5 w-2.5 shrink-0 rounded-full ring-2 ring-surface" style="background-color: rgb(var(--ci))"></i>
                    <span x-text="item.label"></span>
                    <small x-show="item.group" class="font-medium text-ink-3" x-text="item.group"></small>
                </a>
            </template>
            <span x-show="!recentItems.length" class="shrink-0 whitespace-nowrap text-[13px] text-ink-3">Nada ainda</span>
        </nav>
    </div>
</header>

{{-- ========================= Painel de Módulos ========================= --}}
<div x-show="launcherOpen" x-cloak x-transition.opacity.duration.200ms @click="launcherOpen = false"
    class="fixed inset-0 z-40 bg-ink/30 backdrop-blur-[2px]"></div>

<div id="lara-launcher" x-show="launcherOpen" x-cloak role="dialog" aria-modal="true" aria-label="Módulos"
    x-effect="if (launcherOpen) $nextTick(() => $refs.launcherClose && $refs.launcherClose.focus())"
    x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
    x-transition:enter-start="opacity-0 translate-y-3"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-x-2 bottom-2 z-50 mx-auto flex max-h-[calc(100dvh-80px)] max-w-[1180px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface text-ink shadow-pop sm:inset-x-4 sm:bottom-auto sm:top-[calc(84px+env(safe-area-inset-top,0px))] sm:max-h-[calc(100dvh-104px)]">

    <div class="flex shrink-0 items-center gap-3 border-b border-line px-4 py-3 sm:px-5">
        <h2 class="font-display text-lg font-semibold tracking-tight">Módulos</h2>
        <span class="font-mono text-xs text-ink-3">{{ count($navGroups) }}</span>

        {{-- Abas só no celular; na tela larga as três partes aparecem juntas. --}}
        <div class="ml-auto grid grid-cols-3 gap-1 rounded-full bg-subtle p-1 md:hidden" role="tablist" aria-label="O que mostrar">
            @foreach (['todas' => ['grid', 'Módulos'], 'favoritos' => ['star', 'Favoritos'], 'recentes' => ['history', 'Recentes']] as $tab => [$glyph, $label])
                <button type="button" role="tab" @click="launcherTab = '{{ $tab }}'" :aria-selected="(launcherTab === '{{ $tab }}').toString()" class="{{ $tabClasses }}">
                    <x-icon :name="$glyph" class="h-3.5 w-3.5" /><span class="hidden min-[400px]:inline">{{ $label }}</span>
                </button>
            @endforeach
        </div>

        <button type="button" x-ref="launcherClose" @click="launcherOpen = false" aria-label="Fechar Módulos" class="{{ $islandButton }} md:ml-auto">
            <x-icon name="x" class="h-5 w-5" />
        </button>
    </div>

    {{-- Só esta parte rola. --}}
    <div data-mod-scroll class="grid min-h-0 flex-1 grid-cols-1 overflow-y-auto md:grid-cols-[minmax(0,1fr)_260px]">

        {{-- Módulos (11/11/2026): todos fechados e compactos, para caberem sem
             rolar. O nome abre a primeira aba do módulo; o botão com o número
             de páginas abre as outras abas, um módulo por vez (openMod).
             Grid com `order` (e não a ordem do DOM) para respeitar a ordem de
             "Organizar menu".

             As abas abrem SOBREPOSTAS (11/11/2026, pedido do usuário): a lista
             flutua sobre os módulos de baixo em vez de empurrá-los. Perto do
             fim do painel, sem espaço embaixo, ela abre para cima (openUp). --}}
        <div x-show="launcherTab === 'todas'"
            x-data="{
                openMod: null,
                openUp: false,
                toggleMod(key, button, count) {
                    if (this.openMod === key) { this.openMod = null; return; }
                    var box = button.closest('[data-mod-scroll]').getBoundingClientRect();
                    var tile = button.closest('section').getBoundingClientRect();
                    var need = count * 38 + 24;
                    var below = box.bottom - tile.bottom;
                    this.openUp = below < need && (tile.top - box.top) > below;
                    this.openMod = key;
                },
            }"
            @keydown.escape.stop="openMod ? openMod = null : (launcherOpen = false)"
            @click.self="openMod = null"
            class="grid content-start items-start grid-cols-2 gap-2 p-3 sm:p-4 md:!grid lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($navGroups as $group)
                @php
                    $pages = $group['children'];
                    $hasPages = count($pages) > 1;
                    $isHere = ($navCurrent['group']['key'] ?? null) === $group['key'];
                    $panelId = 'mod-' . $group['key'];
                @endphp
                <section style="{{ \App\View\AreaColor::style($group['area'], paint: false) }}" :style="{ order: navRank('{{ $group['key'] }}') }"
                    class="relative min-w-0 rounded-2xl bg-surface ring-1 {{ $isHere ? 'ring-2 ring-[rgb(var(--ci))]' : 'ring-line' }}"
                    :class="openMod === '{{ $group['key'] }}' ? 'z-20' : 'z-0'">
                    <div class="flex items-center rounded-2xl" style="background-color: rgb(var(--c)); color: rgb(var(--ci))">
                        <a href="{{ $group['url'] }}" title="Abrir {{ $group['label'] }}"
                            class="group/mod flex min-w-0 flex-1 items-center gap-2.5 py-2 pl-2 pr-1 no-underline {{ $focus }} rounded-2xl">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-surface transition group-hover/mod:scale-105 motion-reduce:group-hover/mod:scale-100">
                                <x-icon :name="$group['glyph']" class="h-[18px] w-[18px]" />
                            </span>
                            <b class="truncate text-[13.5px] font-bold leading-tight group-hover/mod:underline">{{ $group['label'] }}</b>
                        </a>
                        @if ($hasPages)
                            <button type="button" @click="toggleMod('{{ $group['key'] }}', $el, {{ count($pages) }})"
                                :aria-expanded="(openMod === '{{ $group['key'] }}').toString()" aria-expanded="false" aria-controls="{{ $panelId }}"
                                aria-label="Ver as {{ count($pages) }} páginas de {{ $group['label'] }}"
                                class="mr-1.5 inline-flex h-8 shrink-0 items-center gap-0.5 rounded-full px-2 text-xs font-bold transition hover:bg-surface/70 aria-expanded:bg-surface {{ $focus }}">
                                <span class="hidden font-mono sm:inline">{{ count($pages) }}</span>
                                <x-icon name="chevron-down" class="h-4 w-4 transition-transform duration-200" x-bind:class="openMod === '{{ $group['key'] }}' ? 'rotate-180' : ''" />
                            </button>
                        @endif
                    </div>
                    @if ($hasPages)
                        <ul id="{{ $panelId }}" x-show="openMod === '{{ $group['key'] }}'" x-cloak
                            x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none" x-transition:enter-start="opacity-0 scale-[.97]" x-transition:enter-end="opacity-100 scale-100"
                            :class="openUp ? 'bottom-full mb-1.5 origin-bottom' : 'top-full mt-1.5 origin-top'"
                            class="absolute inset-x-0 z-20 flex flex-col gap-0.5 rounded-2xl bg-surface p-1.5 shadow-pop ring-1 ring-line" aria-label="Abas de {{ $group['label'] }}">
                            @foreach ($pages as $page)
                                <li>
                                    <a href="{{ $page['url'] }}" :aria-current="currentKey === '{{ $page['key'] }}' ? 'page' : null"
                                        class="flex h-9 items-center gap-2 rounded-xl px-2.5 text-[13px] font-semibold text-ink no-underline transition hover:bg-[rgb(var(--c))] hover:text-[rgb(var(--ci))] aria-[current=page]:bg-[rgb(var(--c))] aria-[current=page]:text-[rgb(var(--ci))] {{ $focus }}">
                                        <i class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: rgb(var(--ci))"></i>
                                        <span class="truncate">{{ $page['label'] }}</span>
                                        @if ($loop->first)
                                            <small class="ml-auto shrink-0 text-[11px] font-medium text-ink-3">primeira</small>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>

        {{-- Favoritos e recentes: coluna ao lado na tela larga, aba no celular. --}}
        <aside x-show="launcherTab !== 'todas'" class="flex flex-col gap-5 border-line bg-canvas/60 p-4 sm:p-5 md:!flex md:border-l">
            @foreach (['favoritos' => ['favItems', 'star', 'Favoritos', 'Nenhum favorito ainda. Use a estrela na capa de qualquer módulo.'], 'recentes' => ['recentItems', 'history', 'Recentes', 'As páginas que você abrir aparecem aqui.']] as $tab => [$list, $glyph, $title, $empty])
                <section x-show="launcherTab === '{{ $tab }}'" class="md:!block">
                    <h3 class="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-[0.08em] text-ink-3">
                        <x-icon :name="$glyph" :class="$tab === 'favoritos' ? 'h-3.5 w-3.5 text-star' : 'h-3.5 w-3.5'" />{{ $title }}
                    </h3>
                    <div class="flex flex-col gap-0.5">
                        <template x-for="item in {{ $list }}" :key="'{{ $tab }}-' + item.key">
                            <a :href="item.url" class="flex items-center gap-3 rounded-xl px-2 py-2 text-ink no-underline transition hover:bg-surface hover:shadow-card {{ $focus }}">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-[10px]" :style="item.areaStyle + ' background-color: rgb(var(--c)); color: rgb(var(--ci));'">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                                    </svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold" x-text="item.label"></span>
                                    <small class="block truncate text-xs text-ink-3" x-text="item.group || 'Página inicial'"></small>
                                </span>
                            </a>
                        </template>
                        <p x-show="!{{ $list }}.length" class="rounded-2xl border-[1.5px] border-dashed border-line-strong p-4 text-sm text-ink-2">{{ $empty }}</p>
                    </div>
                </section>
            @endforeach
        </aside>
    </div>

    <div class="flex shrink-0 items-center justify-between gap-3 border-t border-line px-4 py-3 text-[13px] sm:px-5">
        <span class="hidden text-ink-3 sm:inline">Dica: <kbd class="rounded-md bg-subtle px-1.5 py-0.5 font-mono text-[11px]">Ctrl K</kbd> busca qualquer página, de qualquer tela</span>
        <button type="button" @click="launcherOpen = false; organizerOpen = true" class="inline-flex h-9 items-center gap-1.5 rounded-full px-3 font-bold text-grena-ink transition hover:bg-grena-tint {{ $focus }}">
            <x-icon name="sliders" class="h-4 w-4" />Organizar menu
        </button>
    </div>
</div>

{{-- ===================== Celular: doca flutuante ===================== --}}
<nav class="fixed inset-x-3 bottom-3 z-30 grid grid-cols-5 items-end rounded-[22px] border border-surface/60 bg-surface/80 px-1 shadow-pop ring-1 ring-line/70 backdrop-blur-2xl backdrop-saturate-[1.8] sm:hidden"
    style="margin-bottom: env(safe-area-inset-bottom, 0px)" aria-label="Navegação">
    @php $tabbarItem = 'flex h-[60px] flex-col items-center justify-center gap-[3px] rounded-2xl text-[11px] font-bold text-ink-3 no-underline transition active:scale-95 aria-[current=page]:text-grena-ink ' . $focus; @endphp
    <a href="{{ route('dashboard') }}" class="{{ $tabbarItem }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
        <x-icon name="home" class="h-[21px] w-[21px]" />Início
    </a>
    <button type="button" @click="openLauncher('favoritos')" class="{{ $tabbarItem }}">
        <x-icon name="star" class="h-[21px] w-[21px]" />Favoritos
    </button>
    <button type="button" @click="toggleLauncher('todas')" :aria-expanded="launcherOpen.toString()" aria-expanded="false" aria-controls="lara-launcher"
        class="flex h-[72px] flex-col items-center justify-start gap-1 pt-0 text-[11px] font-bold text-ink no-underline {{ $focus }} rounded-2xl">
        <span class="-mt-4 grid h-[52px] w-[52px] place-items-center rounded-[18px] bg-grena text-white shadow-pop ring-4 ring-surface transition active:scale-95">
            <x-icon name="grid" class="h-6 w-6" />
        </span>
        Módulos
    </button>
    <button type="button" @click="openLauncher('recentes')" class="{{ $tabbarItem }}">
        <x-icon name="history" class="h-[21px] w-[21px]" />Recentes
    </button>
    <button type="button" @click="openPalette()" class="{{ $tabbarItem }}">
        <x-icon name="search" class="h-[21px] w-[21px]" />Buscar
    </button>
</nav>
