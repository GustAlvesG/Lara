{{--
    Ficha de cadastro de um funcionário (aba do RH).

    Férias, afastamento e rescisão são informativos: não mexem na importação do
    espelho de ponto nem no cálculo de saldo. A ficha de quem saiu continua
    inteira no Banco de Horas.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $employee->name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Matrícula {{ $employee->employee_code }}
                    @if($employee->cpf) — CPF {{ $employee->cpf }} @endif
                </p>
            </div>
            <a href="{{ route('comp-time.employees.index') }}"
               class="flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Voltar
            </a>
        </div>
    </x-slot>

    <x-slot name="slot">
        @include('partials.alerts')

        <div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Resumo vindo da importação --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow px-6 py-5">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wide">Cargo</span>
                        <span class="text-gray-800 dark:text-gray-200">{{ $employee->position ?: '--' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wide">Estrutura (ponto)</span>
                        <span class="text-gray-800 dark:text-gray-200">{{ $employee->department ?: '--' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wide">Admissão</span>
                        <span class="text-gray-800 dark:text-gray-200">{{ $employee->admission_date?->format('d/m/Y') ?? '--' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wide">Usuário do sistema</span>
                        @if($employee->user)
                            <span class="text-gray-800 dark:text-gray-200">{{ $employee->user->name }}</span>
                        @else
                            {{-- O vínculo é por matrícula (users.matricula =
                                 employees.employee_code) e não existe FK: um
                                 lado existe sem o outro. --}}
                            <span class="text-amber-600 dark:text-amber-400">Sem usuário vinculado</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Setor e rescisão --}}
            <form method="POST" action="{{ route('comp-time.employees.update', $employee) }}"
                  class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                @csrf
                @method('PUT')

                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">Cadastro</h3>
                </div>

                <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="sector_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Setor</label>
                        <select name="sector_id" id="sector_id"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">-- Sem setor --</option>
                            @foreach($sectors as $sectorId => $sectorName)
                                <option value="{{ $sectorId }}" {{ (string) old('sector_id', $employee->sector_id) === (string) $sectorId ? 'selected' : '' }}>{{ $sectorName }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            É por ele que o coordenador enxerga a equipe.
                        </p>
                        <x-input-error :messages="$errors->get('sector_id')" class="mt-1" />
                    </div>

                    <div>
                        <label for="termination_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Rescisão</label>
                        <input type="date" name="termination_date" id="termination_date"
                               value="{{ old('termination_date', $employee->termination_date?->format('Y-m-d')) }}"
                               class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Informativo — não apaga nem congela o banco de horas.
                        </p>
                        <x-input-error :messages="$errors->get('termination_date')" class="mt-1" />
                    </div>

                    <div>
                        <label for="termination_reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Motivo</label>
                        <input type="text" name="termination_reason" id="termination_reason"
                               value="{{ old('termination_reason', $employee->termination_reason) }}"
                               placeholder="Pedido de demissão, dispensa..."
                               class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Limpar a data limpa o motivo junto.
                        </p>
                        <x-input-error :messages="$errors->get('termination_reason')" class="mt-1" />
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/30 flex justify-end">
                    <x-primary-button type="submit">Salvar cadastro</x-primary-button>
                </div>
            </form>

            {{-- Férias e afastamentos --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">Férias e afastamentos</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        A data de início é obrigatória. Deixar o fim em branco registra um período
                        <strong>em aberto</strong> — o normal quando ainda não se sabe a data de retorno.
                    </p>
                </div>

                <form method="POST" action="{{ route('comp-time.employees.absences.store', $employee) }}"
                      class="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                            <select name="type" id="type"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @foreach(\App\Models\EmployeeAbsence::TYPES as $value => $label)
                                    <option value="{{ $value }}" {{ old('type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Início</label>
                            <input type="date" name="start_date" id="start_date" required value="{{ old('start_date') }}"
                                   class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fim (opcional)</label>
                            <input type="date" name="end_date" id="end_date" value="{{ old('end_date') }}"
                                   class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Observação</label>
                            <input type="text" name="notes" id="notes" value="{{ old('notes') }}"
                                   class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <x-primary-button type="submit" class="w-full justify-center">Registrar</x-primary-button>
                        </div>
                    </div>

                    @if($errors->any())
                        <div class="mt-3 space-y-1">
                            @foreach($errors->all() as $error)
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Início</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fim</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Observação</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($employee->absences as $absence)
                                <tr class="{{ $absence->coversDate(now()) ? 'bg-amber-50/60 dark:bg-amber-900/10' : '' }}">
                                    <td class="px-6 py-3 text-gray-800 dark:text-gray-200">
                                        {{ $absence->type_label }}
                                        @if($absence->coversDate(now()))
                                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                Vigente
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $absence->start_date->format('d/m/Y') }}</td>
                                    <td class="px-6 py-3 text-gray-700 dark:text-gray-300">
                                        {{ $absence->end_date?->format('d/m/Y') ?? 'Em aberto' }}
                                    </td>
                                    <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $absence->notes ?: '--' }}</td>
                                    <td class="px-6 py-3 text-right">
                                        <form method="POST" action="{{ route('comp-time.employees.absences.destroy', [$employee, $absence]) }}"
                                              onsubmit="return confirm('Remover este registro?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Nenhuma férias ou afastamento registrado.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </x-slot>
</x-app-layout>
