@props([
    'href' => '#',
    'label' => '',
    'icon' => '',
])

<a href="{{ $href }}"
   class="group bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex flex-col items-center justify-center gap-2 text-center hover:shadow-md hover:border-red-200 dark:hover:border-red-800 hover:-translate-y-0.5 transition">
    <div class="w-11 h-11 bg-red-50 dark:bg-red-900/30 rounded-xl flex items-center justify-center text-red-700 dark:text-red-400 group-hover:scale-110 transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
        </svg>
    </div>
    <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ __($label) }}</span>
</a>
