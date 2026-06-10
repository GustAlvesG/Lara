<x-app-layout>

    <style>
        .tab-active { border-bottom: 3px solid #4f46e5; color: #4f46e5; }
    </style>

    @php
        // Separa as regras pelo período de vigência: as vigentes ficam na aba
        // principal, as fora de vigência (expiradas ou ainda não iniciadas) vão
        // para a aba de consulta.
        [$activeRules, $expiredRules] = $companyDetails->rules->partition->isWithinValidityPeriod();
    @endphp

<div class="max-w-5xl mx-auto">

        <!-- Botão Voltar e Ações Rápidas -->
        <div class="my-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('company.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Perfil da Empresa</h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('company.edit', $companyDetails->id) }}" class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-bold text-sm shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Editar Dados
                </a>
                <form action="{{ route('company.destroy', $companyDetails->id) }}" method="POST"
                      onsubmit="return confirm('Deseja realmente remover esta empresa?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800 text-red-600 dark:text-red-400 rounded-lg font-bold text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/30 transition">
                        Excluir
                    </button>
                </form>
            </div>
        </div>

        <!-- Cabeçalho / Hero Section -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700 overflow-hidden mb-8">
            <div class="h-32 bg-indigo-600"></div>
            <div class="px-8 pb-8">
                <div class="relative flex justify-between items-end -mt-12 mb-6">
                    <img src="{{ $companyDetails->image ? asset('images/' . $companyDetails->image) : 'https://placehold.co/128x128/ffffff/4f46e5?text=' . urlencode($companyDetails->name) }}"
                         onerror="this.onerror=null;this.src='https://placehold.co/128x128/ffffff/4f46e5?text={{ urlencode($companyDetails->name) }}';"
                         alt="Logo" class="w-32 h-32 rounded-2xl border-4 border-white dark:border-gray-800 shadow-xl bg-white object-cover">
                    <div class="flex gap-3 mb-2">
                        <span class="px-3 py-1 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 rounded-full text-xs font-bold uppercase">Ativa</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <h2 class="text-4xl font-black text-gray-900 dark:text-white mb-2">{{ $companyDetails->name }}</h2>
                        <p class="text-gray-500 dark:text-gray-400 font-medium leading-relaxed">
                            {{ $companyDetails->description }}
                        </p>
                    </div>
                    <div class="space-y-3 pt-4">
                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            {{ $companyDetails->email }}
                        </div>
                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 00.948-.684l1.498-4.493a1 1 0 011.902 0l1.498 4.493a1 1 0 00.948.684H19a2 2 0 012 2v10a2 2 0 01-2 2h-3.28a1 1 0 00-.948.684l-1.498 4.493a1 1 0 01-1.902 0l-1.498-4.493A1 1 0 005.72 17H3a2 2 0 01-2-2V5z"></path></svg>
                            {{ $companyDetails->telephone }}
                        </div>
                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            {{ $companyDetails->address }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navegação por Abas -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="flex border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30">
                <button onclick="switchTab('workers')" id="tab-workers" class="tab-btn px-8 py-4 text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition tab-active">
                    Funcionários
                </button>
                <button onclick="switchTab('rules')" id="tab-rules" class="tab-btn px-8 py-4 text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                    Regras de Acesso
                </button>
                <button onclick="switchTab('expired')" id="tab-expired" class="tab-btn px-8 py-4 text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                    Fora de Vigência
                    @if($expiredRules->isNotEmpty())
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300">{{ $expiredRules->count() }}</span>
                    @endif
                </button>
            </div>

            <!-- Conteúdo da Aba: Funcionários -->
            <div id="content-workers" class="tab-content p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-extrabold text-gray-800 dark:text-white">Equipe Registada</h3>
                    <a href="{{ route('company.worker.create', $companyDetails->id) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold text-xs shadow-md hover:bg-indigo-700 transition">
                        Adicionar Funcionário
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if($companyDetails->workers->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400 italic">Nenhum funcionário registado para esta empresa.</p>
                    @endif
                    @foreach($companyDetails->workers as $worker)
                    <div class="flex items-center p-4 border border-gray-100 dark:border-gray-700 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition shadow-sm">
                        @if($worker->image)
                            <img src="{{ asset('images/' . $worker->image) }}" alt="Foto de {{ $worker->name }}" class="h-12 w-12 rounded-full object-cover mr-4">
                        @else
                        <div class="h-12 w-12 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-700 dark:text-indigo-400 font-bold mr-4">
                            {{ strtoupper(substr($worker->name, 0, 1)) }}
                        </div>
                        @endif
                        <div class="flex-grow">
                            <h4 class="font-bold text-gray-900 dark:text-white">{{ $worker->name }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ ucfirst($worker->position) }}</p>
                        </div>
                         <div class="flex gap-2 items-center">
                            <a href="{{ route('company.worker.show', [$companyDetails->id, $worker->id]) }}"
                               class="p-2 text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition" title="Ver Funcionário">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                            <form action="{{ route('company.worker.destroy', [$companyDetails->id, $worker->id]) }}" method="POST"
                                  onsubmit="return confirm('Deseja realmente remover este funcionário?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-2 text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 transition" title="Excluir Funcionário">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Conteúdo da Aba: Regras -->
            <div id="content-rules" class="tab-content p-6 hidden">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-extrabold text-gray-800 dark:text-white">Regras de Acesso</h3>
                    <a href="{{ route('company.rules.create', $companyDetails->id) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold text-xs shadow-md hover:bg-indigo-700 transition">
                        Nova Regra
                    </a>
                </div>

                @forelse($activeRules as $rule)
                    @include('companies.rules.partials.card', ['rule' => $rule, 'companyId' => $companyDetails->id, 'showWorker' => true])
                @empty
                    <p class="text-gray-400 dark:text-gray-500 italic text-sm">Nenhuma regra vigente no momento.</p>
                @endforelse
            </div>

            <!-- Conteúdo da Aba: Fora de Vigência (consulta) -->
            <div id="content-expired" class="tab-content p-6 hidden">
                <div class="flex items-center gap-2 mb-6">
                    <h3 class="text-xl font-extrabold text-gray-800 dark:text-white">Regras Fora de Vigência</h3>
                    <span class="text-xs text-gray-400 dark:text-gray-500">(somente consulta)</span>
                </div>

                @forelse($expiredRules as $rule)
                    @include('companies.rules.partials.card', ['rule' => $rule, 'companyId' => $companyDetails->id, 'showWorker' => true, 'expired' => true])
                @empty
                    <p class="text-gray-400 dark:text-gray-500 italic text-sm">Nenhuma regra fora do período de vigência.</p>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('tab-active');
            });

            document.getElementById('content-' + tabName).classList.remove('hidden');
            document.getElementById('tab-' + tabName).classList.add('tab-active');
        }
    </script>
</x-app-layout>
