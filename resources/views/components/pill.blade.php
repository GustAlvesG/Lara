@props(['kind' => 'off', 'icon' => true])

@php
    /*
     | Selo de status. O ícone vai junto da cor de propósito: quem não
     | distingue bem verde de vermelho continua lendo o estado.
     |   ok     → liberado, pago, ativo
     |   warn   → aguardando, pendente
     |   danger → negado, cancelado, vencido
     |   info   → agendado, informativo (grená claro)
     |   off    → inativo, encerrado, arquivado
     */
    $styles = [
        'ok' => ['bg-ok-soft text-ok', 'check'],
        'warn' => ['bg-warn-soft text-warn', 'clock'],
        'danger' => ['bg-danger-soft text-danger', 'x'],
        'info' => ['bg-grena-tint text-grena-ink', null],
        'off' => ['bg-subtle text-ink-3', null],
    ];
    [$classes, $iconName] = $styles[$kind] ?? $styles['off'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 h-[26px] px-2.5 rounded-full text-[12.5px] font-bold whitespace-nowrap {$classes}"]) }}>
    @if ($icon && $iconName)
        <x-icon :name="$iconName" class="w-3.5 h-3.5" />
    @endif
    {{ $slot }}
</span>
