{{--
    Campo de texto de cláusula: negrito, itálico, sublinhado, listas, desfazer
    e os selects que inserem variável e remissão. O HTML vai para o textarea
    escondido no envio (ver partials/editor-js); o servidor saneia
    (ContractContent::html). A caixa cresce com o texto e pode ser puxada
    pelo canto (.ct-rich em partials/styles).

    $name, $value, $placeholder, $refs (bool: mostra a remissão; considerando também pode).
--}}
@php
    $btn = 'rounded-md px-2.5 py-1 text-xs font-bold text-ink-2 hover:bg-surface hover:text-ink';
    // O plugin de forms põe a seta à direita: o pr-8 abre espaço para ela não cobrir o texto.
    $sel = 'h-8 max-w-[15rem] rounded-md border border-line bg-surface py-0 pl-2 pr-8 text-xs text-ink-2';
@endphp
<div class="ct-editor rounded-xl border border-line-strong bg-surface focus-within:border-grena focus-within:ring-4 focus-within:ring-grena-tint">
    <div class="flex flex-wrap items-center gap-1 rounded-t-xl border-b border-line bg-subtle p-1.5" role="toolbar" aria-label="Formatação">
        <button type="button" data-cmd="bold" class="{{ $btn }}" title="Negrito (Ctrl+B)"><span class="font-black">N</span></button>
        <button type="button" data-cmd="italic" class="{{ $btn }}" title="Itálico (Ctrl+I)"><span class="italic">I</span></button>
        <button type="button" data-cmd="underline" class="{{ $btn }}" title="Sublinhado (Ctrl+U)"><span class="underline">S</span></button>
        <button type="button" data-cmd="insertUnorderedList" class="{{ $btn }}" title="Lista com marcador">• Lista</button>
        <button type="button" data-cmd="insertOrderedList" class="{{ $btn }}" title="Lista numerada">1. Lista</button>
        <button type="button" data-cmd="removeFormat" class="{{ $btn }}" title="Tirar a formatação do trecho selecionado">Limpar</button>
        <button type="button" data-cmd="undo" class="{{ $btn }}" title="Desfazer (Ctrl+Z)">↶</button>
        <button type="button" data-cmd="redo" class="{{ $btn }}" title="Refazer (Ctrl+Y)">↷</button>
        <span class="mx-1 h-4 w-px bg-line-strong"></span>
        <select data-insert="token" class="{{ $sel }}" aria-label="Inserir variável"><option value="">Inserir variável…</option></select>
        @if($refs ?? true)
            <select data-insert="ref" class="{{ $sel }}" aria-label="Inserir remissão"><option value="">Inserir remissão…</option></select>
        @endif
    </div>
    <div class="ct-rich text-ink" contenteditable="true" data-placeholder="{{ $placeholder ?? 'Escreva o texto…' }}">{!! $value !!}</div>
    <textarea name="{{ $name }}" class="hidden" data-rich-source>{{ $value }}</textarea>
</div>
