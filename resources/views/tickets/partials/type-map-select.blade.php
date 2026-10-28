{{-- Lugar marcado do tipo, só se o evento tem mapa: o mapa cujos lugares o
     tipo vende (vazio = ingresso de entrada) e, se for só parte deles, a
     categoria. Recebe $event (com maps.seats), $id e $type (ou null). --}}
@if ($event->maps->isNotEmpty())
    <div>
        <x-input-label :for="$id . '-map'" value="Lugar marcado (mesa, bistrô…)" />
        <select id="{{ $id }}-map" name="ticket_map_id" class="mt-1 h-10 w-full rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink">
            <option value="">Não: ingresso de entrada</option>
            @foreach ($event->maps as $map)
                <option value="{{ $map->id }}" @selected($type?->ticket_map_id === $map->id)>Lugar do mapa {{ $map->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-input-label :for="$id . '-category'" value="Categoria dos lugares (opcional)" />
        <x-text-input :id="$id . '-category'" name="seat_category" class="mt-1" :value="$type?->seat_category" maxlength="60" list="map-categories" placeholder="Vazio = todos" />
    </div>
@endif
