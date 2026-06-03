<x-app-layout>

    <div class="max-w-4xl mx-auto">

        <!-- Botão Voltar -->
        <div class="my-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('company.show', $company->id) }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Perfil do Funcionário</h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('company.worker.edit', [$company->id, $worker->id]) }}"
                   class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-bold text-sm shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Editar
                </a>
                <form action="{{ route('company.worker.destroy', [$company->id, $worker->id]) }}" method="POST"
                      onsubmit="return confirm('Deseja realmente remover este funcionário?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800 text-red-600 dark:text-red-400 rounded-lg font-bold text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/30 transition">
                        Excluir
                    </button>
                </form>
            </div>
        </div>

        <!-- Perfil Card -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700 overflow-hidden mb-8">
            <div class="h-24 bg-indigo-600"></div>
            <div class="px-8 pb-8">
                <div class="flex items-end gap-6 -mt-12 mb-6">
                    @if($worker->image)
                        <img src="{{ asset('images/' . $worker->image) }}" alt="Foto de {{ $worker->name }}"
                             class="w-24 h-24 rounded-2xl border-4 border-white dark:border-gray-800 shadow-xl object-cover bg-white">
                    @else
                        <div class="w-24 h-24 rounded-2xl border-4 border-white dark:border-gray-800 shadow-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-700 dark:text-indigo-400 text-3xl font-black">
                            {{ strtoupper(substr($worker->name, 0, 1)) }}
                        </div>
                    @endif
                    <div class="pb-2">
                        <h2 class="text-2xl font-black text-gray-900 dark:text-white">{{ $worker->name }}</h2>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">{{ ucfirst($worker->position) }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400 gap-2">
                        <svg class="w-5 h-5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        {{ $worker->email ?? '—' }}
                    </div>
                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400 gap-2">
                        <svg class="w-5 h-5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        {{ $worker->telephone ?? '—' }}
                    </div>
                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400 gap-2">
                        <svg class="w-5 h-5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2"></path>
                        </svg>
                        CPF: {{ $worker->document ?? '—' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Regras Individuais -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30">
                <h3 class="text-lg font-extrabold text-gray-800 dark:text-white">Regras de Acesso Individuais</h3>
                <a href="{{ route('company.worker.rules.create', [$company->id, $worker->id]) }}"
                   class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold text-xs shadow-md hover:bg-indigo-700 transition">
                    Nova Regra Individual
                </a>
            </div>

            <div class="p-6 space-y-4">
                @forelse($worker->rules as $rule)
                    <div class="bg-white dark:bg-gray-800 border rounded-2xl shadow-sm overflow-hidden border-gray-100 dark:border-gray-700">
                        <div class="flex flex-col md:flex-row">
                            <div class="w-full md:w-2 {{ $rule->type === 'include' ? 'bg-indigo-600' : 'bg-red-600' }}"></div>
                            <div class="px-5 py-3 flex-grow">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="flex-grow">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $rule->type === 'include' ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400' : 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400' }}">
                                                {{ $rule->type === 'include' ? 'Inclusão' : 'Exclusão' }}
                                            </span>
                                            <h4 class="font-extrabold text-gray-900 dark:text-white">{{ $rule->description ?? '—' }}</h4>
                                        </div>

                                        <div class="flex items-center text-xs text-gray-500 dark:text-gray-400 mb-3">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            Vigência: <span class="ml-1 font-bold text-gray-700 dark:text-gray-300">{{ date('d/m/Y', strtotime($rule->start_date)) }}</span>
                                            @if($rule->end_date)
                                                <span class="mx-1">até</span>
                                                <span class="font-bold text-gray-700 dark:text-gray-300">{{ date('d/m/Y', strtotime($rule->end_date)) }}</span>
                                            @else
                                                <span class="ml-1 text-indigo-600 dark:text-indigo-400 font-bold">(Indeterminado)</span>
                                            @endif
                                        </div>

                                        <div class="flex gap-1.5 mb-3">
                                            @foreach(['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'] as $d)
                                                <span class="w-8 h-8 flex items-center justify-center rounded-lg text-[10px] font-black uppercase transition-colors {{ in_array($d, $rule->weekdays->pluck('short_name_pt')->toArray()) ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-700 text-gray-300 dark:text-gray-500' }}">
                                                    {{ $d }}
                                                </span>
                                            @endforeach
                                        </div>

                                        @if($rule->start_time && $rule->end_time)
                                            <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                Horário: <span class="mx-1 font-bold text-gray-700 dark:text-gray-300">{{ date('H:i', strtotime($rule->start_time)) }}</span> / <span class="ml-1 font-bold text-gray-700 dark:text-gray-300">{{ date('H:i', strtotime($rule->end_time)) }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('company.rules.edit', [$company->id, $rule->id]) }}"
                                           class="p-2 text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition" title="Editar Regra">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <form action="{{ route('company.rules.destroy', [$company->id, $rule->id]) }}" method="POST"
                                              onsubmit="return confirm('Remover esta regra?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 transition" title="Excluir Regra">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-400 dark:text-gray-500 italic text-sm">Nenhuma regra individual cadastrada. Este funcionário segue as regras gerais da empresa.</p>
                @endforelse
            </div>
        </div>

    </div>

</x-app-layout>
