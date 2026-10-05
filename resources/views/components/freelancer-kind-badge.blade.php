@props(['service', 'note' => false])

{{--
    Diz o que a linha é quando ela NÃO é o contrato do turno: comissão de venda,
    aditivo de horário ou contrato aditivado. Some no contrato comum — a maioria
    das linhas — para não virar ruído.

    Use :note="true" onde a pessoa decide sobre dinheiro (lote, aprovação,
    financeiro): a frase explica se o valor soma ou substitui o do turno.
--}}
@php
    $label = $service->kindLabel();

    $classes = match (true) {
        $service->isCommissionAmendment() => 'bg-ok-soft text-ok',
        $service->isAmendment() => 'bg-grena-tint text-grena-ink',
        default => 'bg-subtle text-ink-2',
    };
@endphp

@if($label)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold whitespace-nowrap ' . $classes]) }}
          title="{{ $service->kindNote() }}">
        {{ $label }}
    </span>
    @if($note && $service->kindNote())
        <span class="block mt-1 text-xs text-ink-2">{{ $service->kindNote() }}</span>
    @endif
@endif
