{{--
    O painel de decisão — a mesma caixa serve os três níveis.

    O que muda entre eles é o formulário: a Gerência escolhe a Diretoria, os
    outros só decidem. Quem pode decidir agora é `$meuPasso`, resolvido no
    controller: sem ele, a caixa vira só andamento.

    Aprovar aqui NUNCA aprova o próximo nível. Mesmo com a mesma pessoa nos três
    cargos, são três decisões, e a linha do tempo abaixo mostra as três.
--}}
@php
    $nivel = $processo?->current_level;
    $ehGerencia = $nivel === \App\Models\PurchaseOrderApproval::LEVEL_MANAGEMENT;
    $ehDiretoria = $nivel === \App\Models\PurchaseOrderApproval::LEVEL_DIRECTORS;
    $podeDecidir = $meuPasso !== null;

    $rotulos = \App\Models\PurchaseOrderApproval::LEVEL_LABELS;
@endphp

<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6 mb-6">

    {{-- ============ ESTADO ============ --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="font-extrabold text-gray-900 dark:text-white">Aprovação</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                @if($processo === null)
                    Esta ordem ainda não entrou no fluxo. Ela começa quando a Contabilidade decide.
                @elseif($processo->isApproved())
                    Aprovada nos três níveis e autorizada no Questor.
                @elseif($processo->isRejected())
                    Reprovada — o processo foi encerrado.
                @else
                    No nível {{ $processo->current_level }} de 3: <strong>{{ $processo->currentLevelLabel() }}</strong>.
                @endif
            </p>
        </div>
        @if($processo)
            <span class="px-2.5 py-1 rounded-lg text-xs font-bold shrink-0
                {{ $processo->isApproved() ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                   : ($processo->isRejected() ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'
                   : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300') }}">
                Processo #{{ $processo->id }}
            </span>
        @endif
    </div>

    {{-- ============ LINHA DO TEMPO ============ --}}
    @if($processo)
        <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach($rotulos as $n => $rotulo)
                @php
                    $passos = $processo->steps->where('level', $n);
                    $decididos = $passos->whereIn('decision', ['approved', 'rejected']);
                    $recusado = $passos->where('decision', 'rejected')->isNotEmpty();
                    $concluido = $passos->isNotEmpty() && $passos->where('decision', 'pending')->isEmpty() && !$recusado;
                    $atual = $processo->isOpen() && $processo->current_level === $n;
                @endphp
                <div class="rounded-xl border p-3
                    {{ $recusado ? 'border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-900/10'
                       : ($concluido ? 'border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/10'
                       : ($atual ? 'border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/10'
                       : 'border-gray-200 dark:border-gray-700')) }}">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">{{ $n }} · {{ $rotulo }}</p>
                    @forelse($passos as $passo)
                        <p class="text-sm text-gray-700 dark:text-gray-200 mt-1">
                            {{ $passo->assigneeLabel() }}
                            @if($passo->decided_at)
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    {{ $passo->wasApproved() ? 'aprovou' : ($passo->decision === 'rejected' ? 'reprovou' : 'não decidiu') }}
                                    em {{ $passo->decided_at->format('d/m H:i') }}
                                    @if($passo->source === 'manual') · escolhido pelo gerente @endif
                                </span>
                            @else
                                <span class="block text-xs text-gray-400">aguardando</span>
                            @endif
                        </p>
                    @empty
                        <p class="text-sm text-gray-400 mt-1">—</p>
                    @endforelse
                </div>
            @endforeach
        </div>

        @if($processo->sem_centro_custo)
            <p class="mt-4 text-sm text-amber-600 dark:text-amber-400">
                Esta ordem tem item sem centro de custo — não há diretor sugerido para essa parte, e a escolha
                do último nível é inteiramente da Gerência.
            </p>
        @endif
    @endif

    {{-- ============ FORMULÁRIO ============ --}}
    @if($processo === null && !$ehContabilidade)
        <p class="mt-5 text-sm text-gray-500 dark:text-gray-400">
            Aguardando a Contabilidade iniciar a aprovação.
        </p>
    @elseif($processo?->isOpen() && !$podeDecidir)
        <p class="mt-5 text-sm text-gray-500 dark:text-gray-400">
            Não é a sua vez nesta ordem — ou você já decidiu este nível. Cada nível é uma decisão própria,
            então aprovar um não vale pelo seguinte.
        </p>
    @elseif($processo === null || $processo->isOpen())
        <form method="POST" action="{{ route('questor.purchase-orders.decide', $ordem->CD_ORDEM_COMPRA) }}"
              x-data="{ decisao: 'aprovar' }" class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-700 space-y-4">
            @csrf

            @if($ehGerencia)
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                        Diretores que vão decidir esta ordem
                    </label>
                    <div class="flex flex-wrap gap-2">
                        @forelse($diretores as $diretor)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="diretores[]" value="{{ $diretor->id }}"
                                       class="peer sr-only" @checked(in_array($diretor->id, $sugeridos, true))>
                                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium border transition
                                             border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400
                                             peer-checked:bg-[#A00001] peer-checked:text-white peer-checked:border-[#A00001]">
                                    {{ $diretor->name }}
                                </span>
                            </label>
                        @empty
                            <span class="text-sm text-red-600 dark:text-red-400">
                                Nenhum usuário no setor Diretoria — a ordem não tem como avançar.
                            </span>
                        @endforelse
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        Vêm marcados os diretores ligados aos centros de custo dos itens. Você pode trocar —
                        e a troca fica registrada como escolha sua.
                        {{ config('questor.aprovacao.quorum_diretoria', 'todos') === 'todos'
                            ? 'Todos os marcados precisarão aprovar.'
                            : 'Basta um dos marcados aprovar.' }}
                    </p>
                </div>
            @endif

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">
                    Observação <span class="font-normal normal-case tracking-normal">(opcional)</span>
                </label>
                <input type="text" name="observacao" maxlength="1000" value="{{ old('observacao') }}"
                       class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm"
                       placeholder="Fica registrada na trilha da Lara">
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" name="decisao" value="aprovar"
                        class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition">
                    Aprovar como {{ $processo === null ? 'Contabilidade' : $processo->currentLevelLabel() }}
                </button>
                <button type="submit" name="decisao" value="reprovar"
                        onclick="return confirm('Reprovar encerra o processo desta ordem. Confirma?')"
                        class="px-5 py-2.5 rounded-xl bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-sm font-bold text-red-700 dark:text-red-400 transition">
                    Reprovar
                </button>
            </div>

            @if($ehDiretoria)
                <p class="text-xs text-gray-400">
                    Aprovar aqui, sendo o último diretor pendente, grava a autorização no Questor.
                </p>
            @endif
        </form>
    @endif
</div>
