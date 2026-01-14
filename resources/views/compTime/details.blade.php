<x-app-layout>
    <x-slot name="header">
       <div class="flex justify-between items-center">
        <div class="div">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Banco de Horas - Detalhes do Funcionário {{ $employee->name ?? '' }}
            </h2>
        </div>
        
        <div class="div">    
            
        </div>
    </x-slot>

    <x-slot name="slot">

        @php
            // Função para formatar minutos em horas e minutos
            $formatMinutes = function($mins) {
                $mins = abs($mins);
                $h = floor($mins / 60);
                $m = $mins % 60;
                return sprintf('%02d:%02d', $h, $m);
            };

            $checkPositive = function($mins) {
                return $mins >= 0;
            };

            $entries = $details['timeEntries'] ?? [];
        @endphp
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-12 print:py-0 print:px-0 print:w-full print:max-w-none">
        <!-- Dashboard Resumo -->
        @if(isset($dashboard))
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Créditos Totais</h3>
                    <p class="text-2xl font-bold text-green-600">{{ $formatMinutes($dashboard['total_credit_minutes'] ?? 0) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Débitos Totais</h3>
                    <p class="text-2xl font-bold text-red-600">{{ $formatMinutes($dashboard['total_debit_minutes'] ?? 0) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Saldo Atual</h3>
                    <p class="text-2xl font-bold {{ !$checkPositive($dashboard['net_balance_minutes'] ?? 0) ? 'text-red-600' : 'text-gray-700' }}">{{ !$checkPositive($dashboard['net_balance_minutes'] ?? 0) ? '-' : '' }}{{ $formatMinutes($dashboard['net_balance_minutes'] ?? 0) }}</p>
                </div>
            </div>
        @endif
        <!-- Tabela -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wider print:bg-gray-100">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left w-8"></th> <!-- Coluna do ícone de expandir -->
                                    <th scope="col" class="px-6 py-3 text-left">Data</th>
                                    <th scope="col" class="px-6 py-3 text-left">Horário Ref.</th>
                                    <th scope="col" class="px-6 py-3 text-left">Marcações</th>
                                    <th scope="col" class="px-6 py-3 text-center">Tipo</th>
                                    <th scope="col" class="px-6 py-3 text-right">Ocorrência</th>
                                    <th scope="col" class="px-6 py-3 text-right">Saldo Restante</th>
                                    <th scope="col" class="px-6 py-3 text-center">Validade</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                                @forelse($entries as $index => $entry)
                                    @php
                                        $entry = (object) $entry;
                                        
                                        // Proteção contra dados incompletos
                                        if (!isset($entry->entry_date)) continue;

                                        $date = \Carbon\Carbon::parse($entry->entry_date);
                                        $dueDate = (!empty($entry->due_date)) ? \Carbon\Carbon::parse($entry->due_date) : null;
                                        
                                        $rowClass = '';
                                        $amountClass = 'text-gray-500';
                                        
                                        $type = $entry->type ?? 'NONE';
                                        
                                        if ($type === 'CREDIT') {
                                            $rowClass = 'bg-green-50/30';
                                            $amountClass = 'text-green-600 font-medium';
                                        } elseif ($type === 'DEBIT') {
                                            $rowClass = 'bg-red-50/30';
                                            $amountClass = 'text-red-600 font-medium';
                                        }
                                        
                                        $amountMinutes = $entry->amount_minutes ?? 0;
                                        $balanceMinutes = $entry->balance_minutes ?? 0;

                                        // Ajustes (adjustments) - Verifica se existem
                                        // O JSON pode ter ajustes na propriedade "adjustments" ou "adjustments_as_target" dependendo de como foi carregado
                                        // Assumindo que vem como 'adjustments' conforme o padrão REST/JSON comum, ou 'adjustments_as_source'/'adjustments_as_target' se for direto do Eloquent sem resource.
                                        // Adaptar conforme a estrutura real do JSON.
                                        
                                        // Tentativa de pegar ajustes de várias chaves possíveis
                                        $countAdjustments = count($entry->adjustments ?? $entry->adjustments_as_target ?? $entry->adjustments_as_source ?? []);
                                        $hasAdjustments = $countAdjustments > 0;
                                        $adjustments = $entry->adjustments ?? $entry->adjustments_as_target ?? $entry->adjustments_as_source ?? [];
                                        
                                        // Gera um ID único para o accordion
                                        $rowId = 'row-' . $entry->id . '-' . $index;
                                    @endphp
                                    
                                    <!-- Linha Principal -->
                                    <tr class="hover:bg-gray-50 transition-colors {{ $rowClass }} {{ $hasAdjustments ? 'cursor-pointer' : '' }}" 
                                        @if($hasAdjustments) onclick="toggleRow('{{ $rowId }}')" @endif>
                                        <td class="px-6 py-3 whitespace-nowrap text-gray-500">
                                            @if($hasAdjustments)
                                                <svg id="icon-{{ $rowId }}" class="h-5 w-5 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-gray-900">
                                            {{ $date->format('d/m/Y') }}
                                            <span class="text-xs text-gray-400 block">{{ ucfirst($date->locale('pt_BR')->dayName) }}</span>
                                        </td>
                                        <td class="px-6 py-3 text-gray-500 whitespace-nowrap text-xs">
                                            {{ $entry->reference_time ?? '-' }}
                                        </td>
                                        <td class="px-6 py-3 text-gray-700 max-w-xs truncate" title="{{ $entry->entry_times ?? '' }}">
                                            {{ $entry->entry_times ?: '-' }}
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            @if($type === 'CREDIT')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Crédito</span>
                                            @elseif($type === 'DEBIT')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Débito</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">Padrão</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-right whitespace-nowrap {{ $amountClass }}">
                                            @if($amountMinutes > 0)
                                                {{ $formatMinutes($amountMinutes) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-right whitespace-nowrap font-bold text-gray-700">
                                            @if($balanceMinutes > 0)
                                                {{ $formatMinutes($balanceMinutes) }}
                                            @else
                                                <span class="text-gray-300">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-center whitespace-nowrap text-xs text-gray-500">
                                            @if($balanceMinutes > 0 && $dueDate)
                                                <span class="{{ $dueDate->isPast() ? 'text-red-500 font-bold' : '' }}">
                                                    {{ $dueDate->format('d/m/Y') }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>

                                    <!-- Linha de Detalhes (Accordion) -->
                                    @if($hasAdjustments)
                                        <tr id="{{ $rowId }}" class="hidden bg-gray-50">
                                            <td colspan="8" class="px-6 py-4">
                                                <div class="rounded-md bg-white border border-gray-200 p-4 shadow-inner">
                                                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Histórico de Compensações</h4>
                                                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg">
                                                        <table class="min-w-full divide-y divide-gray-300">
                                                            <thead class="bg-gray-50">
                                                                <tr>
                                                                    <th scope="col" class="py-2 pl-4 pr-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Valor Abatido</th>
                                                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Saldo Anterior</th>
                                                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Saldo Posterior</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                                @foreach($adjustments as $adj)
                                                                    @php $adj = (object) $adj; @endphp
                                                                    <tr>
                                                                        <td class="whitespace-nowrap py-2 pl-4 pr-3 text-xs text-gray-900">
                                                                            {{ \Carbon\Carbon::parse($adj->adjustment_date)->format('d/m/Y') }}
                                                                        </td>
                                                                        <td class="whitespace-nowrap px-3 py-2 text-xs text-right font-medium text-indigo-600">
                                                                            {{ $formatMinutes($adj->amount_minutes) }}
                                                                        </td>
                                                                        <td class="whitespace-nowrap px-3 py-2 text-xs text-right text-gray-500">
                                                                            {{ $formatMinutes($adj->amount_minutes_before ?? $adj->before_adjustment_minutes ?? 0) }}
                                                                        </td>
                                                                        <td class="whitespace-nowrap px-3 py-2 text-xs text-right text-gray-500">
                                                                            {{ $formatMinutes($adj->amount_minutes_after ?? $adj->after_adjustment_minutes ?? 0) }}
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">Nenhum registro encontrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
        </div>
        <!-- Script Simples para o Accordion -->
                    <script>
                        function toggleRow(rowId) {
                            const detailsRow = document.getElementById(rowId);
                            const icon = document.getElementById('icon-' + rowId);
                            
                            if (detailsRow) {
                                if (detailsRow.classList.contains('hidden')) {
                                    detailsRow.classList.remove('hidden');
                                    // Gira o ícone para baixo
                                    if(icon) icon.classList.add('rotate-90');
                                } else {
                                    detailsRow.classList.add('hidden');
                                    // Gira o ícone de volta
                                    if(icon) icon.classList.remove('rotate-90');
                                }
                            }
                        }
                    </script>
    </div>
    </x-slot>
</x-app-layout>