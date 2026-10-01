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
    <div @click="closePalette()" class="absolute inset-0 bg-ink/30 backdrop-blur-sm"></div>

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
        <div class="overflow-hidden rounded-[20px] border border-line bg-surface text-ink shadow-pop">
            {{-- Campo --}}
            <div class="flex items-center gap-3 border-b border-line px-4">
                <x-icon name="search" class="h-5 w-5 text-ink-3" />
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
                    placeholder="Buscar módulo ou página, ex.: monitor, frota, pagamentos"
                    class="h-14 w-full border-0 bg-transparent px-0 text-base text-ink placeholder:text-ink-3 focus:outline-none focus:ring-0"
                >
                <kbd class="hidden shrink-0 rounded-md border border-line-strong px-1.5 py-0.5 font-mono text-[11px] font-semibold text-ink-3 sm:block">Esc</kbd>
            </div>

            {{-- Resultados --}}
            <div class="max-h-[52vh] overflow-y-auto p-2">
                <p
                    x-show="!query.trim()"
                    class="px-2.5 pb-1 pt-1 text-[11px] font-bold uppercase tracking-[0.06em] text-ink-3"
                    x-text="favItems.length ? 'Favoritos' : 'Atalhos'"
                ></p>

                <template x-for="(item, i) in results" :key="item.key">
                    <div
                        class="group flex items-center rounded-xl"
                        :class="i === cursor ? 'bg-grena-tint' : ''"
                    >
                        <a
                            :href="item.url"
                            @mouseenter="cursor = i"
                            class="flex min-w-0 flex-1 items-center gap-3 px-2.5 py-2 text-sm text-ink no-underline"
                        >
                            <span class="grid h-[30px] w-[30px] shrink-0 place-items-center rounded-[9px]" :style="item.areaStyle + ' background-color: rgb(var(--c)); color: rgb(var(--ci));'">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                </svg>
                            </span>
                            <span class="truncate" x-text="item.label"></span>
                            <span
                                x-show="item.group"
                                class="ml-auto shrink-0 truncate pl-3 text-[12.5px] text-ink-3"
                                x-text="item.group"
                            ></span>
                        </a>

                        <button
                            type="button"
                            @click.prevent.stop="toggleFav(item.key)"
                            :title="isFav(item.key) ? 'Remover dos favoritos' : 'Fixar nos favoritos'"
                            class="shrink-0 px-3 py-2.5 transition"
                            :class="isFav(item.key) ? 'text-star' : 'text-ink-3 opacity-50 hover:text-star hover:opacity-100'"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" :fill="isFav(item.key) ? 'currentColor' : 'none'">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.12 4.7 5.11.6c.47.05.66.64.31.96l-3.8 3.45 1.03 5.05c.09.46-.4.82-.81.59L12 16.3l-4.48 2.55c-.41.23-.9-.13-.81-.59l1.03-5.05-3.8-3.45c-.35-.32-.16-.91.31-.96l5.11-.6 2.12-4.7z" />
                            </svg>
                        </button>
                    </div>
                </template>

                <p x-show="!results.length" class="px-4 py-8 text-center text-sm text-ink-3">
                    Nenhum módulo encontrado.
                </p>
            </div>

            {{-- Rodapé com as teclas --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line px-4 py-2.5 text-xs text-ink-3">
                <span>&uarr;&darr; navegar</span>
                <span>&crarr; abrir</span>
                <span class="flex items-center gap-1">
                    <svg class="h-3 w-3 text-star" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11.48 3.5a.56.56 0 011.04 0l2.12 4.7 5.11.6c.47.05.66.64.31.96l-3.8 3.45 1.03 5.05c.09.46-.4.82-.81.59L12 16.3l-4.48 2.55c-.41.23-.9-.13-.81-.59l1.03-5.05-3.8-3.45c-.35-.32-.16-.91.31-.96l5.11-.6 2.12-4.7z" />
                    </svg>
                    fixar no menu
                </span>
            </div>
        </div>
    </div>
</div>
