<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    InfoClube
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($search !== '')
                        {{ $infos->total() }} {{ $infos->total() === 1 ? 'resultado' : 'resultados' }} para
                        <span class="font-medium">"{{ $search }}"</span>
                    @else
                        {{ $infos->total() }} {{ $infos->total() === 1 ? 'informação publicada' : 'informações publicadas' }}
                    @endif
                </p>
            </div>

            <div class="flex w-full flex-wrap items-center gap-3 sm:w-auto">
                {{--
                    Busca no servidor (e não no navegador): com paginação, um
                    filtro client-side só encontraria o que está na página atual.
                --}}
                <form method="GET" action="{{ route('information.index') }}" class="flex flex-1 items-center gap-2 sm:flex-none">
                    <input type="text" name="q" value="{{ $search }}"
                           placeholder="Nome, tag, responsável…"
                           class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:w-64">
                    <x-primary-button type="submit" class="shrink-0">Buscar</x-primary-button>
                    @if ($search !== '')
                        <a href="{{ route('information.index') }}"
                           class="shrink-0 text-sm text-gray-500 hover:underline dark:text-gray-400">Limpar</a>
                    @endif
                </form>

                @can('create information')
                    <x-primary-button-a href="{{ route('information.create') }}" class="shrink-0">
                        Nova Informação
                    </x-primary-button-a>
                @endcan
            </div>
        </div>
    </x-slot>

    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/information/form.css') }}">
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if ($infos->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-600 dark:bg-gray-800">
                    @if ($search !== '')
                        <p class="text-gray-600 dark:text-gray-300">
                            Nenhuma informação corresponde a <span class="font-medium">"{{ $search }}"</span>.
                        </p>
                        <div class="mt-4">
                            <x-secondary-button-a href="{{ route('information.index') }}">
                                Limpar busca
                            </x-secondary-button-a>
                        </div>
                    @else
                        <p class="text-gray-600 dark:text-gray-300">Nenhuma informação cadastrada ainda.</p>
                        @can('create information')
                            <div class="mt-4">
                                <x-primary-button-a href="{{ route('information.create') }}">
                                    Criar a primeira
                                </x-primary-button-a>
                            </div>
                        @endcan
                    @endif
                </div>
            @else
                {{--
                    Os cards são filhos diretos do grid, e o grid estica os
                    itens (items-stretch): é isso que mantém todos com a mesma
                    altura em cada linha.
                --}}
                <div id="elements-container"
                     class="grid grid-cols-1 items-stretch gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($infos as $item)
                        @include('information.partials.element', ['item' => $item])
                    @endforeach
                </div>

                @if ($infos->hasPages())
                    <div class="mt-8">
                        {{ $infos->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
