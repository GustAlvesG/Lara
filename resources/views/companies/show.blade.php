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
                        @if($worker->image)
                            <img src="{{ asset('images/' . $worker->image) }}" alt="Foto de {{ $worker->name }}" class="h-12 w-12 rounded-full object-cover mr-4">
                        @else
                        <div class="h-12 w-12 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold mr-4">
                            {{ strtoupper(substr($worker->name, 0, 1)) }}
                        </div>
                        @endif
                        <div class="flex-grow">
                            <h4 class="font-bold text-gray-900">{{ $worker->name }}</h4>
                            <p class="text-xs text-gray-500">{{ ucfirst($worker->position) }}</p>
                        </div>
                         <div class="flex gap-2 items-center">
                            <!-- Botão Editar (Acionado pela seta) -->
                            <a href="#" class="p-2 text-gray-400 hover:text-indigo-600 transition" title="Editar Funcionário">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                            <form id="delete-worker-{{ $worker->id }}" action="{{ route('company.worker.destroy', [$companyDetails->id, $worker->id]) }}" method="POST" style="display: none;">
                                    @csrf
                                    @method('DELETE')
                                <!-- Botão Excluir (Lixeira) -->
                                <button type="button" 
                                        onclick="confirm('Deseja realmente remover este funcionário?') ? document.getElementById('delete-worker-{{ $worker->id }}').submit() : null"
                                        class="p-2 text-gray-400 hover:text-red-600 transition" 
                                        title="Excluir Funcionário">
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
                    <h3 class="text-xl font-extrabold text-gray-800">Regras de Acesso</h3>
                    <a href="{{ route('company.rules.create', $companyDetails->id) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold text-xs shadow-md hover:bg-indigo-700 transition">
                        Nova Regra
                    </a>
                </div>

                @foreach($companyDetails->rules as $rule)
                    <div class="bg-white border rounded-2xl shadow-sm overflow-hidden border-gray-100">
                        <div class="flex flex-col md:flex-row">
                            <!-- Barra Lateral de Tipo -->
                            <div class="w-full md:w-2 {{ $rule['type'] === 'include' ? 'bg-indigo-600' : 'bg-red-600' }}"></div>
                            
                            <div class="px-5 py-2 flex-grow">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="flex-grow">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $rule['type'] === 'include' ? 'bg-indigo-100 text-indigo-700' : 'bg-red-100 text-red-700' }}">
                                                {{ $rule['type'] === 'include' ? 'Inclusão' : 'Exclusão' }}
                                            </span>
                                            <h4 class="font-extrabold text-gray-900 text-lg">{{ $rule['description'] }}</h4>
                                        </div>
                                        
                                        <!-- Vigência -->
                                        <div class="flex items-center text-xs text-gray-500 mb-4">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            Vigência: <span class="ml-1 font-bold text-gray-700">{{ date('d/m/Y', strtotime($rule['start_date'])) }}</span>
                                            @if($rule['end_date'])
                                                <span class="mx-1">até</span> <span class="font-bold text-gray-700">{{ date('d/m/Y', strtotime($rule['end_date'])) }}</span>
                                            @else
                                                <span class="ml-1 text-indigo-600 font-bold">(Indeterminado)</span>
                                            @endif
                                        </div>

                                        <!-- Dias da Semana -->
                                        <div class="flex gap-1.5 mb-4">
                                            @foreach(['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'] as $d)
                                                <span class="w-8 h-8 flex items-center justify-center rounded-lg text-[10px] font-black uppercase transition-colors {{ in_array($d, $rule['weekdays']->pluck('short_name_pt')->toArray()) ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 text-gray-300' }}">
                                                    {{ $d }}
                                                </span>
                                            @endforeach
                                        </div>

                                        @if($rule->start_time && $rule->end_time)
                                            <!-- Horário -->
                                            <div class="flex items-center text-xs text-gray-500">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                Horário: <span class="mx-1 font-bold text-gray-700">{{ date('H:i', strtotime($rule['start_time']))}}</span> /<span class="ml-1 font-bold text-gray-700">{{ date('H:i', strtotime($rule['end_time'])) }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Horários e Ação -->
                                    <div class="flex flex-col items-end gap-3 min-w-[120px]">
                                        <a class="text-xs font-bold text-indigo-600 hover:bg-indigo-50 px-3 py-1.5 rounded-lg transition">Configurar</a>
                                    </div>

                                    
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
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