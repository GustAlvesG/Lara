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

            <button type="button" data-action="fullscreen" title="Tela cheia" aria-pressed="false" class="rich-editor-btn rich-editor-btn-fullscreen">
                <svg class="rich-editor-icon-expand h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                </svg>
                <svg class="rich-editor-icon-collapse h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h4.5m0 0V4.5m0 4.5L3 3.75M9 15H4.5M9 15v4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5m-4.5 0v4.5m0-4.5l5.25 5.25" />
                </svg>
            </button>
        </div>

        <div
            class="rich-editor-content min-h-[220px] max-h-[480px] overflow-y-auto p-3 text-gray-900 dark:text-gray-100 focus:outline-none"
            contenteditable="true"
            data-rich-editor-content
        >{!! $value !!}</div>

        <textarea name="{{ $name }}" id="{{ $name }}" class="hidden rich-editor-source">{{ $value }}</textarea>
    </div>
@endif
