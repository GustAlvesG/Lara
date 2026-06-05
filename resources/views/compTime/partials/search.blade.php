<div class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
    <form method="POST" action="{{ route('comp-time.index.filter') }}">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

            <!-- Estrutura -->
            <div>
                <label for="search_structure" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Setor</label>
                <select id="search_structure" name="structure"
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">-- Todos os Setores --</option>
                    @foreach($structures as $structure)
                        <option value="{{ $structure }}" {{ ($filters['structure'] ?? '') === $structure ? 'selected' : '' }}>{{ $structure }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Nome do Funcionário -->
            <div>
                <label for="search_employee_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nome do Funcionário</label>
                <input type="text" name="employee_name" id="search_employee_name"
                       class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       placeholder="Digite o nome do funcionário"
                       value="{{ $filters['employee_name'] ?? '' }}">
            </div>

            <!-- Código do Funcionário e Status -->
            <div>
                <div class="flex space-x-2">
                    <div class="flex-1">
                        <label for="search_employee_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Matrícula</label>
                        <input type="text" name="employee_code" id="search_employee_code"
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="Digite a matrícula"
                        value="{{ $filters['employee_code'] ?? '' }}">
                    </div>
                    <div class="flex-1">
                        <label for="search_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select id="search_status" name="status"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">-- Qualquer Status --</option>
                            <option value="with_balance"    {{ ($filters['status'] ?? '') === 'with_balance'    ? 'selected' : '' }}>Pendente de Compensação</option>
                            <option value="without_balance" {{ ($filters['status'] ?? '') === 'without_balance' ? 'selected' : '' }}>Compensado</option>
                            <option value="credit_only"     {{ ($filters['status'] ?? '') === 'credit_only'     ? 'selected' : '' }}>Apenas Horas Extras</option>
                            <option value="debit_only"      {{ ($filters['status'] ?? '') === 'debit_only'      ? 'selected' : '' }}>Apenas Horas Faltantes</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Período Início e Fim -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Período</label>
                <div class="flex space-x-2">
                    <input type="date" name="period_start" id="search_period_start"
                           class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                           value="{{ $filters['period_start'] ?? '' }}">
                    <input type="date" name="period_end" id="search_period_end"
                           class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                           value="{{ $filters['period_end'] ?? '' }}">
                </div>
            </div>
        </div>

        <div class="flex justify-between items-center pt-2">
            @if(!empty($filters) && array_filter($filters))
                <a href="{{ route('comp-time.index') }}" class="text-sm text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 transition-colors">
                    Limpar filtros
                </a>
            @else
                <span></span>
            @endif
            <x-primary-button type="submit">
                Filtrar
            </x-primary-button>
        </div>

    </form>
</div>
