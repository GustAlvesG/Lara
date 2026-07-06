{{-- Seletor de modo de navegação: Lateral / Superior (estado em Alpine: navMode / setNav) --}}
<div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Navegação</p>
    <div class="flex gap-1 p-1 rounded-lg bg-gray-100 dark:bg-gray-900">
        <button type="button" @click="setNav('side')"
            class="flex-1 flex items-center justify-center gap-1.5 px-2 py-1.5 rounded-md text-xs font-medium transition"
            :class="navMode === 'side' ? 'bg-white dark:bg-gray-700 text-red-800 dark:text-red-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 6v12a2 2 0 002 2h4V4H6a2 2 0 00-2 2z" />
            </svg>
            Lateral
        </button>
        <button type="button" @click="setNav('top')"
            class="flex-1 flex items-center justify-center gap-1.5 px-2 py-1.5 rounded-md text-xs font-medium transition"
            :class="navMode === 'top' ? 'bg-white dark:bg-gray-700 text-red-800 dark:text-red-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 6v12a2 2 0 002 2h12a2 2 0 002-2V6M4 10h16" />
            </svg>
            Superior
        </button>
    </div>
</div>
