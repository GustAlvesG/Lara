{{--
    Navegação por Módulos (rebrand v4). Duas faixas fixas no topo — a barra
    (logo, Módulos, "você está em", busca, sino, conta) e os atalhos (favoritos e
    recentes) —, o painel de Módulos e, no celular, a barra de baixo.

    Sem x-data próprio: tudo vem do laraShell (layouts/app.blade.php), o mesmo
    estado dos menus de antes, então favoritar aqui aparece lá e vice-versa.
--}}
@php
    $userName = (string) (Auth::user()->name ?? '');
    $nameParts = preg_split('/\s+/', trim($userName), -1, PREG_SPLIT_NO_EMPTY);
    // Primeiro e último nome: "Gustavo Delgado Alves" vira GA.
    $initials = $nameParts
        ? mb_strtoupper(mb_substr($nameParts[0], 0, 1) . (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : ''))
        : '?';

    $chipClasses = 'inline-flex h-[30px] shrink-0 items-center gap-[7px] whitespace-nowrap rounded-full border border-line bg-surface pl-[9px] pr-3 text-[13px] font-semibold text-ink no-underline transition hover:border-line-strong aria-[current=page]:border-transparent aria-[current=page]:bg-grena-tint aria-[current=page]:text-grena-ink';
    $labelClasses = 'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap pr-1 text-xs font-bold uppercase tracking-[0.06em] text-ink-3';
    $tabClasses = 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 py-1.5 text-[13px] font-bold text-ink-2 transition aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-card';
@endphp

<header class="sticky z-40 border-b border-line bg-surface text-ink" style="top: env(safe-area-inset-top, 0px)">
    <div class="flex h-[62px] items-center gap-2.5 px-3 sm:gap-3 sm:px-5">
        <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2 no-underline" aria-label="LARA, ir para o início">
            <x-application-logo :width="'22px'" :height="'28px'" :color="'#A00001'" class="fill-carmim dark:fill-white" />
            <span class="hidden font-display text-[17px] font-bold tracking-tight text-ink sm:inline">LARA</span>
        </a>

        <button type="button" @click="toggleLauncher('todas')" :aria-expanded="launcherOpen.toString()" aria-expanded="false" aria-controls="lara-launcher"
            class="inline-flex h-[38px] shrink-0 items-center gap-2 rounded-full bg-subtle px-[11px] text-[13.5px] font-bold text-ink transition hover:bg-line aria-expanded:bg-ink aria-expanded:text-canvas sm:pr-3.5">
            <x-icon name="grid" class="h-[18px] w-[18px]" />
            <span class="hidden sm:inline">Módulos</span>
        </button>

        @if ($navCurrent)
            <nav class="hidden min-w-0 items-center gap-[7px] overflow-hidden whitespace-nowrap text-[13.5px] text-ink-3 md:flex"
                aria-label="Você está em" style="{{ \App\View\AreaColor::style($navCurrent['group']['area'], paint: false) }}">
                <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: rgb(var(--ci))"></span>
                @if (isset($navCurrent['group']['children']))
                    <a href="{{ route($navCurrent['group']['route']) }}" class="text-ink-3 no-underline hover:text-ink">{{ __($navCurrent['group']['label']) }}</a>
                    <span aria-hidden="true">/</span>
                @endif
                <b class="truncate font-semibold text-ink">{{ $navCurrent['label'] }}</b>
            </nav>
        @endif

        <button type="button" @click="openPalette()" aria-label="Buscar (Ctrl K)"
            class="ml-auto flex h-[38px] w-[38px] shrink-0 items-center justify-center gap-2 rounded-full text-[13.5px] text-ink-3 transition hover:bg-subtle md:w-[200px] md:justify-start md:border-[1.5px] md:border-line md:bg-surface md:px-3 md:hover:border-line-strong md:hover:bg-surface lg:w-[280px]">
            <x-icon name="search" class="h-[18px] w-[18px]" />
            <span class="hidden md:inline">Buscar ou ir para…</span>
            <kbd class="ml-auto hidden rounded-md border border-line-strong px-1.5 py-0.5 font-mono text-[11px] font-semibold text-ink-3 lg:inline">Ctrl K</kbd>
        </button>

        @include('partials.notification-bell', ['placement' => 'bar'])

        <div x-data="{ userOpen: false }" class="relative" @keydown.escape.window="userOpen = false">
            <button type="button" @click="userOpen = !userOpen" @click.outside="userOpen = false"
                :aria-expanded="userOpen.toString()" aria-expanded="false" aria-label="Sua conta"
                class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grena-tint text-[12.5px] font-bold text-grena-ink transition hover:ring-4 hover:ring-grena-tint">
                {{ $initials }}
            </button>

            <div x-show="userOpen" x-cloak x-transition.opacity.duration.150ms
                class="absolute right-0 top-full z-50 mt-2 w-[min(18rem,calc(100vw-24px))] overflow-hidden rounded-[20px] border border-line bg-surface p-2 shadow-pop">
                <div class="px-2.5 pb-2 pt-1.5">
                    <b class="block truncate text-ink">{{ $userName }}</b>
                    @if (Auth::user()->email)
                        <small class="block truncate text-xs text-ink-3">{{ Auth::user()->email }}</small>
                    @endif
                </div>
                <x-nav-account-links />
            </div>
        </div>
    </div>

         {{-- recentes do navegador; os dois são montados no cliente. --}}
         {{-- localStorage de cada pessoa, então são montados no cliente. --}}
    <nav class="flex h-[46px] items-center gap-1.5 overflow-x-auto border-t border-line bg-canvas px-3 [scrollbar-width:none] sm:px-5 [&::-webkit-scrollbar]:hidden"
        aria-label="Favoritos e recentes">
        <button type="button" @click="openLauncher('favoritos')" class="{{ $labelClasses }}">
            <x-icon name="star" class="h-3.5 w-3.5 text-star" />Favoritos
        </button>
        <template x-for="item in favItems" :key="'fav-' + item.key">
            <a :href="item.url" :style="item.areaStyle" :aria-current="item.key === currentKey ? 'page' : null" class="{{ $chipClasses }}">
                <i class="h-2 w-2 shrink-0 rounded-full" style="background-color: rgb(var(--ci))"></i>
                <span x-text="item.label"></span>
            </a>
        </template>
        <span x-show="!favItems.length" class="shrink-0 whitespace-nowrap text-[13px] text-ink-3">Marque páginas com a estrela</span>

        <span class="mx-2 h-[22px] w-px shrink-0 bg-line-strong" aria-hidden="true"></span>

        <span class="{{ $labelClasses }}"><x-icon name="history" class="h-3.5 w-3.5" />Recentes</span>
        <template x-for="item in recentItems" :key="'rec-' + item.key">
            <a :href="item.url" :style="item.areaStyle" class="{{ $chipClasses }}">
                <i class="h-2 w-2 shrink-0 rounded-full" style="background-color: rgb(var(--ci))"></i>
                <span x-text="item.label"></span>
                <small x-show="item.group" class="font-medium text-ink-3" x-text="item.group"></small>
            </a>
        </template>
        <span x-show="!recentItems.length" class="shrink-0 whitespace-nowrap text-[13px] text-ink-3">Nada ainda</span>
    </nav>
</header>

{{-- Painel de Módulos --}}
<div x-show="launcherOpen" x-cloak x-transition.opacity.duration.150ms @click="launcherOpen = false"
    class="fixed inset-0 z-40 bg-ink/25 backdrop-blur-[1px]"></div>

<div id="lara-launcher" x-show="launcherOpen" x-cloak role="dialog" aria-label="Módulos"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 -translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    class="fixed left-3 z-50 flex max-h-[calc(100vh-100px)] w-[min(500px,calc(100vw-24px))] flex-col gap-3 overflow-auto rounded-[20px] border border-line bg-surface p-3.5 text-ink shadow-pop sm:left-5"
    style="top: calc(70px + env(safe-area-inset-top, 0px))">
    <div class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-full bg-subtle p-1" role="tablist">
        @foreach (['todas' => ['grid', 'Todos os módulos'], 'favoritos' => ['star', 'Favoritos'], 'recentes' => ['history', 'Recentes']] as $tab => [$glyph, $label])
            <button type="button" role="tab" @click="launcherTab = '{{ $tab }}'" :aria-selected="(launcherTab === '{{ $tab }}').toString()" class="{{ $tabClasses }}">
                <x-icon :name="$glyph" class="h-3.5 w-3.5" />{{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Flex com `order` (e não grid na ordem do DOM) para respeitar a ordem
         dos grupos que a pessoa escolheu em "Organizar menu". --}}
    <div x-show="launcherTab === 'todas'" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach ($navGroups as $group)
            <a href="{{ $group['url'] }}" style="{{ \App\View\AreaColor::style($group['area']) }}" :style="{ order: navRank('{{ $group['key'] }}') }"
                class="flex min-h-[96px] flex-col justify-between gap-3 rounded-[14px] p-3 no-underline transition hover:-translate-y-0.5">
                <x-icon :name="$group['glyph']" class="h-[23px] w-[23px]" />
                <span>
                    <b class="block text-[13.5px] font-bold leading-tight">{{ $group['label'] }}</b>
                    <span class="text-[11.5px] opacity-80">{{ $group['pages'] }} {{ $group['pages'] === 1 ? 'página' : 'páginas' }}</span>
                </span>
            </a>
        @endforeach
    </div>

    @foreach (['favoritos' => ['favItems', 'Nenhum favorito ainda. Use a estrela na capa de qualquer módulo.'], 'recentes' => ['recentItems', 'As páginas que você abrir aparecem aqui.']] as $tab => [$list, $empty])
        <div x-show="launcherTab === '{{ $tab }}'" x-cloak class="flex flex-col gap-1">
            <template x-for="item in {{ $list }}" :key="'{{ $tab }}-' + item.key">
                <a :href="item.url" class="flex items-center gap-3 rounded-xl px-2.5 py-2 text-ink no-underline transition hover:bg-subtle">
                    <span class="grid h-[30px] w-[30px] shrink-0 place-items-center rounded-[9px]" :style="item.areaStyle + ' background-color: rgb(var(--c)); color: rgb(var(--ci));'">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                    </span>
                    <span class="min-w-0 truncate font-semibold" x-text="item.label"></span>
                    <small class="ml-auto shrink-0 text-[12.5px] text-ink-3" x-text="item.group || 'Página inicial'"></small>
                </a>
            </template>
            <div x-show="!{{ $list }}.length" class="flex items-center gap-3 rounded-2xl border-[1.5px] border-dashed border-line-strong p-5 text-ink-2">
                <x-icon name="star" class="h-5 w-5 text-star" />{{ $empty }}
            </div>
        </div>
    @endforeach

    <div class="flex items-center justify-between border-t border-line pt-3 text-[13px]">
        <span class="text-ink-3">Dica: <kbd class="rounded-md border border-line-strong px-1.5 py-0.5 font-mono text-[11px]">Ctrl K</kbd> busca qualquer página</span>
        <button type="button" @click="launcherOpen = false; organizerOpen = true" class="font-bold text-grena-ink hover:underline">Organizar</button>
    </div>
</div>

{{-- Celular: navegação embaixo, ao alcance do polegar. --}}
<nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-line bg-surface sm:hidden"
    style="padding-bottom: env(safe-area-inset-bottom, 0px)" aria-label="Navegação">
    @php $tabbarItem = 'flex h-[62px] flex-col items-center justify-center gap-[3px] text-[11px] font-bold text-ink-3 no-underline aria-[current=page]:text-grena-ink'; @endphp
    <a href="{{ route('dashboard') }}" class="{{ $tabbarItem }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
        <x-icon name="home" class="h-[21px] w-[21px]" />Início
    </a>
    <button type="button" @click="openLauncher('favoritos')" class="{{ $tabbarItem }}">
        <x-icon name="star" class="h-[21px] w-[21px]" />Favoritos
    </button>
    <button type="button" @click="toggleLauncher('todas')" class="{{ $tabbarItem }}">
        <x-icon name="grid" class="h-[21px] w-[21px]" />Módulos
    </button>
    <button type="button" @click="openPalette()" class="{{ $tabbarItem }}">
        <x-icon name="search" class="h-[21px] w-[21px]" />Buscar
    </button>
</nav>
