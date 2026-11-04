{{--
    Trechos anexados ao pedido de revisão pelo Claude (04/11/2026): texto
    colado (proposta do fornecedor, contrato anterior, orientação do jurídico)
    que vai junto com a minuta, como material de referência. Ver
    ContractAiReviewer::normalizeExtras.

    $form: id do formulário dono dos campos (o editor usa `ctForm`); nulo =
    o formulário em volta.
--}}
@php
    use App\Services\Signature\Contracts\ContractAiReviewer as AI;

    $form = $form ?? null;
    $attr = $form ? 'form="' . e($form) . '"' : '';
    $campo = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
@endphp
<details class="text-sm" data-ai-extras>
    <summary class="cursor-pointer text-xs font-bold text-grena-ink">Anexar trechos ao pedido (opcional)</summary>
    <div class="mt-2 space-y-2 rounded-xl border border-line p-3">
        <p class="text-xs text-ink-2">
            Cole um texto para o Claude usar como referência: a proposta do fornecedor, o contrato anterior, uma orientação.
            Ele confere a minuta contra esse material e aponta as divergências. Até {{ AI::MAX_EXTRAS }} trechos de
            {{ number_format(AI::MAX_EXTRA_TEXT, 0, ',', '.') }} caracteres. Esse texto também vai à Anthropic: não cole dado que não deva sair.
        </p>
        <div class="space-y-2" data-ai-extras-list>
            <div class="space-y-1" data-ai-extra>
                <input type="text" name="extras[0][title]" {!! $attr !!} maxlength="{{ AI::MAX_EXTRA_TITLE }}" class="{{ $campo }}" placeholder="Título (ex.: Proposta comercial)">
                <textarea name="extras[0][text]" {!! $attr !!} rows="4" maxlength="{{ AI::MAX_EXTRA_TEXT }}" class="{{ $campo }}" placeholder="Cole aqui o trecho"></textarea>
            </div>
        </div>
        <button type="button" data-ai-extra-add class="rounded-full bg-subtle px-3 py-1.5 text-xs font-bold text-ink hover:bg-line">+ Outro trecho</button>
    </div>
</details>

@once
    <script>
        document.addEventListener('click', (e) => {
            const botao = e.target.closest('[data-ai-extra-add]');
            if (!botao) return;

            const lista = botao.closest('[data-ai-extras]').querySelector('[data-ai-extras-list]');
            const total = lista.querySelectorAll('[data-ai-extra]').length;
            if (total >= {{ \App\Services\Signature\Contracts\ContractAiReviewer::MAX_EXTRAS }}) {
                botao.disabled = true;
                return;
            }

            const novo = lista.querySelector('[data-ai-extra]').cloneNode(true);
            novo.querySelectorAll('[name]').forEach((el) => {
                el.name = el.name.replace(/^extras\[\d+\]/, 'extras[' + total + ']');
                el.value = '';
            });
            lista.appendChild(novo);
            if (total + 1 >= {{ \App\Services\Signature\Contracts\ContractAiReviewer::MAX_EXTRAS }}) botao.disabled = true;
        });
    </script>
@endonce
