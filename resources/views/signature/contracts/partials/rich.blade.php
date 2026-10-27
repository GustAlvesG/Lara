{{--
    Campo de texto de cláusula: negrito, itálico, sublinhado, listas e os
    botões que inserem variável e remissão. O HTML vai para o textarea
    escondido no envio (ver partials/editor-js); o servidor saneia
    (ContractContent::html).

    $name, $value, $placeholder, $refs (bool: mostra a remissão; considerando também pode).
--}}
@php
    $btn = 'rounded-md px-2 py-1 text-xs font-bold text-ink-2 hover:bg-surface hover:text-ink';
    $sel = 'h-7 rounded-md border border-line bg-surface px-2 text-xs text-ink-2';
@endphp
<div class="ct-editor overflow-hidden rounded-xl border border-line-strong bg-surface focus-within:border-grena focus-within:ring-4 focus-within:ring-grena-tint">
    <div class="flex flex-wrap items-center gap-1 border-b border-line bg-subtle p-1.5" role="toolbar" aria-label="Formatação">
        <button type="button" data-cmd="bold" class="{{ $btn }}" title="Negrito"><span class="font-black">N</span></button>
        <button type="button" data-cmd="italic" class="{{ $btn }}" title="Itálico"><span class="italic">I</span></button>
        <button type="button" data-cmd="underline" class="{{ $btn }}" title="Sublinhado"><span class="underline">S</span></button>
        <button type="button" data-cmd="insertUnorderedList" class="{{ $btn }}" title="Lista com marcador">• Lista</button>
        <button type="button" data-cmd="insertOrderedList" class="{{ $btn }}" title="Lista numerada">1. Lista</button>
        <span class="mx-1 h-4 w-px bg-line-strong"></span>
        <select data-insert="token" class="{{ $sel }}" aria-label="Inserir variável"><option value="">Inserir variável…</option></select>
        @if($refs ?? true)
            <select data-insert="ref" class="{{ $sel }}" aria-label="Inserir remissão"><option value="">Inserir remissão…</option></select>
        @endif
    </div>
    <div class="ct-rich text-sm text-ink" contenteditable="true" data-placeholder="{{ $placeholder ?? 'Escreva o texto…' }}">{!! $value !!}</div>
    <textarea name="{{ $name }}" class="hidden" data-rich-source>{{ $value }}</textarea>
</div>
