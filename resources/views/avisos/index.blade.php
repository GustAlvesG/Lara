<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Avisos e Lembretes
            </h2>
            @can('manage avisos')
                <x-primary-button-a href="{{ route('avisos.create') }}">
                    + Novo Aviso
                </x-primary-button-a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5"
             x-data="{ tab: '{{ request('tab', 'ativos') }}' }">

            @if(session('success'))
                <div class="p-4 bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-200 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Busca por título ou tag --}}
            <form method="GET" action="{{ route('avisos.index') }}" class="flex gap-2">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                        </svg>
                    </span>
                    <input type="text" name="q" value="{{ $search }}"
                           placeholder="Buscar por título ou tag…"
                           class="w-full pl-10 pr-4 py-2 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-red-500 focus:ring-red-500">
                </div>
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-800 hover:bg-red-700 rounded-lg transition">
                    Buscar
                </button>
                @if($search)
                    <a href="{{ route('avisos.index') }}"
                       class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition flex items-center">
                        Limpar
                    </a>
                @endif
            </form>

            @if($search)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Resultados para <span class="font-medium text-gray-700 dark:text-gray-300">"{{ $search }}"</span>
                </p>
            @endif

            {{-- Tabs --}}
            <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
                <button @click="tab = 'ativos'"
                    :class="tab === 'ativos'
                        ? 'border-red-700 text-red-800 dark:text-red-400 dark:border-red-500'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition">
                    Ativos
                    <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                        {{ $avisos->count() }}
                    </span>
                </button>
                <button @click="tab = 'todos'"
                    :class="tab === 'todos'
                        ? 'border-red-700 text-red-800 dark:text-red-400 dark:border-red-500'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition">
                    Todos
                    <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                        {{ $todos->count() }}
                    </span>
                </button>
            </div>

            {{-- Tab: Ativos --}}
            <div x-show="tab === 'ativos'" x-transition>
                @if($avisos->isEmpty())
                    <div class="p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg text-center text-gray-500 dark:text-gray-400">
                        Nenhum aviso ativo no momento.
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        @foreach($avisos as $aviso)
                            @include('avisos.partials.card', ['aviso' => $aviso])
                        @endforeach
                    </div>
                @endif

                {{-- Expirados colapsáveis dentro da aba Ativos --}}
                @if($expirados->isNotEmpty())
                    <div x-data="{ open: false }" class="pt-2">
                        <button @click="open = !open"
                            class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
                            <svg class="w-4 h-4" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="transition: transform 0.2s">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            Expirados ({{ $expirados->count() }})
                        </button>
                        <div x-show="open" x-transition class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                            @foreach($expirados as $aviso)
                                @include('avisos.partials.card', ['aviso' => $aviso, 'expired' => true])
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Tab: Todos --}}
            <div x-show="tab === 'todos'" x-transition>
                @if($todos->isEmpty())
                    <div class="p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg text-center text-gray-500 dark:text-gray-400">
                        Nenhum aviso encontrado.
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        @foreach($todos as $aviso)
                            @include('avisos.partials.card', ['aviso' => $aviso])
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
