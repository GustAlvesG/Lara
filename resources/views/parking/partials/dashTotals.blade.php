@php
    $identified = max($todayParkingCount - $todayParkingNoPlate, 0);
    $identifiedRate = $todayParkingCount > 0 ? ($identified / $todayParkingCount) * 100 : 0;
@endphp

{{-- Hoje na cancela: dentro da faixa da Busca (parking.partials.form). --}}
<aside class="flex min-w-0 flex-col gap-5 rounded-3xl bg-white/[.06] p-5 ring-1 ring-white/10 sm:p-6" aria-label="Hoje na cancela">
    <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/60">Hoje na cancela</p>

    <div>
        <p class="font-mono text-[44px] font-semibold leading-none sm:text-[56px]">{{ $todayParkingCount }}</p>
        <p class="mt-1.5 text-sm text-white/70">veículos registrados pelas câmeras</p>
    </div>

    {{-- Taxa de leitura: quanto dos registros veio com a placa lida. --}}
    <div>
        <div class="flex items-baseline justify-between gap-3 text-sm">
            <span class="text-white/70">Placas identificadas</span>
            <span class="font-mono font-semibold">{{ $identified }} <span class="text-[rgb(var(--siv-glow))]">({{ number_format($identifiedRate, 0) }}%)</span></span>
        </div>
        <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/15" role="meter" aria-valuemin="0" aria-valuemax="100"
             aria-valuenow="{{ round($identifiedRate) }}" aria-label="Placas identificadas">
            <div class="h-full rounded-full bg-[rgb(var(--siv-glow))]" style="width: {{ min($identifiedRate, 100) }}%"></div>
        </div>
    </div>

    <div class="flex items-center justify-between gap-3 border-t border-white/10 pt-4 text-sm">
        <span class="inline-flex items-center gap-2 text-white/70">
            <x-icon name="clock" class="h-4 w-4" /> Sem placa identificada
        </span>
        <span class="font-mono text-lg font-semibold {{ $todayParkingNoPlate > 0 ? 'text-amber-300' : '' }}">{{ $todayParkingNoPlate }}</span>
    </div>
</aside>
