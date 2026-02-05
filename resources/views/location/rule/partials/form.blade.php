<div class="space-y-8">
    
    <!-- Secção 1: Identificação da Regra -->
    <div>
        <label class="block text-sm font-bold text-gray-700 mb-4 flex items-center">
            <span class="mr-3 bg-indigo-600 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">1</span>
            Identificação da Regra
        </label>
        
        <div class="bg-white p-2 rounded-2xl border border-gray-100 shadow-sm">
            <x-input-label for="rule_name" :value="__('Nome da Regra')" />
            <x-text-input name="name" id="rule_name" class="block mt-1 w-full" type="text" :value="old('name', $item->name ?? '')" required placeholder="Ex: Acesso Noturno - Quadras" />
            <p class="mt-2 text-xs text-gray-500">Dê um nome claro para identificar esta regra no futuro.</p>
        </div>
    </div>

    <!-- Secção 2: Definição do Tipo -->
    <div>
        <label class="block text-sm font-bold text-gray-700 mb-4 flex items-center">
            <span class="mr-3 bg-indigo-600 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">2</span>
            Tipo de Regra
        </label>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Opção Inclusão -->
            <label class="relative flex items-center p-4 border-2 rounded-2xl cursor-pointer transition group border-gray-100 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                <input type="radio" name="type" value="include" class="w-5 h-5 text-indigo-600 border-gray-300 focus:ring-indigo-500" required {{ (old('type', $item->type ?? 'include') == 'include') ? 'checked' : '' }}>
                <div class="ml-4">
                    <p class="font-bold text-gray-800">Inclusão</p>
                    <p class="text-xs text-gray-500">Permite os agendamentos conforme os critérios.</p>
                </div>
                <div class="ml-auto text-indigo-600 opacity-0 group-has-[:checked]:opacity-100 transition">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                </div>
            </label>

            <!-- Opção Exclusão -->
            <label class="relative flex items-center p-4 border-2 rounded-2xl cursor-pointer transition group border-gray-100 has-[:checked]:border-red-600 has-[:checked]:bg-red-50">
                <input type="radio" name="type" value="exclude" class="w-5 h-5 text-red-600 border-gray-300 focus:ring-red-500" {{ (old('type', $item->type ?? '') == 'exclude') ? 'checked' : '' }}>
                <div class="ml-4">
                    <p class="font-bold text-gray-800">Exclusão</p>
                    <p class="text-xs text-gray-500">Bloqueia os agendamentos durante o período.</p>
                </div>
                <div class="ml-auto text-red-600 opacity-0 group-has-[:checked]:opacity-100 transition">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                </div>
            </label>
        </div>
    </div>

    <!-- Secção 3: Vigência (Datas) -->
    <div>
        <label class="block text-sm font-bold text-gray-700 mb-4 flex items-center">
            <span class="mr-3 bg-indigo-600 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">3</span>
            Período de Vigência (Obrigatório)
        </label>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-6 rounded-2xl border border-gray-100 shadow-inner">
            <!-- Data Início -->
            <div>
                <label for="start_date" class="block text-xs font-bold text-gray-400 uppercase mb-2 tracking-wider">Data de Início</label>
                <input type="date" id="start_date" name="start_date" required
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm bg-white font-medium"
                       value="{{ old('start_date', $item->start_date ?? '') }}">
                <p class="mt-1.5 text-[10px] text-gray-400 font-medium italic">Início da validade da regra.</p>
            </div>

            <!-- Data Fim -->
            <div>
                <label for="end_date" class="block text-xs font-bold text-gray-400 uppercase mb-2 tracking-wider">Data de Término (Opcional)</label>
                <input type="date" id="end_date" name="end_date"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm bg-white font-medium"
                       value="{{ old('end_date', $item->end_date ?? '') }}">
                <p class="mt-1.5 text-[10px] text-gray-400 font-medium italic">Vazio = Tempo indeterminado.</p>
            </div>
        </div>
    </div>

    <!-- Secção 4: Dias da Semana -->
    <div>
        <label class="block text-sm font-bold text-gray-700 mb-4 flex items-center">
            <span class="mr-3 bg-indigo-600 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">4</span>
            Dias da Semana (Marque ao menos um dia)
        </label>
        
        <div class="flex flex-wrap gap-3">
            @php
                $item = $item ?? new \App\Models\ScheduleRules();
                $weekdays = [
                    'dom' => ['Dom', 1],
                    'seg' => ['Seg', 2],
                    'ter' => ['Ter', 3],
                    'qua' => ['Qua', 4],
                    'qui' => ['Qui', 5],
                    'sex' => ['Sex', 6],
                    'sab' => ['Sab', 7],
                ];

                $ids = [];
                foreach ($item->weekdays as $weekday) {
                    $ids[] = $weekday->id;
                }
                $item->weekdays = $ids;

                $selectedDays = old('weekdays', $item->weekdays ?? [1,2,3,4,5,6,7]);
            @endphp
            @foreach($weekdays as $key => $label)
            <label class="flex-1 min-w-[70px] cursor-pointer group">
                <input type="checkbox" name="weekdays[]" value="{{ $label[1] }}" class="hidden peer" {{ in_array($label[1], $selectedDays) ? 'checked' : '' }}>
                <div class="px-2 py-4 border-2 border-gray-100 rounded-xl bg-white text-center transition-all peer-checked:border-indigo-600 peer-checked:bg-indigo-50 group-hover:bg-gray-50 shadow-sm">
                    <span class="text-xs font-black uppercase text-gray-400 peer-checked:text-indigo-700 transition-colors group-has-[:checked]:text-indigo-700">
                        {{ $key }}
                    </span>
                    <p class="hidden md:block text-[9px] text-gray-400 font-bold group-has-[:checked]:text-indigo-500 mt-1">
                        {{ $label[0] }}
                    </p>
                </div>
            </label>
            @endforeach
        </div>
    </div>

    <!-- Secção 5: Horário de Acesso -->
    <div>
        <label class="block text-sm font-bold text-gray-700 mb-4 flex items-center">
            <span class="mr-3 bg-indigo-600 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">5</span>
            Intervalo de Horário (Opcional)
        </label>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 rounded-2xl">
            <!-- Horario Início -->
            <div>
                <label for="start_time" class="block text-xs font-bold text-indigo-300 uppercase mb-2 tracking-wider">Hora de Início</label>
                <input type="time" id="start_time" name="start_time"
                       class="w-full px-4 py-3 border-none rounded-xl focus:ring-2 focus:ring-white outline-none transition shadow-inner font-bold text-lg"
                       value="{{ old('start_time', $item->start_time ?? '') }}">
            </div>

            <!-- Hora Fim -->
            <div>
                <label for="end_time" class="block text-xs font-bold text-indigo-300 uppercase mb-2 tracking-wider">Hora de Término</label>
                <input type="time" id="end_time" name="end_time"
                       class="w-full px-4 py-3 border-none rounded-xl focus:ring-2 focus:ring-white outline-none transition shadow-inner font-bold text-lg"
                       value="{{ old('end_time', $item->end_time ?? '') }}">
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-400 italic font-medium px-2">Defina o período exato em que a regra será aplicada.</p>
    </div>


    <!-- Secção 6: Quadras -->
    <div>
        <label class="block text-sm font-bold text-gray-700 mb-4 flex items-center">
            <span class="mr-3 bg-indigo-600 text-white w-8 h-8 flex items-center justify-center rounded-full text-lg">4</span>
            Quadras (Marque ao menos uma quadra)
        </label>
        
        <div class="flex flex-wrap gap-3">
            @php
                
                $item->places_ids = $item->places->pluck('id')->toArray();
                
            @endphp
            @foreach($group->places as $key => $label)

            <label class="flex-1 min-w-[70px] cursor-pointer group">
                <input type="checkbox" name="places[]" value="{{ $label['id'] }}" class="hidden peer" 
                {{ in_array($label['id'], old('places', $item->places_ids)) ? 'checked' : '' }}
            
                >
                <div class="px-2 py-4 border-2 border-gray-100 rounded-xl bg-white text-center transition-all peer-checked:border-indigo-600 peer-checked:bg-indigo-50 group-hover:bg-gray-50 shadow-sm">
                    <span class="text-xs font-black uppercase text-gray-400 peer-checked:text-indigo-700 transition-colors group-has-[:checked]:text-indigo-700">
                        {{ $label['name'] }}
                    </span>
                    <p class="hidden md:block text-[9px] text-gray-400 font-bold group-has-[:checked]:text-indigo-500 mt-1">
                        {{ $group->name }}
                    </p>
                </div>
            </label>
            @endforeach
        </div>
    </div>
</div>

<script>
    // Validação de Datas
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');

    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function() {
            if (endDateInput.value && endDateInput.value < this.value) {
                endDateInput.value = '';
            }
            endDateInput.min = this.value;
        });
    }

    // Validação de Horários
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');

    if (startTimeInput && endTimeInput) {
        endTimeInput.addEventListener('blur', function() {
            if (startTimeInput.value && this.value && startTimeInput.value >= this.value) {
                console.warn('O horário de término deve ser posterior ao de início.');
                // Opcional: feedback visual de erro aqui
            }
        });
    }
</script>