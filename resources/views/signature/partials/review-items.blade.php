@php
    /**
     * A lista de ITENS DA REVISÃO do modelo: o que a revisão interna confere
     * ("Cadastro atualizado no sistema", "Pagamento lançado"). Uma linha por
     * item, só o rótulo — a chave sai dele, no servidor
     * (SignatureReviewService::normalize).
     *
     * Parâmetro: $items — os itens atuais ([label]).
     * O nome dos campos é `review_items[i][label]`.
     */
    $items = array_values($items ?? []);
@endphp

<div id="reviewItemsList" class="space-y-2"></div>

<p id="reviewItemsEmpty" class="text-xs text-ink-2" @if(count($items)) hidden @endif>
    Nenhum item: a revisão só registra o resultado e a observação.
</p>

<button type="button" id="reviewItemsAdd" class="mt-2 text-xs font-bold text-grena-ink hover:underline">+ Acrescentar item</button>

@foreach($errors->get('review_items.*') as $mensagens)
    @foreach($mensagens as $mensagem)
        <p class="mt-1 text-xs text-danger">{{ $mensagem }}</p>
    @endforeach
@endforeach

<template id="reviewItemsRow">
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-line p-3" data-review-item-row>
        <input type="text" data-field="label" maxlength="120" placeholder="Ex.: Cadastro do associado atualizado no sistema"
               aria-label="O que a revisão confere"
               class="min-w-0 flex-1 rounded-lg border-line-strong text-sm">
        <button type="button" class="text-xs text-danger hover:underline" data-remove-review-item>Remover</button>
    </div>
</template>

<script>
(function () {
    var lista = document.getElementById('reviewItemsList');
    var vazio = document.getElementById('reviewItemsEmpty');
    var molde = document.getElementById('reviewItemsRow');
    var botao = document.getElementById('reviewItemsAdd');

    if (!lista || !molde || !botao) {
        return;
    }

    // Só cresce: dois itens nunca dividem o mesmo `name` depois de uma remoção.
    var indice = 0;

    function linha(item) {
        var row = molde.content.firstElementChild.cloneNode(true);
        var campo = row.querySelector('[data-field="label"]');

        campo.name = 'review_items[' + (indice++) + '][label]';
        campo.value = (item && item.label) || '';

        return row;
    }

    function atualiza() {
        vazio.hidden = lista.querySelectorAll('[data-review-item-row]').length > 0;
    }

    botao.addEventListener('click', function () {
        lista.appendChild(linha());
        atualiza();
    });

    lista.addEventListener('click', function (event) {
        if (event.target.matches('[data-remove-review-item]')) {
            event.target.closest('[data-review-item-row]').remove();
            atualiza();
        }
    });

    @json($items).forEach(function (item) {
        lista.appendChild(linha(item));
    });

    atualiza();
})();
</script>
