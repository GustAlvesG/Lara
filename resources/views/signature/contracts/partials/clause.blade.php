{{--
    Uma cláusula no editor: título, itens e as observações abertas da
    Gerência sobre ela. $k = chave; $cl = {uid, title, items}; $comments =
    observações não resolvidas desta cláusula. O número ("CLÁUSULA
    PRIMEIRA") não se digita: sai da posição.
--}}
@php
    $f = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2.5 text-base font-bold text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
@endphp
<div class="ct-row space-y-3 rounded-2xl border border-line bg-surface p-4 shadow-card sm:p-5" data-row="clause" data-uid="{{ $cl['uid'] ?? '' }}">
    <input type="hidden" name="clauses[{{ $k }}][uid]" value="{{ $cl['uid'] ?? '' }}">
    {{-- Ações em cima e o título com a linha inteira: com os botões ao lado, o título ficava espremido. --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="rounded-full bg-grena-tint px-3 py-1 text-xs font-bold uppercase tracking-wider text-grena-ink" data-clause-number>Cláusula</span>
        <div class="flex flex-wrap items-center gap-1">
            <button type="button" data-move="up" class="rounded-md px-2 py-1 text-xs text-ink-2 hover:bg-subtle" title="Subir a cláusula">↑</button>
            <button type="button" data-move="down" class="rounded-md px-2 py-1 text-xs text-ink-2 hover:bg-subtle" title="Descer a cláusula">↓</button>
            <button type="button" data-save-library class="rounded-md px-2 py-1 text-xs font-bold text-grena-ink hover:bg-grena-tint" title="Salvar esta cláusula na biblioteca do setor">Salvar na biblioteca</button>
            <button type="button" data-remove data-confirm="Remover a cláusula inteira?" class="rounded-md px-2 py-1 text-xs font-bold text-danger hover:bg-danger-soft">Remover</button>
        </div>
    </div>
    <input type="text" name="clauses[{{ $k }}][title]" value="{{ $cl['title'] ?? '' }}" class="{{ $f }}" maxlength="200"
           placeholder="Título da cláusula (ex.: Objeto, Prazo, Valor e forma de pagamento)" aria-label="Título da cláusula">

    @foreach($comments ?? [] as $comment)
        <div class="flex items-start justify-between gap-3 rounded-xl bg-warn-soft px-3 py-2 text-sm text-ink" data-comment="{{ $comment->id }}">
            <div>
                <span class="text-xs font-bold text-warn">Observação da Gerência · {{ $comment->author_name }}</span>
                <p class="whitespace-pre-line">{{ $comment->body }}</p>
            </div>
            <button type="button" data-resolve="{{ route('signature-contracts.comments.resolve', [$contract, $comment]) }}"
                    class="shrink-0 rounded-full bg-surface px-3 py-1 text-xs font-bold text-ink hover:bg-subtle">Resolvida</button>
        </div>
    @endforeach

    <div class="space-y-2" data-items>
        @foreach($cl['items'] ?? [] as $item)
            @include('signature.contracts.partials.item', ['c' => $k, 'k' => $item['uid'], 'i' => $item])
        @endforeach
    </div>

    <div class="relative flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3" data-library-wrap>
        <button type="button" data-add-item class="rounded-full bg-subtle px-3 py-1.5 text-xs font-bold text-ink hover:bg-line">+ Item</button>
        {{-- Entre cláusulas: a nova (em branco ou da biblioteca) entra logo abaixo desta. --}}
        <div class="flex flex-wrap gap-2">
            <button type="button" data-add="clause" class="rounded-full px-3 py-1.5 text-xs font-bold text-grena-ink hover:bg-grena-tint" title="Inserir uma cláusula logo abaixo desta">+ Cláusula abaixo</button>
            <button type="button" data-library-open class="rounded-full px-3 py-1.5 text-xs font-bold text-grena-ink hover:bg-grena-tint" title="Inserir uma cláusula da biblioteca logo abaixo desta">Da biblioteca abaixo…</button>
        </div>
    </div>
</div>
