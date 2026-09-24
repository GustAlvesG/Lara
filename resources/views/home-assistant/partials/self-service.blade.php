{{--
    Autoatendimento de iluminação: o que o sócio pode acender sozinho pelo app.

    A aba responde três perguntas, nesta ordem, porque é a ordem em que elas
    aparecem quando alguém da diretoria pergunta "por que não acendeu?":
    quando está liberado, quais quadras estão liberadas e quem está usando agora.
--}}
@php
    $diasDaSemana = [
        0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta',
        4 => 'Quinta',  5 => 'Sexta',   6 => 'Sábado',
    ];
    $teto   = (int) (config('home_assistant.self_service.max_minutes') ?: 120);
    $minimo = (int) (config('home_assistant.self_service.min_minutes') ?: 15);
@endphp

{{-- ─── Como funciona ─────────────────────────────────────────────────── --}}
<section class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
    <div class="p-5 sm:p-6 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Acionamento pelo sócio</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-2xl">
                    No fim de semana não há reserva de quadra: o uso é livre. Nestes horários o sócio
                    acende a luz pelo aplicativo, escolhendo o tempo — até
                    <strong>{{ $teto >= 60 ? intdiv($teto, 60) . 'h' . ($teto % 60 ? $teto % 60 : '') : $teto . ' min' }}</strong>
                    por acionamento, <strong>uma quadra por vez</strong>. Acabando o tempo,
                    qualquer sócio presente aciona de novo e a luz continua acesa sem piscar.
                </p>
            </div>

            <div class="shrink-0">
                @if($selfServiceToday && $selfServiceToday->contains($now))
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold
                                 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                        Aberto até {{ $selfServiceToday->end->format('H:i') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold
                                 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                        Fechado agora
                    </span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Horário fixo</p>
                <ul class="mt-2 space-y-1 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($selfServiceWindows as $dia => $faixa)
                        <li>{{ $diasDaSemana[$dia] ?? 'Dia ' . $dia }} · {{ $faixa[0] }} às {{ $faixa[1] }}</li>
                    @empty
                        <li class="text-gray-400">Nenhum dia liberado.</li>
                    @endforelse
                </ul>
                <p class="mt-2 text-xs text-gray-400">Alterar exige mudança em <code>config/home_assistant.php</code>.</p>
            </div>

            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Próximo horário</p>
                @if($selfServiceNext)
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        {{ $selfServiceNext->start->translatedFormat('D, d/m') }}<br>
                        <strong>{{ $selfServiceNext->start->format('H:i') }} às {{ $selfServiceNext->end->format('H:i') }}</strong>
                    </p>
                    @if($selfServiceNext->reason)
                        <p class="mt-1 text-xs text-indigo-500 dark:text-indigo-400">{{ $selfServiceNext->reason }}</p>
                    @endif
                @else
                    <p class="mt-2 text-sm text-gray-400">Nenhum nas próximas semanas.</p>
                @endif
            </div>

            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Recusas automáticas</p>
                <ul class="mt-2 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <li>Quadra com reserva confirmada.</li>
                    <li>Sócio que já está em <em>outra</em> quadra.</li>
                    <li>Menos de {{ $minimo }} min para o fim do horário.</li>
                </ul>
                <p class="mt-2 text-xs text-gray-400">
                    Quadra já acesa não recusa: acionar de novo prolonga.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- ─── Quadras liberadas ─────────────────────────────────────────────── --}}
<section class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
    <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white">Quadras liberadas</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Marcadas em <strong>Espaços → editar → Autoatendimento do sócio</strong>.
            </p>
        </div>
        <span class="text-2xl font-black text-gray-300 dark:text-gray-600">{{ $selfServicePlaces->count() }}</span>
    </div>

    @if($selfServicePlaces->isEmpty())
        <div class="px-5 sm:px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
            Nenhuma quadra liberada. Enquanto isso, o aplicativo não mostra a opção de acender a luz.
        </div>
    @else
        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($selfServicePlaces->sortBy(fn ($p) => ($p->group?->name ?? '') . $p->name) as $place)
                <li class="px-5 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate">
                            {{ $place->name }}
                        </p>
                        <p class="text-xs text-gray-400">
                            {{ $place->group?->name ?? 'Sem grupo' }}
                            @if($place->contactor)
                                · {{ $place->contactor->name }} ({{ $place->contactor->entity_id }})
                            @endif
                        </p>
                    </div>
                    @unless($place->group)
                        {{-- Sem grupo o espaço não aparece no app: a tela pede grupo antes da quadra. --}}
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">
                            Sem grupo — invisível no app
                        </span>
                    @endunless
                </li>
            @endforeach
        </ul>
    @endif
</section>

{{-- ─── Acesas agora ──────────────────────────────────────────────────── --}}
@if($selfServiceActive->isNotEmpty())
    <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="text-base font-bold text-gray-900 dark:text-white">Acesas agora pelos sócios</h3>
        </div>
        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($selfServiceActive as $activation)
                <li class="px-5 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="font-semibold text-gray-800 dark:text-gray-200">
                        {{ $activation->place?->name ?? 'Espaço removido' }}
                    </span>
                    <span class="text-gray-500 dark:text-gray-400">
                        Sócio #{{ $activation->member_id }} · até {{ $activation->ends_at->format('H:i') }}
                        <span class="text-gray-400">({{ $activation->minutesRemaining($now) }} min)</span>
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@endif

{{-- ─── Datas especiais ───────────────────────────────────────────────── --}}
<section class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
    <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700">
        <h3 class="text-base font-bold text-gray-900 dark:text-white">Datas especiais</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            A data sempre vence o dia da semana: <strong>Liberar</strong> abre um feriado no meio da semana e
            <strong>Bloquear</strong> fecha um sábado de torneio. Uma regra por data — salvar de novo substitui a anterior.
        </p>
    </div>

    <form method="POST" action="{{ route('home-assistant.self-service.dates.store') }}"
        class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
            <div class="sm:col-span-1">
                <label for="ss-date" class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Data</label>
                <input type="date" name="date" id="ss-date" required value="{{ old('date') }}"
                    class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="sm:col-span-1">
                <label for="ss-mode" class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Regra</label>
                <select name="mode" id="ss-mode"
                    class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="allow" @selected(old('mode') === 'allow')>Liberar</option>
                    <option value="block" @selected(old('mode') === 'block')>Bloquear</option>
                </select>
            </div>
            <div class="sm:col-span-1">
                <label for="ss-start" class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Das</label>
                <input type="time" name="starts_at" id="ss-start" value="{{ old('starts_at') }}"
                    class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="sm:col-span-1">
                <label for="ss-end" class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Até</label>
                <input type="time" name="ends_at" id="ss-end" value="{{ old('ends_at') }}"
                    class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="sm:col-span-1">
                <label for="ss-reason" class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Motivo</label>
                <input type="text" name="reason" id="ss-reason" maxlength="120" value="{{ old('reason') }}"
                    placeholder="Natal, torneio…"
                    class="w-full px-3 py-2 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="sm:col-span-1">
                <button type="submit"
                    class="w-full px-4 py-2 bg-red-800 hover:bg-red-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                    Salvar data
                </button>
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-400">
            Horários em branco num “Liberar” usam a janela padrão de feriado
            ({{ implode(' às ', (array) config('home_assistant.self_service.holiday_window', ['17:00', '21:00'])) }}).
            Num “Bloquear” são ignorados: o bloqueio é o dia inteiro.
        </p>
    </form>

    @if($selfServiceDates->isEmpty())
        <div class="px-5 sm:px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
            Nenhuma data cadastrada daqui para a frente.
        </div>
    @else
        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($selfServiceDates as $date)
                <li class="px-5 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0
                            {{ $date->isBlock()
                                ? 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300'
                                : 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300' }}">
                            {{ $date->isBlock() ? 'Bloqueado' : 'Liberado' }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                {{ $date->date->translatedFormat('D, d/m/Y') }}
                                @unless($date->isBlock())
                                    <span class="font-normal text-gray-500 dark:text-gray-400">
                                        · {{ $date->starts_at && $date->ends_at
                                            ? substr($date->starts_at, 0, 5) . ' às ' . substr($date->ends_at, 0, 5)
                                            : 'janela padrão de feriado' }}
                                    </span>
                                @endunless
                            </p>
                            @if($date->reason)
                                <p class="text-xs text-gray-400 truncate">{{ $date->reason }}</p>
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('home-assistant.self-service.dates.destroy', $date) }}"
                        onsubmit="return confirm('Remover esta data especial?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-800 dark:text-red-400">
                            Remover
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</section>
