{{--
    Detalhe da ordem de compra + o painel de decisão do fluxo de três níveis.

    A decisão que os botões tomam é a do nível em que a ordem está — o painel
    resolve isso sozinho. O Questor só é tocado quando o terceiro nível fecha;
    até lá nada sai daqui para o ERP.
--}}
@php
    $brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $dataHora = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y H:i') : '—';
    $data = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';

    $autorizada = $ordem->CD_USUARIO_AUTORIZOU !== null;
    $reprovada = $ordem->CD_USUARIO_REPROVOU !== null;
    $naFila = !$autorizada && !$reprovada
        && (int) $ordem->CD_STATUS === (int) config('questor.status.pendente', 1);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Ordem de Compra') }} #{{ $ordem->CD_ORDEM_COMPRA }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <a href="{{ route('questor.purchase-orders.index') }}"
           class="inline-flex items-center gap-1 text-sm font-bold text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white mb-6">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Voltar para a fila
        </a>

        @include('partials.alerts')
        @include('questor.purchase-orders.partials.mode-banner', ['config' => $config])

        {{-- ============ CABEÇALHO ============ --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6 mb-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">
                            Ordem #{{ $ordem->CD_ORDEM_COMPRA }}
                        </h1>
                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                            {{ $naFila ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                       : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $ordem->DS_STATUS ?: 'STATUS ' . $ordem->CD_STATUS }}
                        </span>
                        @if($autorizada)
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                Já autorizada
                            </span>
                        @endif
                        @if($reprovada)
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                Reprovada
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Filial {{ $ordem->CD_FILIAL }} · Empresa {{ $ordem->CD_EMPRESA }} ·
                        Cadastrada em {{ $data($ordem->DT_CADASTRO) }}
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Valor total</p>
                    <p class="text-2xl font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $brl($ordem->VL_TOTAL) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 pt-6 border-t border-gray-100 dark:border-gray-700">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Fornecedor</p>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $ordem->FORNECEDOR_RAZAO_SOCIAL ?: '—' }}</p>
                    @if($ordem->FORNECEDOR_FANTASIA)
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $ordem->FORNECEDOR_FANTASIA }}</p>
                    @endif
                    <p class="text-xs text-gray-400">{{ $ordem->FORNECEDOR_CNPJ ?: '' }} {{ $ordem->FORNECEDOR_EMAIL ?: '' }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Departamento / Comprador</p>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $ordem->DS_DEPARTAMENTO ?: '—' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $ordem->DS_COMPRADOR ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Solicitante / Referente</p>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $ordem->DS_SOLICITANTE ?: '—' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $ordem->DS_REFERENTE ?: '—' }}</p>
                </div>
            </div>

            @if($ordem->DS_OBS)
                <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Observações</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ $ordem->DS_OBS }}</p>
                </div>
            @endif

            @if($autorizada || $reprovada)
                <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-700 grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    @if($autorizada)
                        <p class="text-gray-600 dark:text-gray-300">
                            Autorizada pelo usuário <strong>#{{ $ordem->CD_USUARIO_AUTORIZOU }}</strong>
                            em {{ $dataHora($ordem->DT_AUTORIZACAO) }}.
                        </p>
                    @endif
                    @if($reprovada)
                        <p class="text-gray-600 dark:text-gray-300">
                            Reprovada pelo usuário <strong>#{{ $ordem->CD_USUARIO_REPROVOU }}</strong>
                            em {{ $dataHora($ordem->DT_REPROVACAO) }}.
                            {{ $ordem->DS_MOTIVO_REPROVADO ? 'Motivo: ' . $ordem->DS_MOTIVO_REPROVADO : '' }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        {{-- ============ ITENS ============ --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h2 class="font-extrabold text-gray-900 dark:text-white">Itens ({{ $itens->count() }})</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs uppercase tracking-wider text-gray-400">
                        <tr>
                            <th class="px-6 py-3 text-left font-bold">#</th>
                            <th class="px-6 py-3 text-left font-bold">Material</th>
                            <th class="px-6 py-3 text-right font-bold">Qtde</th>
                            <th class="px-6 py-3 text-left font-bold">Un</th>
                            <th class="px-6 py-3 text-right font-bold">Unitário</th>
                            <th class="px-6 py-3 text-right font-bold">Total</th>
                            <th class="px-6 py-3 text-left font-bold">C. Custo</th>
                            <th class="px-6 py-3 text-left font-bold">Entrega</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($itens as $item)
                            <tr>
                                <td class="px-6 py-3 text-gray-400 tabular-nums">{{ $item->CD_ITEM }}</td>
                                <td class="px-6 py-3">
                                    <p class="text-gray-900 dark:text-white">{{ $item->DS_MATERIAL }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ $item->CD_MATERIAL }}{{ $item->DS_OBS ? ' · ' . $item->DS_OBS : '' }}
                                    </p>
                                </td>
                                <td class="px-6 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                    {{ number_format((float) $item->NR_QUANTIDADE, 3, ',', '.') }}
                                </td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $item->DS_UNIDADE }}</td>
                                <td class="px-6 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $brl($item->VL_UNITARIO) }}</td>
                                <td class="px-6 py-3 text-right tabular-nums font-bold text-gray-900 dark:text-white">{{ $brl($item->VL_TOTAL) }}</td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $item->CD_CENTRO_CUSTO }}</td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $data($item->DT_ENTREGA) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-8 text-center text-gray-400">Sem itens nesta ordem.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ============ DECISÃO ============ --}}
        @include('questor.purchase-orders.partials.decision-panel')

        @if($simulacao ?? null)
            @include('questor.purchase-orders.partials.simulation', ['simulacao' => $simulacao])
        @endif

        {{-- ============ TRILHA DA LARA ============ --}}
        @if($decisoes->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden mt-6">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="font-extrabold text-gray-900 dark:text-white">Histórico de decisões (Lara)</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No Questor aparece só o usuário técnico. Quem de fato decidiu está aqui.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs uppercase tracking-wider text-gray-400">
                            <tr>
                                <th class="px-6 py-3 text-left font-bold">Quando</th>
                                <th class="px-6 py-3 text-left font-bold">Decisão</th>
                                <th class="px-6 py-3 text-left font-bold">Quem</th>
                                <th class="px-6 py-3 text-left font-bold">Motivo</th>
                                <th class="px-6 py-3 text-right font-bold">Linhas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($decisoes as $decisao)
                                <tr>
                                    <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $decisao->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-3">
                                        <span class="px-2 py-0.5 rounded text-xs font-bold
                                            {{ $decisao->action === \App\Models\QuestorOrderDecision::ACTION_APPROVE
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' }}">
                                            {{ $decisao->action === \App\Models\QuestorOrderDecision::ACTION_APPROVE ? 'Aprovação' : 'Reprovação' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-gray-900 dark:text-white">{{ $decisao->decided_by_name ?: '—' }}</td>
                                    <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $decisao->motivo ?: '—' }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums {{ $decisao->tookEffect() ? 'text-gray-600 dark:text-gray-300' : 'text-amber-600 dark:text-amber-400 font-bold' }}">
                                        {{ $decisao->rows_affected }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="px-6 py-3 text-xs text-gray-400 border-t border-gray-100 dark:border-gray-700">
                    Linhas = 0 significa que a gravação foi enviada mas não pegou nada: a ordem já havia saído da fila.
                </p>
            </div>
        @endif

    </div>
</div>
</x-app-layout>
