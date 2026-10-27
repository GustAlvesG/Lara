{{--
    Uma cláusula no editor: título, itens e as observações abertas da
    Gerência sobre ela. $k = chave; $cl = {uid, title, items}; $comments =
    observações não resolvidas desta cláusula. O número ("CLÁUSULA
    PRIMEIRA") não se digita: sai da posição.
--}}
@php
    $f = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm font-bold text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
@endphp
<div class="ct-row space-y-3 rounded-2xl border border-line bg-surface p-4 shadow-card" data-row="clause" data-uid="{{ $cl['uid'] ?? '' }}">
    <input type="hidden" name="clauses[{{ $k }}][uid]" value="{{ $cl['uid'] ?? '' }}">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-bold uppercase tracking-wider text-ink-3" data-clause-number>Cláusula</span>
        <input type="text" name="clauses[{{ $k }}][title]" value="{{ $cl['title'] ?? '' }}" class="{{ $f }} min-w-0 flex-1" maxlength="200" placeholder="Título (ex.: Objeto, Prazo, Valor e forma de pagamento)">
        <div class="flex items-center gap-1">
            <button type="button" data-move="up" class="rounded-md px-2 py-1 text-xs text-ink-2 hover:bg-subtle" title="Subir a cláusula">↑</button>
            <button type="button" data-move="down" class="rounded-md px-2 py-1 text-xs text-ink-2 hover:bg-subtle" title="Descer a cláusula">↓</button>
            <button type="button" data-save-library class="rounded-md px-2 py-1 text-xs font-bold text-grena-ink hover:bg-grena-tint" title="Salvar esta cláusula na biblioteca do setor">Salvar na biblioteca</button>
            <button type="button" data-remove data-confirm="Remover a cláusula inteira?" class="rounded-md px-2 py-1 text-xs font-bold text-danger hover:bg-danger-soft">Remover</button>
        </div>
    </div>

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

    <button type="button" data-add-item class="rounded-full bg-subtle px-3 py-1.5 text-xs font-bold text-ink hover:bg-line">+ Item</button>
</div>
