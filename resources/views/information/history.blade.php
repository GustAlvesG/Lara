<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Histórico: {{ $info->first()->name }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $info->count() }} {{ $info->count() === 1 ? 'versão registrada' : 'versões registradas' }},
                    da mais recente para a mais antiga.
                </p>
            </div>
            <x-secondary-button-a href="{{ route('information.show', $info->first()->id) }}">
                Voltar para a informação
            </x-secondary-button-a>
        </div>
    </x-slot>

    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/information/editor.css') }}">
        <link rel="stylesheet" href="{{ asset('css/information/form.css') }}">
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            {{-- Cada .element é uma página da paginação em paginationHistory.js (1 por vez). --}}
            @foreach ($info as $index => $version)
                <div class="element">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-6 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Versão {{ $info->count() - $index }} de {{ $info->count() }}
                                @if ($index === 0)
                                    <span class="ml-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                        atual
                                    </span>
                                @endif
                            </p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Criada em {{ $version->created_at?->format('d/m/Y H:i') ?? '—' }}
                                @if ($version->user)
                                    por {{ $version->user->name }}
                                @endif
                            </p>
                        </div>

                        @if ($index > 0)
                            @can('edit information')
                                <form action="{{ route('information.update', $version->id) }}" method="POST"
                                      onsubmit="return confirm('Restaurar esta versão? Ela será copiada como a nova versão atual.')">
                                    @csrf
                                    @method('PUT')
                                    <x-primary-button type="submit">Tornar versão atual</x-primary-button>
                                </form>
                            @endcan
                        @endif
                    </div>

                    @include('information.partials.details', ['info' => $version])
                </div>
            @endforeach

            <div class="flex justify-center">
                @include('partials.navPagination')
            </div>
        </div>
    </div>

    <x-slot name="js">
        <script src="{{ asset('js/information/paginationHistory.js') }}"></script>
    </x-slot>
</x-app-layout>
