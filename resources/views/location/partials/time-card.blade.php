@php
    $isBlocked = $slot['excluded_by_rule'] ?? false;
    $isBooked = isset($slot['colided_member']) && $slot['colided_member'] !== null;
    $isPast = isset($slot['past_date']) && $slot['past_date'] === true;
    // Horário que já começou e ainda está à venda: vale proporcional ao tempo
    // restante, então o card precisa mostrar o valor de agora, não o cheio.
    $inProgress = ($slot['in_progress'] ?? false) === true;
    $slotPrice = $slot['price'] ?? null;
    $slotPercent = isset($slot['price_factor']) ? round($slot['price_factor'] * 100) : null;
    $range = $slot['start_time'] . ' – ' . $slot['end_time'];
    $base = 'relative flex min-h-[84px] flex-col justify-between gap-1 rounded-2xl p-3 text-left transition';
@endphp
{{--
    Um horário da agenda. Livre é botão (marca/desmarca para a reserva);
    ocupado leva ao agendamento; bloqueado e passado só informam. O estado vai
    em texto além da cor, para quem não distingue bem verde de amarelo.
--}}
@if($isBooked)
    @php $confirmed = $slot['colided_status_id'] == '1'; @endphp
    <a href="{{ route('schedule.show', ['id' => $slot['colides']['id']]) }}"
       class="{{ $base }} group border-l-4 {{ $confirmed ? 'border-ok bg-ok-soft text-ok' : 'border-warn bg-warn-soft text-warn' }} hover:shadow-card">
        <div class="flex items-start justify-between gap-2">
            <span class="font-mono text-xs font-semibold leading-none">{{ $range }}</span>
            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-surface text-[9px] font-bold" aria-hidden="true">
                {{ mb_strtoupper(mb_substr($slot['colided_member']['name'], 0, 2)) }}
            </span>
        </div>

        <div class="min-w-0">
            <p class="truncate text-xs font-bold text-ink">{{ $slot['colided_member']['name'] }}</p>

            {{-- Pendente mostra há quanto tempo espera o pagamento. --}}
            @if($slot['colided_status_id'] == '3')
                @php
                    $seconds = \Carbon\Carbon::parse($slot['colides']['created_at'])->diffInSeconds(now());
                    $formatted = gmdate('i:s', $seconds);
                @endphp
                <p class="mt-0.5 flex items-center gap-1 text-[11px] font-bold">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-warn"></span>
                    Pendente · <span class="font-mono">{{ $formatted }}</span>
                </p>
            @else
                <p class="mt-0.5 text-[10px] font-bold uppercase tracking-[0.08em]">Confirmado</p>
            @endif
        </div>

        <span class="absolute right-2 bottom-2 text-[10px] font-bold text-ink-2 opacity-0 transition group-hover:opacity-100">Detalhes →</span>
    </a>
@elseif($isBlocked)
    <div class="{{ $base }} bg-subtle text-ink-2">
        <div class="flex items-start justify-between gap-2">
            <span class="font-mono text-xs font-semibold leading-none">{{ $range }}</span>
            <x-icon name="lock" class="h-4 w-4 text-danger" />
        </div>
        <div class="min-w-0">
            <p class="truncate text-xs font-bold text-ink">{{ $slot['excluded_by_rule']['name'] ?? 'Indisponível' }}</p>
            <p class="mt-0.5 text-[10px] font-bold uppercase tracking-[0.08em] text-danger">Bloqueado</p>
        </div>
    </div>
@elseif($isPast)
    <div class="{{ $base }} border border-line bg-canvas text-ink-3">
        <div class="flex items-start justify-between gap-2">
            <span class="font-mono text-xs font-semibold leading-none">{{ $range }}</span>
            <x-icon name="clock" class="h-4 w-4" />
        </div>
        <p class="text-[10px] font-bold uppercase tracking-[0.08em]">Já passou</p>
    </div>
@else
    <input value="{{ $slot['start_time']}} - {{ $slot['end_time'] }}" class="hidden" type="checkbox" name="selected_slots[]">
    <button type="button"
        onclick="toggleSlot(this, '{{ $place['id'] }}', '{{ $slot['start_time'] }}')"
        data-price="{{ $slotPrice ?? ($place['price'] ?? 0) }}"
        aria-pressed="false"
        class="slot-button {{ $base }} border-[1.5px] border-dashed {{ $inProgress ? 'border-warn' : 'border-line-strong' }} bg-surface text-ink hover:border-ok hover:bg-ok-soft focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
        <div class="flex w-full items-start justify-between gap-2">
            <span class="font-mono text-xs font-semibold leading-none">{{ $range }}</span>
            <span class="icon-container grid h-6 w-6 shrink-0 place-items-center rounded-full bg-subtle text-ink-3 transition">
                <x-icon name="check" class="h-3 w-3" />
            </span>
        </div>
        @if($inProgress)
            {{-- Fora da .status-text de propósito: o JS reescreve aquele texto ao
                 selecionar/limpar o slot e apagaria o valor proporcional. --}}
            <p class="text-[10px] font-bold leading-tight text-warn">
                Em andamento · resta {{ $slot['remaining_minutes'] ?? 0 }} min
            </p>
            <p class="font-mono text-[11px] font-semibold leading-none text-ok">
                R$ {{ number_format((float) $slotPrice, 2, ',', '.') }}
                <span class="text-ink-3">({{ $slotPercent }}%)</span>
            </p>
        @endif
        <p class="status-text text-[10px] font-bold uppercase tracking-[0.08em] text-ink-3">Livre</p>
    </button>
@endif
