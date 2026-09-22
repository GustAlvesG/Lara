@props(['service'])

@php
    $classes = match (true) {
        // A falta é um cancelamento, mas não se lê como um: quem varre a
        // listagem procura o que o freelancer não cumpriu, e no cinza de
        // "cancelado" isso desaparece.
        $service->isNoShow() => 'bg-orange-100 dark:bg-orange-900/40 text-orange-800 dark:text-orange-300 border-orange-200 dark:border-orange-700',
        $service->isCancelled() => 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600',
        $service->isFullySigned() => 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 border-green-200 dark:border-green-700',
        $service->isSigned() => 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-700',
        default => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 border-blue-200 dark:border-blue-700',
    };
@endphp

<span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold border whitespace-nowrap {{ $classes }}">
    {{ $service->signatureLabel() }}
</span>
