<div class="flex flex-wrap items-center justify-between gap-3">
    <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Regras de locação</h2>
    <x-primary-button-a size="sm" href="{{ route('place-group.createScheduleRule', $item->id) }}"><x-icon name="plus" /> Nova regra</x-primary-button-a>
</div>

@if (blank($rules) || count($rules) === 0)
    <x-empty-state icon="clock">Nenhuma regra de locação nesta modalidade.</x-empty-state>
@else
    @if (count($rules) > 2)
        <x-search-bar mode="client" target="#regras" id="busca-regras" placeholder="Buscar regra" />
    @endif
    <div id="regras" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        @foreach ($rules as $rule)
            <div data-search="">
                @include('location.placeGroup.partials.rule-card', ['rule' => $rule])
            </div>
        @endforeach
    </div>
@endif
