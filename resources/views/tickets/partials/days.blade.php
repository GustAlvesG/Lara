{{-- Dias do evento de vários dias. Com dias, cada tipo é a diária de um dia
     ou, sem dia, o passaporte. Recebe $event (com days e types) e $manage. --}}
@php
    $dt = fn ($value) => $value ? $value->format('Y-m-d\TH:i') : '';
@endphp
<section class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-card">
    <div>
        <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Dias do evento</h2>
        <p class="text-sm text-ink-3">Só para evento de mais de um dia. Com dias cadastrados, cada tipo de ingresso é a <strong>diária</strong> de um dia ou, sem dia, o <strong>passaporte</strong> (vale todos os dias, uma entrada por dia). O passaporte só existe se você criar um tipo sem dia. A entrada de cada dia abre {{ (int) config('tickets.checkin_hours_before', 3) }}h antes do início dele.</p>
    </div>

    @if ($event->days->isNotEmpty())
        <ul class="divide-y divide-line rounded-xl border border-line">
            @foreach ($event->days as $day)
                <li class="flex flex-col gap-2 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm">
                            <strong class="text-ink">{{ $day->label() }}</strong>
                            <span class="font-mono text-ink-2">{{ $day->hoursLabel() }}</span>
                            <span class="text-xs text-ink-3">· {{ $event->types->where('ticket_event_day_id', $day->id)->count() }} diária(s)</span>
                        </p>
                        @if ($manage)
                            <form method="POST" action="{{ route('tickets.days.destroy', [$event, $day]) }}" onsubmit="return confirm('Apagar o dia {{ addslashes($day->label()) }}?')">
                                @csrf @method('DELETE')
                                <x-danger-button size="sm">Apagar dia</x-danger-button>
                            </form>
                        @endif
                    </div>
                    @if ($manage)
                        <details>
                            <summary class="cursor-pointer text-sm font-semibold text-ink-2">Editar dia</summary>
                            <form method="POST" action="{{ route('tickets.days.update', [$event, $day]) }}" class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                                @csrf @method('PUT')
                                <div><x-input-label for="day-{{ $day->id }}-name" value="Nome (opcional)" /><x-text-input id="day-{{ $day->id }}-name" name="name" class="mt-1" :value="$day->name" maxlength="100" placeholder="Ex.: Sábado" /></div>
                                <div><x-input-label for="day-{{ $day->id }}-starts" value="Início" /><x-text-input type="datetime-local" id="day-{{ $day->id }}-starts" name="starts_at" class="mt-1" :value="$dt($day->starts_at)" required /></div>
                                <div><x-input-label for="day-{{ $day->id }}-ends" value="Fim" /><x-text-input type="datetime-local" id="day-{{ $day->id }}-ends" name="ends_at" class="mt-1" :value="$dt($day->ends_at)" required /></div>
                                <div class="sm:text-right"><x-primary-button>Salvar dia</x-primary-button></div>
                            </form>
                        </details>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($manage)
        <details @if ($event->days->isNotEmpty()) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-grena">Novo dia</summary>
            <form method="POST" action="{{ route('tickets.days.store', $event) }}" class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                @csrf
                <div><x-input-label for="new-day-name" value="Nome (opcional)" /><x-text-input id="new-day-name" name="name" class="mt-1" maxlength="100" placeholder="Ex.: Sábado" /></div>
                <div><x-input-label for="new-day-starts" value="Início" /><x-text-input type="datetime-local" id="new-day-starts" name="starts_at" class="mt-1" required /></div>
                <div><x-input-label for="new-day-ends" value="Fim" /><x-text-input type="datetime-local" id="new-day-ends" name="ends_at" class="mt-1" required /></div>
                <div class="sm:text-right"><x-primary-button>Criar dia</x-primary-button></div>
            </form>
        </details>
    @endif
</section>
