<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- COLUNA DA ESQUERDA: CONFIGURAÇÕES (2/3 da largura no desktop) -->
    <div class="lg:col-span-2 space-y-8">
        
        <!-- Secção 1: Identificação Básica -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-2 mb-6">
                <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg text-indigo-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-white uppercase tracking-tight">Identificação</h3>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="name" :value="__('Nome do Grupo')" />
                    <x-text-input name="name" id="name" class="block mt-1 w-full" type="text" :value="old('name', $item->name ?? '')" required placeholder="Ex: Quadras de Ténis" />
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

        <!-- Secção 2: Horários de Operação e Vendas -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-2 mb-6">
                <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg text-amber-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-white uppercase tracking-tight">Gestão de Horários</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Bloco de Funcionamento -->
                <div class="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-100 dark:border-gray-800">
                    <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Horário de Agendamento</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="start_time" :value="__('Abertura')" />
                            <x-text-input name="start_time" id="start_time" type="time" class="block mt-1 w-full" :value="old('start_time', $item->start_time ?? null)" required />
                        </div>
                        <div>
                            <x-input-label for="end_time" :value="__('Fechamento')" />
                            <x-text-input name="end_time" id="end_time" type="time" class="block mt-1 w-full" :value="old('end_time', $item->end_time ?? null)" required />
                        </div>
                    </div>
                </div>

                <!-- Bloco de Vendas -->
                <div class="p-4 bg-indigo-50 dark:bg-indigo-900/10 rounded-xl border border-indigo-100 dark:border-indigo-900/30">
                    <p class="text-xs font-black text-indigo-400 uppercase tracking-widest mb-4">Janela de Vendas (App)</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="start_time_sales" :value="__('Início')" />
                            <x-text-input name="start_time_sales" id="start_time_sales" type="time" class="block mt-1 w-full" :value="old('start_time_sales', $item->start_time_sales ?? null)"  />
                        </div>
                        <div>
                            <x-input-label for="end_time_sales" :value="__('Fim')" />
                            <x-text-input name="end_time_sales" id="end_time_sales" type="time" class="block mt-1 w-full" :value="old('end_time_sales', $item->end_time_sales ?? null)"  />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secção 3: Regras e Limites de Agendamento -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-2 mb-6">
                <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-white uppercase tracking-tight">Regras de Reserva</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <x-input-label for="minimum_antecedence" :value="__('Ant. Mín. (d)')" />
                    <x-text-input name="minimum_antecedence" id="minimum_antecedence" type="number" class="block mt-1 w-full" :value="old('minimum_antecedence', $item->minimum_antecedence ?? '0')" />
                </div>

                <div>
                    <x-input-label for="maximum_antecedence" :value="__('Ant. Máx. (d)')" />
                    <x-text-input name="maximum_antecedence" id="maximum_antecedence" type="number" class="block mt-1 w-full" :value="old('maximum_antecedence', $item->maximum_antecedence ?? '0')" />
                </div>

                <div>
                    <x-input-label for="duration" :value="__('Duração')" />
                    <x-text-input name="duration" id="duration" type="time" class="block mt-1 w-full" :value="old('duration', $item->duration ?? '01:00')" required />
                </div>

                <div>
                    <x-input-label for="daily_limit" :value="__('Limite Diário')" />
                    <x-text-input name="daily_limit" id="daily_limit" type="number" class="block mt-1 w-full" :value="old('daily_limit', $item->daily_limit ?? '1')" required />
                </div>
            </div>

            <!-- Dias da Semana -->
            <div class="mt-8 pt-6 border-t border-gray-50 dark:border-gray-700">
                <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Disponibilidade Semanal</p>
                <div class="flex flex-wrap gap-2">
                    @php
                        $weekdays = [
                            'dom' => ['Dom', 1], 'seg' => ['Seg', 2], 'ter' => ['Ter', 3], 'qua' => ['Qua', 4],
                            'qui' => ['Qui', 5], 'sex' => ['Sex', 6], 'sab' => ['Sab', 7],
                        ];
                        $selectedWeekdays = old('weekdays', $item->weekdays ?? [1,2,3,4,5,6,7]);
                    @endphp
                    @foreach($weekdays as $key => $label)
                    <label class="flex-1 min-w-[65px] cursor-pointer group">
                        <input type="checkbox" name="weekdays[]" value="{{ $label[1] }}" class="hidden peer" {{ in_array($label[1], $selectedWeekdays) ? 'checked' : '' }}>
                        <div class="py-3 border-2 border-gray-100 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-center transition-all peer-checked:border-indigo-600 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-900/20 group-hover:bg-gray-50 dark:group-hover:bg-gray-700 shadow-sm">
                            <span class="text-xs font-black uppercase text-gray-400 peer-checked:text-indigo-700 dark:peer-checked:text-indigo-400">
                                {{ $key }}
                            </span>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- COLUNA DA DIREITA: MÍDIA (1/3 da largura no desktop) -->
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 sticky top-24">
            <h3 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-4">Mídia e Design</h3>
            
            <div class="space-y-8">
                <!-- Imagem Vertical -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-tight">Imagem Vertical</p>
                        <label for="image_vertical" class="cursor-pointer text-[10px] font-black text-indigo-600 hover:text-indigo-800 uppercase">Alterar</label>
                    </div>
                    <input type="file" name="image_vertical" id="image_vertical" class="hidden image-upload" accept="image/*" />
                    <div class="rounded-xl overflow-hidden bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-700">
                        @include('partials.imagePreview', ['id_preview' => 'image_vertical'])
                    </div>
                </div>

                <div class="border-t border-gray-50 dark:border-gray-700"></div>

                <!-- Imagem Horizontal -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-tight">Imagem Horizontal</p>
                        <label for="image_horizontal" class="cursor-pointer text-[10px] font-black text-indigo-600 hover:text-indigo-800 uppercase">Alterar</label>
                    </div>
                    <input type="file" name="image_horizontal" id="image_horizontal" class="hidden image-upload" accept="image/*" />
                    <div class="rounded-xl overflow-hidden bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-700">
                        @include('partials.imagePreview', ['id_preview' => 'image_horizontal'])
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>