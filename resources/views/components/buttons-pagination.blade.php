<button {{ $attributes->merge(['type' => 'submit', 'class' => 'relative inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 text-sm font-semibold text-white dark:text-gray-800 ring-1 ring-inset ring-gray-300 hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white focus:outline-offset-0 page-button']) }}>
    {{ $slot }}
</button>