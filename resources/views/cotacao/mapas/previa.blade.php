{{--
    Busca da Solicitação de Compra e prévia do mapa.

    Duas entradas, porque são duas situações reais: quem tem o número da SC na
    mão digita o número; quem só lembra "as tintas do parquinho" usa a busca por
    período e texto (query 1.b), que procura inclusive na descrição dos itens.

    A prévia mostra o que o mapa vai virar ANTES de criá-lo — inclusive quais
    itens não têm cadastro no Questor e, por isso, nascerão sem histórico.
--}}
@php
    $brl = fn($v) => $v === null ? '—' : 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
    $qtd = fn($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Nova Cotação') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Buscar solicitação de compra</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                O Questor é lido, nunca escrito. A cotação inteira fica na Lara.
            </p>
        </div>

        @include('partials.alerts')

        @if($erro)
            <div class="mb-6 bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-red-200 dark:border-red-900 p-6">
                <p class="font-extrabold text-red-700 dark:text-red-400">Não deu para consultar</p>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $erro }}</p>
            </div>
        @endif

        {{-- ============ BUSCA ============ --}}
        <div class="grid gap-4 lg:grid-cols-3 mb-8">
            <form method="GET" class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Sei o número</p>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Nº da solicitação (SC)</label>
                <input type="number" name="solicitacao" value="{{ $codigo }}" min="1" placeholder="34334" required
                       class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm tabular-nums">
                <button type="submit"
                        class="mt-4 w-full px-5 py-2.5 rounded-xl bg-red-800 hover:bg-red-900 text-white text-sm font-bold shadow transition">
                    Buscar solicitação
                </button>
            </form>

            <form method="GET" class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Não sei o número</p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">De</label>
                        <input type="date" name="de" value="{{ $filtros['de'] }}"
                               class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Até</label>
                        <input type="date" name="ate" value="{{ $filtros['ate'] }}"
                               class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Texto</label>
                        <input type="text" name="busca" value="{{ $filtros['busca'] }}" placeholder="solicitante, obs. ou item"
                               class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                    </div>
                </div>
                <button type="submit"
                        class="mt-4 px-5 py-2.5 rounded-xl bg-gray-800 dark:bg-gray-700 hover:bg-gray-900 text-white text-sm font-bold shadow transition">
                    Procurar
                </button>
                <p class="text-xs text-gray-400 mt-2">Procura também na descrição dos itens da solicitação.</p>
            </form>
        </div>

        {{-- ============ RESULTADOS DA BUSCA POR PERÍODO ============ --}}
        @if($resultados->isNotEmpty())
            <div class="mb-8 bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <p class="font-extrabold text-gray-900 dark:text-white">{{ $resultados->count() }} solicitação(ões) encontrada(s)</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-bold">SC</th>
                                <th class="px-4 py-3 text-left font-bold">Data</th>
                                <th class="px-4 py-3 text-left font-bold">Solicitante</th>
                                <th class="px-4 py-3 text-left font-bold">Observação</th>
                                <th class="px-4 py-3 text-center font-bold">Itens</th>
                                <th class="px-4 py-3 text-left font-bold">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($resultados as $r)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="px-4 py-3 font-bold tabular-nums">
                                        <a href="{{ route('cotacao.mapas.previa', ['solicitacao' => $r->codigo]) }}"
                                           class="text-red-800 dark:text-red-400 hover:underline">{{ $r->codigo }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $data($r->dataCadastro) }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $r->solicitante ?: '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400 max-w-lg truncate">{{ $r->observacoes ?: '—' }}</td>
                                    <td class="px-4 py-3 text-center tabular-nums">{{ $r->qtdItens }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $r->status ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ============ PRÉVIA DA SOLICITAÇÃO ============ --}}
        @if($solicitacao)
            @if($mapaExistente)
                <div class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="font-extrabold text-amber-800 dark:text-amber-300">
                            Já existe mapa para a SC {{ $solicitacao->codigo }}
                        </p>
                        <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                            Mapa #{{ $mapaExistente->id }} — {{ $mapaExistente->titulo }}
                            ({{ mb_strtolower($mapaExistente->statusLabel()) }}).
                            Gerar outro duplicaria o trabalho de quem já está cotando.
                        </p>
                    </div>
                    <a href="{{ route('cotacao.mapas.show', $mapaExistente) }}"
                       class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold shadow transition shrink-0">
                        Abrir o mapa existente
                    </a>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden mb-6">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Solicitação de compra</p>
                    <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">
                        SC {{ $solicitacao->codigo }}
                    </h2>
                    <div class="grid gap-x-8 gap-y-2 sm:grid-cols-2 lg:grid-cols-4 mt-4 text-sm">
                        <div><span class="text-gray-400">Solicitante:</span> <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $solicitacao->solicitante ?: '—' }}</span></div>
                        <div><span class="text-gray-400">Departamento:</span> <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $solicitacao->departamento ?: '—' }}</span></div>
                        <div><span class="text-gray-400">Filial:</span> <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $solicitacao->filialNome ?: $solicitacao->filial }}</span></div>
                        <div><span class="text-gray-400">Cadastro:</span> <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $data($solicitacao->dataCadastro) }}</span></div>
                        <div class="sm:col-span-2 lg:col-span-4"><span class="text-gray-400">Observação:</span> <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $solicitacao->observacoes ?: '—' }}</span></div>
                    </div>
                </div>

                {{-- Itens --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-bold">Item</th>
                                <th class="px-4 py-3 text-left font-bold">Und</th>
                                <th class="px-4 py-3 text-left font-bold">Descrição</th>
                                <th class="px-4 py-3 text-right font-bold">Qnt.</th>
                                <th class="px-4 py-3 text-left font-bold">Última compra</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($itens as $item)
                                @php $ultima = $ultimasCompras->get($item->item); @endphp
                                <tr>
                                    <td class="px-4 py-3 tabular-nums text-gray-700 dark:text-gray-300">{{ $item->item }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $item->unidade ?: '—' }}</td>
                                    <td class="px-4 py-3 text-gray-800 dark:text-gray-200">
                                        {{ $item->descricao }}
                                        @if($item->semCadastro())
                                            {{-- A armadilha nº 1, dita em voz alta antes de o mapa nascer. --}}
                                            <span class="ml-2 px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                                  title="Item digitado como texto livre na solicitação, sem cadastro em TBL_MATERIAIS. Não há histórico de compra por código.">
                                                sem cadastro
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $qtd($item->quantidade) }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        @if($ultima?->encontrada())
                                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $brl($ultima->valorUnitario) }}</span>
                                            <span class="text-xs">· {{ $data($ultima->data) }} · {{ $ultima->fornecedorCurto() }}</span>
                                        @elseif($item->semCadastro())
                                            <span class="text-xs italic">item sem cadastro — sem histórico</span>
                                        @else
                                            <span class="text-xs italic">sem compra anterior</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ============ GERAR O MAPA ============ --}}
            <form method="POST" action="{{ route('cotacao.mapas.store') }}"
                  class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                @csrf
                <input type="hidden" name="solicitacao" value="{{ $solicitacao->codigo }}">

                <h3 class="text-lg font-extrabold text-gray-900 dark:text-white mb-4">Gerar o mapa</h3>

                <div class="grid gap-4 sm:grid-cols-2 mb-6">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            Título <span class="font-normal text-gray-400">— vai no cabeçalho do XLSX</span>
                        </label>
                        <input type="text" name="titulo" maxlength="255"
                               value="{{ old('titulo', $solicitacao->tituloSugerido()) }}"
                               class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                        @error('titulo')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Comprador</label>
                        <input type="text" name="comprador" maxlength="100"
                               value="{{ old('comprador', auth()->user()->name) }}"
                               class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                    </div>
                </div>

                {{-- Sugestão de colunas --}}
                <div class="border-t border-gray-100 dark:border-gray-700 pt-5">
                    <p class="text-sm font-bold text-gray-800 dark:text-gray-200">
                        A quem pedir cotação?
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-4">
                        Fornecedores que já venderam estes itens à empresa. Marque quem vira coluna do mapa —
                        nenhum é marcado automaticamente, senão o mapa nasce com vinte colunas.
                        Você pode acrescentar outros depois, inclusive quem não está no cadastro do Questor.
                    </p>

                    @if($sugestoes->isEmpty())
                        <p class="text-sm text-gray-400 italic">
                            Nenhum histórico de fornecimento para os itens desta solicitação.
                            O mapa nasce sem colunas e você as acrescenta na tela dele.
                        </p>
                    @else
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 max-h-80 overflow-y-auto pr-1">
                            @foreach($sugestoes as $s)
                                <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-red-300 dark:hover:border-red-800 cursor-pointer transition">
                                    <input type="checkbox" name="fornecedores[]" value="{{ $s['codigo'] }}"
                                           class="mt-0.5 rounded border-gray-300 text-red-800 focus:ring-red-700">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-bold text-gray-900 dark:text-white truncate">{{ $s['nome'] }}</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                                            atende {{ $s['itens_atendidos'] }} de {{ $itens->count() }} item(ns)
                                            · última compra {{ $data($s['ultima_compra']) }}
                                        </span>
                                        @unless($s['ativo'])
                                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                inativo no Questor
                                            </span>
                                        @endunless
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('fornecedores')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
                    @endif
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                            class="px-6 py-3 rounded-xl bg-red-800 hover:bg-red-900 text-white text-sm font-bold shadow-lg transition">
                        Gerar mapa com {{ $itens->count() }} item(ns)
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
</x-app-layout>
