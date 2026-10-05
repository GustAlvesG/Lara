@php
    $aviso = $aviso ?? null;
    $currentPrivacy = old('privacy', $aviso?->privacy ?? 'setor');
    $allUsers = $users ?? collect();
    $selectedUserIds = old('user_ids', $aviso?->users?->pluck('id')->all() ?? []);
    // Só lembretes ainda não enviados são editáveis. Os já enviados são
    // histórico (exibidos na tela do aviso) e não devem voltar como inputs,
    // senão o salvar os recriaria como pendentes e eles disparariam de novo.
    $existingLembretes = $aviso?->lembretes
        ->where('sent', false)
        ->map(fn($l) => ['remind_at' => $l->remind_at->format('Y-m-d\TH:i')])
        ->values()
        ->toArray() ?? [];

    $existingTags = old('tags', $aviso?->tags->pluck('name')->values()->toArray() ?? []);

    $privacyOptions = [
        'pessoa' => ['label' => 'Pessoal', 'desc' => 'Só você', 'icon' => 'lock'],
        'setor' => ['label' => 'Setor', 'desc' => 'Seu setor', 'icon' => 'users'],
        'publico' => ['label' => 'Público', 'desc' => 'Todos', 'icon' => 'globe'],
        'grupo' => ['label' => 'Grupo', 'desc' => 'Selecionar', 'icon' => 'user-plus'],
    ];

    $label = 'mb-1.5 block text-sm font-bold text-ink';
    $hint = 'mt-1.5 text-xs text-ink-3';
    $error = 'mt-1 text-xs font-semibold text-danger';
    $field = 'w-full h-11 px-3.5 rounded-xl border border-line-strong bg-surface text-ink placeholder:text-ink-3 shadow-none transition focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $chip = 'inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold';
    $chipBox = 'flex min-h-11 flex-wrap items-center gap-2 rounded-xl border border-line-strong bg-surface p-2 transition focus-within:border-grena focus-within:ring-4 focus-within:ring-grena-tint';
    $editorBtn = 'grid h-8 w-8 place-items-center rounded-lg text-sm text-ink-2 transition hover:bg-line hover:text-ink';
@endphp

<div class="flex flex-col gap-5" x-data="{ privacy: '{{ $currentPrivacy }}' }">

    {{-- Título --}}
    <div>
        <label for="aviso-title" class="{{ $label }}">Título <span class="text-danger">*</span></label>
        <input type="text" id="aviso-title" name="title" maxlength="200" required
               value="{{ old('title', $aviso?->title) }}"
               class="{{ $field }}"
               placeholder="Título curto e direto">
        @error('title')
            <p class="{{ $error }}">{{ $message }}</p>
        @enderror
    </div>

    {{-- Editor de texto --}}
    <div>
        <span class="{{ $label }}">Conteúdo</span>
        <div class="overflow-hidden rounded-xl border border-line-strong bg-surface transition focus-within:border-grena focus-within:ring-4 focus-within:ring-grena-tint">
            <div class="flex gap-1 border-b border-line bg-subtle p-2" role="toolbar" aria-label="Formatação do texto">
                <button type="button" onclick="editorCmd('bold')" class="{{ $editorBtn }} font-bold" title="Negrito"><b>N</b></button>
                <button type="button" onclick="editorCmd('italic')" class="{{ $editorBtn }} italic" title="Itálico"><i>I</i></button>
                <button type="button" onclick="editorCmd('underline')" class="{{ $editorBtn }} underline" title="Sublinhado"><u>S</u></button>
            </div>
            <div id="aviso-editor" contenteditable="true"
                 class="min-h-32 p-3 text-[15px] leading-relaxed text-ink focus:outline-none">
                {!! old('content', $aviso?->content) !!}
            </div>
        </div>
        <input type="hidden" name="content" id="aviso-content-input">
    </div>

    {{-- Privacidade --}}
    <div>
        <span class="{{ $label }}">Visibilidade</span>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($privacyOptions as $value => $opt)
                <label class="cursor-pointer">
                    <input type="radio" name="privacy" value="{{ $value }}"
                           {{ $currentPrivacy === $value ? 'checked' : '' }}
                           @change="privacy = '{{ $value }}'"
                           class="peer sr-only">
                    <div class="flex flex-col items-center gap-0.5 rounded-2xl border-[1.5px] border-line bg-surface p-3 text-center transition hover:border-line-strong peer-checked:border-grena peer-checked:bg-grena-tint peer-focus-visible:ring-4 peer-focus-visible:ring-grena-tint">
                        <x-icon :name="$opt['icon']" class="h-5 w-5 text-ink-2" />
                        <span class="mt-0.5 text-sm font-bold text-ink">{{ $opt['label'] }}</span>
                        <span class="text-xs text-ink-3">{{ $opt['desc'] }}</span>
                    </div>
                </label>
            @endforeach
        </div>

        {{-- Seletor de usuários (visível apenas quando privacy = grupo) --}}
        <div x-show="privacy === 'grupo'" x-transition
             x-data="usersSelector({{ $allUsers->toJson() }}, {{ json_encode($selectedUserIds) }})"
             class="mt-3">
            <span class="{{ $label }}">Destinatários <span class="text-danger">*</span></span>

            <div class="relative">
                <div class="{{ $chipBox }}" @click="$refs.userField.focus()">
                    <template x-for="user in selected" :key="user.id">
                        <span class="{{ $chip }} bg-area-externos text-area-externos-ink">
                            <input type="hidden" name="user_ids[]" :value="user.id">
                            <span x-text="user.name"></span>
                            <button type="button" @click.stop="remove(user)" class="opacity-70 hover:opacity-100" title="Remover">
                                <x-icon name="x" class="h-3 w-3" />
                            </button>
                        </span>
                    </template>

                    <input type="text" x-ref="userField" x-model="search" @focus="open = true" @click.outside="open = false"
                           placeholder="Buscar pessoa para adicionar…"
                           autocomplete="off"
                           class="min-w-[160px] flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-3 focus:ring-0">
                </div>

                <div x-show="open && filtered.length > 0" x-cloak
                     class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-2xl border border-line bg-surface p-1 shadow-pop">
                    <template x-for="user in filtered" :key="user.id">
                        <button type="button" @click="add(user); open = filtered.length > 0"
                                class="w-full rounded-xl px-3 py-2 text-left text-sm text-ink transition hover:bg-subtle">
                            <span x-text="user.name"></span>
                        </button>
                    </template>
                </div>
            </div>

            <p x-show="selected.length === 0" class="{{ $error }}">Selecione ao menos um destinatário.</p>
            @error('user_ids')
                <p class="{{ $error }}">{{ $message }}</p>
            @enderror
        </div>
        @error('privacy')
            <p class="{{ $error }}">{{ $message }}</p>
        @enderror
    </div>

    {{-- Leitura obrigatória: só coordenador de setor pode exigir. Para os
         demais o campo nem aparece (e o servidor ignora, se vier). --}}
    @if (auth()->user()->isCoordinator())
        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-line-strong p-4 transition hover:border-ink-3 has-[:checked]:border-grena has-[:checked]:bg-grena-tint/50">
            <input type="hidden" name="mandatory" value="0">
            <input type="checkbox" name="mandatory" value="1" @checked(old('mandatory', $aviso?->mandatory))
                   class="mt-0.5 rounded border-line-strong text-grena focus:ring-grena-tint">
            <span>
                <span class="block text-sm font-bold text-ink">Leitura obrigatória</span>
                <span class="mt-0.5 block text-sm text-ink-2">
                    Quem recebe o aviso vê este texto em tela cheia ao entrar no sistema e só segue depois de confirmar que leu.
                    A confirmação fica registrada com nome, data e hora.
                </span>
            </span>
        </label>
    @endif

    {{-- Imagem --}}
    <div>
        <label for="aviso-image" class="{{ $label }}">Imagem (opcional)</label>
        <x-input-file id="aviso-image" name="image" accept="image/*" />
    </div>

    {{-- Tags --}}
    <div x-data="tagsInput({{ json_encode(array_values($existingTags)) }})">
        <span class="{{ $label }}">Tags</span>

        <div class="{{ $chipBox }}" @click="$refs.tagField.focus()">
            <template x-for="(tag, index) in items" :key="index">
                <span class="{{ $chip }} bg-area-info text-area-info-ink">
                    <span x-text="'#' + tag"></span>
                    <input type="hidden" name="tags[]" :value="tag">
                    <button type="button" @click.stop="remove(index)" class="opacity-70 hover:opacity-100" title="Remover tag">
                        <x-icon name="x" class="h-3 w-3" />
                    </button>
                </span>
            </template>

            <input type="text" x-ref="tagField" x-model="draft"
                   @keydown.enter.prevent="add()"
                   @keydown.,.prevent="add()"
                   @keydown.backspace="if (draft === '') removeLast()"
                   @blur="add()"
                   aria-label="Adicionar tag"
                   placeholder="Digite e tecle Enter…"
                   class="min-w-[120px] flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-3 focus:ring-0">
        </div>
        <p class="{{ $hint }}">Tags inexistentes são criadas automaticamente. Não diferencia maiúsculas de minúsculas.</p>
    </div>

    {{-- Lembretes múltiplos --}}
    <div x-data="lembretes({{ json_encode(count($existingLembretes) ? $existingLembretes : []) }})">
        <div class="mb-1.5 flex items-center justify-between">
            <span class="text-sm font-bold text-ink">Lembretes</span>
            <button type="button" @click="add()" class="inline-flex items-center gap-1 text-xs font-bold text-grena-ink hover:underline">
                <x-icon name="plus" class="h-3.5 w-3.5" /> Adicionar lembrete
            </button>
        </div>

        <div class="flex flex-col gap-2">
            <template x-for="(item, index) in items" :key="index">
                <div class="flex items-center gap-2">
                    <input type="datetime-local"
                           :name="`lembretes[${index}][remind_at]`"
                           x-model="item.remind_at"
                           aria-label="Data e hora do lembrete"
                           class="{{ $field }} flex-1 font-mono text-sm">
                    <button type="button" @click="remove(index)" title="Remover lembrete"
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger">
                        <x-icon name="x" />
                    </button>
                </div>
            </template>
        </div>

        <p x-show="items.length === 0" class="{{ $hint }}">Nenhum lembrete agendado. Use "Adicionar lembrete".</p>
        <p class="{{ $hint }}">As notificações são enviadas nas datas e horas definidas.</p>
    </div>

    {{-- Expiração --}}
    <div>
        <label for="aviso-expires" class="{{ $label }}">Data de expiração (opcional)</label>
        <input type="date" id="aviso-expires" name="expires_at"
               value="{{ old('expires_at', $aviso?->expires_at?->format('Y-m-d')) }}"
               class="{{ $field }} font-mono text-sm sm:max-w-xs">
        <p class="{{ $hint }}">O aviso é arquivado depois desta data.</p>
        @error('expires_at')
            <p class="{{ $error }}">{{ $message }}</p>
        @enderror
    </div>

</div>
