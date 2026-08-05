@php
    use App\Models\FunctionFreelancer;

    // Faixas em JSON para a prévia da tela. Quem calcula de verdade continua
    // sendo o servidor (EmployeeCacheService) — isto aqui é só para o
    // coordenador ver o valor enquanto digita.
    $functionRates = $functions->mapWithKeys(fn($f) => [$f->id => $f->ratesByHour()->map(fn($p) => (float) $p)]);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Solicitar Cachê') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        @include('employee.cache.partials.tabs')
        @include('partials.alerts')

        @if($functions->isEmpty())
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-6 mb-6">
                <p class="font-bold text-amber-800 dark:text-amber-200">Nenhuma função de cachê disponível.</p>
                <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                    Cadastre uma função com a modalidade <strong>Cachê</strong> e preencha as dez faixas de valor
                    (2h a 11h) em <a href="{{ route('freelancer-functions.index') }}" class="underline font-semibold">Funções</a>.
                </p>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-2xl p-6">
                <p class="font-bold text-red-800 dark:text-red-200 mb-2">Corrija os itens abaixo:</p>
                <ul class="list-disc list-inside text-sm text-red-700 dark:text-red-300 space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('employee-caches.store') }}"
              x-data="cacheRequestForm({{ Js::from($functionRates) }}, {{ Js::from(old('caches', [['employee_id' => '', 'function_freelancer_id' => '', 'location' => '', 'description' => '', 'event_date' => '', 'start_time' => '', 'end_time' => '']])) }})">
            @csrf

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden mb-6">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50 flex flex-col md:flex-row md:items-end gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Título da solicitação</label>
                        <input type="text" name="title" value="{{ old('title') }}" placeholder="Ex.: Festa Junina — apoio do salão"
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                    </div>
                    <div class="md:w-64">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Setor</label>
                        <select name="sector_id" class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            <option value="">—</option>
                            @foreach($sectors as $sector)
                                <option value="{{ $sector->id }}" @selected(old('sector_id') == $sector->id)>{{ $sector->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-bold text-gray-400 uppercase">Total previsto</p>
                        <p class="text-2xl font-extrabold text-gray-900 dark:text-white" x-text="formatMoney(total)"></p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-3 py-3">#</th>
                                <th class="px-3 py-3 min-w-56">Funcionário</th>
                                <th class="px-3 py-3 min-w-44">Função</th>
                                <th class="px-3 py-3 min-w-48">Evento / Local</th>
                                <th class="px-3 py-3">Data</th>
                                <th class="px-3 py-3">Início</th>
                                <th class="px-3 py-3">Término</th>
                                <th class="px-3 py-3">Faixa</th>
                                <th class="px-3 py-3">Valor</th>
                                <th class="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template x-for="(row, index) in rows" :key="index">
                                <tr>
                                    <td class="px-3 py-3 text-gray-400 font-bold" x-text="index + 1"></td>
                                    <td class="px-3 py-3">
                                        <select :name="`caches[${index}][employee_id]`" x-model="row.employee_id" required
                                            class="w-full px-2 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                            <option value="">Selecione</option>
                                            @foreach($employees as $employee)
                                                <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->employee_code }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-3 py-3">
                                        <select :name="`caches[${index}][function_freelancer_id]`" x-model="row.function_freelancer_id" required
                                            class="w-full px-2 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                            <option value="">Selecione</option>
                                            @foreach($functions as $function)
                                                <option value="{{ $function->id }}">{{ $function->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input type="text" :name="`caches[${index}][location]`" x-model="row.location" required
                                            class="w-full px-2 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                    </td>
                                    <td class="px-3 py-3">
                                        <input type="date" :name="`caches[${index}][event_date]`" x-model="row.event_date" required
                                            class="px-2 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                    </td>
                                    <td class="px-3 py-3">
                                        <input type="time" :name="`caches[${index}][start_time]`" x-model="row.start_time" required
                                            class="px-2 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                    </td>
                                    <td class="px-3 py-3">
                                        <input type="time" :name="`caches[${index}][end_time]`" x-model="row.end_time" required
                                            class="px-2 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                        <span x-show="crossesMidnight(row)" class="block text-[11px] text-amber-600 dark:text-amber-400 font-bold">vira o dia</span>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                        <span x-text="durationLabel(row)"></span>
                                        <span class="block text-xs text-gray-400" x-text="billedLabel(row)"></span>
                                    </td>
                                    <td class="px-3 py-3 font-bold text-gray-900 dark:text-white whitespace-nowrap" x-text="formatMoney(rowPrice(row))"></td>
                                    <td class="px-3 py-3 text-right">
                                        <button type="button" @click="removeRow(index)" x-show="rows.length > 1"
                                            class="text-red-600 dark:text-red-400 hover:underline text-xs font-bold">Remover</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                    <button type="button" @click="addRow()"
                        class="px-4 py-2 text-sm font-bold text-[#A00001] dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition">
                        + Adicionar linha
                    </button>
                    <span class="ml-3 text-xs text-gray-400">Repete função, local, data e horários da linha anterior.</span>
                </div>
            </div>

            <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-2xl p-4 mb-6 text-sm text-indigo-800 dark:text-indigo-200">
                O horário informado aqui é o <strong>previsto</strong>. Quem informa o horário real é o próprio
                funcionário, ao assinar — e, se ele divergir do previsto, o cachê volta para a sua conferência e
                para a da gerência antes de ir ao financeiro.
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('employee-caches.index') }}" class="px-6 py-3 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancelar</a>
                <button type="submit" @disabled($functions->isEmpty())
                    class="px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition disabled:opacity-50">
                    Criar solicitação
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Prévia do valor enquanto o coordenador digita. A conta oficial é a do
    // servidor: FunctionFreelancer::cacheBilledHours() + a faixa da função.
    // Se as duas divergirem, quem vale é a de lá.
    function cacheRequestForm(rates, initialRows) {
        const emptyRow = {
            employee_id: '', function_freelancer_id: '', location: '',
            description: '', event_date: '', start_time: '', end_time: '',
        };

        return {
            rates,
            rows: initialRows.length ? initialRows : [{ ...emptyRow }],

            blankRow(previous = null) {
                return {
                    employee_id: '',
                    function_freelancer_id: previous?.function_freelancer_id ?? '',
                    location: previous?.location ?? '',
                    description: '',
                    event_date: previous?.event_date ?? '',
                    start_time: previous?.start_time ?? '',
                    end_time: previous?.end_time ?? '',
                };
            },

            addRow() {
                this.rows.push(this.blankRow(this.rows[this.rows.length - 1]));
            },

            removeRow(index) {
                this.rows.splice(index, 1);
            },

            minutes(row) {
                if (!row.start_time || !row.end_time) return 0;

                const [sh, sm] = row.start_time.split(':').map(Number);
                const [eh, em] = row.end_time.split(':').map(Number);
                let diff = (eh * 60 + em) - (sh * 60 + sm);

                if (diff <= 0) diff += 24 * 60; // virou a meia-noite

                return diff;
            },

            crossesMidnight(row) {
                if (!row.start_time || !row.end_time) return false;

                return row.end_time <= row.start_time;
            },

            // Soma 15 minutos e toma a hora cheia; piso de 2h, teto de 11h.
            billedHours(row) {
                const minutes = this.minutes(row);

                if (!minutes) return 0;

                return Math.min(11, Math.max(2, Math.floor((minutes + 15) / 60)));
            },

            durationLabel(row) {
                const minutes = this.minutes(row);

                if (!minutes) return '—';

                const h = Math.floor(minutes / 60);
                const m = minutes % 60;

                return m === 0 ? `${h}h` : `${h}h${String(m).padStart(2, '0')}`;
            },

            billedLabel(row) {
                const hours = this.billedHours(row);

                return hours ? `faixa de ${hours}h` : '';
            },

            rowPrice(row) {
                const table = this.rates[row.function_freelancer_id];
                const hours = this.billedHours(row);

                if (!table || !hours) return 0;

                return Number(table[hours] ?? 0);
            },

            get total() {
                return this.rows.reduce((sum, row) => sum + this.rowPrice(row), 0);
            },

            formatMoney(value) {
                return 'R$ ' + Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };
    }
</script>
</x-app-layout>
