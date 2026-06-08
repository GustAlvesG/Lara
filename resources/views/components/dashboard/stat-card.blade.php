@props([
    'color' => 'indigo',
    'label' => '',
    'value' => '',
    'sub' => null,
])

@php
    $palette = [
        'indigo'  => ['bg' => 'bg-indigo-100 dark:bg-indigo-900/40',  'icon' => 'text-indigo-600 dark:text-indigo-400'],
        'sky'     => ['bg' => 'bg-sky-100 dark:bg-sky-900/40',        'icon' => 'text-sky-600 dark:text-sky-400'],
        'amber'   => ['bg' => 'bg-amber-100 dark:bg-amber-900/40',    'icon' => 'text-amber-600 dark:text-amber-400'],
        'violet'  => ['bg' => 'bg-violet-100 dark:bg-violet-900/40',  'icon' => 'text-violet-600 dark:text-violet-400'],
        'emerald' => ['bg' => 'bg-emerald-100 dark:bg-emerald-900/40','icon' => 'text-emerald-600 dark:text-emerald-400'],
        'green'   => ['bg' => 'bg-green-100 dark:bg-green-900/40',    'icon' => 'text-green-600 dark:text-green-400'],
        'red'     => ['bg' => 'bg-red-100 dark:bg-red-900/40',        'icon' => 'text-red-500 dark:text-red-400'],
        'teal'    => ['bg' => 'bg-teal-100 dark:bg-teal-900/40',      'icon' => 'text-teal-600 dark:text-teal-400'],
    ];
    $c = $palette[$color] ?? $palette['indigo'];
@endphp

<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex items-center gap-4">
    <div class="w-12 h-12 {{ $c['bg'] }} rounded-xl flex items-center justify-center shrink-0">
        <svg class="w-6 h-6 {{ $c['icon'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            {{ $slot }}
        </svg>
    </div>
    <div class="min-w-0">
        <p class="text-2xl font-black text-gray-900 dark:text-white truncate">{{ $value }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ __($label) }}</p>
        @if($sub)
            <p class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold mt-0.5">{{ $sub }}</p>
        @endif
    </div>
</div>
