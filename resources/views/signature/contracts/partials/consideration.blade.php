{{-- Um considerando: (i), (ii)… numerados sozinhos. $k = chave; $html = o texto. --}}
<div class="ct-row flex items-start gap-2" data-row="consideration">
    <div class="min-w-0 flex-1">
        @include('signature.contracts.partials.rich', ['name' => 'considerations[' . $k . ']', 'value' => $html, 'placeholder' => 'A CONTRATADA é pessoa jurídica cujo objeto social abrange…'])
    </div>
    <div class="flex flex-col gap-1">
        <button type="button" data-move="up" class="rounded-md px-2 py-1 text-xs text-ink-2 hover:bg-subtle" title="Subir">↑</button>
        <button type="button" data-move="down" class="rounded-md px-2 py-1 text-xs text-ink-2 hover:bg-subtle" title="Descer">↓</button>
        <button type="button" data-remove class="rounded-md px-2 py-1 text-xs font-bold text-danger hover:bg-danger-soft" title="Remover">✕</button>
    </div>
</div>
