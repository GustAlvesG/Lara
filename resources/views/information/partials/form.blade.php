@php
    /**
     * Formulário único de criação e edição, em duas colunas:
     *   esquerda  -> imagem (só exibição/preview), nome e descrição
     *   direita   -> tags e todos os demais campos
     *
     * Os dois fluxos postam para information.store: é a presença de
     * information_id que faz o controller gravar uma nova versão em vez de
     * criar uma informação nova.
     *
     * Os valores iniciais vêm de old() quando a request voltou com erro de
     * validação, e do $info quando é edição.
     */
    $isEdit = isset($info);

    // Blocos repetíveis: reconstrói de old() para não perder o que o usuário
    // digitou quando a validação falha.
    $oldNames = old('name_price');
    if (is_array($oldNames)) {
        $prices = [];
        foreach ($oldNames as $i => $value) {
            $prices[] = [
                'name' => $value ?? '',
                'associated' => old('price_associated.' . $i, ''),
                'not_associated' => old('price_not_associated.' . $i, ''),
            ];
        }
    } else {
        $prices = $isEdit ? $info->price_rows : [];
    }

    $oldResponsibles = old('responsible');
    if (is_array($oldResponsibles)) {
        $responsibles = [];
        foreach ($oldResponsibles as $i => $value) {
            $responsibles[] = [
                'name' => $value ?? '',
                'contact' => old('responsible_contact.' . $i, ''),
            ];
        }
    } else {
        $responsibles = $isEdit ? $info->responsible_rows : [];
    }

    $oldDays = old('day');
    if (is_array($oldDays)) {
        $schedules = [];
        foreach ($oldDays as $i => $value) {
            $schedules[] = [
                'day' => $value ?? '',
                'start' => old('start_hour.' . $i, ''),
                'end' => old('end_hour.' . $i, ''),
            ];
        }
    } else {
        $schedules = $isEdit ? $info->schedule_rows : [];
    }

    $tags = old('tags', $isEdit ? $info->tags->pluck('name')->values()->all() : []);
    $currentImage = $isEdit ? $info->image : null;

    $toggles = [
        'image' => (bool) $currentImage,
        'fee' => filled(old('fee', $isEdit ? $info->fee : null)),
        'prices' => count($prices) > 0,
        'schedules' => count($schedules) > 0,
        'responsibles' => count($responsibles) > 0,
        'slots' => filled(old('slots', $isEdit ? $info->slots : null)),
        'status' => filled(old('status', $isEdit ? $info->status : null)),
        'location' => filled(old('location', $isEdit ? $info->location : null)),
    ];

    // Campos simples de um valor só, renderizados pelo mesmo laço.
    $simpleFields = [
        ['key' => 'fee', 'name' => 'fee', 'label' => 'Taxa de Matrícula', 'type' => 'number', 'attrs' => 'min="0" max="99999.99" step="0.01"', 'placeholder' => '0,00'],
        ['key' => 'slots', 'name' => 'slots', 'label' => 'Número de Vagas', 'type' => 'number', 'attrs' => 'min="0"', 'placeholder' => '0'],
        ['key' => 'status', 'name' => 'status', 'label' => 'Status', 'type' => 'text', 'attrs' => 'maxlength="255"', 'placeholder' => 'Ex.: Inscrições abertas'],
        ['key' => 'location', 'name' => 'location', 'label' => 'Localização', 'type' => 'text', 'attrs' => 'maxlength="255"', 'placeholder' => 'Ex.: Piscina coberta'],
    ];

    $inputClass = 'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white dark:bg-gray-900 text-gray-900 dark:text-white';
    $cardClass = 'rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800';

    // Renderizadas como HTML estático (e não por x-for) para que o x-model do
    // select encontre a opção salva já no primeiro render.
    $dayOptions = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira',
        'Sexta-feira', 'Sábado', 'Dias de Semana', 'Fim de Semana', 'Todos os dias'];
@endphp

<form
    action="{{ $route }}"
    method="POST"
    enctype="multipart/form-data"
    class="space-y-6"
    x-data="informationForm(@js([
        'toggles' => $toggles,
        'prices' => $prices,
        'responsibles' => $responsibles,
        'schedules' => $schedules,
        'tags' => array_values($tags),
        'hasImage' => (bool) $currentImage,
        'imageUrl' => $currentImage ? asset('images/' . $currentImage) : null,
        'title' => old('name', $isEdit ? $info->name : ''),
        'minTags' => 3,
    ]))"
>
    @csrf

    @if ($isEdit)
        <input type="hidden" name="information_id" value="{{ $info->information_id }}">
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/30">
            <p class="mb-1 text-sm font-semibold text-red-800 dark:text-red-300">
                Corrija os itens abaixo antes de salvar:
            </p>
            <x-input-error :messages="$errors->all()" class="text-red-700 dark:text-red-300" />
        </div>
    @endif

    {{-- 10 colunas: 6/4 = 60% / 40%. --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-10">

        {{-- ---------- Coluna esquerda (60%): imagem, título e descrição ---------- --}}
        <div class="space-y-6 lg:col-span-6">
            <section class="{{ $cardClass }}">
                <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-gray-100">
                    Identificação
                </h3>

                {{-- Imagem aqui é só exibição; o upload fica na coluna da direita. --}}
                <img :src="previewUrl()"
                     alt="Pré-visualização da imagem"
                     class="mb-4 h-48 w-full rounded-lg border border-gray-200 object-cover dark:border-gray-700">

                <div class="space-y-4">
                    <div>
                        <x-input-label for="name">Nome <span class="text-red-600">*</span></x-input-label>
                        <input type="text" name="name" id="name" maxlength="255" required
                               x-model="title"
                               class="{{ $inputClass }} mt-1">
                    </div>

                    <div>
                        <x-input-label for="description">Descrição <span class="text-red-600">*</span></x-input-label>
                        <div class="mt-1">
                            <x-rich-editor name="description" :value="old('description', $isEdit ? $info->description : '')" />
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- ---------- Coluna direita (40%): tags e demais campos ---------- --}}
        <div class="space-y-6 lg:col-span-4">

            {{-- Tags --}}
            <section class="{{ $cardClass }}">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                    Tags <span class="text-red-600">*</span>
                </h3>
                <p class="mb-3 mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Mínimo de 3. Digite e tecle Enter (ou vírgula) para adicionar.
                </p>

                <div class="info-tags-box" @click="$refs.tagField.focus()">
                    <template x-for="(tag, index) in tags" :key="tag">
                        <span class="info-tag-chip">
                            <span x-text="'#' + tag"></span>
                            <input type="hidden" name="tags[]" :value="tag">
                            <button type="button" @click.stop="removeTag(index)" title="Remover tag">&times;</button>
                        </span>
                    </template>

                    <input type="text" x-ref="tagField" x-model="tagDraft"
                           @keydown.enter.prevent="addTag()"
                           @keydown.,.prevent="addTag()"
                           @keydown.backspace="if (tagDraft === '') removeLastTag()"
                           @blur="addTag()"
                           maxlength="50"
                           aria-label="Adicionar tag"
                           placeholder="Digite e tecle Enter…">
                </div>

                <p class="mt-2 text-xs" x-show="tagsMissing() > 0"
                   :class="'text-amber-600 dark:text-amber-400'">
                    Faltam <span x-text="tagsMissing()"></span>
                    <span x-text="tagsMissing() === 1 ? 'tag' : 'tags'"></span> para atingir o mínimo.
                </p>
                <p class="mt-2 text-xs text-green-600 dark:text-green-400" x-show="tagsMissing() === 0" x-cloak>
                    <span x-text="tags.length"></span> tags cadastradas.
                </p>
            </section>

            {{-- Campos opcionais de valor único --}}
            <section class="{{ $cardClass }}">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Detalhes adicionais</h3>
                <p class="mb-4 mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Ative apenas o que se aplica. O que ficar desativado não é salvo.
                </p>

                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    {{-- Imagem (upload) --}}
                    <div class="py-4 first:pt-0">
                        <label class="info-switch">
                            <input type="checkbox" x-model="toggles.image">
                            <span class="info-switch-track"></span>
                            <span class="info-switch-label">Imagem</span>
                        </label>

                        <template x-if="toggles.image">
                            <div class="mt-3 space-y-3">
                                @if ($currentImage)
                                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                        <input type="checkbox" name="remove_image" value="1" x-model="removeImage"
                                               class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                        Remover a imagem atual
                                    </label>
                                @endif

                                <div x-show="!removeImage">
                                    <x-input-label for="image">
                                        {{ $currentImage ? 'Substituir imagem' : 'Selecionar imagem' }}
                                    </x-input-label>
                                    <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/gif"
                                           @change="onImagePicked($event)"
                                           class="{{ $inputClass }} mt-1 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1 file:text-sm dark:file:bg-gray-700 dark:file:text-gray-200">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">JPG, PNG ou GIF, até 4 MB.</p>
                                </div>
                            </div>
                        </template>
                    </div>

                    @foreach ($simpleFields as $field)
                        <div class="py-4">
                            <label class="info-switch">
                                <input type="checkbox" x-model="toggles.{{ $field['key'] }}">
                                <span class="info-switch-track"></span>
                                <span class="info-switch-label">{{ $field['label'] }}</span>
                            </label>

                            <template x-if="toggles.{{ $field['key'] }}">
                                <div class="mt-3">
                                    <input type="{{ $field['type'] }}" {!! $field['attrs'] !!}
                                           name="{{ $field['name'] }}" id="field_{{ $field['name'] }}"
                                           aria-label="{{ $field['label'] }}"
                                           placeholder="{{ $field['placeholder'] }}"
                                           value="{{ old($field['name'], $isEdit ? $info->{$field['name']} : '') }}"
                                           class="{{ $inputClass }}">
                                </div>
                            </template>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Preços --}}
            <section class="{{ $cardClass }}">
                <label class="info-switch">
                    <input type="checkbox" x-model="toggles.prices" @change="onToggle('prices')">
                    <span class="info-switch-track"></span>
                    <span class="info-switch-label">Preços (Sócio / Não Sócio)</span>
                </label>

                <p class="mb-3 mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="toggles.prices" x-cloak>
                    Use as setas para ordenar. <strong>O primeiro preço da lista é o que aparece no card</strong> da listagem.
                </p>

                <template x-if="toggles.prices">
                    <div class="mt-4">
                        <template x-for="(row, index) in prices" :key="row._id">
                          <div class="info-repeat-item">
                            <span class="info-featured-badge" x-show="index === 0">★ Exibido no card</span>
                            <div class="info-repeat-row">
                                <div class="info-repeat-fields">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400"
                                               x-text="'Título #' + (index + 1)"></label>
                                        <input type="text" name="name_price[]" x-model="row.name" maxlength="255"
                                               placeholder="Ex.: Mensalidade" aria-label="Título do preço"
                                               class="{{ $inputClass }} mt-1">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">R$ Sócio</label>
                                        <input type="number" min="0" max="99999.99" step="0.01" name="price_associated[]"
                                               x-model="row.associated" placeholder="0,00" aria-label="Preço sócio"
                                               class="{{ $inputClass }} mt-1">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">R$ Não Sócio</label>
                                        <input type="number" min="0" max="99999.99" step="0.01" name="price_not_associated[]"
                                               x-model="row.not_associated" placeholder="0,00" aria-label="Preço não sócio"
                                               class="{{ $inputClass }} mt-1">
                                    </div>
                                </div>
                                <div class="info-row-actions">
                                    <button type="button" class="info-move-btn" title="Mover para cima"
                                            :disabled="index === 0"
                                            @click="moveRow('prices', index, -1)">&uarr;</button>
                                    <button type="button" class="info-move-btn" title="Mover para baixo"
                                            :disabled="index === prices.length - 1"
                                            @click="moveRow('prices', index, 1)">&darr;</button>
                                    <button type="button" class="info-remove-btn" title="Remover este preço"
                                            @click="removeRow('prices', index)">&times;</button>
                                </div>
                            </div>
                          </div>
                        </template>

                        <button type="button" @click="addRow('prices')"
                                class="mt-3 inline-flex items-center gap-1 rounded-lg border border-dashed border-gray-400 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                            + Adicionar preço
                        </button>
                    </div>
                </template>
            </section>

            {{-- Dias e horários --}}
            <section class="{{ $cardClass }}">
                <label class="info-switch">
                    <input type="checkbox" x-model="toggles.schedules" @change="onToggle('schedules')">
                    <span class="info-switch-track"></span>
                    <span class="info-switch-label">Dias e Horários</span>
                </label>

                <template x-if="toggles.schedules">
                    <div class="mt-4">
                        <template x-for="(row, index) in schedules" :key="row._id">
                          <div class="info-repeat-item">
                            <div class="info-repeat-row">
                                <div class="info-repeat-fields">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Dia</label>
                                        <select name="day[]" x-model="row.day" aria-label="Dia"
                                                class="{{ $inputClass }} mt-1">
                                            <option value="#">Selecione uma opção</option>
                                            <template x-if="legacyDay(row.day)">
                                                <option :value="row.day" x-text="row.day"></option>
                                            </template>
                                            @foreach ($dayOptions as $dayOption)
                                                <option value="{{ $dayOption }}">{{ $dayOption }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Início</label>
                                        <input type="time" name="start_hour[]" x-model="row.start"
                                               aria-label="Horário de início" class="{{ $inputClass }} mt-1">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Fim</label>
                                        <input type="time" name="end_hour[]" x-model="row.end"
                                               aria-label="Horário de fim" class="{{ $inputClass }} mt-1">
                                    </div>
                                </div>
                                <div class="info-row-actions">
                                    <button type="button" class="info-move-btn" title="Mover para cima"
                                            :disabled="index === 0"
                                            @click="moveRow('schedules', index, -1)">&uarr;</button>
                                    <button type="button" class="info-move-btn" title="Mover para baixo"
                                            :disabled="index === schedules.length - 1"
                                            @click="moveRow('schedules', index, 1)">&darr;</button>
                                    <button type="button" class="info-remove-btn" title="Remover este horário"
                                            @click="removeRow('schedules', index)">&times;</button>
                                </div>
                            </div>
                          </div>
                        </template>

                        <button type="button" @click="addRow('schedules')"
                                class="mt-3 inline-flex items-center gap-1 rounded-lg border border-dashed border-gray-400 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                            + Adicionar dia
                        </button>
                    </div>
                </template>
            </section>

            {{-- Responsáveis --}}
            <section class="{{ $cardClass }}">
                <label class="info-switch">
                    <input type="checkbox" x-model="toggles.responsibles" @change="onToggle('responsibles')">
                    <span class="info-switch-track"></span>
                    <span class="info-switch-label">Responsáveis</span>
                </label>

                <p class="mb-3 mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="toggles.responsibles" x-cloak>
                    <strong>O primeiro responsável é o que aparece no card</strong>, e o telefone dele vira o link de WhatsApp.
                </p>

                <template x-if="toggles.responsibles">
                    <div class="mt-4">
                        <template x-for="(row, index) in responsibles" :key="row._id">
                          <div class="info-repeat-item">
                            <span class="info-featured-badge" x-show="index === 0">★ Exibido no card</span>
                            <div class="info-repeat-row">
                                <div class="info-repeat-fields">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400"
                                               x-text="'Responsável #' + (index + 1)"></label>
                                        <input type="text" name="responsible[]" x-model="row.name" maxlength="255"
                                               placeholder="Nome" aria-label="Nome do responsável"
                                               class="{{ $inputClass }} mt-1">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Telefone (WhatsApp)</label>
                                        <input type="text" name="responsible_contact[]" x-model="row.contact" maxlength="50"
                                               placeholder="(00) 00000-0000" aria-label="Telefone do responsável"
                                               class="{{ $inputClass }} mt-1">
                                    </div>
                                </div>
                                <div class="info-row-actions">
                                    <button type="button" class="info-move-btn" title="Mover para cima"
                                            :disabled="index === 0"
                                            @click="moveRow('responsibles', index, -1)">&uarr;</button>
                                    <button type="button" class="info-move-btn" title="Mover para baixo"
                                            :disabled="index === responsibles.length - 1"
                                            @click="moveRow('responsibles', index, 1)">&darr;</button>
                                    <button type="button" class="info-remove-btn" title="Remover este responsável"
                                            @click="removeRow('responsibles', index)">&times;</button>
                                </div>
                            </div>
                          </div>
                        </template>

                        <button type="button" @click="addRow('responsibles')"
                                class="mt-3 inline-flex items-center gap-1 rounded-lg border border-dashed border-gray-400 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                            + Adicionar responsável
                        </button>
                    </div>
                </template>
            </section>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-3">
        <x-secondary-button-a href="{{ $isEdit ? route('information.show', $info->id) : route('information.index') }}">
            Cancelar
        </x-secondary-button-a>
        <x-primary-button type="submit">
            {{ $isEdit ? 'Salvar nova versão' : 'Criar informação' }}
        </x-primary-button>
    </div>
</form>
