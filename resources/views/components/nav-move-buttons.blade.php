@props(['up', 'down', 'first', 'last'])

{{--
    Setas de mover para cima/baixo.

    Existem ao lado do arrastar porque o drag-and-drop nativo do HTML não
    funciona em toque, e porque com botão a reordenação fica acessível por
    teclado.

    `up`/`down` são as chamadas Alpine a executar; `first`/`last` são
    expressões Alpine que dizem quando desabilitar cada seta.
--}}
<div class="flex shrink-0 items-center">
    <button type="button" @click="{{ $up }}" :disabled="{{ $first }}" title="Mover para cima"
        class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-200/60 hover:text-gray-600 disabled:pointer-events-none disabled:opacity-30 dark:hover:bg-gray-700 dark:hover:text-gray-200">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
        </svg>
    </button>
    <button type="button" @click="{{ $down }}" :disabled="{{ $last }}" title="Mover para baixo"
        class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-200/60 hover:text-gray-600 disabled:pointer-events-none disabled:opacity-30 dark:hover:bg-gray-700 dark:hover:text-gray-200">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
</div>
