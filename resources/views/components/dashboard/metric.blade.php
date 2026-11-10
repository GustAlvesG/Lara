@props([
    'label' => '',
    'value' => '',
    'sub' => null,
    'tone' => null,
])

{{--
    Número de um painel do Dashboard: valor grande, rótulo embaixo. `tone`
    só quando o número é um estado — ok (permitido), danger (negado), warn.
--}}
@php
    $tones = ['ok' => 'text-ok', 'danger' => 'text-danger', 'warn' => 'text-warn'];
@endphp
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <p class="truncate font-mono text-[28px] font-semibold leading-none tracking-tight {{ $tones[$tone] ?? 'text-ink' }}">{{ $value }}</p>
    <p class="mt-1.5 text-sm font-semibold text-ink-2">{{ __($label) }}</p>
    @if ($sub)
        <p class="mt-0.5 text-xs font-bold text-warn">{{ $sub }}</p>
    @endif
</div>
