<nav x-data="{ open: false }" class="sticky top-0 z-40 bg-white dark:bg-gray-800 shadow-lg border-b border-gray-100 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                {{-- Logo --}}
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center">
                        <x-application-logo :width="'30px'" :height="'38px'" :color="'#A00001'" class="fill-red-800 dark:fill-white" />
                        <span class="ml-2 text-xl font-extrabold text-red-800 dark:text-white">LARA</span>
                    </a>
                </div>

                {{-- Desktop links --}}
                <div class="hidden space-x-1 sm:-my-px sm:ms-10 sm:flex">
                    @foreach($navLinks as $link)
                        @php
                            $permission = $link['permission'] ?? null;
                            if ($permission && !auth()->user()->can($permission)) continue;
                            $children = $link['children'] ?? null;
                            $isActive = $children
                                ? collect($children)->contains(fn($c) => request()->routeIs($c['route']))
                                : request()->routeIs($link['active'] ?? $link['route']);
                            $baseClasses     = 'inline-flex items-center px-3 py-2 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out rounded-t-md';
                            $activeClasses   = 'border-red-800 dark:border-red-400 text-red-800 dark:text-red-400 bg-red-50/50 dark:bg-red-900/20';
                            $inactiveClasses = 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700';
                        @endphp

                        @if($children)
                            <div x-data="{ dropOpen: false }" class="relative flex items-center" @mouseleave="dropOpen = false">
                                <button @click="dropOpen = !dropOpen" @mouseenter="dropOpen = true"
                                    class="{{ $baseClasses }} {{ $isActive ? $activeClasses : $inactiveClasses }}">
                                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}"></path>
                                    </svg>
                                    {{ __($link['label']) }}
                                    <svg class="ms-1 w-3 h-3 transition-transform duration-200" :class="dropOpen ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <div x-show="dropOpen" x-transition @click.outside="dropOpen = false"
                                    class="absolute top-full left-0 mt-1 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10 py-1 z-50"
                                    style="display: none;">
                                    @foreach($children as $child)
                                        @php $childActive = request()->routeIs($child['route']); @endphp
                                        <a href="{{ route($child['route']) }}"
                                            class="block px-4 py-2 text-sm {{ $childActive ? 'text-red-800 dark:text-red-400 bg-red-50 dark:bg-red-900/20 font-semibold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                            {{ __($child['label']) }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <a href="{{ route($link['route']) }}"
                                class="{{ $baseClasses }} {{ $isActive ? $activeClasses : $inactiveClasses }}">
                                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}"></path>
                                </svg>
                                {{ __($link['label']) }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Desktop user dropdown --}}
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <div x-data="{ userOpen: false }" class="relative">
                    <button @click="userOpen = !userOpen" @click.outside="userOpen = false"
                        class="inline-flex items-center gap-2 px-3 py-2 border border-gray-200 dark:border-gray-600 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition shadow-sm">
                        <span class="w-7 h-7 rounded-full bg-red-800 text-white flex items-center justify-center text-xs font-bold">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </span>
                        <span>{{ Auth::user()->name }}</span>
                        <svg class="w-4 h-4" :class="userOpen ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <div x-show="userOpen" x-transition
                        class="absolute right-0 mt-2 w-56 rounded-xl shadow-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 overflow-hidden z-50"
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
                </div>
            </div>

            {{-- Hamburger --}}
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = !open"
                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': !open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Responsive menu --}}
    <div :class="{'block': open, 'hidden': !open}" class="hidden sm:hidden border-t border-gray-100 dark:border-gray-700">
        <div class="pt-2 pb-3 space-y-1">
            @foreach($navLinks as $link)
                @php
                    $permission = $link['permission'] ?? null;
                    if ($permission && !auth()->user()->can($permission)) continue;
                    $children = $link['children'] ?? null;
                @endphp
                @if($children)
                    <div class="px-4 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __($link['label']) }}</div>
                    @foreach($children as $child)
                        <x-responsive-nav-link :href="route($child['route'])" :active="request()->routeIs($child['route'])">
                            {{ __($child['label']) }}
                        </x-responsive-nav-link>
                    @endforeach
                @else
                    <x-responsive-nav-link :href="route($link['route'])" :active="request()->routeIs($link['active'] ?? $link['route'])">
                        {{ __($link['label']) }}
                    </x-responsive-nav-link>
                @endif
            @endforeach
        </div>

        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">{{ __('Perfil') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('docs.index')">{{ __('Documentação') }}</x-responsive-nav-link>
                @role('admin')
                <x-responsive-nav-link :href="route('users.index')">{{ __('Usuários') }}</x-responsive-nav-link>
                @endrole
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
