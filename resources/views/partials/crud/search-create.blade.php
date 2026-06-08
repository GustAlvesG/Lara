<div class="w-full sm:w-auto sm:flex-1 sm:max-w-2xl sm:ml-auto">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">

        <div class="relative flex-grow">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 pointer-events-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </span>
            <input type="text" id="search-filter-text" placeholder="Pesquisar empresa ou funcionário..."
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                    onkeyup="filterCards()">
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
            @isset($routeSecondary)
                <a href="{{ $routeSecondary }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-xl font-semibold text-sm shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    {{ $buttonSecondaryText ?? __('Novo') }}
                </a>
            @endisset

            <a href="{{ $routeCreate }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 border border-transparent rounded-xl font-semibold text-sm text-white shadow-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ $buttonCreateText ?? __('Novo Registro') }}
            </a>
        </div>

    </div>
</div>
