{{-- "Vale para" do tipo, só no evento de vários dias: a diária de um dia ou,
     vazio, o passaporte. Recebe $event (com days), $id e $selected (dia ou null). --}}
@if ($event->days->isNotEmpty())
    <div>
        <x-input-label :for="$id" value="Vale para" />
        <select id="{{ $id }}" name="ticket_event_day_id" class="mt-1 h-10 w-full rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink">
            <option value="">Passaporte (todos os dias)</option>
            @foreach ($event->days as $day)
                <option value="{{ $day->id }}" @selected($selected === $day->id)>Diária · {{ $day->label() }}</option>
            @endforeach
        </select>
    </div>
@endif
