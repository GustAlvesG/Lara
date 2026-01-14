<x-app-layout>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f9; }
        .tab-active { border-bottom: 3px solid #4f46e5; color: #4f46e5; }
    </style>

<div class="max-w-5xl mx-auto">
        
        <!-- Botão Voltar e Ações Rápidas -->
        <div class="my-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('company.index') }}" class="p-2 bg-white rounded-xl shadow-md text-gray-400 hover:text-indigo-600 border border-gray-100 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Perfil da Empresa</h1>
            </div>
            <div class="flex gap-2">
                <a href="#" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg font-bold text-sm shadow-sm hover:bg-gray-50 transition">
                    Editar Dados
                </a>
            </div>
        </div>

        <!-- Cabeçalho / Hero Section -->
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden mb-8">
            <div class="h-32 bg-indigo-600"></div>
            <div class="px-8 pb-8">
                <div class="relative flex justify-between items-end -mt-12 mb-6">
                    <img src="{{ "https://placehold.co/128x128/ffffff/4f46e5?text=" . $companyDetails->name }}" alt="Logo" class="w-32 h-32 rounded-2xl border-4 border-white shadow-xl bg-white object-cover">
                    <div class="flex gap-3 mb-2">
                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold uppercase">Ativa</span>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <h2 class="text-4xl font-black text-gray-900 mb-2">{{ $companyDetails->name }}</h2>
                        <p class="text-gray-500 font-medium leading-relaxed">
                            {{ $companyDetails->description }}
                        </p>
                    </div>
                    <div class="space-y-3 pt-4">
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            {{ $companyDetails->email }}
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 00.948-.684l1.498-4.493a1 1 0 011.902 0l1.498 4.493a1 1 0 00.948.684H19a2 2 0 012 2v10a2 2 0 01-2 2h-3.28a1 1 0 00-.948.684l-1.498 4.493a1 1 0 01-1.902 0l-1.498-4.493A1 1 0 005.72 17H3a2 2 0 01-2-2V5z"></path></svg>
                            {{ $companyDetails->telephone }}
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            {{ $companyDetails->address }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navegação por Abas -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="flex border-b border-gray-100 bg-gray-50/50">
                <button onclick="switchTab('workers')" id="tab-workers" class="tab-btn px-8 py-4 text-sm font-bold uppercase tracking-wider text-gray-500 hover:text-indigo-600 transition tab-active">
                    Funcionários
                </button>
                <button onclick="switchTab('rules')" id="tab-rules" class="tab-btn px-8 py-4 text-sm font-bold uppercase tracking-wider text-gray-500 hover:text-indigo-600 transition">
                    Regras de Acesso
                </button>
            </div>

            <!-- Conteúdo da Aba: Funcionários -->
            <div id="content-workers" class="tab-content p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-extrabold text-gray-800">Equipa Registada</h3>
                    <a href="{{ route('company.worker.create', $companyDetails->id) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold text-xs shadow-md hover:bg-indigo-700 transition">
                        Adicionar Funcionário
                    </a>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Card de Funcionário Exemplo -->
                    @if($companyDetails->workers->isEmpty())
                        <p class="text-gray-500 italic">Nenhum funcionário registado para esta empresa.</p>
                    @endif
                    @foreach($companyDetails->workers as $worker)
                    <div class="flex items-center p-4 border border-gray-100 rounded-xl hover:bg-gray-50 transition shadow-sm">
                        <div class="h-12 w-12 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold mr-4">
                            {{ strtoupper(substr($worker->name, 0, 1)) }}
                        </div>
                        <div class="flex-grow">
                            <h4 class="font-bold text-gray-900">{{ $worker->name }}</h4>
                            <p class="text-xs text-gray-500">{{ $worker->position }}</p>
                        </div>
                        <button class="text-gray-400 hover:text-indigo-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Conteúdo da Aba: Regras -->
            <div id="content-rules" class="tab-content p-6 hidden">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-extrabold text-gray-800">Regras de Acesso</h3>
                    <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold text-xs shadow-md hover:bg-indigo-700 transition">
                        Nova Regra
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Exemplo de Regra -->
                    <div class="p-4 bg-indigo-50 border-l-4 border-indigo-600 rounded-r-xl flex justify-between items-center">
                        @if($companyDetails->rules->isEmpty())
                            <p class="text-gray-500 italic">Nenhuma regra de acesso definida para esta empresa.</p>
                        @endif
                        @foreach($companyDetails->rules as $rule)
                            <div>
                                <h4 class="font-bold text-indigo-900 text-lg">Acesso Total - Carga e Descarga</h4>
                                <p class="text-sm text-indigo-700">Permite entrada 24h para veículos identificados.</p>
                            </div>
                            <div class="flex gap-2">
                                <button class="text-indigo-600 font-bold text-xs hover:underline">Configurar</button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        /**
         * Lógica de Alternância de Abas (Vanilla JS)
         */
        function switchTab(tabName) {
            // Ocultar todos os conteúdos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            // Remover estilo ativo de todos os botões
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('tab-active');
            });

            // Mostrar o conteúdo selecionado
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Adicionar estilo ativo ao botão selecionado
            document.getElementById('tab-' + tabName).classList.add('tab-active');
        }
    </script>
</x-app-layout>