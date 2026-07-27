@props(['service'])

@php
    $classes = match (true) {
        $service->isCancelled() => 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600',
        $service->isFullySigned() => 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 border-green-200 dark:border-green-700',
        $service->isSigned() => 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-700',
        default => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 border-blue-200 dark:border-blue-700',
    };
@endphp

<span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold border whitespace-nowrap {{ $classes }}">
    {{ $service->signatureLabel() }}
</span>
