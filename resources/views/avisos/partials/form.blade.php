@php
    $aviso = $aviso ?? null;
    $currentPrivacy = old('privacy', $aviso?->privacy ?? 'setor');
    // Só lembretes ainda não enviados são editáveis. Os já enviados são
    // histórico (exibidos na tela do aviso) e não devem voltar como inputs,
    // senão o salvar os recriaria como pendentes e eles disparariam de novo.
    $existingLembretes = $aviso?->lembretes
        ->where('sent', false)
        ->map(fn($l) => ['remind_at' => $l->remind_at->format('Y-m-d\TH:i')])
        ->values()
        ->toArray() ?? [];

    $existingTags = old('tags', $aviso?->tags->pluck('name')->values()->toArray() ?? []);
@endphp

<div class="space-y-5">

    {{-- Título --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Título <span class="text-red-500">*</span>
        </label>
        <input type="text" name="title" maxlength="200" required
               value="{{ old('title', $aviso?->title) }}"
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-red-500 focus:ring-red-500"
               placeholder="Título curto e direto">
        @error('title')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Editor de Texto --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Conteúdo</label>
        <div class="flex gap-1 p-2 bg-gray-50 dark:bg-gray-700 border border-b-0 border-gray-300 dark:border-gray-600 rounded-t-lg">
            <button type="button" onclick="editorCmd('bold')"
                class="w-8 h-8 flex items-center justify-center rounded hover:bg-gray-200 dark:hover:bg-gray-600 font-bold text-gray-700 dark:text-gray-200 text-sm transition"
                title="Negrito"><b>B</b></button>
            <button type="button" onclick="editorCmd('italic')"
                class="w-8 h-8 flex items-center justify-center rounded hover:bg-gray-200 dark:hover:bg-gray-600 italic text-gray-700 dark:text-gray-200 text-sm transition"
                title="Itálico"><i>I</i></button>
            <button type="button" onclick="editorCmd('underline')"
                class="w-8 h-8 flex items-center justify-center rounded hover:bg-gray-200 dark:hover:bg-gray-600 underline text-gray-700 dark:text-gray-200 text-sm transition"
                title="Sublinhado"><u>U</u></button>
        </div>
        <div id="aviso-editor" contenteditable="true"
             class="min-h-32 p-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-b-lg text-gray-800 dark:text-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500"
             style="line-height: 1.6;">
            {!! old('content', $aviso?->content) !!}
        </div>
        <input type="hidden" name="content" id="aviso-content-input">
    </div>

    {{-- Privacidade --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Visibilidade</label>
        <div class="flex gap-2 flex-wrap">
            @foreach(['pessoa' => ['label' => 'Pessoal', 'desc' => 'Só você', 'icon' => '🔒'], 'setor' => ['label' => 'Setor', 'desc' => 'Seu setor', 'icon' => '👥'], 'publico' => ['label' => 'Público', 'desc' => 'Todos', 'icon' => '🌐']] as $value => $opt)
                <label class="flex-1 min-w-[100px] cursor-pointer">
                    <input type="radio" name="privacy" value="{{ $value }}"
                           {{ $currentPrivacy === $value ? 'checked' : '' }}
                           class="sr-only peer">
                    <div class="flex flex-col items-center p-3 rounded-lg border-2 border-gray-200 dark:border-gray-600
                                peer-checked:border-red-700 peer-checked:bg-red-50 dark:peer-checked:bg-red-900/20 dark:peer-checked:border-red-600
                                hover:border-gray-300 dark:hover:border-gray-500 transition text-center">
                        <span class="text-lg">{{ $opt['icon'] }}</span>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200 mt-0.5">{{ $opt['label'] }}</span>
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $opt['desc'] }}</span>
                    </div>
                </label>
            @endforeach
        </div>
        @error('privacy')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Imagem --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Imagem (opcional)</label>
        <input type="file" name="image" accept="image/*"
               class="block w-full text-sm text-gray-500 dark:text-gray-400
                      file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                      file:text-sm file:font-medium file:bg-red-50 dark:file:bg-red-900/30
                      file:text-red-800 dark:file:text-red-300 hover:file:bg-red-100 cursor-pointer">
    </div>

    {{-- Tags --}}
    <div x-data="tagsInput({{ json_encode(array_values($existingTags)) }})">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Tags
        </label>

        <div class="flex flex-wrap items-center gap-2 p-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus-within:border-red-500 focus-within:ring-1 focus-within:ring-red-500 transition"
             @click="$refs.tagField.focus()">
            <template x-for="(tag, index) in items" :key="index">
                <span class="flex items-center gap-1 px-2 py-1 text-xs rounded-full bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300">
                    <span x-text="tag"></span>
                    <input type="hidden" name="tags[]" :value="tag">
                    <button type="button" @click="remove(index)" class="hover:text-red-900 dark:hover:text-red-100" title="Remover tag">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </span>
            </template>

            <input type="text" x-ref="tagField" x-model="draft"
                   @keydown.enter.prevent="add()"
                   @keydown.,.prevent="add()"
                   @keydown.backspace="if (draft === '') removeLast()"
                   @blur="add()"
                   placeholder="Digite e tecle Enter…"
                   class="flex-1 min-w-[120px] border-0 p-0 bg-transparent text-sm text-gray-800 dark:text-gray-200 focus:ring-0 placeholder-gray-400">
        </div>
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
            Tags inexistentes são criadas automaticamente. Não diferencia maiúsculas de minúsculas.
        </p>
    </div>

    {{-- Lembretes múltiplos --}}
    <div x-data="lembretes({{ json_encode(count($existingLembretes) ? $existingLembretes : []) }})">
        <div class="flex justify-between items-center mb-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Lembretes
            </label>
            <button type="button" @click="add()"
                class="text-xs text-red-700 dark:text-red-400 hover:underline flex items-center gap-1">
                + Adicionar lembrete
            </button>
        </div>

        <div class="space-y-2">
            <template x-for="(item, index) in items" :key="index">
                <div class="flex gap-2 items-center">
                    <input type="datetime-local"
                           :name="`lembretes[${index}][remind_at]`"
                           x-model="item.remind_at"
                           class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm">
                    <button type="button" @click="remove(index)"
                        class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded transition"
                        title="Remover lembrete">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        <p x-show="items.length === 0" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
            Nenhum lembrete agendado. Clique em "+ Adicionar lembrete".
        </p>
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
            Notificações serão enviadas às datas e horas definidas.
        </p>
    </div>

    {{-- Expiração --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Data de expiração (opcional)
        </label>
        <input type="date" name="expires_at"
               value="{{ old('expires_at', $aviso?->expires_at?->format('Y-m-d')) }}"
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm">
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">O aviso é arquivado após esta data</p>
        @error('expires_at')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

</div>
