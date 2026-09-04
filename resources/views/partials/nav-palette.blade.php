{{--
    Busca de módulos (Ctrl+K / Cmd+K).

    Não declara x-data próprio de propósito: consome o estado do shell
    (laraShell, em layouts/app.blade.php), que é quem tem o índice plano do
    menu e a lista de favoritos. Assim a estrela marcada aqui aparece na hora
    na barra lateral e na superior, sem recarregar a página.
--}}
<div
    x-show="paletteOpen"
    x-cloak
    x-transition.opacity.duration.150ms
    x-effect="document.body.style.overflow = (paletteOpen || organizerOpen) ? 'hidden' : ''"
    class="fixed inset-0 z-[70]"
    role="dialog"
    aria-modal="true"
    aria-label="Buscar módulo"
>
    <div @click="closePalette()" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

    <div
        x-show="paletteOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-3"
        class="relative mx-auto mt-20 w-[92%] max-w-xl sm:mt-28"
    >
        <div class="overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
            {{-- Campo --}}
            <div class="flex items-center gap-3 border-b border-gray-100 px-4 dark:border-gray-700">
                <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
                <input
                    x-ref="paletteInput"
                    x-model="query"
                    @input="cursor = 0"
                    @keydown.down.prevent="moveCursor(1)"
                    @keydown.up.prevent="moveCursor(-1)"
                    @keydown.enter.prevent="openResult()"
                    @keydown.escape.prevent="closePalette()"
                    type="text"
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="Buscar módulo, tela ou atalho..."
                    class="w-full border-0 bg-transparent px-0 py-4 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-gray-100"
                >
                <kbd class="hidden shrink-0 rounded border border-gray-200 px-1.5 py-0.5 text-[10px] font-medium text-gray-400 sm:block dark:border-gray-600">Esc</kbd>
            </div>

            {{-- Resultados --}}
            <div class="max-h-80 overflow-y-auto py-2">
                <p
                    x-show="!query.trim()"
                    class="px-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400"
                    x-text="favItems.length ? 'Favoritos' : 'Atalhos'"
                ></p>

                <template x-for="(item, i) in results" :key="item.key">
                    <div
                        class="group flex items-center"
                        :class="i === cursor ? 'bg-gray-100 dark:bg-gray-700/60' : ''"
                    >
                        <a
                            :href="item.url"
                            @mouseenter="cursor = i"
                            class="flex min-w-0 flex-1 items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200"
                        >
                            <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                            </svg>
                            <span class="truncate" x-text="item.label"></span>
                            <span
                                x-show="item.group"
                                class="ml-auto shrink-0 truncate pl-3 text-xs text-gray-400"
                                x-text="item.group"
                            ></span>
                        </a>

                        <button
                            type="button"
                            @click.prevent.stop="toggleFav(item.key)"
                            :title="isFav(item.key) ? 'Remover dos favoritos' : 'Fixar nos favoritos'"
                            class="shrink-0 px-3 py-2.5 transition"
                            :class="isFav(item.key) ? 'text-amber-400' : 'text-gray-300 hover:text-amber-400 dark:text-gray-600'"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" :fill="isFav(item.key) ? 'currentColor' : 'none'">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.12 4.7 5.11.6c.47.05.66.64.31.96l-3.8 3.45 1.03 5.05c.09.46-.4.82-.81.59L12 16.3l-4.48 2.55c-.41.23-.9-.13-.81-.59l1.03-5.05-3.8-3.45c-.35-.32-.16-.91.31-.96l5.11-.6 2.12-4.7z" />
                            </svg>
                        </button>
                    </div>
                </template>

                <p x-show="!results.length" class="px-4 py-8 text-center text-sm text-gray-400">
                    Nenhum módulo encontrado.
                </p>
            </div>

            {{-- Rodapé com as teclas --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-gray-100 px-4 py-2 text-[11px] text-gray-400 dark:border-gray-700">
                <span>&uarr;&darr; navegar</span>
                <span>&crarr; abrir</span>
                <span class="flex items-center gap-1">
                    <svg class="h-3 w-3 text-amber-400" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11.48 3.5a.56.56 0 011.04 0l2.12 4.7 5.11.6c.47.05.66.64.31.96l-3.8 3.45 1.03 5.05c.09.46-.4.82-.81.59L12 16.3l-4.48 2.55c-.41.23-.9-.13-.81-.59l1.03-5.05-3.8-3.45c-.35-.32-.16-.91.31-.96l5.11-.6 2.12-4.7z" />
                    </svg>
                    fixar no menu
                </span>
            </div>
        </div>
    </div>
</div>
