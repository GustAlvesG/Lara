<div class="flex flex-wrap items-center justify-between gap-3">
    <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Locais</h2>
    <x-primary-button-a size="sm" href="{{ route('place-group.createPlace', $item->id) }}"><x-icon name="plus" /> Novo local</x-primary-button-a>
</div>

@if (blank($places) || count($places) === 0)
    <x-empty-state icon="calendar">Nenhum local nesta modalidade.</x-empty-state>
@else
    @if (count($places) > 4)
        <x-search-bar mode="client" target="#locais" id="busca-locais" placeholder="Buscar local" />
    @endif
    <div id="locais" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($places as $place)
            @include('location.placeGroup.partials.place-card', ['place' => $place])
        @endforeach
    </div>
@endif
