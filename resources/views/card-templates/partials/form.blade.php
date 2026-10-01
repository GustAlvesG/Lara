@php
    $isEdit = isset($template);
@endphp

<form method="POST"
      action="{{ $isEdit ? route('card-templates.update', $template) : route('card-templates.store') }}"
      enctype="multipart/form-data"
      x-data="cardTemplateEditor({{ Js::from($template->layout ?? null) }}, {{ Js::from($isEdit ? $template->frontImageUrl() : null) }}, {{ Js::from($isEdit ? $template->backImageUrl() : null) }})"
      @pointermove.window="onDragMove($event); onResizeMove($event)"
      @pointerup.window="endDrag(); endResize()">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div>
            <x-input-label for="name" value="Nome do modelo" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                          value="{{ old('name', $template->name ?? '') }}" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>

        <div class="flex items-end">
            <label class="inline-flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}
                       class="rounded border-line-strong text-grena focus:ring-grena-tint">
                <span class="text-sm font-bold text-ink">Modelo ativo (aparece na emissão de carteirinhas)</span>
            </label>
        </div>
    </div>

    <div class="mb-6 rounded-2xl bg-grena-tint p-4 text-sm text-ink">
        <p class="font-bold mb-1">Recomendações para as imagens de frente e verso</p>
        <ul class="list-disc list-inside space-y-0.5">
            <li><strong>Orientação:</strong> vertical (retrato) — largura menor que a altura</li>
            <li><strong>Proporção:</strong> 54&nbsp;:&nbsp;85,6 (largura&nbsp;:&nbsp;altura do cartão, ≈ 0,63:1)</li>
            <li><strong>Resolução:</strong> cerca de 638&nbsp;×&nbsp;1011px (300 DPI) para impressão nítida</li>
            <li><strong>Formato:</strong> PNG ou JPG, até 5 MB por imagem</li>
        </ul>
        <p class="mt-1 text-xs text-ink-2">
            Imagens fora dessa proporção são cortadas para preencher o cartão (o navegador ajusta preenchendo e cortando as bordas).
        </p>
    </div>

    <x-input-error :messages="$errors->get('layout')" class="mb-4" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- FRENTE -->
        <div>
            <h3 class="mb-2 font-display font-semibold text-ink">Frente</h3>

            <x-input-file id="front_image" name="front_image" accept="image/*"
                          @change="onImageChange('front', $event)" />
            <x-input-error :messages="$errors->get('front_image')" class="mt-1" />

            <div class="card-canvas relative w-full mt-3 rounded-xl overflow-hidden border-2 border-line bg-subtle"
                 style="aspect-ratio: 54 / 85.6; max-width: 320px;">
                <img :src="frontPreviewUrl" x-show="frontPreviewUrl" class="absolute inset-0 w-full h-full object-cover pointer-events-none">
                <template x-for="(field, key) in layout.front" :key="key">
                    <div class="absolute border-2 border-dashed flex items-center justify-center text-center px-1 select-none touch-none"
                         :class="key === 'photo' ? 'border-indigo-500 bg-indigo-500/10 text-indigo-700 cursor-move' : 'border-amber-500 bg-amber-500/10 text-amber-800 cursor-move'"
                         :style="boxStyle(field)"
                         @pointerdown="startDrag($event, 'front', key)">
                        <span class="text-[10px] font-extrabold leading-tight" x-text="fieldLabel('front', key)"></span>
                        <div class="absolute w-3.5 h-3.5 rounded-full -right-1.5 -bottom-1.5 cursor-nwse-resize border border-white"
                             :class="key === 'photo' ? 'bg-indigo-600' : 'bg-amber-600'"
                             @pointerdown.stop="startResize($event, 'front', key)"></div>
                    </div>
                </template>
            </div>
        </div>

        <!-- VERSO -->
        <div>
            <h3 class="mb-2 font-display font-semibold text-ink">Verso</h3>

            <x-input-file id="back_image" name="back_image" accept="image/*"
                          @change="onImageChange('back', $event)" />
            <x-input-error :messages="$errors->get('back_image')" class="mt-1" />

            <div class="card-canvas relative w-full mt-3 rounded-xl overflow-hidden border-2 border-line bg-subtle"
                 style="aspect-ratio: 54 / 85.6; max-width: 320px;">
                <img :src="backPreviewUrl" x-show="backPreviewUrl" class="absolute inset-0 w-full h-full object-cover pointer-events-none">
                <template x-for="(field, key) in layout.back" :key="key">
                    <div class="absolute border-2 border-dashed border-amber-500 bg-amber-500/10 text-amber-800 flex items-center justify-center text-center px-1 select-none touch-none cursor-move"
                         :style="boxStyle(field)"
                         @pointerdown="startDrag($event, 'back', key)">
                        <span class="text-[10px] font-extrabold leading-tight" x-text="fieldLabel('back', key)"></span>
                        <div class="absolute w-3.5 h-3.5 bg-amber-600 rounded-full -right-1.5 -bottom-1.5 cursor-nwse-resize border border-white"
                             @pointerdown.stop="startResize($event, 'back', key)"></div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <p class="mt-3 text-xs text-ink-3">
        Arraste as caixas para posicionar cada campo. Todas as caixas podem ser redimensionadas pela alça no canto inferior direito.
    </p>

    <!-- ESTILO DOS CAMPOS DE TEXTO -->
    <div class="mt-8">
        <h3 class="mb-3 font-display font-semibold text-ink">Estilo dos campos de texto</h3>
        <div class="overflow-x-auto rounded-2xl border border-line">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-xs font-bold text-ink-3">
                        <th class="py-2 px-4">Campo</th>
                        <th class="py-2 px-4">Tamanho</th>
                        <th class="py-2 px-4">Negrito</th>
                        <th class="py-2 px-4">Alinhamento</th>
                        <th class="py-2 px-4">Cor</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="tf in textFields" :key="tf.side + tf.key">
                        <tr class="border-b border-line last:border-0">
                            <td class="py-2 px-4 font-semibold text-ink" x-text="tf.label"></td>
                            <td class="py-2 px-4">
                                <input type="number" min="6" max="40"
                                       class="w-20 rounded-lg px-2 py-1 font-mono text-sm"
                                       x-model.number="layout[tf.side][tf.key].font_size">
                            </td>
                            <td class="py-2 px-4">
                                <input type="checkbox" x-model="layout[tf.side][tf.key].bold"
                                       class="rounded border-line-strong text-grena focus:ring-grena-tint">
                            </td>
                            <td class="py-2 px-4">
                                <select class="rounded-lg py-1 pl-2 pr-8 text-sm"
                                        x-model="layout[tf.side][tf.key].align">
                                    <option value="left">Esquerda</option>
                                    <option value="center">Centro</option>
                                    <option value="right">Direita</option>
                                </select>
                            </td>
                            <td class="py-2 px-4">
                                <input type="color" x-model="layout[tf.side][tf.key].color" class="h-8 w-10 cursor-pointer rounded-lg border border-line-strong p-0">
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <input type="hidden" name="layout" :value="JSON.stringify(layout)">

    <div class="mt-8 flex flex-wrap justify-end gap-2.5 border-t border-line pt-5">
        <x-secondary-button-a href="{{ route('card-templates.index') }}">Cancelar</x-secondary-button-a>
        <x-primary-button type="submit"><x-icon name="check" /> {{ $isEdit ? 'Salvar alterações' : 'Cadastrar modelo' }}</x-primary-button>
    </div>
</form>

<script>
    function cardTemplateEditor(initialLayout, initialFrontUrl, initialBackUrl) {
        return {
            layout: initialLayout || cardTemplateDefaultLayout(),
            frontPreviewUrl: initialFrontUrl,
            backPreviewUrl: initialBackUrl,
            drag: null,
            resize: null,
            textFields: [
                { side: 'front', key: 'name', label: 'Nome (frente)' },
                { side: 'front', key: 'role', label: 'Função (frente)' },
                { side: 'back', key: 'name', label: 'Nome (verso)' },
                { side: 'back', key: 'admission_date', label: 'Data de admissão (verso)' },
                { side: 'back', key: 'registration_number', label: 'Matrícula (verso)' },
            ],

            fieldLabel(side, key) {
                const labels = {
                    'front.photo': 'FOTO',
                    'front.name': 'NOME',
                    'front.role': 'FUNÇÃO',
                    'back.name': 'NOME',
                    'back.admission_date': 'DATA DE ADMISSÃO',
                    'back.registration_number': 'MATRÍCULA',
                };
                return labels[side + '.' + key] || key;
            },

            onImageChange(side, event) {
                const file = event.target.files[0];
                if (!file) return;
                const url = URL.createObjectURL(file);
                if (side === 'front') this.frontPreviewUrl = url;
                else this.backPreviewUrl = url;
            },

            boxStyle(field) {
                return `left:${field.x}%;top:${field.y}%;width:${field.w}%;height:${field.h}%`;
            },

            startDrag(event, side, key) {
                event.preventDefault();
                const canvas = event.currentTarget.closest('.card-canvas');
                const rect = canvas.getBoundingClientRect();
                const field = this.layout[side][key];
                this.drag = {
                    side, key,
                    startClientX: event.clientX,
                    startClientY: event.clientY,
                    startX: field.x,
                    startY: field.y,
                    rectW: rect.width,
                    rectH: rect.height,
                };
                event.currentTarget.setPointerCapture(event.pointerId);
            },

            onDragMove(event) {
                if (!this.drag) return;
                const d = this.drag;
                const field = this.layout[d.side][d.key];
                const dxPct = ((event.clientX - d.startClientX) / d.rectW) * 100;
                const dyPct = ((event.clientY - d.startClientY) / d.rectH) * 100;
                field.x = cardTemplateClamp(d.startX + dxPct, 0, 100 - field.w);
                field.y = cardTemplateClamp(d.startY + dyPct, 0, 100 - field.h);
            },

            endDrag() {
                this.drag = null;
            },

            startResize(event, side, key) {
                event.preventDefault();
                const canvas = event.currentTarget.closest('.card-canvas');
                const rect = canvas.getBoundingClientRect();
                const field = this.layout[side][key];
                this.resize = {
                    side, key,
                    startClientX: event.clientX,
                    startClientY: event.clientY,
                    startW: field.w,
                    startH: field.h,
                    rectW: rect.width,
                    rectH: rect.height,
                };
                event.currentTarget.setPointerCapture(event.pointerId);
            },

            onResizeMove(event) {
                if (!this.resize) return;
                const r = this.resize;
                const field = this.layout[r.side][r.key];
                const dwPct = ((event.clientX - r.startClientX) / r.rectW) * 100;
                const dhPct = ((event.clientY - r.startClientY) / r.rectH) * 100;
                field.w = cardTemplateClamp(r.startW + dwPct, 8, 100 - field.x);
                field.h = cardTemplateClamp(r.startH + dhPct, 8, 100 - field.y);
            },

            endResize() {
                this.resize = null;
            },
        };
    }

    function cardTemplateClamp(value, min, max) {
        if (max < min) max = min;
        return Math.min(Math.max(value, min), max);
    }

    function cardTemplateDefaultLayout() {
        return {
            front: {
                photo: { type: 'photo', x: 15, y: 6, w: 70, h: 45 },
                name:  { type: 'text', x: 5, y: 55, w: 90, h: 10, font_size: 16, bold: true, align: 'center', color: '#000000' },
                role:  { type: 'text', x: 5, y: 66, w: 90, h: 8, font_size: 12, bold: false, align: 'center', color: '#333333' },
            },
            back: {
                name:                { type: 'text', x: 5, y: 10, w: 90, h: 8, font_size: 12, bold: true, align: 'center', color: '#000000' },
                admission_date:      { type: 'text', x: 5, y: 22, w: 90, h: 6, font_size: 10, bold: false, align: 'center', color: '#000000' },
                registration_number: { type: 'text', x: 5, y: 32, w: 90, h: 6, font_size: 10, bold: false, align: 'center', color: '#000000' },
            },
        };
    }
</script>
