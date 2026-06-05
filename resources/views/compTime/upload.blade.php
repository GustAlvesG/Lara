<x-app-layout>
    <x-slot name="header">
       <div class="flex justify-between items-center">
        <div class="div">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Banco de Horas
            </h2>
        </div>
        
        @if($isCoordinator ?? false)
        <div class="div">

            <div class="flex items-center">

                 <!-- Form -->
            <form id="uploadForm" action="{{ route('comp-time.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-col items-center justify-center">
                @csrf

                <!-- Input Oculto -->
                <input id="arquivo" name="arquivo" type="file" class="hidden" accept=".html,.xls,.txt" onchange="fileSelected(this)">

                <div class="flex items-center gap-3 w-full justify-center">

                    <!-- Recalcular Horas - abre modal de confirmação -->
                    <button type="button" onclick="document.getElementById('modal-recalculate').classList.remove('hidden')"
                        class="flex items-center justify-center py-2 px-6 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-all duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Recalcular Horas
                    </button>

                    <!-- Botão Principal -->
                    <button id="mainBtn" type="button" onclick="handleMainClick()"
                        class="flex items-center justify-center py-2 px-6 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                        <svg id="uploadIcon" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        <span id="btnText">Carregar arquivo</span>
                    </button>

                    <!-- Label do Arquivo e Botão Cancelar (Inicialmente oculto) -->
                    <div id="fileInfo" class="hidden items-center gap-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded px-3 animate-fade-in">
                        <span id="fileName" class="text-sm text-gray-700 dark:text-gray-200 truncate max-w-[150px]"></span>
                        <button type="button" onclick="cancelFile()" class="text-gray-400 dark:text-gray-400 hover:text-red-500 dark:hover:text-red-400 focus:outline-none transition-colors" title="Cancelar">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

            </form>

            </div>

        </div>
        @endif
    </x-slot>

    <x-slot name="slot">
        @include('partials.alerts')

        @if($isCoordinator ?? false)
        <x-accordion>
            <x-slot name="title">
                <h2 class="text-xl text-gray-800 dark:text-white font-bold flex items-center">
                    <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    Pesquisa Funcionários por Setor
                </h2>
            </x-slot>

            <x-slot name="body">
                @include('compTime.partials.search', ['structures' => $structures])
            </x-slot>

        </x-accordion>
        @endif

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-12 print:py-0 print:px-0 print:w-full print:max-w-none">
        
        @if(empty($reportData))
            <div class="bg-white dark:bg-gray-800 p-12 text-center rounded-lg shadow">
                @if(!empty($filters) && array_filter($filters ?? []))
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 text-lg font-medium">Nenhum funcionário encontrado com os filtros aplicados.</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Tente ajustar os filtros de pesquisa acima.</p>
                @elseif($isCoordinator ?? false)
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 text-lg">Nenhum dado disponível. Importe um arquivo de espelho de ponto para começar.</p>
                @else
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 text-lg font-medium">Nenhum registro encontrado.</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Não foram encontrados registros de banco de horas para a sua matrícula.</p>
                @endif
            </div>
        @else
            @php $resultCount = count($reportData); @endphp
            <div class="flex items-center justify-between mb-2 px-1">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $resultCount }}</span>
                    {{ $resultCount === 1 ? 'funcionário encontrado' : 'funcionários encontrados' }}
                    @if(!empty($filters) && array_filter($filters ?? []))
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                            Filtro ativo
                        </span>
                    @endif
                </p>
            </div>
            

            @foreach($reportData as $data)
                @php
                    // Extração de Funcionário
                    $employeeRaw = $data['employee'] ?? null;
                    $employee = (object) $employeeRaw;

                    // Dados pré-calculados pelo service
                    $summary = $data['summary'] ?? [];
                    $totalCredits    = $summary['total_credits_minutes'] ?? 0;
                    $totalDebits     = $summary['total_debits_minutes'] ?? 0;
                    $netBalance      = $summary['net_balance_minutes'] ?? 0;
                    $writtenOffCount = $summary['written_off_count'] ?? 0;
                    $expiredBalance  = $summary['expired_balance_minutes'] ?? 0;
                    $expiredCount    = $summary['expired_count'] ?? 0;
                    $nextExpiryEntry = $summary['next_expiry_entry'] ?? null;
                    $daysToExpiry    = $summary['days_to_expiry'] ?? null;
                    $nextExpiryDate  = $nextExpiryEntry ? \Carbon\Carbon::parse($nextExpiryEntry->due_date) : null;
                    $nextExpiryBalance = $nextExpiryEntry ? $nextExpiryEntry->balance_minutes : 0;
                    $isPositiveExpiry  = $nextExpiryEntry && ($nextExpiryEntry->type ?? '') === 'CREDIT';

                    $isPositive   = $netBalance >= 0;
                    $hasToExpire  = $nextExpiryDate !== null;

                    $formatMinutes = function($mins) {
                        $mins = abs($mins);
                        $h = floor($mins / 60);
                        $m = $mins % 60;
                        return sprintf('%02d:%02d', $h, $m);
                    };
                @endphp

                <!-- Card do Funcionário -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700 print:shadow-none print:border-none print:break-inside-avoid page-break">

                    <!-- Cabeçalho -->
                    <div class="px-6 py-4 border-gray-200 dark:border-gray-700 print:bg-gray-100">
                        <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $employee->name ?? 'Nome Indisponível' }}</h2>
                                <div class="mt-1 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="flex items-center">
                                        <svg class="mr-1.5 h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0c0 .884-.95 2-2.122 2H5m14 0h-2.878C18.05 8 17 6.884 17 6m0 0a2 2 0 100-4 2 2 0 000 4zm-7 0a2 2 0 100-4 2 2 0 000 4z" />
                                        </svg>
                                        Matrícula: <span class="font-medium text-gray-900 dark:text-gray-100 ml-1">{{ $employee->employee_code ?? '--' }}</span>
                                    </span>
                                    <span class="flex items-center">
                                        <svg class="mr-1.5 h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        Cargo: <span class="font-medium text-gray-900 dark:text-gray-100 ml-1">{{ $employee->position ?? '--' }}</span>
                                    </span>
                                    <span class="flex items-center">
                                        <svg class="mr-1.5 h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        Depto: <span class="font-medium text-gray-900 dark:text-gray-100 ml-1">{{ $employee->department ?? '--' }}</span>
                                    </span>
                                    <span class="flex items-center">
                                        <svg class="mr-1.5 h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Admissão: <span class="font-medium text-gray-900 dark:text-gray-100 ml-1">{{ !empty($employee->admission_date) ? \Carbon\Carbon::parse($employee->admission_date)->format('d/m/Y') : '--' }}</span>
                                    </span>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span>Créditos: <strong class="text-green-600 dark:text-green-400">+{{ $formatMinutes($totalCredits) }} hs</strong></span>
                                    <span>Débitos: <strong class="text-red-600 dark:text-red-400">{{ $formatMinutes($totalDebits) }} hs</strong></span>
                                    @if($expiredBalance > 0)
                                        <span class="text-red-500 dark:text-red-400">Vencido: <strong>{{ $formatMinutes($expiredBalance) }} hs ({{ $expiredCount }})</strong></span>
                                    @endif
                                    @if($writtenOffCount > 0)
                                        <span class="text-gray-400 dark:text-gray-500">Baixas: <strong>{{ $writtenOffCount }}</strong></span>
                                    @endif
                                </div>
                                <div>
                                    <form action="{{ route('comp-time.show.details') }}" method="post">
                                        @csrf
                                        <input name="employee_id" type="text" value="{{ $employee->id ?? '' }}" class="hidden">
                                        <button type="submit" class="mt-3 inline-block text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium transition-colors">
                                            Ver detalhes dos registros &rarr;
                                        </button>
                                    </form>
                                </div>
                            </div>

                            @if($hasToExpire)
                            <div class="px-4 py-2 rounded-lg border flex flex-col items-end print:border-gray-300 print:bg-white
                                {{ $daysToExpiry > 30
                                    ? 'border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900'
                                    : 'border-red-700 bg-red-300 dark:border-red-700 dark:bg-red-900' }}">
                                <span class="text-xs uppercase font-bold text-gray-500 dark:text-gray-300 tracking-wider">Saldo a Expirar</span>
                                <div class="flex items-baseline mt-1">
                                    <span class="text-2xl font-extrabold {{ $daysToExpiry > 30 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                                        {{ $isPositiveExpiry ? '+' : '-' }}{{ $formatMinutes($nextExpiryBalance) }}<span class="ml-1 text-xs text-gray-500 dark:text-gray-400 font-medium">hs</span> {{ $nextExpiryDate ? $nextExpiryDate->format('d/m') : '--' }}
                                    </span>
                                </div>
                            </div>
                            @endif
                            <div class="px-4 py-2 rounded-lg border flex flex-col items-end print:border-gray-300 print:bg-white
                                {{ $isPositive
                                    ? 'border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900'
                                    : 'border-red-200 bg-red-50 dark:border-red-700 dark:bg-red-900' }}">
                                <span class="text-xs uppercase font-bold text-gray-500 dark:text-gray-300 tracking-wider">Saldo Líquido Atual</span>
                                <div class="flex items-baseline mt-1">
                                    <span class="text-2xl font-extrabold {{ $isPositive ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                                        {{ $isPositive ? '+' : '-' }}{{ $formatMinutes($netBalance) }}
                                    </span>
                                    <span class="ml-1 text-xs text-gray-500 dark:text-gray-400 font-medium">hs</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
    
    <style>
        @media print {
            body { background-color: white; }
            .page-break { page-break-inside: avoid; break-inside: avoid; margin-bottom: 2rem; border: 1px solid #ddd; }
        }
    </style>
    </x-slot>

    <x-slot name="css">
       
    </x-slot>




   
    <x-slot name="js">
        @if($isCoordinator ?? false)
        <script src="{{ asset('js/accordion.js') }}"></script>
        @endif
         <script>
        @if($isCoordinator ?? false)
        const fileInput = document.getElementById('arquivo');
        const mainBtn = document.getElementById('mainBtn');
        const btnText = document.getElementById('btnText');
        const uploadIcon = document.getElementById('uploadIcon');
        const fileInfo = document.getElementById('fileInfo');
        const fileNameSpan = document.getElementById('fileName');
        const form = document.getElementById('uploadForm');

        function handleMainClick() {
            // Se não tem arquivo, o botão serve para abrir a seleção
            if (fileInput.files.length === 0) {
                fileInput.click();
            } else {
                // Se tem arquivo, o botão serve para enviar
                form.submit();
            }
        }

        function fileSelected(input) {
            if (input.files && input.files[0]) {
                // Atualiza Nome do Arquivo
                fileNameSpan.textContent = input.files[0].name;
                
                // Mostra o container com nome e botão X
                fileInfo.classList.remove('hidden');
                fileInfo.classList.add('flex');
                
                // Muda texto e estilo do botão
                btnText.textContent = "Processar Arquivo";
                mainBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                mainBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                
                // Troca ícone para um "Check" ou "Play" (opcional, aqui mudei para um raio/processar)
                uploadIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />';
            }
        }

        function cancelFile() {
            // Limpa o input
            fileInput.value = '';
            
            // Esconde info do arquivo
            fileInfo.classList.add('hidden');
            fileInfo.classList.remove('flex');
            
            // Reseta botão para estado inicial
            btnText.textContent = "Carregar arquivo";
            mainBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            mainBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
            
            // Reseta ícone
            uploadIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>';
        }
        @endif
    </script>
    </x-slot>


    @if($isCoordinator ?? false)
    <!-- Modal de Confirmação - Recalcular Horas -->
    <div id="modal-recalculate" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center mr-3">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recalcular todos os saldos?</h3>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">
                Esta operação apagará todos os ajustes existentes e recalculará os saldos do banco de horas do zero.
                O processo pode demorar alguns minutos dependendo do volume de dados.
            </p>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-recalculate').classList.add('hidden')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    Cancelar
                </button>
                <form action="{{ route('comp-time.recalculate') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-md transition-colors">
                        Sim, recalcular
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif

</x-app-layout>
