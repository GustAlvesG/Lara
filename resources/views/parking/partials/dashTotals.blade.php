@php
    $identified = max($todayParkingCount - $todayParkingNoPlate, 0);
    $identifiedRate = $todayParkingCount > 0 ? ($identified / $todayParkingCount) * 100 : 0;

    $totals = [
        ['car', 'Veículos registrados hoje', $todayParkingCount, null, 'portaria'],
        ['check', 'Placas identificadas', $identified, number_format($identifiedRate, 0) . '%', 'reservas'],
        ['clock', 'Sem placa identificada', $todayParkingNoPlate, null, $todayParkingNoPlate > 0 ? 'freela' : 'cartao'],
    ];
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    @foreach ($totals as [$glyph, $label, $value, $extra, $area])
        <div class="flex items-center gap-4 rounded-card bg-surface p-5 shadow-card">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl" style="{{ \App\View\AreaColor::style($area) }}">
                <x-icon :name="$glyph" class="h-6 w-6" />
            </span>
            <div class="min-w-0">
                <p class="font-mono text-3xl font-semibold leading-none text-ink">{{ $value }}</p>
                <p class="mt-1 text-xs font-semibold text-ink-2">
                    {{ $label }}
                    @if ($extra)
                        <span class="font-bold text-ok">({{ $extra }})</span>
                    @endif
                </p>
            </div>
        </div>
    @endforeach
</div>
