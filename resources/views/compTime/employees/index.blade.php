{{--
    Cadastro de funcionários do Banco de Horas — a aba do RH.

    O funcionário continua nascendo da importação do espelho de ponto. Aqui se
    cuida do que o espelho não traz: setor, férias, afastamento e rescisão.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Banco de Horas — Funcionários
            </h2>
            <a href="{{ route('comp-time.index') }}"
               class="flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Voltar ao Banco de Horas
            </a>
        </div>
    </x-slot>

    <x-slot name="slot">
        @include('partials.alerts')

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Filtros --}}
            <form method="GET" action="{{ route('comp-time.employees.index') }}"
                  class="bg-white dark:bg-gray-800 rounded-xl shadow px-6 py-5">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Funcionário</label>
                        <input type="text" name="search" id="search"
                               value="{{ $filters['search'] ?? '' }}"
                               placeholder="Nome, matrícula ou CPF"
                               class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="sector_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Setor</label>
                        <select name="sector_id" id="sector_id"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">-- Todos --</option>
                            @foreach($sectors as $sectorId => $sectorName)
                                <option value="{{ $sectorId }}" {{ (string) ($filters['sector_id'] ?? '') === (string) $sectorId ? 'selected' : '' }}>{{ $sectorName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="situation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Situação</label>
                        <select name="situation" id="situation"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="active"     {{ ($filters['situation'] ?? 'active') === 'active'     ? 'selected' : '' }}>Ativos</option>
                            <option value="terminated" {{ ($filters['situation'] ?? '')       === 'terminated' ? 'selected' : '' }}>Desligados</option>
                            <option value="all"        {{ ($filters['situation'] ?? '')       === 'all'        ? 'selected' : '' }}>Todos</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-4">
                    @if(array_filter($filters))
                        <a href="{{ route('comp-time.employees.index') }}"
                           class="text-sm text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 transition-colors">
                            Limpar filtros
                        </a>
                    @else
                        <span></span>
                    @endif
                    <x-primary-button type="submit">Filtrar</x-primary-button>
                </div>
            </form>

            {{-- Listagem --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                        {{ $employees->total() }} funcionário(s)
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Funcionário</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Setor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cargo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Admissão</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Situação</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($employees as $employee)
                                @php $absence = $currentAbsences->get($employee->id); @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-6 py-3">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $employee->name }}</span>
                                        <span class="block text-xs text-gray-400">#{{ $employee->employee_code }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-gray-700 dark:text-gray-300">
                                        {{ $employee->sector?->name ?? $employee->department }}
                                        @unless($employee->sector)
                                            {{-- Sem setor resolvido: sobrou só o texto cru da estrutura. --}}
                                            <span class="ml-1 text-xs text-amber-600 dark:text-amber-400">(sem setor)</span>
                                        @endunless
                                    </td>
                                    <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $employee->position }}</td>
                                    <td class="px-6 py-3 text-gray-500 dark:text-gray-400">
                                        {{ $employee->admission_date?->format('d/m/Y') ?? '--' }}
                                    </td>
                                    <td class="px-6 py-3">
                                        @if($employee->isTerminated())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                                Desligado em {{ $employee->termination_date->format('d/m/Y') }}
                                            </span>
                                        @elseif($absence)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                {{ $absence->type_label }}{{ $absence->isOpenEnded() ? ' (em aberto)' : ' até ' . $absence->end_date->format('d/m/Y') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                                Ativo
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <a href="{{ route('comp-time.employees.show', $employee) }}"
                                           class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm font-medium">
                                            Gerenciar
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        Nenhum funcionário encontrado com esses filtros.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($employees->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $employees->links() }}
                    </div>
                @endif
            </div>

        </div>
    </x-slot>
</x-app-layout>
