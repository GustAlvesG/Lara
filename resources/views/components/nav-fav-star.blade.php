@props(['routeKey'])

{{--
    Estrela de favorito de um item do menu.

    Só marca/desmarca — quem guarda a lista é o laraShell (localStorage), e
    tanto a barra lateral quanto a superior leem de lá. Por isso o componente
    não declara x-data: ele precisa cair no escopo do shell.

    A estrela fica invisível até o hover, então o pai precisa ter a classe
    `group`.
--}}
<button
    type="button"
    @click.prevent.stop="toggleFav('{{ $routeKey }}')"
    :title="isFav('{{ $routeKey }}') ? 'Remover dos favoritos' : 'Fixar nos favoritos'"
    {{ $attributes->merge(['class' => 'shrink-0 p-1.5 rounded-lg transition']) }}
    :class="isFav('{{ $routeKey }}')
        ? 'text-amber-400 opacity-100'
        : 'text-gray-300 dark:text-gray-600 hover:text-amber-400 opacity-0 group-hover:opacity-100 focus:opacity-100'"
>
    <svg class="w-4 h-4" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
        :fill="isFav('{{ $routeKey }}') ? 'currentColor' : 'none'">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.12 4.7 5.11.6c.47.05.66.64.31.96l-3.8 3.45 1.03 5.05c.09.46-.4.82-.81.59L12 16.3l-4.48 2.55c-.41.23-.9-.13-.81-.59l1.03-5.05-3.8-3.45c-.35-.32-.16-.91.31-.96l5.11-.6 2.12-4.7z" />
    </svg>
</button>
