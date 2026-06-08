@props([
    'title' => '',
    'icon' => '',
    'href' => null,
    'linkLabel' => 'Ver mais',
    'color' => 'indigo',
])

@php
    $palette = [
        'indigo'  => ['bg' => 'bg-indigo-100 dark:bg-indigo-900/40',  'icon' => 'text-indigo-600 dark:text-indigo-400'],
        'sky'     => ['bg' => 'bg-sky-100 dark:bg-sky-900/40',        'icon' => 'text-sky-600 dark:text-sky-400'],
        'violet'  => ['bg' => 'bg-violet-100 dark:bg-violet-900/40',  'icon' => 'text-violet-600 dark:text-violet-400'],
        'teal'    => ['bg' => 'bg-teal-100 dark:bg-teal-900/40',      'icon' => 'text-teal-600 dark:text-teal-400'],
        'emerald' => ['bg' => 'bg-emerald-100 dark:bg-emerald-900/40','icon' => 'text-emerald-600 dark:text-emerald-400'],
    ];
    $c = $palette[$color] ?? $palette['indigo'];
@endphp

<section class="space-y-4">
    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-2">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 {{ $c['bg'] }} rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 {{ $c['icon'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
                </svg>
            </div>
            <h2 class="text-lg font-extrabold text-gray-800 dark:text-white">{{ __($title) }}</h2>
        </div>
        @if($href)
            <a href="{{ $href }}" class="text-sm font-bold text-red-700 dark:text-red-400 hover:underline whitespace-nowrap">{{ __($linkLabel) }}</a>
        @endif
    </div>
    {{ $slot }}
</section>
