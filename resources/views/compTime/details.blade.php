<x-app-layout>
    <x-slot name="header">
       <div class="flex justify-between items-center">
            <div class="div">
                @php
                    // Extração de dados altamente resiliente para capturar do JSON enviado
                    $entries = [];
                    $dashData = [];
                    $empData = [];

                    $sourceData = is_string($details ?? null) ? json_decode($details, true) : ($details ?? []);
                    
                    if (is_iterable($sourceData)) {
                        // Tenta extração direta se for array associativo
                        if (isset($sourceData['timeEntries'])) {
                            $entries = $sourceData['timeEntries'];
                        }
                        
                        // Itera caso seja a estrutura de 3 arrays como o JSON fornecido
                        foreach ($sourceData as $item) {
                            if (is_array($item) || is_object($item)) {
                                $arr = (array) $item;
                                if (array_key_exists('timeEntries', $arr)) {
                                    $entries = $arr['timeEntries'];
                                } elseif (array_key_exists('employee_code', $arr)) {
                                    $empData = $arr;
                                } elseif (array_key_exists('total_credit_minutes', $arr)) {
                                    $dashData = $arr;
                                }
                            }
                        }
                    }

                    $employeeObj = (object) ($empData ?: ($employee ?? []));
                @endphp
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Banco de Horas - Detalhes do Funcionário {{ $employeeObj->name ?? '--' }}
                </h2>
            </div>
            
            <div class="print-hidden">    
                <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Exportar PDF / Imprimir
                </button>
            </div>
       </div>
    </x-slot>

    <x-slot name="slot">

        <style>
            /* Lógica de Filtro CSS */
            .filter-balance .entry-row[data-has-balance="false"],
            .filter-balance .detail-row[data-has-balance="false"] {
                display: none !important;
            }

            .filter-occurrences .entry-row[data-is-occurrence="false"],
            .filter-occurrences .detail-row[data-is-occurrence="false"] {
                display: none !important;
            }

            .filter-expired .entry-row[data-is-expired="false"],
            .filter-expired .detail-row[data-is-expired="false"] {
                display: none !important;
            }

            /* Regras de CSS específicas para Impressão/PDF */
            @media print {
                body, main { background-color: white !important; color: black !important; }
                header, nav, aside, footer { display: none !important; }
                .print-hidden { display: none !important; }
                .print-only { display: block !important; }
                .avoid-break { page-break-inside: avoid; }
                .page-break { page-break-before: always; }
                main { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
            }
        </style>

        @php
            $formatMinutes = function($mins) {
                $isNegative = $mins < 0;
                $mins = abs($mins);
                $h = floor($mins / 60);
                $m = $mins % 60;
                return ($isNegative ? '-' : '') . sprintf('%02d:%02d', $h, $m);
            };

            $checkPositive = function($mins) {
                return $mins >= 0;
            };

            // Totais
            $totalCredit = $dashData['total_credit_minutes'] ?? $dashboard['total_credit_minutes'] ?? 0;
            $totalDebit = $dashData['total_debit_minutes'] ?? $dashboard['total_debit_minutes'] ?? 0;
            $netBalance = $dashData['net_balance_minutes'] ?? $dashboard['net_balance_minutes'] ?? 0;

            // Ordenação
            $sortedEntries = collect($entries)->sortBy('entry_date');
        @endphp
    
    <div id="report-container">
        <!-- ========================================== -->
        <!-- VISUALIZAÇÃO DE TELA (ACCORDION INTERATIVO) -->
        <!-- ========================================== -->
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-8 print-hidden">
            
            <!-- Dashboard Resumo -->
            {{-- <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Créditos Totais</h3>
                    <p class="text-2xl font-bold text-green-600">{{ $formatMinutes($totalCredit) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Débitos Totais</h3>
                    <p class="text-2xl font-bold text-red-600">{{ $formatMinutes($totalDebit) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Saldo Atual</h3>
                    <p class="text-2xl font-bold {{ !$checkPositive($netBalance) ? 'text-red-600' : 'text-gray-700 dark:text-gray-300' }}">
                        {{ !$checkPositive($netBalance) ? '-' : '' }}{{ $formatMinutes(abs($netBalance)) }}
                    </p>
                </div>
            </div> --}}

            <!-- Filtros de Relatório -->
            <div class="flex flex-col md:flex-row justify-between items-center bg-white dark:bg-gray-800 p-4 shadow rounded-lg gap-4">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                    Visualização:
                </div>
                <div class="inline-flex flex-wrap justify-center rounded-md shadow-sm" role="group">
                    <button type="button" onclick="setFilter('all')" id="btn-filter-all" class="px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-100 border border-indigo-200 rounded-l-lg dark:bg-indigo-900 dark:text-indigo-300 dark:border-indigo-700 focus:z-10 focus:ring-2 focus:ring-indigo-500 transition-colors">
                        Completo
                    </button>
                    <button type="button" onclick="setFilter('occurrences')" id="btn-filter-occurrences" class="-ml-px px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700 focus:z-10 focus:ring-2 focus:ring-indigo-500 transition-colors">
                        Apenas Ocorrências
                    </button>
                    <button type="button" onclick="setFilter('balance')" id="btn-filter-balance" class="-ml-px px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700 focus:z-10 focus:ring-2 focus:ring-indigo-500 transition-colors">
                        Com Saldo
                    </button>
                    <button type="button" onclick="setFilter('expired')" id="btn-filter-expired" class="-ml-px px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-r-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700 focus:z-10 focus:ring-2 focus:ring-indigo-500 transition-colors">
                        Com Saldo Vencido
                    </button>
                </div>
            </div>
            
            <!-- Tabela Accordion -->
            <div class="overflow-x-auto shadow rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left w-8"></th>
                            <th scope="col" class="px-6 py-3 text-left">Data</th>
                            <th scope="col" class="px-6 py-3 text-left">Horário Ref.</th>
                            <th scope="col" class="px-6 py-3 text-left">Marcações</th>
                            <th scope="col" class="px-6 py-3 text-center">Tipo</th>
                            <th scope="col" class="px-6 py-3 text-right">Ocorrência</th>
                            <th scope="col" class="px-6 py-3 text-right">Saldo Restante</th>
                            <th scope="col" class="px-6 py-3 text-center">Validade</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        @forelse($sortedEntries as $index => $entry)
                            @php
                                $entry = (object) $entry;
                                
                                if (!isset($entry->entry_date)) continue;

                                $date = \Carbon\Carbon::parse($entry->entry_date);
                                $dueDate = (!empty($entry->due_date)) ? \Carbon\Carbon::parse($entry->due_date) : null;
                                
                                $type = $entry->type ?? 'NONE';
                                $rowClass = '';
                                $amountClass = 'text-gray-500 dark:text-gray-400';
                                
                                $isCredit = strcasecmp($type, 'CREDIT') === 0;
                                $isDebit = strcasecmp($type, 'DEBIT') === 0;
                                
                                if ($isCredit) {
                                    $rowClass = 'bg-green-50/30 dark:bg-green-900/20';
                                    $amountClass = 'text-green-600 dark:text-green-400 font-medium';
                                } elseif ($isDebit) {
                                    $rowClass = 'bg-red-50/30 dark:bg-red-900/20';
                                    $amountClass = 'text-red-600 dark:text-red-400 font-medium';
                                }
                                
                                $amountMinutes = $entry->amount_minutes ?? 0;
                                $balanceMinutes = $entry->balance_minutes ?? 0;
                                
                                // Variáveis de controle para os filtros
                                $hasBalance = $balanceMinutes > 0 ? 'true' : 'false'; 
                                $isOccurrence = ($isCredit || $isDebit) ? 'true' : 'false';
                                $isExpired = ($balanceMinutes > 0 && $dueDate && $dueDate->endOfDay()->isPast()) ? 'true' : 'false';

                                $adjustments = $entry->adjustments ?? $entry->adjustments_as_target ?? $entry->adjustments_as_source ?? [];
                                $hasAdjustments = count($adjustments) > 0;
                                
                                $rowId = 'row-' . ($entry->id ?? $index);
                            @endphp
                            
                            <!-- Linha Principal -->
                            <tr class="entry-row hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $rowClass }} {{ $hasAdjustments ? 'cursor-pointer' : '' }}" 
                                data-has-balance="{{ $hasBalance }}"
                                data-is-occurrence="{{ $isOccurrence }}"
                                data-is-expired="{{ $isExpired }}"
                                @if($hasAdjustments) onclick="toggleRow('{{ $rowId }}')" @endif>
                                <td class="px-6 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    @if($hasAdjustments)
                                        <svg id="icon-{{ $rowId }}" class="h-5 w-5 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    @endif
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100">
                                    {{ $date->format('d/m/Y') }}
                                    <span class="text-xs text-gray-400 dark:text-gray-500 block">{{ ucfirst($date->locale('pt_BR')->dayName) }}</span>
                                </td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap text-xs">
                                    {{ $entry->reference_time ?? '-' }}
                                </td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300 max-w-xs truncate" title="{{ $entry->entry_times ?? '' }}">
                                    {{ $entry->entry_times ?: '-' }}
                                </td>
                                <td class="px-6 py-3 text-center">
                                    @if($isCredit)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Crédito</span>
                                    @elseif($isDebit)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Débito</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Padrão</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right whitespace-nowrap {{ $amountClass }}">
                                    @if($amountMinutes > 0)
                                        {{ $formatMinutes($amountMinutes) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right whitespace-nowrap font-bold text-gray-700 dark:text-gray-200">
                                    @if($balanceMinutes > 0)
                                        {{ $formatMinutes($balanceMinutes) }}
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-center whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                    @if($balanceMinutes > 0 && $dueDate)
                                        <span class="{{ $dueDate->isPast() ? 'text-red-500 dark:text-red-400 font-bold' : '' }}">
                                            {{ $dueDate->format('d/m/Y') }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>

                            <!-- Linha de Detalhes (Accordion) -->
                            @if($hasAdjustments)
                                <tr id="{{ $rowId }}" class="detail-row hidden bg-gray-50 dark:bg-gray-800" data-has-balance="{{ $hasBalance }}" data-is-occurrence="{{ $isOccurrence }}" data-is-expired="{{ $isExpired }}">
                                    <td colspan="8" class="px-6 py-4">
                                        <div class="rounded-md bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4 shadow-inner">
                                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Histórico de Compensações</h4>
                                            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg">
                                                <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-700">
                                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                                        <tr>
                                                            <th scope="col" class="py-2 pl-4 pr-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data Ref.</th>
                                                            <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor Abatido</th>
                                                            <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Saldo Anterior</th>
                                                            <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Saldo Posterior</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                                                        @foreach($adjustments as $adj)
                                                            @php $adj = (object) $adj; @endphp
                                                            <tr>
                                                                <td class="whitespace-nowrap py-2 pl-4 pr-3 text-xs text-gray-900 dark:text-gray-100">
                                                                    {{ isset($adj->adjustment_date) ? \Carbon\Carbon::parse($adj->adjustment_date)->format('d/m/Y') : '-' }}
                                                                </td>
                                                                <td class="whitespace-nowrap px-3 py-2 text-xs text-right font-medium text-indigo-600 dark:text-indigo-400">
                                                                    {{ $formatMinutes($adj->amount_minutes ?? 0) }}
                                                                </td>
                                                                <td class="whitespace-nowrap px-3 py-2 text-xs text-right text-gray-500 dark:text-gray-400">
                                                                    {{ $formatMinutes($adj->before_adjustment_minutes ?? $adj->amount_minutes_before ?? 0) }}
                                                                </td>
                                                                <td class="whitespace-nowrap px-3 py-2 text-xs text-right text-gray-500 dark:text-gray-400">
                                                                    {{ $formatMinutes($adj->after_adjustment_minutes ?? $adj->amount_minutes_after ?? 0) }}
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
                            <tr class="always-empty">
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">Nenhum registro encontrado no banco de dados.</td>
                            </tr>
                        @endforelse
                        
                        <!-- Linha de Feedback de Filtro Vazio -->
                        <tr class="empty-filter-msg hidden">
                            <td colspan="8" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span class="text-gray-500 dark:text-gray-400 text-base font-medium">Nenhum registro correspondente a esse filtro.</span>
                                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Experimente alterar a visualização acima para encontrar o que procura.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Scripts Interativos -->
            <script>
                function toggleRow(rowId) {
                    const detailsRow = document.getElementById(rowId);
                    const icon = document.getElementById('icon-' + rowId);
                    
                    if (detailsRow) {
                        if (detailsRow.classList.contains('hidden')) {
                            detailsRow.classList.remove('hidden');
                            if(icon) icon.classList.add('rotate-90');
                        } else {
                            detailsRow.classList.add('hidden');
                            if(icon) icon.classList.remove('rotate-90');
                        }
                    }
                }

                function setFilter(type) {
                    const container = document.getElementById('report-container');
                    const btns = {
                        all: document.getElementById('btn-filter-all'),
                        occurrences: document.getElementById('btn-filter-occurrences'),
                        balance: document.getElementById('btn-filter-balance'),
                        expired: document.getElementById('btn-filter-expired')
                    };
                    const printSubtitle = document.getElementById('print-report-type');

                    const activeClasses = ['bg-indigo-100', 'text-indigo-700', 'border-indigo-200', 'dark:bg-indigo-900', 'dark:text-indigo-300', 'dark:border-indigo-700'];
                    const inactiveClasses = ['bg-white', 'text-gray-700', 'border-gray-200', 'dark:bg-gray-800', 'dark:text-gray-300', 'dark:border-gray-700', 'hover:bg-gray-50', 'dark:hover:bg-gray-700'];

                    container.classList.remove('filter-balance', 'filter-occurrences', 'filter-expired');
                    
                    Object.values(btns).forEach(btn => {
                        if(btn) {
                            btn.classList.remove(...activeClasses);
                            btn.classList.add(...inactiveClasses);
                        }
                    });

                    if (type === 'balance') {
                        container.classList.add('filter-balance'); 
                        btns.balance.classList.remove(...inactiveClasses);
                        btns.balance.classList.add(...activeClasses);
                        if(printSubtitle) printSubtitle.innerText = '(Apenas com Saldo)';
                    } else if (type === 'occurrences') {
                        container.classList.add('filter-occurrences'); 
                        btns.occurrences.classList.remove(...inactiveClasses);
                        btns.occurrences.classList.add(...activeClasses);
                        if(printSubtitle) printSubtitle.innerText = '(Apenas Ocorrências)';
                    } else if (type === 'expired') {
                        container.classList.add('filter-expired'); 
                        btns.expired.classList.remove(...inactiveClasses);
                        btns.expired.classList.add(...activeClasses);
                        if(printSubtitle) printSubtitle.innerText = '(Com Saldo Vencido)';
                    } else {
                        btns.all.classList.remove(...inactiveClasses);
                        btns.all.classList.add(...activeClasses);
                        if(printSubtitle) printSubtitle.innerText = '(Completo)';
                    }
                    
                    // Valida se ficou alguma linha visível no container principal da tela
                    let hasVisible = false;
                    const rows = document.querySelectorAll('.print-hidden .entry-row');
                    
                    rows.forEach(row => {
                        let show = true;
                        if (type === 'balance' && row.getAttribute('data-has-balance') === 'false') show = false;
                        if (type === 'occurrences' && row.getAttribute('data-is-occurrence') === 'false') show = false;
                        if (type === 'expired' && row.getAttribute('data-is-expired') === 'false') show = false;
                        
                        if(show) hasVisible = true;
                    });
                    
                    // Mostra ou oculta feedback de lista vazia
                    document.querySelectorAll('.empty-filter-msg').forEach(msg => {
                        if (!hasVisible && rows.length > 0) {
                            msg.classList.remove('hidden');
                            msg.style.display = 'table-row';
                        } else {
                            msg.classList.add('hidden');
                            msg.style.display = 'none';
                        }
                    });
                }
            </script>
        </div>

        <!-- ========================================== -->
        <!-- VISUALIZAÇÃO DE IMPRESSÃO (PDF EXPORT) -->
        <!-- ========================================== -->
        <div class="hidden print-only bg-white text-black p-8 mx-auto w-full max-w-5xl">
            
            <!-- Cabeçalho do Relatório (PDF) -->
            <div class="border-b border-gray-300 pb-4 mb-6 flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 uppercase tracking-tight">
                        Extrato - Banco de Horas <span id="print-report-type" class="text-lg text-indigo-700 font-medium normal-case ml-2">(Completo)</span>
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Gerado em {{ \Carbon\Carbon::now()->format('d/m/Y \à\s H:i') }}</p>
                </div>
                <div class="text-right">
                    <div class="text-xl font-extrabold text-indigo-800">Clube dos Funcionários</div>
                    <p class="text-xs text-gray-500">Banco de Horas</p>
                </div>
            </div>

            <!-- Dados do Colaborador (PDF) -->
            <div class="bg-gray-50 p-4 rounded-lg mb-6 flex flex-col sm:flex-row justify-between items-center border border-gray-200">
                <div class="w-full sm:w-2/3">
                    <h2 class="text-lg font-bold text-gray-800">{{ $employeeObj->name ?? '--' }}</h2>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 mt-2 text-sm text-gray-600">
                        <div><strong class="text-gray-900">Matrícula:</strong> {{ $employeeObj->employee_code ?? '--' }}</div>
                        <div><strong class="text-gray-900">CPF:</strong> {{ $employeeObj->cpf ?? '--' }}</div>
                        <div><strong class="text-gray-900">Cargo:</strong> {{ $employeeObj->position ?? '--' }}</div>
                        <div><strong class="text-gray-900">Depto:</strong> {{ $employeeObj->department ?? '--' }}</div>
                    </div>
                </div>
                
                {{-- <div class="w-full sm:w-1/3 mt-4 sm:mt-0 text-right">
                    <div class="inline-block bg-white border border-gray-300 rounded p-3 shadow-sm text-center min-w-[180px]">
                        <span class="block text-xs uppercase tracking-wider text-gray-500 font-bold mb-1">Saldo Atual</span>
                        @php
                            $pdfIsPositive = $netBalance >= 0;
                        @endphp
                        <span class="text-2xl font-black {{ $pdfIsPositive ? 'text-green-600' : 'text-red-600' }}">
                            {{ $pdfIsPositive ? '+' : '-' }}{{ $formatMinutes(abs($netBalance)) }}
                        </span>
                    </div>
                </div> --}}
            </div>

            <!-- Tabela Estática de Ocorrências (PDF) -->
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-left text-sm border-collapse">
                    <thead class="bg-gray-100 border-b border-gray-200 text-gray-600">
                        <tr>
                            <th class="p-2 font-semibold uppercase text-xs w-24">Data</th>
                            <th class="p-2 font-semibold uppercase text-xs">Apontamentos</th>
                            <th class="p-2 font-semibold uppercase text-xs text-center w-20">Tipo</th>
                            <th class="p-2 font-semibold uppercase text-xs text-right w-20">Original</th>
                            <th class="p-2 font-semibold uppercase text-xs text-right w-20">Saldo</th>
                            <th class="p-2 font-semibold uppercase text-xs text-center w-24">Validade</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($sortedEntries as $entry)
                            @php
                                $entry = (object) $entry;
                                if (!isset($entry->entry_date)) continue;

                                $date = \Carbon\Carbon::parse($entry->entry_date);
                                $dueDate = (!empty($entry->due_date)) ? \Carbon\Carbon::parse($entry->due_date) : null;
                                
                                $type = $entry->type ?? 'NONE';
                                $isCredit = strcasecmp($type, 'CREDIT') === 0;
                                $isDebit = strcasecmp($type, 'DEBIT') === 0;
                                
                                $typeLabel = $isCredit ? 'Crédito' : ($isDebit ? 'Débito' : 'Padrão');
                                
                                $amountMinutes = $entry->amount_minutes ?? 0;
                                $balanceMinutes = $entry->balance_minutes ?? 0;
                                
                                $hasBalance = $balanceMinutes > 0 ? 'true' : 'false';
                                $isOccurrence = ($isCredit || $isDebit) ? 'true' : 'false';
                                $isExpired = ($balanceMinutes > 0 && $dueDate && $dueDate->endOfDay()->isPast()) ? 'true' : 'false';

                                $adjustments = $entry->adjustments ?? $entry->adjustments_as_target ?? $entry->adjustments_as_source ?? [];
                                $hasAdjustments = count($adjustments) > 0;
                            @endphp
                            
                            <tr class="entry-row avoid-break {{ $hasAdjustments ? 'border-b-0 bg-gray-50/30' : '' }}" 
                                data-has-balance="{{ $hasBalance }}" data-is-occurrence="{{ $isOccurrence }}" data-is-expired="{{ $isExpired }}">
                                <td class="p-2 align-top">
                                    <div class="font-medium text-gray-900">{{ $date->format('d/m/Y') }}</div>
                                </td>
                                <td class="p-2 align-top text-gray-600">
                                    <div class="text-[10px] text-gray-400 mb-0.5">{{ $entry->reference_time ?? '-' }}</div>
                                    <div class="font-mono text-xs">{{ $entry->entry_times ?: 'Sem registo' }}</div>
                                </td>
                                <td class="p-2 align-top text-center">
                                    @if($amountMinutes > 0)
                                        <span class="inline-block px-1.5 py-0.5 text-[9px] font-bold uppercase rounded border border-gray-300 {{ $isCredit ? 'text-green-700 bg-green-50' : ($isDebit ? 'text-red-700 bg-red-50' : 'text-gray-700') }}">
                                            {{ $typeLabel }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-xs">-</span>
                                    @endif
                                </td>
                                <td class="p-2 align-top text-right font-medium {{ $isCredit ? 'text-green-600' : ($isDebit ? 'text-red-600' : 'text-gray-500') }}">
                                    {{ $amountMinutes > 0 ? $formatMinutes($amountMinutes) : '-' }}
                                </td>
                                <td class="p-2 align-top text-right font-bold text-gray-900">
                                    {{ $balanceMinutes > 0 ? $formatMinutes($balanceMinutes) : '00:00' }}
                                </td>
                                <td class="p-2 align-top text-center text-xs text-gray-500">
                                    @if($balanceMinutes > 0 && $dueDate)
                                        <span class="{{ $dueDate->isPast() ? 'text-red-600 font-bold underline' : '' }}">
                                            {{ $dueDate->format('d/m/Y') }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>

                            <!-- Histórico Recuado (Apenas visível no PDF) -->
                            @if($hasAdjustments)
                                <tr class="detail-row bg-gray-50/50 avoid-break border-t-0" data-has-balance="{{ $hasBalance }}" data-is-occurrence="{{ $isOccurrence }}" data-is-expired="{{ $isExpired }}">
                                    <td colspan="6" class="p-0 pb-3">
                                        <div class="pl-14 pr-4 py-2 border-l-2 border-indigo-200 ml-4 mb-1 rounded-r-md">
                                            <div class="text-[10px] font-bold text-indigo-700 uppercase tracking-wide mb-1 flex items-center">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                                Histórico de Compensações
                                            </div>
                                            <ul class="space-y-1">
                                                @foreach($adjustments as $adj)
                                                    @php $adj = (object) $adj; @endphp
                                                    <li class="text-[11px] text-gray-600 flex justify-between items-center bg-white py-1 px-2 border border-gray-100 shadow-sm rounded">
                                                        <span>
                                                            <span class="text-gray-400 font-bold mr-1">↳</span>
                                                            Ref. {{ isset($adj->adjustment_date) ? \Carbon\Carbon::parse($adj->adjustment_date)->format('d/m/Y') : '' }} {{ $adj->reason ?? $adj->description ?? '' }}
                                                        </span>
                                                        <div class="text-right">
                                                            <span class="text-gray-400">Abatido:</span> 
                                                            <strong class="text-gray-900 mx-1">{{ $formatMinutes($adj->amount_minutes ?? 0) }}</strong> 
                                                            <span class="text-gray-300">|</span> 
                                                            <span class="text-gray-400 ml-1">Ficou:</span> 
                                                            <span class="font-medium text-gray-700">{{ $formatMinutes($adj->after_adjustment_minutes ?? $adj->amount_minutes_after ?? 0) }}</span>
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr class="always-empty">
                                <td colspan="6" class="p-6 text-center text-gray-500">Nenhum registro no banco de dados.</td>
                            </tr>
                        @endforelse
                        
                        <!-- Linha de Feedback de Filtro Vazio PDF -->
                        <tr class="empty-filter-msg hidden">
                            <td colspan="6" class="p-6 text-center text-gray-500">
                                Nenhum registro corresponde a esse filtro.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 text-center text-xs text-gray-400 border-t border-gray-200 pt-4">
                Relatório gerado pelo Lara. Confidencial.
            </div>
        </div>
    </div>
    </x-slot>
</x-app-layout>