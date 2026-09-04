{{--
    Lista dos mapas de cotação.

    O Questor NÃO é consultado nesta tela: tudo aqui é da Lara. É de propósito —
    a lista precisa abrir mesmo com o ERP fora do ar, e só a criação de um mapa
    novo depende dele.
--}}
@php
    $cores = [
        'rascunho'   => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        'em_cotacao' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'fechado'    => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'cancelado'  => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Mapas de Cotação') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Mapas de Cotação</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">
                    Comparação de fornecedores a partir das solicitações de compra do Questor.
                    Nada é gravado no ERP.
                </p>
            </div>

            @can('create', App\Models\CotacaoMapa::class)
                <a href="{{ route('cotacao.mapas.previa') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-red-800 hover:bg-red-900 text-white text-sm font-bold shadow-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nova cotação
                </a>
            @endcan
        </div>

        @include('partials.alerts')

        @unless($config['enabled'])
            <div class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-4">
                <p class="font-bold text-amber-800 dark:text-amber-300">Integração com o Questor desligada</p>
                <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                    Os mapas já criados continuam abrindo e editáveis. Gerar um mapa novo ou consultar histórico
                    precisa de <code class="font-mono">QUESTOR_ENABLED</code> ligado no <code class="font-mono">.env</code>.
                </p>
            </div>
        @endunless

        {{-- ============ FILTROS ============ --}}
        <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
            <input type="text" name="busca" value="{{ $filtros['busca'] }}"
                   placeholder="Nº da SC, título, solicitante ou comprador"
                   class="flex-1 min-w-[18rem] rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-sm">
            <select name="status"
                    class="rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-sm">
                <option value="">Todas as situações</option>
                @foreach(['rascunho' => 'Rascunho', 'em_cotacao' => 'Em cotação', 'fechado' => 'Fechado', 'cancelado' => 'Cancelado'] as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected($filtros['status'] === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
            <button type="submit"
                    class="px-5 py-2 rounded-xl bg-gray-800 dark:bg-gray-700 text-white text-sm font-bold hover:bg-gray-900 transition">
                Filtrar
            </button>
        </form>

        {{-- ============ LISTA ============ --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left font-bold">Mapa</th>
                            <th class="px-4 py-3 text-left font-bold">SC</th>
                            <th class="px-4 py-3 text-left font-bold">Título</th>
                            <th class="px-4 py-3 text-left font-bold">Solicitante</th>
                            <th class="px-4 py-3 text-left font-bold">Comprador</th>
                            <th class="px-4 py-3 text-center font-bold">Itens</th>
                            <th class="px-4 py-3 text-center font-bold">Colunas</th>
                            <th class="px-4 py-3 text-left font-bold">Data</th>
                            <th class="px-4 py-3 text-left font-bold">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($mapas as $mapa)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                                <td class="px-4 py-3 font-bold text-gray-900 dark:text-white tabular-nums">
                                    <a href="{{ route('cotacao.mapas.show', $mapa) }}" class="text-red-800 dark:text-red-400 hover:underline">
                                        #{{ $mapa->id }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 tabular-nums text-gray-700 dark:text-gray-300">{{ $mapa->questor_solicitacao }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 max-w-md truncate" title="{{ $mapa->titulo }}">
                                    {{ $mapa->titulo }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $mapa->solicitante ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $mapa->comprador ?: '—' }}</td>
                                <td class="px-4 py-3 text-center tabular-nums text-gray-700 dark:text-gray-300">{{ $mapa->itens_count }}</td>
                                <td class="px-4 py-3 text-center tabular-nums text-gray-700 dark:text-gray-300">{{ $mapa->fornecedores_count }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $mapa->data_mapa?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $cores[$mapa->status] ?? '' }}">
                                        {{ $mapa->statusLabel() }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-gray-400">
                                    Nenhum mapa por aqui ainda.
                                    @can('create', App\Models\CotacaoMapa::class)
                                        <a href="{{ route('cotacao.mapas.previa') }}" class="text-red-800 dark:text-red-400 font-bold hover:underline">
                                            Buscar uma solicitação de compra
                                        </a>
                                        para começar.
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $mapas->links() }}</div>
    </div>
</div>
</x-app-layout>
