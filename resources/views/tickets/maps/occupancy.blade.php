{{-- Ocupação de um mapa de lugares num dia: cada lugar com a situação e, ao
     passar o mouse, quem é o responsável; clicar no lugar ocupado abre o
     pedido. Atualiza sozinho (permissão `ingressos.vendas`). --}}
@php
    $stateUrl = route('tickets.maps.state', [$event, $map, 'dia' => $day?->id]);
    // O id do pedido entra no lugar do marcador, no navegador.
    $orderUrl = str_replace('999999999', '__ID__', route('tickets.orders.show', 999999999));
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Ocupação — ' . $map->name" :back="route('tickets.show', $event)">
            {{ $event->title }}@if ($day) · {{ $day->label() }}@endif. Passe o mouse num lugar para ver o tipo e o responsável; clique num lugar ocupado para abrir o pedido.
        </x-page-title>

        @if ($event->days->isNotEmpty())
            <nav class="flex flex-wrap gap-2" aria-label="Dia">
                @foreach ($event->days as $option)
                    @if ($day && $option->id === $day->id)
                        <x-primary-button-a href="{{ route('tickets.maps.occupancy', [$event, $map, 'dia' => $option->id]) }}">{{ $option->label() }}</x-primary-button-a>
                    @else
                        <x-secondary-button-a href="{{ route('tickets.maps.occupancy', [$event, $map, 'dia' => $option->id]) }}">{{ $option->label() }}</x-secondary-button-a>
                    @endif
                @endforeach
            </nav>
        @endif

        <section class="rounded-card bg-surface p-5 shadow-card">
            @include('tickets.maps.partials.viewer', ['id' => 'occupancy-map'])
        </section>
    </x-page>

    <script src="{{ asset('js/tickets/seat-map.js') }}?v={{ filemtime(public_path('js/tickets/seat-map.js')) }}"></script>
    <script>
        TicketSeatMap.viewer(document.getElementById('occupancy-map'), {
            mode: 'view',
            url: {{ Js::from($stateUrl) }},
            orderUrl: {{ Js::from($orderUrl) }},
        });
    </script>
</x-app-layout>
