{{--
    Linha do tempo de hoje de um contator, simulada com as regras atuais.
    Parâmetros:
      $timeline  array  trechos de ContactorStateResolver::timeline()
      $now       Carbon
--}}
@php
    $nowPct = round(($now->hour * 60 + $now->minute) / 1440 * 100, 2);

    $segmentClass = function (array $segment) {
        if (! $segment['on']) {
            // Desligado "porque não há nada" é o fundo; desligado por decisão aparece hachurado
            return $segment['source'] === 'idle' ? null : 'bg-ink-3 ha-hatch';
        }

        return match ($segment['source']) {
            'reservation' => 'bg-[rgb(var(--a-reservas-ink))]',
            'override'    => 'bg-grena',
            'quick'       => 'bg-ink',
            default       => 'bg-ink-3',
        };
    };

    $fmt = fn (int $minutes) => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
@endphp

<div>
    <div class="relative h-3 rounded-full bg-subtle overflow-hidden">
        @foreach($timeline as $segment)
            @php $class = $segmentClass($segment); @endphp
            @if($class)
                <div class="absolute inset-y-0 {{ $class }}"
                    style="left: {{ $segment['start'] / 14.4 }}%; width: {{ ($segment['end'] - $segment['start']) / 14.4 }}%"
                    title="{{ $fmt($segment['start']) }}–{{ $fmt($segment['end'] === 1440 ? 1439 : $segment['end']) }} · {{ $segment['on'] ? 'Ligado' : 'Desligado' }} · {{ $segment['label'] }}"></div>
            @endif
        @endforeach

        {{-- O passado fica esmaecido: a simulação usa as regras de agora, não o que de fato aconteceu --}}
        <div class="absolute inset-y-0 left-0 bg-surface/60 pointer-events-none" style="width: {{ $nowPct }}%"></div>
        <div class="absolute inset-y-0 w-0.5 bg-danger pointer-events-none" style="left: calc({{ $nowPct }}% - 1px)"></div>
    </div>
    <div class="mt-1 flex justify-between text-[10px] font-medium text-ink-3 tabular-nums">
        <span>0h</span><span>6h</span><span>12h</span><span>18h</span><span>24h</span>
    </div>
</div>
