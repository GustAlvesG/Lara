@php
    /**
     * Card de regra de acesso reutilizável.
     *
     * @var \App\Models\Company\CompanyAccessRule $rule
     * @var int  $companyId    Id da empresa (para as rotas de edição/remoção)
     * @var bool $showWorker   Exibe o selo "Exclusiva: {funcionário}" (usado na tela da empresa)
     * @var bool $expired      Renderiza o card esmaecido (fora de vigência / consulta)
     */
    $showWorker = $showWorker ?? false;
    $expired    = $expired ?? false;
@endphp

<div class="bg-white dark:bg-gray-800 border rounded-2xl shadow-sm overflow-hidden border-gray-100 dark:border-gray-700 mb-4 {{ $expired ? 'opacity-60' : '' }}">
    <div class="flex flex-col md:flex-row">
        <div class="w-full md:w-2 {{ $rule->type === 'include' ? 'bg-indigo-600' : 'bg-red-600' }} {{ $expired ? 'grayscale' : '' }}"></div>
        <div class="px-5 py-3 flex-grow">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex-grow">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $rule->type === 'include' ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400' : 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400' }}">
                            {{ $rule->type === 'include' ? 'Inclusão' : 'Exclusão' }}
                        </span>
                        @if($showWorker && $rule->company_worker_id && $rule->worker)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-400">
                                Exclusiva: {{ $rule->worker->name }}
                            </span>
                        @endif
                        @if($expired)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 uppercase">
                                Fora de vigência
                            </span>
                        @endif
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
                        @php $activeDays = $rule->weekdays->pluck('short_name_pt')->toArray(); @endphp
                        @foreach(['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'] as $d)
                            <span class="w-8 h-8 flex items-center justify-center rounded-lg text-[10px] font-black uppercase transition-colors {{ in_array($d, $activeDays) ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-700 text-gray-300 dark:text-gray-500' }}">
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
                    <a href="{{ route('company.rules.edit', [$companyId, $rule->id]) }}"
                       class="p-2 text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition" title="Editar Regra">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </a>
                    <form action="{{ route('company.rules.destroy', [$companyId, $rule->id]) }}" method="POST"
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
