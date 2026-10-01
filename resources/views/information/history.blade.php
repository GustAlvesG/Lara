<x-app-layout :bootstrap-grid="false">
    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/information/editor.css') }}">
    </x-slot>

    <x-page>
        <x-page-title :title="'Histórico: ' . $info->first()->name" :back="route('information.show', $info->first()->id)">
            {{ $info->count() }} {{ $info->count() === 1 ? 'versão registrada' : 'versões registradas' }}, da mais recente para a mais antiga.
        </x-page-title>

        {{-- Cada .element é uma página da paginação em paginationHistory.js (1 por vez). --}}
        @foreach ($info as $index => $version)
            <div class="element flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-card bg-surface px-5 py-4 shadow-card">
                    <div>
                        <p class="flex items-center gap-2 text-sm font-bold text-ink">
                            Versão {{ $info->count() - $index }} de {{ $info->count() }}
                            @if ($index === 0)
                                <x-pill kind="ok">Atual</x-pill>
                            @endif
                        </p>
                        <p class="mt-1 text-sm text-ink-2">
                            Criada em {{ $version->created_at?->format('d/m/Y H:i') ?? '—' }}
                            @if ($version->user)
                                por {{ $version->user->name }}
                            @endif
                        </p>
                    </div>

                    @if ($index > 0)
                        @can('infoclube.editar')
                            <form action="{{ route('information.update', $version->id) }}" method="POST"
                                  onsubmit="return confirm('Restaurar esta versão? Ela será copiada como a nova versão atual.')">
                                @csrf
                                @method('PUT')
                                <x-primary-button type="submit"><x-icon name="history" /> Tornar versão atual</x-primary-button>
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
    </x-page>

    <x-slot name="js">
        <script src="{{ asset('js/information/paginationHistory.js') }}"></script>
    </x-slot>
</x-app-layout>
