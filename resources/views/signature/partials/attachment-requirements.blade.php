@php
    /**
     * A lista de ANEXOS PEDIDOS — a mesma no modelo e no documento: cada
     * linha é um item (rótulo + obrigatório), como as perguntas.
     *
     * Parâmetros:
     *  - $items: os itens atuais ([label, required]);
     *  - $id:    prefixo dos ids, para a tela poder ter mais de uma lista.
     *
     * O nome dos campos é sempre `attachments[i][...]`. A chave de cada item
     * sai do rótulo, no servidor (SignatureAttachmentService::normalize).
     *
     * JavaScript puro e inline, no padrão das demais telas do módulo.
     */
    $id = $id ?? 'attachments';
    $items = array_values($items ?? []);
@endphp

<div id="{{ $id }}List" class="space-y-2"></div>

<p id="{{ $id }}Empty" class="text-xs text-ink-2" @if(count($items)) hidden @endif>Nenhum anexo pedido.</p>

<button type="button" id="{{ $id }}Add" class="mt-2 text-xs font-bold text-grena-ink hover:underline">+ Pedir anexo</button>

@foreach($errors->get('attachments.*') as $mensagens)
    @foreach($mensagens as $mensagem)
        <p class="mt-1 text-xs text-danger">{{ $mensagem }}</p>
    @endforeach
@endforeach

<template id="{{ $id }}Row">
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-line p-3" data-attachment-row>
        <input type="text" data-field="label" maxlength="120" placeholder="Ex.: Documento de identidade (frente e verso)"
               aria-label="Nome do anexo"
               class="min-w-0 flex-1 rounded-lg border-line-strong text-sm">
        <label class="flex items-center gap-2 text-xs text-ink">
            <input type="hidden" data-field="required" data-off value="0">
            <input type="checkbox" data-field="required" value="1"
                   class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
            Obrigatório
        </label>
        <button type="button" class="text-xs text-danger hover:underline" data-remove-attachment>Remover</button>
    </div>
</template>

<script>
(function () {
    var lista = document.getElementById(@json($id . 'List'));
    var vazio = document.getElementById(@json($id . 'Empty'));
    var molde = document.getElementById(@json($id . 'Row'));
    var botao = document.getElementById(@json($id . 'Add'));

    if (!lista || !molde || !botao) {
        return;
    }

    // Só cresce: dois itens nunca dividem o mesmo `name` depois de uma remoção.
    var indice = 0;

    function linha(item) {
        var i = indice++;
        var row = molde.content.firstElementChild.cloneNode(true);

        Array.prototype.forEach.call(row.querySelectorAll('[data-field]'), function (el) {
            el.name = 'attachments[' + i + '][' + el.dataset.field + ']';
        });

        item = item || {};
        row.querySelector('[data-field="label"]').value = item.label || '';
        row.querySelector('[data-field="required"]:not([data-off])').checked =
            item.required === true || item.required === 1 || item.required === '1';

        return row;
    }

    function atualiza() {
        vazio.hidden = lista.querySelectorAll('[data-attachment-row]').length > 0;
    }

    botao.addEventListener('click', function () {
        lista.appendChild(linha());
        atualiza();
    });

    lista.addEventListener('click', function (event) {
        if (event.target.matches('[data-remove-attachment]')) {
            event.target.closest('[data-attachment-row]').remove();
            atualiza();
        }
    });

    @json($items).forEach(function (item) {
        lista.appendChild(linha(item));
    });

    atualiza();
})();
</script>
