@php
    $templatesJson = $templates->map(fn($t) => [
        'id' => $t->id,
        'name' => $t->name,
        'layout' => $t->layout,
        'front_image_url' => $t->frontImageUrl(),
        'back_image_url' => $t->backImageUrl(),
        'card_width_mm' => (float) $t->card_width_mm,
        'card_height_mm' => (float) $t->card_height_mm,
    ]);
@endphp
<x-app-layout :bootstrap-grid="false">
<style>
    @media print {
        body * { visibility: hidden; }
        #print-area, #print-area * { visibility: visible; }
        #print-area { position: absolute; top: 0; left: 0; margin: 0; padding: 0; }
        .card-page { page-break-after: always; box-shadow: none !important; border: none !important; }
    }
    .card-page { width: 220px; aspect-ratio: 54 / 85.6; }
</style>

<div x-data="cardIssuer({{ Js::from($templatesJson) }})">
    <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 pb-12 pt-6 sm:px-6 lg:px-8">
        <x-page-title title="Emitir carteirinha" class="print:hidden">
            Preencha os dados, capture a foto e imprima direto na impressora de cartão PVC.
            Os dados não são salvos — servem só para gerar este cartão.
        </x-page-title>

        @include('partials.alerts')

        @if($templates->isEmpty())
            <x-empty-state icon="card">
                Nenhum modelo de carteirinha ativo.
                <a href="{{ route('card-templates.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar um modelo</a>.
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- FORMULÁRIO -->
                <div class="flex flex-col gap-5 rounded-card bg-surface p-5 shadow-card print:hidden sm:p-6">
                    <div>
                        <x-input-label for="template" value="Modelo" class="mb-1.5" />
                        <x-select-input id="template" x-model.number="selectedTemplateId">
                            <template x-for="t in templates" :key="t.id">
                                <option :value="t.id" x-text="t.name"></option>
                            </template>
                        </x-select-input>
                    </div>

                    <div>
                        <x-input-label for="issue-name" value="Nome" />
                        <x-text-input id="issue-name" type="text" class="mt-1 block w-full" x-model="formData.name" placeholder="Nome completo" />
                    </div>

                    <div>
                        <x-input-label for="issue-role" value="Função" />
                        <x-text-input id="issue-role" type="text" class="mt-1 block w-full" x-model="formData.role" placeholder="Ex: Recepcionista" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="issue-matricula" value="Matrícula" />
                            <x-text-input id="issue-matricula" type="text" class="mt-1 block w-full" x-model="formData.matricula" />
                        </div>
                        <div>
                            <x-input-label for="issue-admission" value="Data de admissão" />
                            <x-text-input id="issue-admission" type="date" class="mt-1 block w-full" x-model="formData.admission_date" />
                        </div>
                    </div>

                    <!-- CÂMERA -->
                    <div>
                        <x-input-label value="Foto" />
                        <div class="relative mt-1.5 flex w-full max-w-[220px] items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-line-strong bg-subtle" style="aspect-ratio:4/3">
                            <span x-show="!cameraOn && !photoDataUrl" class="text-xs font-bold uppercase tracking-wider text-ink-3 text-center p-4">Câmera / Importar</span>
                            <video x-ref="video" autoplay playsinline x-show="cameraOn && !photoDataUrl" class="w-full h-full object-cover scale-x-[-1]"></video>
                            <img :src="photoDataUrl" x-show="photoDataUrl" class="w-full h-full object-cover">
                            <div x-show="loadingCamera" class="absolute inset-0 flex items-center justify-center bg-surface/80">
                                <div class="h-8 w-8 animate-spin rounded-full border-4 border-grena border-t-transparent"></div>
                            </div>
                        </div>
                        <canvas x-ref="canvas" class="hidden"></canvas>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-secondary-button size="sm" x-show="!cameraOn && !photoDataUrl" x-on:click="startCamera()">
                                <x-icon name="image" /> Ativar câmera
                            </x-secondary-button>
                            <x-secondary-button size="sm" x-show="!photoDataUrl" x-on:click="$refs.importInput.click()">
                                <x-icon name="download" class="h-4 w-4 rotate-180" /> Importar foto
                            </x-secondary-button>
                            <x-primary-button type="button" size="sm" x-show="cameraOn && !photoDataUrl" x-on:click="takePhoto()">
                                <x-icon name="check" /> Tirar foto
                            </x-primary-button>
                            <x-danger-button type="button" size="sm" x-show="photoDataUrl" x-on:click="resetPhoto()">
                                <x-icon name="history" /> Tentar novamente
                            </x-danger-button>
                        </div>
                        <input type="file" x-ref="importInput" accept="image/*" class="hidden" @change="importPhoto($event)">
                    </div>

                    <button type="button" :disabled="!canPrint" @click="printCard()"
                            class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-grena px-6 font-bold text-white transition hover:enabled:bg-grena-hover disabled:cursor-not-allowed disabled:bg-line disabled:text-ink-3">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"></path></svg>
                        Imprimir
                    </button>
                    <p class="text-center text-xs text-ink-3" x-show="!canPrint">
                        Selecione um modelo, informe o nome e capture a foto para habilitar a impressão.
                    </p>
                </div>

                <!-- PREVIEW / ÁREA DE IMPRESSÃO -->
                <div class="flex flex-col items-center gap-6">
                    <style x-ref="printSizeStyle"></style>

                    <div id="print-area" class="flex flex-col items-center gap-6">
                        <!-- FRENTE -->
                        <div class="card-page relative rounded-xl overflow-hidden bg-line shadow-pop border border-line">
                            <template x-if="selectedTemplate">
                                <div>
                                    <img :src="selectedTemplate.front_image_url" class="absolute inset-0 w-full h-full object-cover">
                                    <div class="absolute overflow-hidden bg-gray-300/70 flex items-center justify-center"
                                         :style="fieldStyle(selectedTemplate.layout.front.photo)">
                                        <img :src="photoDataUrl" x-show="photoDataUrl" class="w-full h-full object-cover">
                                        {{-- Dentro da arte do cartão: cor fixa, o cartão impresso não tem tema. --}}
                                        <span x-show="!photoDataUrl" class="text-[8px] font-bold text-gray-600">FOTO</span>
                                    </div>
                                    <div class="absolute overflow-hidden flex" :style="fieldStyle(selectedTemplate.layout.front.name)">
                                        <span x-text="formData.name"></span>
                                    </div>
                                    <div class="absolute overflow-hidden flex" :style="fieldStyle(selectedTemplate.layout.front.role)">
                                        <span x-text="formData.role"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- VERSO -->
                        <div class="card-page relative rounded-xl overflow-hidden bg-line shadow-pop border border-line">
                            <template x-if="selectedTemplate">
                                <div>
                                    <img :src="selectedTemplate.back_image_url" class="absolute inset-0 w-full h-full object-cover">
                                    <div class="absolute overflow-hidden flex" :style="fieldStyle(selectedTemplate.layout.back.name)">
                                        <span x-text="formData.name"></span>
                                    </div>
                                    <div class="absolute overflow-hidden flex" :style="fieldStyle(selectedTemplate.layout.back.admission_date)">
                                        <span x-text="formatDate(formData.admission_date)"></span>
                                    </div>
                                    <div class="absolute overflow-hidden flex" :style="fieldStyle(selectedTemplate.layout.back.registration_number)">
                                        <span x-text="formData.matricula"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
    let cardIssuerStream = null;

    function cardIssuer(templates) {
        return {
            templates: templates,
            selectedTemplateId: templates.length ? templates[0].id : null,
            formData: { name: '', role: '', matricula: '', admission_date: '' },
            photoDataUrl: null,
            cameraOn: false,
            loadingCamera: false,

            init() {
                this.updatePrintStyle();
                this.$watch('selectedTemplateId', () => this.updatePrintStyle());
            },

            get selectedTemplate() {
                return this.templates.find(t => t.id === this.selectedTemplateId) || null;
            },

            get canPrint() {
                return !!this.selectedTemplate && this.formData.name.trim() !== '' && !!this.photoDataUrl;
            },

            fieldStyle(field) {
                const justify = field.align === 'center' ? 'center' : (field.align === 'right' ? 'flex-end' : 'flex-start');
                let style = `left:${field.x}%;top:${field.y}%;width:${field.w}%;height:${field.h}%;`;
                if (field.type === 'text') {
                    style += `align-items:center;justify-content:${justify};font-size:${field.font_size}px;`
                        + `font-weight:${field.bold ? '700' : '400'};text-align:${field.align};color:${field.color};line-height:1.1;`;
                }
                return style;
            },

            formatDate(iso) {
                if (!iso) return '';
                const [year, month, day] = iso.split('-');
                if (!year || !month || !day) return iso;
                return `${day}/${month}/${year}`;
            },

            updatePrintStyle() {
                const t = this.selectedTemplate;
                const w = t ? t.card_width_mm : 54.0;
                const h = t ? t.card_height_mm : 85.6;
                this.$refs.printSizeStyle.textContent = `
                    @page { size: ${w}mm ${h}mm; margin: 0; }
                    @media print {
                        .card-page { width: ${w}mm !important; height: ${h}mm !important; aspect-ratio: unset !important; }
                    }
                `;
            },

            async startCamera() {
                this.loadingCamera = true;
                try {
                    cardIssuerStream = await navigator.mediaDevices.getUserMedia({
                        video: { width: 640, height: 480, facingMode: 'user' },
                        audio: false,
                    });
                    this.$refs.video.srcObject = cardIssuerStream;
                    this.cameraOn = true;
                } catch (err) {
                    alert('Não foi possível acessar a câmera. Verifique as permissões.');
                } finally {
                    this.loadingCamera = false;
                }
            },

            takePhoto() {
                const video = this.$refs.video;
                const canvas = this.$refs.canvas;
                const w = video.videoWidth || 640;
                const h = video.videoHeight || 480;
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.translate(w, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(video, 0, 0, w, h);
                this.photoDataUrl = canvas.toDataURL('image/jpeg');
                this.stopCamera();
            },

            resetPhoto() {
                this.photoDataUrl = null;
            },

            importPhoto(event) {
                const file = event.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onloadend = () => { this.photoDataUrl = reader.result; };
                reader.readAsDataURL(file);
            },

            stopCamera() {
                if (cardIssuerStream) {
                    cardIssuerStream.getTracks().forEach(t => t.stop());
                    cardIssuerStream = null;
                }
                this.cameraOn = false;
            },

            printCard() {
                if (!this.canPrint) return;
                window.print();
            },
        };
    }
</script>
</x-app-layout>
