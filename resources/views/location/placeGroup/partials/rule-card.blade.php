@php
    // Lógica de Cores e Estilos baseada no Tipo
    $isInclude = $rule->type == 'include';
    $mainColor = $isInclude ? 'indigo' : 'red';
    
    // Status
    $isActive = $rule->status_id == 1;
    $statusClasses = $isActive 
        ? 'bg-ok-soft text-ok border-ok/40' 
        : 'bg-subtle text-ink-2 border-line';

    // Badge de Tipo
    $typeBadge = $isInclude 
        ? 'bg-grena-tint text-grena-ink border-grena/40' 
        : 'bg-danger-soft text-danger border-danger/40';
@endphp

<div class="group relative bg-surface rounded-3xl shadow-card hover:shadow-pop border border-line transition-all duration-300 overflow-hidden">
    
    <!-- Barra Lateral de Identificação -->
    <div class="absolute left-0 top-0 bottom-0 w-1.5 {{ $isInclude ? 'bg-grena' : 'bg-danger' }}"></div>

    <div class="p-5 pl-7">
        <!-- Cabeçalho: Nome e Status -->
        <div class="flex justify-between items-start">
            <div class="flex-grow">
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider border {{ $typeBadge }}">
                        {{ $isInclude ? 'Inclusão' : 'Exclusão' }}
                    </span>
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider border {{ $statusClasses }}">
                        {{ $isActive ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>
                <h3 class="text-lg font-extrabold text-ink leading-tight">
                    {{ $rule->name ?? 'Regra #' . $loop->iteration }}
                </h3>
            </div>

            <!-- Preço/Horário em destaque -->
            <div class="flex-shrink-0 text-right">
                <div class="bg-ink text-canvas px-3 py-1.5 rounded-xl shadow-card border border-line-strong">
                    <p class="text-[8px] font-black text-ink-3 uppercase tracking-widest leading-none mb-1 text-center">Horário</p>
                    <p class="text-sm text-center font-black whitespace-nowrap leading-none">
                        @if ($rule->start_time == $rule->end_time && $rule->start_time == null)
                            <span class="text-xs text-ink-3">Todos</span>
                        @else
                            {{ $rule->start_time ? \Carbon\Carbon::parse($rule->start_time)->format('H:i') : '--:--' }} 
                            - 
                            {{ $rule->end_time ? \Carbon\Carbon::parse($rule->end_time)->format('H:i') : '--:--' }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Conteúdo Principal -->
        <div class="space-y-4">
            <!-- Vigência (Data) -->
            <div class="flex items-center text-xs text-ink-2">
                <svg class="w-4 h-4 mr-2 text-ink-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span class="font-bold">Vigência:</span>
                @if ($rule->start_date == $rule->end_date && $rule->start_date == null)
                    <span class="ml-1 italic">Período não definido</span>
                @else
                    <span class="ml-1 text-ink font-extrabold">
                        {{ $rule->start_date ? \Carbon\Carbon::parse($rule->start_date)->format('d/m/y') : '∞' }}
                        <span class="mx-1 text-ink-3">à</span>
                        {{ $rule->end_date ? \Carbon\Carbon::parse($rule->end_date)->format('d/m/y') : '∞' }}
                    </span>
                @endif
            </div>

            <!-- Dias da Semana (Visual) -->
            <div class="flex gap-1.5 overflow-x-auto pb-1 hide-scrollbar">
                @php
                    $allDays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
                    // Verifica se weekdays é uma relação ou array para extrair nomes
                    $activeDays = $rule->weekdays ? $rule->weekdays->pluck('short_name_pt')->map(fn($day) => ucfirst($day))->toArray() : [];
                @endphp
                @foreach($allDays as $day)
                    @php $isActiveDay = in_array($day, $activeDays); @endphp
                    <div class="w-8 h-8 flex items-center justify-center rounded-lg text-[10px] font-black transition-all duration-300
                        {{ $isActiveDay 
                            ? ($isInclude ? 'bg-grena text-white' : 'bg-danger text-white dark:text-canvas') 
                            : 'bg-subtle text-ink-3' }}">
                        {{ substr($day, 0, 1) }}
                    </div>
                @endforeach
            </div>

            <!-- LISTAGEM DE LOCAIS (NOVO) -->
            @if($rule->places && count($rule->places) > 0)
            <div class="pt-2">
                <p class="text-[9px] font-black text-ink-3 uppercase tracking-widest mb-2">Locais Vinculados</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($rule->places as $placeItem)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-subtle border border-line text-[10px] font-bold text-ink-2 transition hover:border-grena/40">
                            <svg class="w-2.5 h-2.5 mr-1 text-ink-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                            {{ $placeItem->name ?? '' }}
                        </span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>


        <!-- Footer: Ações ou Checkbox -->
        <div class="mt-6 flex items-center justify-between">
            @isset($checkbox)
                <label class="flex items-center cursor-pointer group/check">
                    <input type="checkbox" name="rules[]" value="{{ $rule->id }}" 
                        @if (isset($rule->places) && isset($place) && collect($rule->places)->contains('id', $place->id)) checked @endif
                        class="w-5 h-5 rounded border-line-strong text-grena-ink focus:ring-grena-tint transition cursor-pointer">
                    <span class="ml-2 text-sm font-bold text-ink-2 group-hover/check:text-grena-ink">Habilitar para este local</span>
                </label>
            @else
                <div class="flex gap-2 w-full">
                    <a href="{{ route('place-group.editScheduleRule', $rule->id) }}" 
                       class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-grena-tint text-grena-ink font-bold text-xs uppercase tracking-widest rounded-xl hover:bg-grena-hover hover:text-white transition duration-200 shadow-card border border-grena/40">
                        <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        Editar
                    </a>
                    <form action="{{ route('place-group.destroyScheduleRule',$rule->id) }}" method="POST" class="flex-shrink-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" 
                                onclick="return confirm('Tem certeza que deseja deletar?')"
                                class="p-2 bg-danger-soft text-danger rounded-xl hover:bg-danger hover:text-white dark:hover:text-canvas transition duration-200 border border-danger/40">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </form>
                </div>
            @endisset
        </div>
    </div>
</div>

<style>
    .hide-scrollbar::-webkit-scrollbar { display: none; }
    .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>