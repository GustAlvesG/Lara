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
        $service->isCommissionAmendment() => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        $service->isAmendment() => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
        default => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    };
@endphp

@if($label)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold whitespace-nowrap ' . $classes]) }}
          title="{{ $service->kindNote() }}">
        {{ $label }}
    </span>
    @if($note && $service->kindNote())
        <span class="block mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $service->kindNote() }}</span>
    @endif
@endif
