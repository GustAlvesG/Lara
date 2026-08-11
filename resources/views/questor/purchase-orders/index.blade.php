{{--
    Fila de ordens de compra pendentes de autorização, lida do Questor.

    SÓ LEITURA — e a faixa do topo diz isso em voz alta. Enquanto o módulo
    estiver em simulação, aprovar aqui não muda nada no ERP: o objetivo desta
    versão é conferir, contra a base real, que a fila é a certa e que o UPDATE
    montado pegaria a linha certa.
--}}
@php
    $brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Ordens de Compra') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Autorização de Compras</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                Ordens pendentes de autorização no Questor — status PENDENTE, sem autorizador e sem reprovador.
            </p>
        </div>

        @include('partials.alerts')
        @include('questor.purchase-orders.partials.mode-banner', ['config' => $config])

        @if($erro)
            <div class="mb-6 bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-red-200 dark:border-red-900 p-6">
                <p class="font-extrabold text-red-700 dark:text-red-400">O Questor não respondeu</p>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $erro }}</p>
                <p class="text-xs text-gray-400 mt-3">
                    Uma fila vazia por falha de conexão é indistinguível de "não há nada para aprovar" — por isso
                    esta tela avisa em vez de mostrar a lista vazia.
                </p>
            </div>
        @endif

        {{-- ============ RESUMO ============ --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Ordens pendentes</p>
                <p class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $resumo['quantidade'] }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Valor parado</p>
                <p class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $brl($resumo['valor']) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Usuário técnico</p>
                @if($usuarioTecnico)
                    <p class="mt-1 text-sm font-extrabold text-gray-900 dark:text-white truncate">{{ $usuarioTecnico->DS_LOGIN }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        #{{ $usuarioTecnico->CD_CODUSUARIO }} ·
                        {{ (int) $usuarioTecnico->X_ATIVO ? 'ativo' : 'INATIVO' }}
                    </p>
                @else
                    <p class="mt-1 text-sm font-extrabold text-amber-600 dark:text-amber-400">Não configurado</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">QUESTOR_USUARIO_TECNICO</p>
                @endif
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Corte da fila</p>
                <p class="mt-1 text-sm font-extrabold text-gray-900 dark:text-white">
                    {{ $config['desde'] ? 'desde ' . $data($config['desde']) : 'todo o histórico' }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $config['filiais'] ? 'filiais ' . implode(', ', $config['filiais']) : 'todas as filiais' }}
                </p>
            </div>
        </div>

        {{-- ============ FILTROS ============ --}}
        <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
            <input type="text" name="busca" value="{{ $filtros['busca'] }}"
                   placeholder="Nº da ordem, fornecedor ou solicitante"
                   class="flex-1 min-w-[16rem] rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-sm">
            <select name="filial"
                    class="rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-sm">
                <option value="">Todas as filiais</option>
                @foreach($filiais as $filial)
                    <option value="{{ $filial }}" @selected((string) $filtros['filial'] === (string) $filial)>
                        Filial {{ $filial }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 rounded-xl bg-[#A00001] text-white text-sm font-bold">Filtrar</button>
            @if($filtros['busca'] || $filtros['filial'])
                <a href="{{ route('questor.purchase-orders.index') }}"
                   class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm font-bold text-gray-600 dark:text-gray-300">
                    Limpar
                </a>
            @endif
        </form>

        {{-- ============ LISTA ============ --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs uppercase tracking-wider text-gray-400">
                        <tr>
                            <th class="px-6 py-3 text-left font-bold">Ordem</th>
                            <th class="px-6 py-3 text-left font-bold">Fornecedor</th>
                            <th class="px-6 py-3 text-left font-bold">Departamento</th>
                            <th class="px-6 py-3 text-left font-bold">Solicitante</th>
                            <th class="px-6 py-3 text-right font-bold">Itens</th>
                            <th class="px-6 py-3 text-right font-bold">Valor</th>
                            <th class="px-6 py-3 text-left font-bold">Cadastro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($ordens as $ordem)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition cursor-pointer"
                                onclick="window.location='{{ route('questor.purchase-orders.show', $ordem->CD_ORDEM_COMPRA) }}'">
                                <td class="px-6 py-4">
                                    <a href="{{ route('questor.purchase-orders.show', $ordem->CD_ORDEM_COMPRA) }}"
                                       class="font-extrabold text-[#A00001] dark:text-red-400">
                                        #{{ $ordem->CD_ORDEM_COMPRA }}
                                    </a>
                                    <p class="text-xs text-gray-400">Filial {{ $ordem->CD_FILIAL }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-gray-900 dark:text-white">
                                        {{ $ordem->FORNECEDOR_FANTASIA ?: ($ordem->FORNECEDOR_RAZAO_SOCIAL ?: '—') }}
                                    </p>
                                    <p class="text-xs text-gray-400">{{ $ordem->FORNECEDOR_CNPJ ?: '' }}</p>
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ $ordem->DS_DEPARTAMENTO ?: '—' }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ $ordem->DS_SOLICITANTE ?: '—' }}</td>
                                <td class="px-6 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $ordem->NR_ITENS }}</td>
                                <td class="px-6 py-4 text-right tabular-nums font-bold text-gray-900 dark:text-white">{{ $brl($ordem->VL_TOTAL) }}</td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ $data($ordem->DT_CADASTRO) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                    {{ $erro ? 'Não foi possível carregar a fila.' : 'Nenhuma ordem pendente de autorização.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($ordens->count() >= (int) config('questor.limite_listagem', 200))
            <p class="mt-3 text-xs text-amber-600 dark:text-amber-400">
                A lista foi cortada em {{ config('questor.limite_listagem') }} ordens — use os filtros para chegar à que procura.
            </p>
        @endif

    </div>
</div>
</x-app-layout>
