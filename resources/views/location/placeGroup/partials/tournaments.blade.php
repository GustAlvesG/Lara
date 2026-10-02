<div class="flex flex-wrap items-center justify-between gap-3">
    <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Torneios</h2>
    <x-primary-button-a size="sm" href="{{ route('tournaments.create', $item->id) }}"><x-icon name="plus" /> Novo torneio</x-primary-button-a>
</div>

@if (blank($tournaments) || count($tournaments) === 0)
    <x-empty-state icon="trophy">Nenhum torneio nesta modalidade.</x-empty-state>
@else
    @if (count($tournaments) > 4)
        <x-search-bar mode="client" target="#torneios" id="busca-torneios" placeholder="Buscar torneio" />
    @endif
    <div id="torneios" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($tournaments as $tournament)
            @include('location.placeGroup.partials.tournament-card', ['tournament' => $tournament])
        @endforeach
    </div>
@endif
