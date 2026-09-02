{{-- Mobile backdrop --}}
<div
    x-show="mobileOpen"
    @click="mobileOpen = false"
    class="fixed inset-0 bg-black/50 z-30 sm:hidden"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
></div>

{{-- Sidebar --}}
<aside
    class="fixed inset-y-0 left-0 z-40 flex flex-col bg-white dark:bg-gray-800 border-r border-gray-100 dark:border-gray-700 shadow-lg transition-all duration-300 ease-in-out"
    :class="[collapsed ? 'w-20' : 'w-64', mobileOpen ? 'translate-x-0' : '-translate-x-full sm:translate-x-0']"
>
    {{-- Header: logo + toggle --}}
    <div class="flex items-center h-16 px-4 border-b border-gray-100 dark:border-gray-700 shrink-0"
         :class="collapsed ? 'justify-center' : 'justify-between'">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0">
            <x-application-logo :width="'28px'" :height="'36px'" :color="'#A00001'" class="fill-red-800 dark:fill-white shrink-0" />
            <span x-show="!collapsed" x-transition class="text-lg font-extrabold text-red-800 dark:text-white truncate">LARA</span>
        </a>
        <button @click="toggle()" x-show="!collapsed"
            class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7M18 19l-7-7 7-7" />
            </svg>
        </button>
    </div>

    {{-- Expand button when collapsed --}}
    <div x-show="collapsed" class="hidden sm:flex justify-center py-2 border-b border-gray-100 dark:border-gray-700">
        <button @click="toggle()"
            class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M6 5l7 7-7 7" />
            </svg>
        </button>
    </div>

    {{-- Busca: com muitos módulos, digitar o nome é mais rápido do que caçar
         o item na lista. O atalho global está registrado no shell. --}}
    <div class="shrink-0 px-2 pt-3">
        <button type="button" @click="openPalette()"
            class="w-full flex items-center gap-2 rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 text-sm text-gray-400 hover:border-gray-300 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            :class="collapsed ? 'justify-center' : ''"
            title="Buscar módulo (Ctrl+K)">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
            </svg>
            <span x-show="!collapsed" class="flex-1 text-left">Buscar...</span>
            <kbd x-show="!collapsed" class="shrink-0 rounded border border-gray-200 dark:border-gray-600 px-1.5 py-0.5 text-[10px] font-medium">Ctrl K</kbd>
        </button>
    </div>

    {{-- Nav links.

         É flex-col porque a ordem dos grupos é escolhida por quem usa: cada
         item recebe um `order` do flexbox, o que reordena sem precisar mexer
         no DOM que o Blade montou. Por isso `gap` no lugar de `space-y`, que
         calcula margem pela ordem do DOM e sairia errado. --}}
    <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto px-2 py-3">
        {{-- Favoritos: montados no cliente a partir do índice, porque a lista
             vive no localStorage de cada pessoa. --}}
        <div x-show="favItems.length" x-cloak class="order-first mb-2 space-y-0.5 border-b border-gray-100 pb-2 dark:border-gray-700">
            <div x-show="!collapsed" class="flex items-center justify-between px-3 pb-1">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Favoritos</p>
                <button type="button" @click="organizerOpen = true" title="Organizar menu"
                    class="rounded p-0.5 text-gray-300 transition hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7M17 20l3-3-3-3" />
                    </svg>
                </button>
            </div>

            <template x-for="item in favItems" :key="item.key">
                <div class="group flex items-center gap-0.5">
                    <a :href="item.url" :title="item.label"
                        class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2 rounded-xl text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white transition"
                        :class="collapsed ? 'justify-center' : ''">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                        </svg>
                        <span x-show="!collapsed" class="truncate" x-text="item.label"></span>
                    </a>
                    <button type="button" x-show="!collapsed" @click="toggleFav(item.key)"
                        title="Remover dos favoritos"
                        class="shrink-0 p-1.5 rounded-lg text-amber-400 opacity-0 group-hover:opacity-100 focus:opacity-100 transition">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11.48 3.5a.56.56 0 011.04 0l2.12 4.7 5.11.6c.47.05.66.64.31.96l-3.8 3.45 1.03 5.05c.09.46-.4.82-.81.59L12 16.3l-4.48 2.55c-.41.23-.9-.13-.81-.59l1.03-5.05-3.8-3.45c-.35-.32-.16-.91.31-.96l5.11-.6 2.12-4.7z" />
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        @foreach($visibleNavLinks as $link)
            @php
                $children = $link['children'] ?? null;
                $isActive = $children
                    ? collect($children)->contains(fn($c) => request()->routeIs($c['route']))
                    : request()->routeIs($link['active'] ?? $link['route']);
                $activeClasses   = 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-400 font-semibold';
                $inactiveClasses = 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white';
            @endphp

            @if($children)
                <div
                    x-data="{
                        dropOpen: {{ $isActive ? 'true' : 'false' }},
                        flyOpen: false, flyTop: 0, flyLeft: 0, closeTimer: null,
                        openFly(e) {
                            const r = e.currentTarget.getBoundingClientRect();
                            this.flyTop = r.top;
                            this.flyLeft = r.right;
                            clearTimeout(this.closeTimer);
                            this.flyOpen = true;
                        },
                        scheduleClose() { this.closeTimer = setTimeout(() => this.flyOpen = false, 150); },
                        keepOpen() { clearTimeout(this.closeTimer); }
                    }"
                    class="relative"
                    :style="{ order: navRank('{{ $link['key'] }}') }"
                >
                    <button @click="collapsed ? (flyOpen ? flyOpen = false : openFly($event)) : (dropOpen = !dropOpen)"
                        @mouseenter="if (collapsed) openFly($event)"
                        @mouseleave="if (collapsed) scheduleClose()"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition {{ $isActive ? $activeClasses : $inactiveClasses }}"
                        :class="collapsed ? 'justify-center' : ''"
                        :title="collapsed ? '{{ addslashes($link['label']) }}' : undefined"
                    >
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}"></path>
                        </svg>
                        <span x-show="!collapsed" class="flex-1 text-left truncate">{{ __($link['label']) }}</span>
                        <svg x-show="!collapsed" class="w-4 h-4 shrink-0 transition-transform duration-200" :class="dropOpen ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    {{-- Expanded: children indented --}}
                    <div x-show="dropOpen && !collapsed" x-transition class="mt-0.5 ml-8 space-y-0.5">
                        @foreach($children as $child)
                            @php $childActive = request()->routeIs($child['route']); @endphp
                            <div class="group flex items-center gap-0.5">
                                <a href="{{ route($child['route']) }}"
                                    class="block min-w-0 flex-1 truncate px-3 py-2 rounded-lg text-sm transition {{ $childActive ? 'text-red-800 dark:text-red-400 font-semibold bg-red-50/60 dark:bg-red-900/10' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white' }}">
                                    {{ __($child['label']) }}
                                </a>
                                <x-nav-fav-star :route-key="$child['route']" />
                            </div>
                        @endforeach
                    </div>

                    {{-- Collapsed: flyout dialog teleported to body (click + hover) --}}
                    <template x-teleport="body">
                        <div x-show="collapsed && flyOpen" x-transition
                            @click.outside="flyOpen = false"
                            @mouseenter="keepOpen()" @mouseleave="scheduleClose()"
                            class="fixed z-[60] pl-2"
                            :style="{ top: flyTop + 'px', left: flyLeft + 'px' }"
                            style="display: none;">
                            <div class="w-52 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 py-2">
                                <div class="px-4 pb-1.5 mb-1 border-b border-gray-100 dark:border-gray-700 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    {{ __($link['label']) }}
                                </div>
                                @foreach($children as $child)
                                    @php $childActive = request()->routeIs($child['route']); @endphp
                                    <div class="group flex items-center">
                                        <a href="{{ route($child['route']) }}"
                                            class="block min-w-0 flex-1 truncate px-4 py-2 text-sm transition {{ $childActive ? 'text-red-800 dark:text-red-400 font-semibold bg-red-50/60 dark:bg-red-900/10' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white' }}">
                                            {{ __($child['label']) }}
                                        </a>
                                        <x-nav-fav-star :route-key="$child['route']" class="mr-1.5" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </template>
                </div>
            @else
                <div class="group flex items-center gap-0.5" :style="{ order: navRank('{{ $link['key'] }}') }">
                    <a href="{{ route($link['route']) }}"
                        class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition {{ $isActive ? $activeClasses : $inactiveClasses }}"
                        :class="collapsed ? 'justify-center' : ''"
                        title="{{ $link['label'] }}"
                    >
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}"></path>
                        </svg>
                        <span x-show="!collapsed" class="truncate">{{ __($link['label']) }}</span>
                    </a>
                    <div x-show="!collapsed">
                        <x-nav-fav-star :route-key="$link['route']" />
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    {{-- Bottom: user --}}
    <div class="shrink-0 border-t border-gray-100 dark:border-gray-700 p-2 space-y-1">
        {{-- User menu --}}
        <div
            x-data="{
                userOpen: false, flyBottom: 0, flyLeft: 0, closeTimer: null,
                openUserMenu(e) {
                    const r = e.currentTarget.getBoundingClientRect();
                    this.flyBottom = window.innerHeight - r.bottom;
                    this.flyLeft = r.right;
                    clearTimeout(this.closeTimer);
                    this.userOpen = true;
                },
                scheduleClose() { this.closeTimer = setTimeout(() => this.userOpen = false, 150); },
                keepOpen() { clearTimeout(this.closeTimer); }
            }"
            class="relative"
        >
            <button @click="collapsed ? (userOpen ? userOpen = false : openUserMenu($event)) : (userOpen = !userOpen)"
                @click.outside="userOpen = false"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                :class="collapsed ? 'justify-center' : ''"
            >
                <div class="w-7 h-7 rounded-full bg-red-800 text-white flex items-center justify-center text-xs font-bold shrink-0">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <span x-show="!collapsed" class="flex-1 text-left truncate text-sm font-medium">{{ Auth::user()->name }}</span>
                <svg x-show="!collapsed" class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>

            {{-- Expanded: dropdown above the button, within the sidebar --}}
            <div x-show="userOpen && !collapsed" x-transition
                class="absolute bottom-full left-0 mb-1 w-56 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden z-50"
                style="display: none;">
                <x-nav-account-links />
            </div>

            {{-- Collapsed: flyout teleported to body, opens to the right --}}
            <template x-teleport="body">
                <div x-show="userOpen && collapsed" x-transition
                    @click.outside="userOpen = false"
                    @mouseenter="keepOpen()" @mouseleave="scheduleClose()"
                    class="fixed z-[60] pl-2"
                    :style="{ bottom: flyBottom + 'px', left: flyLeft + 'px' }"
                    style="display: none;">
                    <div class="w-56 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <x-nav-account-links />
                    </div>
                </div>
            </template>
        </div>
    </div>
</aside>

{{-- Mobile hamburger (fixed, top-left) --}}
<button @click="mobileOpen = !mobileOpen"
    class="fixed top-4 left-4 z-50 sm:hidden p-2 rounded-lg bg-white dark:bg-gray-800 shadow-md text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 transition">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path :class="{'hidden': mobileOpen, 'inline-flex': !mobileOpen}" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        <path :class="{'hidden': !mobileOpen, 'inline-flex': mobileOpen}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
    </svg>
</button>
