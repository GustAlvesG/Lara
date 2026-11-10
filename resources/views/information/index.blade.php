{{--
    InfoClube › Informações (refeita em 10/11/2026): busca no servidor, atalhos
    pelas tags mais usadas e os cartões com foto.
--}}
@php
    $chip = 'inline-flex h-10 shrink-0 items-center rounded-full px-4 text-sm font-bold no-underline transition focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint';
    $chipOn = 'bg-ink text-canvas';
    $chipOff = 'bg-surface text-ink-2 shadow-card hover:-translate-y-px hover:text-ink';
    $activeTag = mb_strtolower($search);
    $popularTags = $popularTags ?? collect();
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Informações">
            @if ($search !== '')
                {{ $infos->total() }} {{ $infos->total() === 1 ? 'resultado' : 'resultados' }} para “{{ $search }}”
            @else
                {{ $infos->total() }} {{ $infos->total() === 1 ? 'informação publicada' : 'informações publicadas' }}: atividades, serviços e ofertas do clube.
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

        @if ($popularTags->isNotEmpty())
            <nav class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="Filtrar por tag">
                <a href="{{ route('information.index') }}" class="{{ $chip }} {{ $search === '' ? $chipOn : $chipOff }}"
                   @if ($search === '') aria-current="page" @endif>Todas</a>
                @foreach ($popularTags as $tagName)
                    @php $on = $activeTag === mb_strtolower($tagName); @endphp
                    <a href="{{ route('information.index', ['q' => $tagName]) }}" class="{{ $chip }} {{ $on ? $chipOn : $chipOff }}"
                       @if ($on) aria-current="page" @endif>#{{ $tagName }}</a>
                @endforeach
            </nav>
        @endif

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
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
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
