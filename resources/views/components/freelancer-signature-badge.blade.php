@props(['service'])

@php
    $classes = match (true) {
        // A falta é um cancelamento, mas não se lê como um: quem varre a
        // listagem procura o que o freelancer não cumpriu, e no cinza de
        // "cancelado" isso desaparece.
        $service->isNoShow() => 'bg-warn-soft text-warn border-warn/40',
        $service->isCancelled() => 'bg-line text-ink border-line-strong',
        $service->isFullySigned() => 'bg-ok-soft text-ok border-ok/40',
        $service->isSigned() => 'bg-warn-soft text-warn border-warn/40',
        default => 'bg-grena-tint text-grena-ink border-grena/40',
    };
@endphp

<span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold border whitespace-nowrap {{ $classes }}">
    {{ $service->signatureLabel() }}
</span>
