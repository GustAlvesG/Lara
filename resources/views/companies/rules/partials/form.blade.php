@php
    $editing  = isset($rule);
    $selType  = old('type',        $editing ? $rule->type        : 'include');
    $selDays  = old('days',        $editing ? $rule->weekdays->pluck('id')->toArray() : range(1, 7));
    $selStart = old('start_date',  $editing ? $rule->start_date  : '');
    $selEnd   = old('end_date',    $editing ? $rule->end_date    : '');
    $selTimeS = old('start_time',  $editing && $rule->start_time ? substr($rule->start_time, 0, 5) : '');
    $selTimeE = old('end_time',    $editing && $rule->end_time   ? substr($rule->end_time, 0, 5)   : '');
    $selDesc  = old('description', $editing ? $rule->description : '');
@endphp

<input type="hidden" name="company_worker_id" value="{{ $worker->id ?? '' }}">
<input type="hidden" name="company_id" value="{{ $company->id ?? $company }}">

<div class="space-y-8">
    <!-- Secção 1: Tipo de Regra -->
    <div>
        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-4 flex items-center">
            <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2"></span>
            Tipo de Regra
        </label>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Inclusão -->
            <label class="relative flex items-center p-4 border-2 rounded-2xl cursor-pointer transition group border-gray-100 dark:border-gray-600 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-900/20">
                <input type="radio" name="type" value="include" class="w-5 h-5 text-indigo-600 border-gray-300 focus:ring-indigo-500" required {{ $selType === 'include' ? 'checked' : '' }}>
                <div class="ml-4">
                    <p class="font-bold text-gray-800 dark:text-gray-200">Inclusão</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Permite o acesso conforme os critérios.</p>
                </div>
                <div class="ml-auto text-indigo-600 opacity-0 group-has-[:checked]:opacity-100 transition">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                </div>
            </label>

            <!-- Exclusão -->
            <label class="relative flex items-center p-4 border-2 rounded-2xl cursor-pointer transition group border-gray-100 dark:border-gray-600 has-[:checked]:border-red-600 has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                <input type="radio" name="type" value="exclude" class="w-5 h-5 text-red-600 border-gray-300 focus:ring-red-500" {{ $selType === 'exclude' ? 'checked' : '' }}>
                <div class="ml-4">
                    <p class="font-bold text-gray-800 dark:text-gray-200">Exclusão</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Bloqueia o acesso durante o período.</p>
                </div>
                <div class="ml-auto text-red-600 opacity-0 group-has-[:checked]:opacity-100 transition">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                </div>
            </label>
        </div>
    </div>

    <!-- Secção 2: Período de Vigência -->
    <div>
        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-4 flex items-center">
            <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2"></span>
            Período de Vigência
        </label>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 dark:bg-gray-700/40 p-6 rounded-2xl border border-gray-100 dark:border-gray-600">
            <div>
                <label for="start_date" class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase mb-2 tracking-wider">Data de Início</label>
                <input type="date" id="start_date" name="start_date" required value="{{ $selStart }}"
                        class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium">
                <p class="mt-1.5 text-[10px] text-gray-400 dark:text-gray-500 font-medium">A regra passará a valer a partir desta data.</p>
            </div>

            <div>
                <label for="end_date" class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase mb-2 tracking-wider">Data de Término (Opcional)</label>
                <input type="date" id="end_date" name="end_date" value="{{ $selEnd }}"
                        class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium">
                <p class="mt-1.5 text-[10px] text-gray-400 dark:text-gray-500 font-medium">Deixe vazio para uma regra por tempo indeterminado.</p>
            </div>
        </div>
    </div>

    <!-- Secção 3: Dias da Semana -->
    <div>
        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-4 flex items-center">
            <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2"></span>
            Dias da Semana
        </label>

        <div class="flex flex-wrap gap-3">
            @php
                $days = [
                    'dom' => ['Domingo', 1],
                    'seg' => ['Segunda', 2],
                    'ter' => ['Terça',   3],
                    'qua' => ['Quarta',  4],
                    'qui' => ['Quinta',  5],
                    'sex' => ['Sexta',   6],
                    'sab' => ['Sábado',  7],
                ];
            @endphp
            @foreach($days as $key => $label)
            <label class="flex-1 min-w-[80px] cursor-pointer group">
                <input type="checkbox" name="days[]" value="{{ $label[1] }}" class="hidden peer" {{ in_array($label[1], (array) $selDays) ? 'checked' : '' }}>
                <div class="px-3 py-4 border-2 border-gray-100 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-center transition-all peer-checked:border-indigo-600 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-900/30 group-hover:bg-gray-50 dark:group-hover:bg-gray-600">
                    <span class="text-xs font-black uppercase text-gray-400 dark:text-gray-500 transition-colors group-has-[:checked]:text-indigo-700 dark:group-has-[:checked]:text-indigo-400">
                        {{ $key }}
                    </span>
                    <p class="hidden md:block text-[10px] text-gray-400 dark:text-gray-500 font-medium group-has-[:checked]:text-indigo-500 dark:group-has-[:checked]:text-indigo-400 mt-1">
                        {{ $label[0] }}
                    </p>
                </div>
            </label>
            @endforeach
        </div>
    </div>

    <!-- Secção 4: Horário de Acesso -->
    <div>
        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-4 flex items-center">
            <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2"></span>
            Horário de Acesso
        </label>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 dark:bg-gray-700/40 p-6 rounded-2xl border border-gray-100 dark:border-gray-600">
            <div>
                <label for="start_time" class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase mb-2 tracking-wider">Horário de Início</label>
                <input type="time" id="start_time" name="start_time" value="{{ $selTimeS }}"
                        class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium">
                <p class="mt-1.5 text-[10px] text-gray-400 dark:text-gray-500 font-medium">Deixe vazio para valer o dia todo.</p>
            </div>

            <div>
                <label for="end_time" class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase mb-2 tracking-wider">Horário de Término</label>
                <input type="time" id="end_time" name="end_time" value="{{ $selTimeE }}"
                        class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium">
                <p class="mt-1.5 text-[10px] text-gray-400 dark:text-gray-500 font-medium">Deixe vazio para valer o dia todo.</p>
            </div>
        </div>
    </div>

    <!-- Observações -->
    <div>
        <label for="description" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Observações / Motivo</label>
        <textarea id="description" name="description" rows="3"
                  placeholder="Descreva brevemente o objetivo desta regra..."
                  class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm resize-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">{{ $selDesc }}</textarea>
    </div>
</div>

<script>
    const startDateInput = document.getElementById('start_date');
    const endDateInput   = document.getElementById('end_date');

    startDateInput.addEventListener('change', function() {
        if (endDateInput.value && endDateInput.value < this.value) {
            endDateInput.value = '';
        }
        endDateInput.min = this.value;
    });
</script>
