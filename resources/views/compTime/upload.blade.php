<x-app-layout>
    <x-slot name="header">
       <div class="flex justify-between items-center">
        <div class="div">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Banco de Horas
            </h2>
        </div>
        
        <div class="div">

            <div class="flex items-center"> 
            
                 <!-- Form -->
            <form id="uploadForm" action="{{ route('comp-time.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-col items-center justify-center">
                @csrf
                
                <!-- Input Oculto -->
                <input id="arquivo" name="arquivo" type="file" class="hidden" accept=".html,.xls,.txt" onchange="fileSelected(this)">

                <div class="flex items-center gap-3 w-full justify-center">

                    <!-- Recalcular Horas Link -->
                    <a href="{{ route('comp-time.recalculate') }}" 
                        class="flex items-center justify-center py-2 px-6 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                        Recalcular Horas
                    </a>
                    
                    <!-- Botão Principal -->
                    <button id="mainBtn" type="button" onclick="handleMainClick()" 
                        class="flex items-center justify-center py-2 px-6 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                        <svg id="uploadIcon" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        <span id="btnText">Carregar arquivo</span>
                    </button>

                    <!-- Label do Arquivo e Botão Cancelar (Inicialmente oculto) -->
                    <div id="fileInfo" class="hidden items-center gap-2 bg-gray-50 border border-gray-200 rounded px-3 animate-fade-in">
                        <span id="fileName" class="text-sm text-gray-700 truncate max-w-[150px]"></span>
                        <button type="button" onclick="cancelFile()" class="text-gray-400 hover:text-red-500 focus:outline-none transition-colors" title="Cancelar">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

            </form>
            
            </div>
            
        </div>
    </x-slot>

    <x-slot name="slot">
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

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-12 print:py-0 print:px-0 print:w-full print:max-w-none">
        
        @if(empty($reportData))
            <div class="bg-white p-12 text-center rounded-lg shadow">
                <p class="text-gray-500 text-lg">Nenhum dado disponível para visualização.</p>
            </div>
        @else
            

            @foreach($reportData as $data)
                @php
                    // Extração de Funcionário
                    $employeeRaw = $data['employee'] ?? null;
                    $employee = (object) $employeeRaw;

                    // Seleção Inteligente dos Registros
                    // V2: Registros no índice 3
                    // V1: Registros no índice 1
                    
                    $entries = $data['entries'] ?? [];

                    // Cálculo de Totais
                    $totalCredits = 0;
                    $totalDebits = 0;
                    
                    $nextExpiryDate = null;
                    $nextExpiryBalance = null;
                    $nextExpiryEntry = null;

                    foreach($entries as $entry) {
                        $entry = (object) $entry;
                        
                        // Previne erro se o objeto estiver incompleto
                        if (!isset($entry->balance_minutes)) continue;

                        if (($entry->type ?? '') === 'CREDIT') {
    
                            $totalCredits += $entry->balance_minutes;
                        } elseif (($entry->type ?? '') === 'DEBIT') {
                            
                            $totalDebits += $entry->balance_minutes;
                        }

                        if ($entry->balance_minutes > 0) {
                            // Se o saldo for zero ou negativo, zera o saldo
                            $expiredDate = (!empty($entry->due_date)) ? \Carbon\Carbon::parse($entry->due_date) : null;
                            if ($nextExpiryDate === null || ($expiredDate && $expiredDate->lessThan($nextExpiryDate))) {
                                $nextExpiryDate = $expiredDate;
                                $nextExpiryEntry = $entry;
                            }
                            
                        }
                    }
                    

                    $netBalance = $totalCredits - $totalDebits;
                    $isPositive = $netBalance >= 0;
                    $hasToExpire = $nextExpiryDate !== null;
                    $isPositiveExpiry = $nextExpiryEntry !== null && $nextExpiryEntry->type === 'CREDIT';
                    $nextExpiryBalance = $nextExpiryEntry ? $nextExpiryEntry->balance_minutes : 0;

                    //Count days to expiry
                    if ($nextExpiryDate) {
                        $today = \Carbon\Carbon::now();
                        $daysToExpiry = $today->diffInDays($nextExpiryDate, false);
                    } else {
                        $daysToExpiry = null;
                    }

                    $formatMinutes = function($mins) {
                        $mins = abs($mins);
                        $h = floor($mins / 60);
                        $m = $mins % 60;
                        return sprintf('%02d:%02d', $h, $m);
                    };
                @endphp

                <!-- Card do Funcionário -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200 print:shadow-none print:border-none print:break-inside-avoid page-break">
                    {{-- Print $entries type --}}

                    <!-- Cabeçalho -->
                    <div class="px-6 py-4 border-gray-200 print:bg-gray-100">
                        <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">{{ $employee->name ?? 'Nome Indisponível' }}</h2>
                                <div class="mt-1 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600">
                                    <span class="flex items-center">
                                        <svg class="mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0c0 .884-.95 2-2.122 2H5m14 0h-2.878C18.05 8 17 6.884 17 6m0 0a2 2 0 100-4 2 2 0 000 4zm-7 0a2 2 0 100-4 2 2 0 000 4z" />
                                        </svg>
                                        Matrícula: <span class="font-medium text-gray-900 ml-1">{{ $employee->employee_code ?? '--' }}</span>
                                    </span>
                                    <span class="flex items-center">
                                        <svg class="mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        Cargo: <span class="font-medium text-gray-900 ml-1">{{ $employee->position ?? '--' }}</span>
                                    </span>
                                    <span class="flex items-center">
                                        <svg class="mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        Depto: <span class="font-medium text-gray-900 ml-1">{{ $employee->department ?? '--' }}</span>
                                    </span>
                                </div>
                                <div>
                                    <form action="{{ route('comp-time.show.details') }}" method="post">
                                        @csrf
                                        <input name="employee_id" type="text" value ="{{ $employee->id ?? '' }}" class="hidden">
                                        {{-- Detalhar Registros --}}
                                        <button type="submit" href="" class="mt-3 inline-block text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                            Ver detalhes dos registros &rarr;
                                        </button>
                                    </form>
                                </div>
                            </div>

                            @if($hasToExpire)
                            <div class="bg-white px-4 py-2 rounded-lg border {{ $daysToExpiry > 30 ? 'border-green-200 bg-green-50' : 'border-red-700 bg-red-300'  }} flex flex-col items-end print:border-gray-300 print:bg-white">
                                <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">Saldo a Expirar</span>
                                <div class="flex items-baseline mt-1">
                                    <span class="text-2xl font-extrabold {{ $daysToExpiry > 30 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $isPositiveExpiry ? '+' : '-' }}{{ $formatMinutes($nextExpiryBalance) }}<span class="ml-1 text-xs text-gray-500 font-medium">hs</span> {{ $nextExpiryDate ? $nextExpiryDate->format('d/m') : '--' }}
                                    </span>
                                    
                                </div>
                            </div>
                            @endif
                            <div class="bg-white px-4 py-2 rounded-lg border {{ $isPositive ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }} flex flex-col items-end print:border-gray-300 print:bg-white">
                                <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">Saldo Líquido Atual</span>
                                <div class="flex items-baseline mt-1">
                                    <span class="text-2xl font-extrabold {{ $isPositive ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $isPositive ? '+' : '-' }}{{ $formatMinutes($netBalance) }}
                                    </span>
                                    <span class="ml-1 text-xs text-gray-500 font-medium">hs</span>
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
        <script src="{{ asset('js/accordion.js') }}"></script>
         <script>
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
    </script>
    </x-slot>

    
</x-app-layout>
