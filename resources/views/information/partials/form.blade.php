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

    $inputClass = 'w-full h-11 px-3.5 rounded-xl border border-line-strong bg-surface text-ink placeholder:text-ink-3 shadow-none transition focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $fileClass = 'w-full rounded-xl border border-line-strong bg-surface text-sm text-ink-2 shadow-none focus:border-grena focus:ring-4 focus:ring-grena-tint file:mr-3 file:h-10 file:cursor-pointer file:rounded-l-xl file:border-0 file:bg-grena-tint file:px-4 file:font-bold file:text-grena-ink';
    $cardClass = 'rounded-card bg-surface p-5 shadow-card';
    $cardTitle = 'font-display text-base font-semibold tracking-tight text-ink';
    $hint = 'text-sm text-ink-2';
    $miniLabel = 'block text-xs font-bold text-ink-2';
    $addButton = 'mt-3 inline-flex items-center gap-1.5 rounded-full border-[1.5px] border-dashed border-line-strong px-3.5 py-2 text-sm font-bold text-ink-2 transition hover:border-grena hover:text-grena-ink';

    // Renderizadas como HTML estático (e não por x-for) para que o x-model do
    // select encontre a opção salva já no primeiro render.
    $dayOptions = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira',
        'Sexta-feira', 'Sábado', 'Dias de Semana', 'Fim de Semana', 'Todos os dias'];
@endphp

<form
    action="{{ $route }}"
    method="POST"
    enctype="multipart/form-data"
    class="flex flex-col gap-4"
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
        <div class="rounded-2xl bg-danger-soft p-4" role="alert">
            <p class="mb-1 text-sm font-bold text-danger">
                Corrija os itens abaixo antes de salvar:
            </p>
            <x-input-error :messages="$errors->all()" />
        </div>
    @endif

    {{-- 10 colunas: 6/4 = 60% / 40%. --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-10">

        {{-- ---------- Coluna esquerda (60%): imagem, título e descrição ---------- --}}
        <div class="flex flex-col gap-4 lg:col-span-6">
            <section class="{{ $cardClass }}">
                <h3 class="{{ $cardTitle }} mb-4">Identificação</h3>

                {{-- Imagem aqui é só exibição; o upload fica na coluna da direita.
                     Sem imagem, o mesmo substituto da listagem (iniciais na cor
                     do InfoClube), acompanhando o nome digitado. --}}
                <div class="relative mb-4 grid aspect-[21/9] place-items-center overflow-hidden rounded-2xl"
                     style="{{ \App\View\AreaColor::style('info') }}">
                    <span class="grid h-[72px] w-24 place-items-center rounded-2xl bg-surface font-display text-2xl font-bold tracking-tight shadow-card"
                          style="color: rgb(var(--ci))" x-text="initials()" aria-hidden="true"></span>
                    <template x-if="previewUrl()">
                        <img :src="previewUrl()" alt="Pré-visualização da imagem" class="absolute inset-0 h-full w-full object-cover">
                    </template>
                </div>

                <div class="space-y-4">
                    <div>
                        <x-input-label for="name">Nome <span class="text-danger">*</span></x-input-label>
                        <input type="text" name="name" id="name" maxlength="255" required
                               x-model="title"
                               class="{{ $inputClass }} mt-1">
                    </div>

                    <div>
                        <x-input-label for="description">Descrição <span class="text-danger">*</span></x-input-label>
                        <div class="mt-1">
                            <x-rich-editor name="description" :value="old('description', $isEdit ? $info->description : '')" />
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- ---------- Coluna direita (40%): tags e demais campos ---------- --}}
        <div class="flex flex-col gap-4 lg:col-span-4">

            {{-- Tags --}}
            <section class="{{ $cardClass }}">
                <h3 class="{{ $cardTitle }}">
                    Tags <span class="text-danger">*</span>
                </h3>
                <p class="{{ $hint }} mb-3 mt-1">
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

                <p class="mt-2 text-xs font-semibold text-warn" x-show="tagsMissing() > 0">
                    Faltam <span x-text="tagsMissing()"></span>
                    <span x-text="tagsMissing() === 1 ? 'tag' : 'tags'"></span> para atingir o mínimo.
                </p>
                <p class="mt-2 text-xs font-semibold text-ok" x-show="tagsMissing() === 0" x-cloak>
                    <span x-text="tags.length"></span> tags cadastradas.
                </p>
            </section>

            {{-- Campos opcionais de valor único --}}
            <section class="{{ $cardClass }}">
                <h3 class="{{ $cardTitle }}">Detalhes adicionais</h3>
                <p class="{{ $hint }} mb-4 mt-1">
                    Ative apenas o que se aplica. O que ficar desativado não é salvo.
                </p>

                <div class="divide-y divide-line">
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
                                    <label class="flex items-center gap-2 text-sm text-ink-2">
                                        <input type="checkbox" name="remove_image" value="1" x-model="removeImage"
                                               class="rounded border-line-strong text-danger focus:ring-danger-soft">
                                        Remover a imagem atual
                                    </label>
                                @endif

                                <div x-show="!removeImage">
                                    <x-input-label for="image">
                                        {{ $currentImage ? 'Substituir imagem' : 'Selecionar imagem' }}
                                    </x-input-label>
                                    <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/gif"
                                           @change="onImagePicked($event)"
                                           class="{{ $fileClass }} mt-1">
                                    <p class="mt-1 text-xs text-ink-3">JPG, PNG ou GIF, até 4 MB.</p>
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

                <p class="{{ $hint }} mb-3 mt-1" x-show="toggles.prices" x-cloak>
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
                                        <label class="{{ $miniLabel }}"
                                               x-text="'Título #' + (index + 1)"></label>
                                        <input type="text" name="name_price[]" x-model="row.name" maxlength="255"
                                               placeholder="Ex.: Mensalidade" aria-label="Título do preço"
                                               class="{{ $inputClass }} mt-1">
                                    </div>
                                    <div>
                                        <label class="{{ $miniLabel }}">R$ Sócio</label>
                                        <input type="number" min="0" max="99999.99" step="0.01" name="price_associated[]"
                                               x-model="row.associated" placeholder="0,00" aria-label="Preço sócio"
                                               class="{{ $inputClass }} mt-1">
                                    </div>
                                    <div>
                                        <label class="{{ $miniLabel }}">R$ Não Sócio</label>
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
                                class="{{ $addButton }}">
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
                                        <label class="{{ $miniLabel }}">Dia</label>
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
                                        <label class="{{ $miniLabel }}">Início</label>
                                        <input type="time" name="start_hour[]" x-model="row.start"
                                               aria-label="Horário de início" class="{{ $inputClass }} mt-1">
                                    </div>
                                    <div>
                                        <label class="{{ $miniLabel }}">Fim</label>
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
                                class="{{ $addButton }}">
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

                <p class="{{ $hint }} mb-3 mt-1" x-show="toggles.responsibles" x-cloak>
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
                                        <label class="{{ $miniLabel }}"
                                               x-text="'Responsável #' + (index + 1)"></label>
                                        <input type="text" name="responsible[]" x-model="row.name" maxlength="255"
                                               placeholder="Nome" aria-label="Nome do responsável"
                                               class="{{ $inputClass }} mt-1">
                                    </div>
                                    <div>
                                        <label class="{{ $miniLabel }}">Telefone (WhatsApp)</label>
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
                                class="{{ $addButton }}">
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
            <x-icon name="check" /> {{ $isEdit ? 'Salvar nova versão' : 'Criar informação' }}
        </x-primary-button>
    </div>
</form>
