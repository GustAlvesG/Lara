@props([
    'name' => 'description',
    'value' => '',
    'readonly' => false,
])

@php
    // Sempre passa pela allow-list, mesmo em conteúdo já salvo: cobre linhas
    // antigas gravadas pelo CKEditor (que aceitava HTML bem mais amplo) e
    // qualquer registro que tenha chegado ao banco por fora do store().
    $value = \App\Support\HtmlSanitizer::clean($value);
@endphp

@if ($readonly)
    <div {{ $attributes->merge(['class' => 'info-rich-text text-gray-900 dark:text-gray-100']) }}>
        {!! $value !!}
    </div>
@else
    <div {{ $attributes->merge(['class' => 'rich-editor rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 overflow-hidden']) }} data-rich-editor>
        <div class="flex flex-wrap items-center gap-1 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-2" role="toolbar" aria-label="Formatação do texto">
            <button type="button" data-cmd="bold" title="Negrito" class="rich-editor-btn"><span class="font-bold">N</span></button>
            <button type="button" data-cmd="italic" title="Itálico" class="rich-editor-btn"><span class="italic">I</span></button>
            <button type="button" data-cmd="underline" title="Sublinhado" class="rich-editor-btn"><span class="underline">S</span></button>

            <span class="mx-1 h-5 w-px bg-gray-300 dark:bg-gray-600"></span>

            <button type="button" data-action="table" title="Inserir tabela" class="rich-editor-btn">Tabela</button>

            <span class="mx-1 h-5 w-px bg-gray-300 dark:bg-gray-600"></span>

            <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300 cursor-pointer" title="Cor de fundo do texto selecionado">
                Fundo
                <input type="color" data-action="bgcolor" value="#fff59d" class="h-6 w-7 cursor-pointer border-0 bg-transparent p-0">
            </label>
            <button type="button" data-action="clear-bgcolor" title="Remover cor de fundo" class="rich-editor-btn text-xs">Limpar cor</button>
        </div>

        <div
            class="rich-editor-content min-h-[220px] max-h-[480px] overflow-y-auto p-3 text-gray-900 dark:text-gray-100 focus:outline-none"
            contenteditable="true"
            data-rich-editor-content
        >{!! $value !!}</div>

        <textarea name="{{ $name }}" id="{{ $name }}" class="hidden rich-editor-source">{{ $value }}</textarea>
    </div>
@endif
