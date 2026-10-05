<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Informações">
            @if ($search !== '')
                {{ $infos->total() }} {{ $infos->total() === 1 ? 'resultado' : 'resultados' }} para “{{ $search }}”
            @else
                {{ $infos->total() }} {{ $infos->total() === 1 ? 'informação publicada' : 'informações publicadas' }}
            @endif

            @can('infoclube.editar')
                <x-slot:actions>
                    <x-primary-button-a href="{{ route('information.create') }}">
                        <x-icon name="plus" /> Nova informação
                    </x-primary-button-a>
                </x-slot:actions>
            @endcan
        </x-page-title>

        {{-- Busca no servidor: com paginação, filtrar só no navegador acharia
             apenas o que está na página aberta. --}}
        <x-search-bar placeholder="Buscar por nome, tag, responsável, local…" />

        @if ($infos->isEmpty())
            <x-empty-state :icon="$search !== '' ? 'search' : 'info'">
                @if ($search !== '')
                    Nenhuma informação corresponde a “{{ $search }}”. Tente outra palavra ou
                    <a href="{{ route('information.index') }}" class="font-bold text-grena-ink hover:underline">limpe a busca</a>.
                @else
                    Nenhuma informação cadastrada ainda.
                    @can('infoclube.editar')
                        <a href="{{ route('information.create') }}" class="font-bold text-grena-ink hover:underline">Criar a primeira</a>.
                    @endcan
                @endif
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($infos as $item)
                    @include('information.partials.element', ['item' => $item])
                @endforeach
            </div>

            @if ($infos->hasPages())
                <div>{{ $infos->links() }}</div>
            @endif
        @endif
    </x-page>
</x-app-layout>
