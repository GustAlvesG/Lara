{{-- Moldura do mapa de lugares para o TicketSeatMap.viewer (public/js/tickets/seat-map.js):
     zoom, legenda, o SVG e a linha de situação. Recebe $id (do contêiner).
     Quem inclui chama TicketSeatMap.viewer(document.getElementById($id), {...}). --}}
@php
    $dot = 'inline-block h-3 w-3 rounded-full border-2';
@endphp
<div id="{{ $id }}" class="flex flex-col gap-3">
    <div class="flex flex-wrap items-center gap-2 text-xs text-ink-2">
        <x-secondary-button type="button" size="sm" data-zoom="out" aria-label="Diminuir">−</x-secondary-button>
        <x-secondary-button type="button" size="sm" data-zoom="reset">100%</x-secondary-button>
        <x-secondary-button type="button" size="sm" data-zoom="in" aria-label="Aumentar">+</x-secondary-button>
        <span class="ml-2 inline-flex items-center gap-1"><span class="{{ $dot }} border-ok bg-ok-soft"></span> Livre</span>
        <span class="inline-flex items-center gap-1"><span class="{{ $dot }} border-warn bg-warn-soft"></span> Reservado</span>
        <span class="inline-flex items-center gap-1"><span class="{{ $dot }} border-danger bg-danger-soft"></span> Vendido</span>
        <span class="inline-flex items-center gap-1"><span class="{{ $dot }} border-line-strong bg-subtle"></span> Indisponível</span>
        <span class="inline-flex items-center gap-1"><span class="{{ $dot }} border-grena bg-grena"></span> Escolhido</span>
    </div>
    <div class="overflow-auto rounded-xl border border-line bg-subtle p-2" style="max-height: 70vh;">
        <svg data-map xmlns="http://www.w3.org/2000/svg" class="block select-none" style="min-width: 320px;"></svg>
    </div>
    <p class="text-xs text-ink-3" data-map-status>Carregando o mapa…</p>
</div>
