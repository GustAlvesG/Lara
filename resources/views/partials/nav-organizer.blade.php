{{--
    Organizar menu: ordem dos grupos e dos favoritos.

    Fica num diálogo próprio em vez de arrastar direto na barra por dois
    motivos: funciona igual nos dois modos de navegação (a superior é
    horizontal e não daria para arrastar do mesmo jeito), e ninguém reordena o
    menu sem querer ao clicar num item.

    Arrastar cobre o mouse; as setas cobrem teclado e toque, onde o
    drag-and-drop nativo do HTML não funciona.
--}}
<div
    x-show="organizerOpen"
    x-cloak
    x-transition.opacity.duration.150ms
    x-effect="document.body.style.overflow = (paletteOpen || organizerOpen) ? 'hidden' : ''"
    class="fixed inset-0 z-[70]"
    role="dialog"
    aria-modal="true"
    aria-label="Organizar menu"
>
    <div @click="organizerOpen = false" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

    <div
        x-show="organizerOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-3"
        class="relative mx-auto mt-12 w-[92%] max-w-lg sm:mt-20"
        @keydown.escape.window="organizerOpen = false"
    >
        <div class="flex max-h-[80vh] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
            <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Organizar menu</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Arraste ou use as setas. A ordem vale só para você.</p>
                </div>
                <button type="button" @click="organizerOpen = false"
                    class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4">
                {{-- Favoritos --}}
                <section>
                    <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Favoritos</h3>

                    <p x-show="!favItems.length" class="rounded-xl border border-dashed border-gray-200 px-4 py-5 text-center text-xs text-gray-400 dark:border-gray-600">
                        Nenhum favorito ainda. Marque a estrela de um item no menu ou na busca (Ctrl+K).
                    </p>

                    <ul class="space-y-1">
                        <template x-for="(item, i) in favItems" :key="item.key">
                            <li
                                draggable="true"
                                @dragstart="dragKey = item.key"
                                @dragover.prevent="dragOverFav(item.key)"
                                @drop.prevent="dragKey = null"
                                @dragend="dragKey = null"
                                class="flex items-center gap-2 rounded-xl border border-gray-100 bg-gray-50/60 px-2 py-2 transition dark:border-gray-700 dark:bg-gray-900/40"
                                :class="dragKey === item.key ? 'opacity-40' : ''"
                            >
                                <x-nav-drag-handle />

                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                </svg>
                                <span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-200" x-text="item.label"></span>
                                <span x-show="item.group" class="hidden shrink-0 truncate text-xs text-gray-400 sm:block" x-text="item.group"></span>

                                <x-nav-move-buttons up="moveFav(item.key, -1)" down="moveFav(item.key, 1)" first="i === 0" last="i === favItems.length - 1" />

                                <button type="button" @click="toggleFav(item.key)" title="Remover dos favoritos"
                                    class="shrink-0 rounded-lg p-1.5 text-amber-400 transition hover:bg-gray-200/60 dark:hover:bg-gray-700">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M11.48 3.5a.56.56 0 011.04 0l2.12 4.7 5.11.6c.47.05.66.64.31.96l-3.8 3.45 1.03 5.05c.09.46-.4.82-.81.59L12 16.3l-4.48 2.55c-.41.23-.9-.13-.81-.59l1.03-5.05-3.8-3.45c-.35-.32-.16-.91.31-.96l5.11-.6 2.12-4.7z" />
                                    </svg>
                                </button>
                            </li>
                        </template>
                    </ul>
                </section>

                {{-- Grupos do menu --}}
                <section class="mt-6">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Menu</h3>
                        <button type="button" x-show="navOrderIsCustom" @click="resetNavOrder()"
                            class="text-xs text-gray-500 underline-offset-2 transition hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-200">
                            Restaurar padrão
                        </button>
                    </div>

                    <ul class="space-y-1">
                        <template x-for="(group, i) in orderedNavGroups" :key="group.key">
                            <li
                                draggable="true"
                                @dragstart="dragKey = group.key"
                                @dragover.prevent="dragOverNav(group.key)"
                                @drop.prevent="dragKey = null"
                                @dragend="dragKey = null"
                                class="flex items-center gap-2 rounded-xl border border-gray-100 bg-gray-50/60 px-2 py-2 transition dark:border-gray-700 dark:bg-gray-900/40"
                                :class="dragKey === group.key ? 'opacity-40' : ''"
                            >
                                <x-nav-drag-handle />

                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="group.icon" />
                                </svg>
                                <span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-200" x-text="group.label"></span>

                                <x-nav-move-buttons up="moveNav(group.key, -1)" down="moveNav(group.key, 1)" first="i === 0" last="i === orderedNavGroups.length - 1" />
                            </li>
                        </template>
                    </ul>
                </section>
            </div>

            <div class="shrink-0 border-t border-gray-100 px-5 py-3 text-right dark:border-gray-700">
                <button type="button" @click="organizerOpen = false"
                    class="rounded-lg bg-red-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-900">
                    Concluído
                </button>
            </div>
        </div>
    </div>
</div>
