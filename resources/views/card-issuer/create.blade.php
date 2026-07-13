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
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Emitir Carteirinha') }}
        </h2>
    </x-slot>

<style>
    @media print {
        body * { visibility: hidden; }
        #print-area, #print-area * { visibility: visible; }
        #print-area { position: absolute; top: 0; left: 0; margin: 0; padding: 0; }
        .card-page { page-break-after: always; box-shadow: none !important; border: none !important; }
    }
    .card-page { width: 220px; aspect-ratio: 54 / 85.6; }
</style>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen" x-data="cardIssuer({{ Js::from($templatesJson) }})">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight mb-1">Emitir Carteirinha</h1>
        <p class="text-gray-500 dark:text-gray-400 font-medium mb-8">
            Preencha os dados, capture a foto e imprima direto na impressora de cartão PVC.
            Os dados não são salvos — servem só para gerar este cartão.
        </p>

        @include('partials.alerts')

        @if($templates->isEmpty())
            <div class="bg-white dark:bg-gray-800 p-12 text-center rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
                <p class="text-gray-500 dark:text-gray-400 text-lg font-medium">Nenhum modelo de carteirinha ativo.</p>
                <a href="{{ route('card-templates.create') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-[#A00001] text-white rounded-lg font-bold hover:bg-[#800000] transition">
                    Cadastrar um modelo
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- FORMULÁRIO -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 border border-gray-100 dark:border-gray-700 space-y-5 print:hidden">
                    <div>
                        <x-input-label for="template" value="Modelo" />
                        <select id="template" x-model.number="selectedTemplateId"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            <template x-for="t in templates" :key="t.id">
                                <option :value="t.id" x-text="t.name"></option>
                            </template>
                        </select>
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
                        <div class="relative w-full max-w-[220px] bg-gray-100 dark:bg-gray-700 rounded-xl overflow-hidden border-2 border-gray-200 dark:border-gray-600 flex items-center justify-center mt-1" style="aspect-ratio:4/3">
                            <span x-show="!cameraOn && !photoDataUrl" class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 text-center p-4">Câmera / Importar</span>
                            <video x-ref="video" autoplay playsinline x-show="cameraOn && !photoDataUrl" class="w-full h-full object-cover scale-x-[-1]"></video>
                            <img :src="photoDataUrl" x-show="photoDataUrl" class="w-full h-full object-cover">
                            <div x-show="loadingCamera" class="absolute inset-0 bg-white/80 dark:bg-gray-800/80 flex items-center justify-center">
                                <div class="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                            </div>
                        </div>
                        <canvas x-ref="canvas" class="hidden"></canvas>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" x-show="!cameraOn && !photoDataUrl" @click="startCamera()"
                                    class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-bold uppercase shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                Ativar Câmera
                            </button>
                            <button type="button" x-show="!photoDataUrl" @click="$refs.importInput.click()"
                                    class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-bold uppercase shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                Importar Foto
                            </button>
                            <button type="button" x-show="cameraOn && !photoDataUrl" @click="takePhoto()"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold uppercase shadow-md hover:bg-indigo-700 transition">
                                Tirar Foto
                            </button>
                            <button type="button" x-show="photoDataUrl" @click="resetPhoto()"
                                    class="px-4 py-2 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 border border-red-100 dark:border-red-800 rounded-lg text-xs font-bold uppercase hover:bg-red-100 dark:hover:bg-red-900/50 transition">
                                Tentar Novamente
                            </button>
                        </div>
                        <input type="file" x-ref="importInput" accept="image/*" class="hidden" @change="importPhoto($event)">
                    </div>

                    <button type="button" :disabled="!canPrint" @click="printCard()"
                            class="w-full inline-flex items-center justify-center px-6 py-3 bg-[#A00001] disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:cursor-not-allowed text-white rounded-xl font-bold shadow-lg hover:enabled:bg-[#800000] transition duration-150">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"></path></svg>
                        Imprimir
                    </button>
                    <p class="text-xs text-gray-500 dark:text-gray-400 text-center" x-show="!canPrint">
                        Selecione um modelo, informe o nome e capture a foto para habilitar a impressão.
                    </p>
                </div>

                <!-- PREVIEW / ÁREA DE IMPRESSÃO -->
                <div class="flex flex-col items-center gap-6">
                    <style x-ref="printSizeStyle"></style>

                    <div id="print-area" class="flex flex-col items-center gap-6">
                        <!-- FRENTE -->
                        <div class="card-page relative rounded-xl overflow-hidden bg-gray-200 dark:bg-gray-700 shadow-xl border border-gray-200 dark:border-gray-700">
                            <template x-if="selectedTemplate">
                                <div>
                                    <img :src="selectedTemplate.front_image_url" class="absolute inset-0 w-full h-full object-cover">
                                    <div class="absolute overflow-hidden bg-gray-300/70 flex items-center justify-center"
                                         :style="fieldStyle(selectedTemplate.layout.front.photo)">
                                        <img :src="photoDataUrl" x-show="photoDataUrl" class="w-full h-full object-cover">
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
                        <div class="card-page relative rounded-xl overflow-hidden bg-gray-200 dark:bg-gray-700 shadow-xl border border-gray-200 dark:border-gray-700">
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
