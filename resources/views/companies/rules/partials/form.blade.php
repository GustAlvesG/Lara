 <input type="hidden" name="company_worker_id" value="{{ $worker->id ?? '' }}">
 <input type="hidden" name="company_id" value="{{ $company }}">
 
 <div class="space-y-8">
    <!-- Secção 1: Definição do Tipo -->
    <div>
        <label class="block text-sm font-bold text-gray-700 mb-4 flex items-center">
            <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2"></span>
            Tipo de Regra
        </label>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Opção Inclusão -->
            <label class="relative flex items-center p-4 border-2 rounded-2xl cursor-pointer transition group border-gray-100 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                <input type="radio" name="type" value="include" class="w-5 h-5 text-indigo-600 border-gray-300 focus:ring-indigo-500" required checked>
                <div class="ml-4">
                    <p class="font-bold text-gray-800">Inclusão</p>
                    <p class="text-xs text-gray-500">Permite o acesso conforme os critérios.</p>
                </div>
                <div class="ml-auto text-indigo-600 opacity-0 group-has-[:checked]:opacity-100 transition">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                </div>
            </label>

            <!-- Opção Exclusão -->
            <label class="relative flex items-center p-4 border-2 rounded-2xl cursor-pointer transition group border-gray-100 has-[:checked]:border-red-600 has-[:checked]:bg-red-50">
                <input type="radio" name="type" value="exclude" class="w-5 h-5 text-red-600 border-gray-300 focus:ring-red-500">
                <div class="ml-4">
                    <p class="font-bold text-gray-800">Exclusão</p>
                    <p class="text-xs text-gray-500">Bloqueia o acesso durante o período.</p>
                </div>
                <div class="ml-auto text-red-600 opacity-0 group-has-[:checked]:opacity-100 transition">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                </div>
            </label>
        </div>
    </div>

    <!-- Secção 2: Vigência -->
    <div>
        <label class="block text-sm font-bold text-gray-700 mb-4 flex items-center">
            <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2"></span>
            Período de Vigência
        </label>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-6 rounded-2xl border border-gray-100">
            <!-- Data Início -->
            <div>
                <label for="start_date" class="block text-xs font-bold text-gray-400 uppercase mb-2 tracking-wider">Data de Início</label>
                <div class="relative">
                    <input type="date" id="start_date" name="start_date" required
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm bg-white font-medium">
                </div>
                <p class="mt-1.5 text-[10px] text-gray-400 font-medium">A regra passará a valer a partir desta data.</p>
            </div>

            <!-- Data Fim -->
            <div>
                <label for="end_date" class="block text-xs font-bold text-gray-400 uppercase mb-2 tracking-wider">Data de Término (Opcional)</label>
                <div class="relative">
                    <input type="date" id="end_date" name="end_date"
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm bg-white font-medium">
                </div>
                <p class="mt-1.5 text-[10px] text-gray-400 font-medium">Deixe vazio para uma regra por tempo indeterminado.</p>
            </div>
        </div>
    </div>

    <!-- Secção 3: Dias da Semana -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-4 flex items-center">
                            <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2"></span>
                            Dias da Semana
                        </label>
                        
                        <div class="flex flex-wrap gap-3">
                            @php
                                $days = [
                                    'dom' => ['Domingo', 1],
                                    'seg' => ['Segunda', 2],
                                    'ter' => ['Terça', 3],
                                    'qua' => ['Quarta', 4],
                                    'qui' => ['Quinta', 5],
                                    'sex' => ['Sexta', 6],
                                    'sab' => ['Sábado', 7],
                                ];
                            @endphp
                            @foreach($days as $key => $label)
                            <label class="flex-1 min-w-[80px] cursor-pointer group">
                                <input type="checkbox" name="days[]" value="{{ $label[1] }}" class="hidden peer" checked>
                                <div class="px-3 py-4 border-2 border-gray-100 rounded-xl bg-white text-center transition-all peer-checked:border-indigo-600 peer-checked:bg-indigo-50 group-hover:bg-gray-50">
                                    <span class="text-xs font-black uppercase text-gray-400 peer-checked:text-indigo-700 transition-colors group-has-[:checked]:text-indigo-700">
                                        {{ $key }}
                                    </span>
                                    <p class="hidden md:block text-[10px] text-gray-400 font-medium group-has-[:checked]:text-indigo-500 mt-1">
                                        {{ $label[0] }}
                                    </p>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>

    <!-- Horário de Acesso -->
    <div>
        <label class="block text-sm font-bold text-gray-700 mb-4 flex items-center">
            <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2"></span>
            Período de Vigência
        </label>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-6 rounded-2xl border border-gray-100">
            <!-- Horario Início -->
            <div>
                <label for="start_time" class="block text-xs font-bold text-gray-400 uppercase mb-2 tracking-wider">Data de Início</label>
                <div class="relative">
                    <input type="time" id="start_time" name="start_time"
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm bg-white font-medium">
                </div>
                <p class="mt-1.5 text-[10px] text-gray-400 font-medium">A regra passará a valer a partir desta data.</p>
            </div>

            <!-- Data Fim -->
            <div>
                <label for="end_time" class="block text-xs font-bold text-gray-400 uppercase mb-2 tracking-wider">Data de Término (Opcional)</label>
                <div class="relative">
                    <input type="time" id="end_time" name="end_time"
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm bg-white font-medium">
                </div>
                <p class="mt-1.5 text-[10px] text-gray-400 font-medium">Deixe vazio para uma regra por tempo indeterminado.</p>
            </div>
        </div>
    </div>

    <!-- Informações Adicionais (Exemplo de campo complementar) -->
    <div>
        <label for="description" class="block text-sm font-bold text-gray-700 mb-2">Observações / Motivo</label>
        <textarea id="description" name="description" rows="3" placeholder="Descreva brevemente o objetivo desta regra..."
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm resize-none"></textarea>
    </div>

</div>


<script>
        // Lógica simples para validar se a data fim é maior que a data início (UX)
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');

        startDateInput.addEventListener('change', function() {
            if (endDateInput.value && endDateInput.value < this.value) {
                endDateInput.value = '';
            }
            endDateInput.min = this.value;
        });

        // Garantir que a hora de término não seja igual ou menor que a de início se for no mesmo dia (Simulação básica)
        const startTime = document.getElementById('start_time');
        const endTime = document.getElementById('end_time');

        endTime.addEventListener('blur', function() {
            if (startTime.value >= this.value) {
                console.warn('O horário de término deve ser posterior ao de início.');
            }
        });
        </script>