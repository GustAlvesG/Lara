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

    {{-- Nav links --}}
    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
        @foreach($navLinks as $link)
            @php
                $permission = $link['permission'] ?? null;
                if ($permission && !auth()->user()->can($permission)) continue;
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
                            <a href="{{ route($child['route']) }}"
                                class="block px-3 py-2 rounded-lg text-sm transition {{ $childActive ? 'text-red-800 dark:text-red-400 font-semibold bg-red-50/60 dark:bg-red-900/10' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white' }}">
                                {{ __($child['label']) }}
                            </a>
                        @endforeach
                    </div>

                    {{-- Collapsed: flyout dialog teleported to body (click + hover) --}}
                    <template x-teleport="body">
                        <div x-show="collapsed && flyOpen" x-transition
                            @click.outside="flyOpen = false"
                            @mouseenter="keepOpen()" @mouseleave="scheduleClose()"
                            class="fixed z-[60] pl-2"
                            :style="`top: ${flyTop}px; left: ${flyLeft}px;`"
                            style="display: none;">
                            <div class="w-52 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 py-2">
                                <div class="px-4 pb-1.5 mb-1 border-b border-gray-100 dark:border-gray-700 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    {{ __($link['label']) }}
                                </div>
                                @foreach($children as $child)
                                    @php $childActive = request()->routeIs($child['route']); @endphp
                                    <a href="{{ route($child['route']) }}"
                                        class="block px-4 py-2 text-sm transition {{ $childActive ? 'text-red-800 dark:text-red-400 font-semibold bg-red-50/60 dark:bg-red-900/10' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white' }}">
                                        {{ __($child['label']) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </template>
                </div>
            @else
                <a href="{{ route($link['route']) }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition {{ $isActive ? $activeClasses : $inactiveClasses }}"
                    :class="collapsed ? 'justify-center' : ''"
                    title="{{ $link['label'] }}"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}"></path>
                    </svg>
                    <span x-show="!collapsed" class="truncate">{{ __($link['label']) }}</span>
                </a>
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
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Perfil</a>
                <a href="{{ route('docs.index') }}" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Documentação</a>
                @role('admin')
                <a href="{{ route('users.index') }}" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Usuários</a>
                @endrole
                <x-nav-mode-toggle />
                <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 dark:border-gray-700">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">Sair</button>
                </form>
            </div>

            {{-- Collapsed: flyout teleported to body, opens to the right --}}
            <template x-teleport="body">
                <div x-show="userOpen && collapsed" x-transition
                    @click.outside="userOpen = false"
                    @mouseenter="keepOpen()" @mouseleave="scheduleClose()"
                    class="fixed z-[60] pl-2"
                    :style="`bottom: ${flyBottom}px; left: ${flyLeft}px;`"
                    style="display: none;">
                    <div class="w-56 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Perfil</a>
                        <a href="{{ route('docs.index') }}" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Documentação</a>
                        @role('admin')
                        <a href="{{ route('users.index') }}" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Usuários</a>
                        @endrole
                        <x-nav-mode-toggle />
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 dark:border-gray-700">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">Sair</button>
                        </form>
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
