<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    
    <!-- COLUNA DA ESQUERDA: IDENTIFICAÇÃO E REGRAS -->
    <div class="space-y-6">
        
        <!-- Bloco 1: Identificação Básica -->
        <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-4">Identificação</h3>
            
            <div class="space-y-4">
                <div>
                    <x-input-label for="name" :value="__('Nome do Grupo')" />
                    <x-text-input name="name" id="name" class="block mt-1 w-full" type="text" :value="old('name', $item->name ?? '')" required />
                </div>

                <div>
                    <x-input-label for="category" :value="__('Tipo de Espaço')" />
                    <select name="category" id="category" class="w-full mt-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                        <option value="esportiva" {{ (old('category', $item->category ?? '') == 'esportiva') ? 'selected' : '' }}>Esportiva</option>
                        <option value="social" {{ (old('category', $item->category ?? '') == 'social') ? 'selected' : '' }} disabled>Social (Em breve)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Bloco 2: Regras de Agendamento (Novos Campos) -->
        <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-4">Regras e Limites</h3>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="start_time" :value="__('Horário Início')" />
                    <x-text-input name="start_time" id="start_time" type="time" class="block mt-1 w-full" :value="old('start_time', $item->start_time ?? null)" required />
                    <p class="mt-1 text-[10px] text-gray-500 italic">Início do primeiro horário disponível</p>
                </div>
                <div>
                    <x-input-label for="end_time" :value="__('Horário Fim')" />
                    <x-text-input name="end_time" id="end_time" type="time" class="block mt-1 w-full" :value="old('end_time', $item->end_time ?? null)" required />
                    <p class="mt-1 text-[10px] text-gray-500 italic">Fim do último horário disponível</p>
                </div>
                <!-- Antecedência Mínima -->
                <div>
                    <x-input-label for="minimum_antecedence" :value="__('Antecedência Mín. (Horas)')" />
                    <x-text-input name="minimum_antecedence" id="minimum_antecedence" type="number" class="antecedence-class block mt-1 w-full" :value="old('minimum_antecedence', $item->minimum_antecedence ?? '0')" />
                    <p class="mt-1 text-[10px] text-gray-500 italic">"Pelo menos quantos dias antes?"</p>
                </div>

                <!-- Antecedência Máxima -->
                <div>
                    <x-input-label for="maximum_antecedence" :value="__('Antecedência Máx. (Dias)')" />
                    <x-text-input name="maximum_antecedence" id="maximum_antecedence" type="number" class="antecedence-class block mt-1 w-full" :value="old('maximum_antecedence', $item->maximum_antecedence ?? '0')" />
                    <p class="mt-1 text-[10px] text-gray-500 italic">"No máximo, quantos dias antes?"</p>
                </div>

                <!-- Duração do Slot -->
                <div>
                    <x-input-label for="duration" :value="__('Duração (Minutos)')" />
                    <x-text-input name="duration" id="duration" type="time" class="block mt-1 w-full" :value="old('duration', $item->duration ?? '01:00')" required />
                    <p class="mt-1 text-[10px] text-gray-500 italic">Padrão: 01h</p>
                </div>

                <!-- Limite Diário -->
                <div>
                    <x-input-label for="daily_limit" :value="__('Limite Diário / Sócio')" />
                    <x-text-input name="daily_limit" id="daily_limit" type="number" class="block mt-1 w-full" :value="old('daily_limit', $item->daily_limit ?? '1')" required />
                    <p class="mt-1 text-[10px] text-gray-500 italic">Máx. de reservas por dia.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- COLUNA DA DIREITA: IMAGENS -->
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-2">Mídia e Design</h3>
            <p class="text-xs text-gray-500 mb-6">{{ __("As imagens serão exibidas no portal de agendamentos.") }}</p>
            
            <div class="grid grid-cols-1 gap-6">
                <!-- Imagem Vertical -->
                <div class="form-group">
                    <p class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">{{ __("Imagem Vertical (Mobile/Cards)") }}</p>
                    <div class="flex items-center gap-4">
                        <label for="image_vertical" class="cursor-pointer inline-flex items-center px-4 py-2 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg font-bold text-xs text-indigo-700 dark:text-indigo-300 uppercase tracking-widest hover:bg-indigo-100 transition shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Selecionar...
                        </label>
                        <input type="file" name="image_vertical" id="image_vertical" class="hidden image-upload" accept="image/*" />
                    </div>
                    <div class="mt-4">
                        @include('partials.imagePreview', ['id_preview' => 'image_vertical'])
                    </div>
                </div>

                <hr class="border-gray-100 dark:border-gray-700">

                <!-- Imagem Horizontal -->
                <div class="form-group">
                    <p class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">{{ __("Imagem Horizontal (Desktop/Banners)") }}</p>
                    <div class="flex items-center gap-4">
                        <label for="image_horizontal" class="cursor-pointer inline-flex items-center px-4 py-2 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg font-bold text-xs text-indigo-700 dark:text-indigo-300 uppercase tracking-widest hover:bg-indigo-100 transition shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Selecionar...
                        </label>
                        <input type="file" name="image_horizontal" id="image_horizontal" class="hidden image-upload" accept="image/*" />
                    </div>
                    <div class="mt-4">
                        @include('partials.imagePreview', ['id_preview' => 'image_horizontal'])
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

