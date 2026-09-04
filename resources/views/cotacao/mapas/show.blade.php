{{--
    A grade do mapa: itens nas linhas, fornecedores nas colunas — o mesmo
    desenho da planilha que a compra usa hoje, com o que ela não tem.

    DECISÕES DESTA TELA:

    - Preço salva por célula, a cada pausa de digitação. Cotação é feita ao
      telefone ao longo de dias; um botão "salvar tudo" perde o trabalho quando
      o navegador fecha.

    - Os números do rodapé vêm do servidor, não são recalculados aqui. Toda
      conta mora em MapaCalculoService, e uma segunda implementação em
      JavaScript seria uma segunda oportunidade de errar — com a agravante de
      divergir da que gera o XLSX.

    - Célula vazia nunca vira zero. `sem_resposta` fica em branco e `NT` mostra
      o texto; nenhuma das duas entra em soma alguma.

    - O teclado anda pela grade: ENTER desce a coluna (mesma loja, item de
      baixo) e TAB anda a linha (mesmo item, próxima loja), virando para a
      primeira loja do item seguinte no fim. Ver `andar()` no fim do arquivo.
--}}
@php
    $brl = fn($v) => $v === null ? '—' : 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
    $qtd = fn($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');

    $podeEditarPrecos = auth()->user()->can('editarPrecos', $mapa);
    $podeDecidir = auth()->user()->can('definirVencedor', $mapa);
    $podeGerenciar = auth()->user()->can('update', $mapa);

    /*
     | Separador vertical das colunas de fornecedor.
     |
     | A grade tem uma coluna por loja consultada e o olho perde a linha entre
     | uma e outra — daí a borda ser de 2px, e não a de 1px do resto da tabela.
     | A PRIMEIRA coluna ganha 4px porque é ali que o mapa deixa de descrever o
     | que se compra e passa a comparar de quem: é a divisão que mais importa.
     */
    $sep = fn (int $i) => $i === 0
        ? 'border-l-4 border-gray-300 dark:border-gray-600'
        : 'border-l-2 border-gray-200 dark:border-gray-700';

    // Zebra bem discreta. Forte demais competiria com o verde do menor preço,
    // que é o único destaque que precisa ser visto de longe.
    $zebra = fn (int $i) => $i % 2 === 1
        ? 'bg-gray-50/60 dark:bg-gray-900/20'
        : 'bg-white dark:bg-gray-800';

    // Preços indexados para a grade não fazer uma busca por célula.
    $precos = [];
    foreach ($mapa->itens as $i) {
        foreach ($i->precos as $p) {
            $precos[$i->id][$p->cotacao_mapa_fornecedor_id] = $p;
        }
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Mapa de Cotação') }} #{{ $mapa->id }}
        </h2>
    </x-slot>

<div class="py-10 bg-gray-50 dark:bg-gray-900 min-h-screen"
     x-data="mapaGrade({
        calculo: @js($calculo),
        urlPreco: '{{ route('cotacao.mapas.precos.salvar', $mapa) }}',
        urlVencedor: '{{ route('cotacao.mapas.vencedor', $mapa) }}',
        urlHistorico: '{{ url('cotacao/mapas/'.$mapa->id.'/itens') }}',
        podeEditar: {{ $podeEditarPrecos ? 'true' : 'false' }},
        nomes: @js($mapa->fornecedores->pluck('nome', 'id')),
        {{-- A ordem da grade, para o teclado andar por ela sem ler o DOM. --}}
        ordemItens: @js($mapa->itens->pluck('id')->map(fn ($id) => (int) $id)->values()),
        ordemFornecedores: @js($mapa->fornecedores->pluck('id')->map(fn ($id) => (int) $id)->values()),
     })">
    <div class="max-w-[110rem] mx-auto px-4 sm:px-6 lg:px-8">

        {{-- ============ CABEÇALHO ============ --}}
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $mapa->titulo }}</h1>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                        @class([
                            'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' => $mapa->status === 'rascunho',
                            'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' => $mapa->status === 'em_cotacao',
                            'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' => $mapa->status === 'fechado',
                            'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $mapa->status === 'cancelado',
                        ])">
                        {{ $mapa->statusLabel() }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    SC {{ $mapa->questor_solicitacao }}
                    · {{ $mapa->departamento ?: '—' }}
                    · solicitante {{ $mapa->solicitante ?: '—' }}
                    · comprador {{ $mapa->comprador ?: '—' }}
                    · {{ $mapa->data_mapa?->format('d/m/Y') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @can('exportar', $mapa)
                    {{-- Dois layouts, e a escolha é de quem exporta: o clássico
                         é o papel da reunião, o completo é o arquivo de análise.
                         Menu em vez de dois botões soltos porque a diferença
                         entre eles precisa de uma frase para ser entendida. --}}
                    {{-- z-[70]: o menu do sistema é `sticky z-40` (topo) ou
                         `fixed z-40` (lateral), e os submenus dele vão a z-[60].
                         Qualquer valor abaixo disso faz o nav passar por cima
                         deste painel assim que a página rola — foi o que
                         acontecia com z-30. --}}
                    <div class="relative" x-data="{ aberto: false }"
                         @click.outside="aberto = false"
                         @keydown.escape.window="aberto = false">
                        <button type="button" @click="aberto = !aberto"
                                :aria-expanded="aberto ? 'true' : 'false'"
                                class="flex items-center gap-2 px-4 py-2 rounded-xl bg-green-700 hover:bg-green-800 text-white text-sm font-bold shadow transition">
                            Exportar XLSX
                            <svg class="w-4 h-4 transition-transform duration-200" :class="aberto && 'rotate-180'"
                                 fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>

                        <div x-show="aberto" x-cloak x-transition
                             class="absolute right-0 top-full z-[70] mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white dark:bg-gray-800 rounded-xl shadow-2xl ring-1 ring-black/5 border border-gray-100 dark:border-gray-700 overflow-hidden">
                            @foreach($layouts as $chave => $layout)
                                <a href="{{ route('cotacao.mapas.exportar', [$mapa, 'layout' => $chave]) }}"
                                   @click="aberto = false"
                                   class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-50 dark:border-gray-700 last:border-0 transition">
                                    <span class="block text-sm font-bold text-gray-900 dark:text-white">
                                        {{ $layout['nome'] }}
                                        @if($chave === \App\Services\Cotacao\MapaExportService::LAYOUT_PADRAO)
                                            <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300">padrão</span>
                                        @endif
                                    </span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-snug">
                                        {{ $layout['descricao'] }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endcan

                @if($podeGerenciar)
                    <form method="POST" action="{{ route('cotacao.mapas.atualizar-historico', $mapa) }}">
                        @csrf
                        <button type="submit"
                                title="Reconsulta o Questor e regrava a última compra de cada item. Muda a base de comparação do mapa."
                                class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 text-sm font-bold hover:bg-gray-50 transition">
                            Atualizar histórico
                        </button>
                    </form>
                @endif

                @if($podeDecidir && $mapa->editavel())
                    <form method="POST" action="{{ route('cotacao.mapas.update', $mapa) }}"
                          onsubmit="return confirm('Fechar o mapa? Depois disso ele fica somente leitura — reabrir exige permissão própria e fica registrado.')">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="fechado">
                        <button type="submit"
                                class="px-4 py-2 rounded-xl bg-gray-800 dark:bg-gray-700 hover:bg-gray-900 text-white text-sm font-bold shadow transition">
                            Fechar mapa
                        </button>
                    </form>
                @endif

                @can('reabrir', $mapa)
                    <form method="POST" action="{{ route('cotacao.mapas.update', $mapa) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="em_cotacao">
                        <button type="submit"
                                class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold shadow transition">
                            Reabrir
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        @include('partials.alerts')

        @unless($mapa->editavel())
            <div class="mb-6 bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl px-5 py-3">
                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">
                    Mapa {{ mb_strtolower($mapa->statusLabel()) }} — somente leitura.
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    É este documento que sustenta a decisão de compra. Corrigir um preço depois do fechamento,
                    sem trilha, transformaria o mapa em rascunho.
                </p>
            </div>
        @endunless

        {{-- ============ RESUMO ============ --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-100 dark:border-gray-700 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Melhor combinação</p>
                <p class="mt-1 text-xl font-extrabold text-gray-900 dark:text-white tabular-nums" x-text="moeda(calculo.totais.melhor_combinacao)"></p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    compra dividida · frete somado
                    <span class="font-semibold" x-text="moeda(calculo.totais.melhor_combinacao_com_frete)"></span>
                </p>
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1" x-show="!calculo.totais.cotacao_completa">
                    parcial — há item sem nenhuma cotação
                </p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-100 dark:border-gray-700 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Melhor fornecedor único</p>
                <p class="mt-1 text-xl font-extrabold text-gray-900 dark:text-white tabular-nums" x-text="moeda(calculo.totais.melhor_fornecedor_unico_total)"></p>
                <p class="text-xs text-gray-500 dark:text-gray-400" x-text="nomeFornecedor(calculo.totais.melhor_fornecedor_unico_id)"></p>
                <p class="text-xs text-gray-400 mt-1">
                    <span x-text="calculo.totais.fornecedores_completos"></span> de
                    <span x-text="calculo.totais.fornecedores_total"></span> cotaram tudo
                </p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-100 dark:border-gray-700 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Economia projetada</p>
                <p class="mt-1 text-xl font-extrabold tabular-nums"
                   :class="(calculo.totais.economia ?? 0) >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'"
                   x-text="moeda(calculo.totais.economia)"></p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    vs. última compra · <span x-text="calculo.totais.itens_comparaveis"></span> item(ns) comparáveis
                </p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-100 dark:border-gray-700 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Decisão registrada</p>
                <p class="mt-1 text-xl font-extrabold text-gray-900 dark:text-white tabular-nums" x-text="moeda(calculo.totais.total_decidido_com_frete)"></p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <span x-text="calculo.totais.itens_decididos"></span> de
                    <span x-text="calculo.totais.itens_total"></span> itens com vencedor
                </p>
                {{-- Mercadoria e frete separados: o frete é o que a compra
                     dividida cobra por fora, e some do total se a decisão se
                     concentrar numa loja só. --}}
                <p class="text-xs text-gray-400 mt-1" x-show="calculo.totais.itens_decididos">
                    mercadoria <span class="font-semibold" x-text="moeda(calculo.totais.total_decidido)"></span>
                    · frete/desc. <span class="font-semibold" x-text="moeda(calculo.totais.total_decidido_frete)"></span>
                    <span x-show="calculo.totais.lojas_decididas > 1"
                          x-text="` em ${calculo.totais.lojas_decididas} lojas`"></span>
                </p>
            </div>
        </div>

        {{-- ============ GRADE ============ --}}
        @if($mapa->fornecedores->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-10 text-center mb-6">
                <p class="font-extrabold text-gray-900 dark:text-white">O mapa ainda não tem colunas</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Acrescente os fornecedores que você vai consultar — inclusive os que não estão no cadastro do Questor.
                </p>
            </div>
        @else
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm border-collapse">
                    <thead>
                        {{-- Linhas 5, 6 e 7 do modelo: condições por fornecedor --}}
                        <tr class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/50">
                            <th colspan="5" class="px-3 py-1.5 text-right font-semibold">Frete</th>
                            @foreach($mapa->fornecedores as $f)
                                <th class="px-3 py-1.5 text-center font-semibold whitespace-nowrap {{ $sep($loop->index) }}">{{ $f->frete ?: '—' }}</th>
                            @endforeach
                        </tr>
                        <tr class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/50">
                            <th colspan="5" class="px-3 py-1.5 text-right font-semibold">Prazo de entrega</th>
                            @foreach($mapa->fornecedores as $f)
                                <th class="px-3 py-1.5 text-center font-semibold whitespace-nowrap {{ $sep($loop->index) }}">{{ $f->prazo_entrega ?: '—' }}</th>
                            @endforeach
                        </tr>
                        <tr class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                            <th colspan="5" class="px-3 py-1.5 text-right font-semibold">Condição de pagamento</th>
                            @foreach($mapa->fornecedores as $f)
                                <th class="px-3 py-1.5 text-center font-semibold whitespace-nowrap {{ $sep($loop->index) }}">{{ $f->condicao_pagamento ?: '—' }}</th>
                            @endforeach
                        </tr>

                        {{-- Linha 8 do modelo --}}
                        <tr class="bg-gray-100 dark:bg-gray-900 text-xs uppercase tracking-wider text-gray-600 dark:text-gray-300">
                            <th class="px-3 py-3 text-left font-extrabold">Item SC</th>
                            <th class="px-3 py-3 text-left font-extrabold">Und</th>
                            <th class="px-3 py-3 text-left font-extrabold min-w-[22rem]">Descrição</th>
                            <th class="px-3 py-3 text-right font-extrabold">Qnt.</th>
                            {{-- Coluna nova, que a planilha não tem. --}}
                            <th class="px-3 py-3 text-right font-extrabold">Últ. compra</th>
                            @foreach($mapa->fornecedores as $f)
                                <th class="px-3 py-3 text-center font-extrabold min-w-[9rem] {{ $sep($loop->index) }}">
                                    {{ mb_strtoupper($f->nome) }}
                                    <span class="block text-[10px] font-normal normal-case text-gray-400"
                                          x-text="cobertura({{ $f->id }})"></span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($mapa->itens as $item)
                            <tr class="{{ $zebra($loop->index) }} hover:bg-red-50/40 dark:hover:bg-red-900/10 transition-colors">
                                <td class="px-3 py-2 tabular-nums text-gray-600 dark:text-gray-400">
                                    {{ $item->questor_cd_item ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $item->unidade ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-800 dark:text-gray-200">
                                    <button type="button" @click="abrirHistorico({{ $item->id }})"
                                            class="text-left hover:text-red-800 dark:hover:text-red-400 hover:underline">
                                        {{ $item->descricao }}
                                    </button>
                                    @unless($item->temCadastroNoQuestor())
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                              title="Item sem cadastro no Questor — não há histórico de compra por código.">sem cadastro</span>
                                    @endunless
                                    @if($item->origem === 'avulso')
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">avulso</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $qtd($item->quantidade) }}</td>

                                {{-- Últ. compra: valor visível, resto no title --}}
                                <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-400 whitespace-nowrap"
                                    title="{{ $item->temUltimaCompra()
                                        ? $data($item->ult_compra_data) . ' · ' . $item->ult_compra_fornecedor_nome . ' · NF ' . ($item->ult_compra_nf ?: '—')
                                        : ($item->temCadastroNoQuestor() ? 'sem compra anterior' : 'item sem cadastro — sem histórico') }}">
                                    {{ $item->temUltimaCompra() ? $brl($item->ult_compra_valor) : '—' }}
                                </td>

                                @foreach($mapa->fornecedores as $f)
                                    @php $p = $precos[$item->id][$f->id] ?? null; @endphp
                                    <td class="px-2 py-1.5 align-top transition-colors {{ $sep($loop->index) }}"
                                        :class="classeCelula({{ $item->id }}, {{ $f->id }})">
                                        <div class="flex items-center gap-1">
                                            {{-- Os `data-` são o endereço da célula na grade: é por eles
                                                 que Enter e Tab encontram a célula vizinha. --}}
                                            <input type="text" inputmode="decimal"
                                                   data-preco
                                                   data-item="{{ $item->id }}"
                                                   data-fornecedor="{{ $f->id }}"
                                                   value="{{ $p && $p->temPreco() ? number_format((float) $p->valor_unitario, 2, ',', '.') : '' }}"
                                                   @if(!$podeEditarPrecos) disabled @endif
                                                   @input.debounce.700ms="salvarPreco({{ $item->id }}, {{ $f->id }}, $event.target.value, null)"
                                                   @change="salvarPreco({{ $item->id }}, {{ $f->id }}, $event.target.value, null)"
                                                   @keydown.enter.prevent="andar({{ $item->id }}, {{ $f->id }}, 'baixo')"
                                                   @keydown.tab.prevent="andar({{ $item->id }}, {{ $f->id }}, $event.shiftKey ? 'tras' : 'frente')"
                                                   class="w-full text-right tabular-nums text-sm rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800 px-2 py-1 focus:ring-2 focus:ring-red-700 focus:border-red-700"
                                                   placeholder="—">
                                            <select @if(!$podeEditarPrecos) disabled @endif
                                                    @change="salvarPreco({{ $item->id }}, {{ $f->id }}, null, $event.target.value)"
                                                    title="Situação da célula"
                                                    class="text-[10px] rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800 px-1 py-1">
                                                <option value="cotado" @selected($p?->situacao === 'cotado')>R$</option>
                                                <option value="nao_trabalha" @selected($p?->situacao === 'nao_trabalha')>NT</option>
                                                <option value="sem_resposta" @selected($p === null || $p->situacao === 'sem_resposta')>—</option>
                                            </select>
                                        </div>

                                        {{-- Variação vs. última compra: verde abaixo, vermelho acima --}}
                                        <p class="text-[10px] text-right mt-0.5 tabular-nums"
                                           x-show="variacao({{ $item->id }}, {{ $f->id }}) !== null"
                                           :class="variacao({{ $item->id }}, {{ $f->id }}) <= 0
                                                ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                           x-text="percentual(variacao({{ $item->id }}, {{ $f->id }}))"></p>

                                        @if($podeDecidir)
                                            <label class="flex items-center justify-end gap-1 mt-1 text-[10px] text-gray-400 cursor-pointer">
                                                <input type="radio" name="vencedor_{{ $item->id }}" value="{{ $f->id }}"
                                                       @checked($item->vencedor_id === $f->id)
                                                       @change="definirVencedor({{ $item->id }}, {{ $f->id }})"
                                                       class="text-red-800 focus:ring-red-700">
                                                escolher
                                            </label>
                                        @elseif($item->vencedor_id === $f->id)
                                            <p class="text-[10px] text-right mt-1 font-bold text-red-800 dark:text-red-400">escolhido</p>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>

                    {{-- Rodapé, como no modelo --}}
                    <tfoot class="bg-gray-50 dark:bg-gray-900/60 text-sm">
                        <tr>
                            <td colspan="5" class="px-3 py-2 text-right font-bold text-gray-600 dark:text-gray-300">FRETE</td>
                            @foreach($mapa->fornecedores as $f)
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300 {{ $sep($loop->index) }}">{{ $brl($f->valor_frete) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td colspan="5" class="px-3 py-2 text-right font-bold text-gray-600 dark:text-gray-300">
                                SUBTOTAL DA LOJA
                                <span class="block text-[10px] font-normal text-gray-400">tudo o que ela cotou, sem frete</span>
                            </td>
                            @foreach($mapa->fornecedores as $f)
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300 {{ $sep($loop->index) }}"
                                    x-text="moeda(calculo.fornecedores[{{ $f->id }}]?.subtotal)"></td>
                            @endforeach
                        </tr>
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                            <td colspan="5" class="px-3 py-2 text-right font-extrabold text-gray-800 dark:text-gray-100">TOTAL</td>
                            @foreach($mapa->fornecedores as $f)
                                <td class="px-3 py-2 text-right tabular-nums font-extrabold {{ $sep($loop->index) }}"
                                    :class="calculo.fornecedores[{{ $f->id }}]?.cobertura_completa
                                        ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500'"
                                    :title="calculo.fornecedores[{{ $f->id }}]?.cobertura_completa
                                        ? 'Cotou todos os itens — comparável com os demais totais.'
                                        : 'Não cotou todos os itens: este total é só do que ele cotou e NÃO é comparável com quem cobriu o mapa inteiro.'"
                                    x-text="moeda(calculo.fornecedores[{{ $f->id }}]?.total)"></td>
                            @endforeach
                        </tr>

                        {{-- ---- O QUE SE COMPRA DE FATO EM CADA LOJA ----
                             As duas linhas acima falam da PROPOSTA; estas duas
                             falam da DECISÃO. A diferença importa porque quase
                             nunca se compra tudo de um só: o subtotal da loja
                             pode ser alto e a compra nela, pequena. Nulo quando
                             a loja não ganhou nada — zero seria "comprei R$ 0,00
                             aqui", que é outra coisa. --}}
                        <tr class="border-t-2 border-red-200 dark:border-red-900/40 bg-red-50/40 dark:bg-red-900/10">
                            <td colspan="5" class="px-3 py-2 text-right font-bold text-gray-700 dark:text-gray-200">
                                SUBTOTAL DOS ESCOLHIDOS
                                <span class="block text-[10px] font-normal text-gray-400">só os itens em que esta loja ganhou · sem frete</span>
                            </td>
                            @foreach($mapa->fornecedores as $f)
                                <td class="px-3 py-2 text-right tabular-nums font-bold text-red-800 dark:text-red-400 {{ $sep($loop->index) }}">
                                    <span x-text="moeda(calculo.fornecedores[{{ $f->id }}]?.total_vencidos)"></span>
                                    <span class="block text-[10px] font-normal text-gray-400"
                                          x-text="escolhidos({{ $f->id }})"></span>
                                </td>
                            @endforeach
                        </tr>
                        <tr class="bg-red-50/40 dark:bg-red-900/10">
                            <td colspan="5" class="px-3 py-2 text-right font-extrabold text-gray-800 dark:text-gray-100">
                                PEDIDO A ESTA LOJA
                                <span class="block text-[10px] font-normal text-gray-400">escolhidos + frete − desconto · é o valor do pedido</span>
                            </td>
                            @foreach($mapa->fornecedores as $f)
                                <td class="px-3 py-2 text-right tabular-nums font-extrabold text-red-800 dark:text-red-400 {{ $sep($loop->index) }}"
                                    title="Frete e desconto entram uma vez só, não por item: é um pedido."
                                    x-text="moeda(calculo.fornecedores[{{ $f->id }}]?.total_vencidos_com_frete)"></td>
                            @endforeach
                        </tr>

                        <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                            <td colspan="5" class="px-3 py-2 text-right font-extrabold text-gray-800 dark:text-gray-100">
                                TOTAL GERAL DO PEDIDO
                                <span class="block text-[10px] font-normal text-gray-400">melhor preço item a item (compra dividida)</span>
                            </td>
                            <td colspan="{{ max($mapa->fornecedores->count(), 1) }}"
                                class="px-3 py-2 text-right tabular-nums font-extrabold text-red-800 dark:text-red-400"
                                x-text="moeda(calculo.totais.melhor_combinacao)"></td>
                        </tr>

                        {{-- A soma da linha "PEDIDO A ESTA LOJA". Fica ao lado
                             do total teórico de propósito: é a diferença entre
                             o que dava para gastar e o que a decisão registrada
                             vai gastar. --}}
                        <tr class="bg-red-50/40 dark:bg-red-900/10">
                            <td colspan="5" class="px-3 py-2 text-right font-extrabold text-gray-800 dark:text-gray-100">
                                TOTAL DECIDIDO
                                <span class="block text-[10px] font-normal text-gray-400"
                                      x-text="resumoDecisao()"></span>
                            </td>
                            <td colspan="{{ max($mapa->fornecedores->count(), 1) }}"
                                class="px-3 py-2 text-right tabular-nums font-extrabold text-red-800 dark:text-red-400"
                                x-text="moeda(calculo.totais.total_decidido_com_frete)"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

        {{-- ============ GESTÃO DE COLUNAS E ITENS ============ --}}
        @if($podeGerenciar)
            @include('cotacao.mapas.partials.colunas', ['mapa' => $mapa])
        @endif

        {{-- ============ TRILHA ============ --}}
        <details class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-100 dark:border-gray-700 p-6">
            <summary class="cursor-pointer font-extrabold text-gray-900 dark:text-white">
                Histórico de alterações ({{ $mapa->logs->count() }})
            </summary>
            <div class="mt-4 space-y-2 max-h-96 overflow-y-auto">
                @forelse($mapa->logs as $log)
                    <div class="flex items-start gap-3 text-sm border-b border-gray-50 dark:border-gray-700 pb-2">
                        <span class="text-xs text-gray-400 tabular-nums whitespace-nowrap w-32 shrink-0">
                            {{ $log->created_at?->format('d/m/Y H:i') }}
                        </span>
                        <span class="font-semibold text-gray-700 dark:text-gray-300 w-52 shrink-0">{{ $log->acaoLabel() }}</span>
                        <span class="text-gray-500 dark:text-gray-400 w-40 shrink-0">{{ $log->user_nome ?: '—' }}</span>
                        <span class="text-xs text-gray-400 font-mono break-all">{{ json_encode($log->payload, JSON_UNESCAPED_UNICODE) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Sem registros.</p>
                @endforelse
            </div>
        </details>
    </div>

    {{-- ============ MODAL: DRILL-DOWN DO ITEM ============ --}}
    <div x-show="historicoAberto" x-cloak
         class="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-10 bg-black/50 overflow-y-auto"
         @click.self="historicoAberto = false" @keydown.escape.window="historicoAberto = false">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-6xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-extrabold text-gray-900 dark:text-white">Histórico do item</h3>
                <button @click="historicoAberto = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <div class="p-6" x-html="historicoHtml">
                <p class="text-gray-400">Carregando…</p>
            </div>
        </div>
    </div>
</div>

<x-slot name="js">
<script>
function mapaGrade(config) {
    const fmt = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const pct = new Intl.NumberFormat('pt-BR', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 });

    return {
        calculo: config.calculo,
        historicoAberto: false,
        historicoHtml: '',

        /**
         * Nulo vira travessão, nunca "R$ 0,00": zero é um preço, e "não
         * respondeu" não é zero. É a mesma regra do servidor, mantida aqui só
         * para a exibição.
         */
        moeda(v) {
            return (v === null || v === undefined) ? '—' : 'R$ ' + fmt.format(v);
        },

        percentual(v) {
            if (v === null || v === undefined) return '';
            return (v > 0 ? '+' : '') + pct.format(v) + ' vs. últ.';
        },

        variacao(itemId, fornecedorId) {
            return this.calculo.itens?.[itemId]?.celulas?.[fornecedorId]?.variacao_vs_ultima ?? null;
        },

        classeCelula(itemId, fornecedorId) {
            const c = this.calculo.itens?.[itemId]?.celulas?.[fornecedorId];
            if (!c) return '';
            if (c.menor) return 'bg-green-50 dark:bg-green-900/20 ring-1 ring-inset ring-green-300 dark:ring-green-800';
            if (c.segundo) return 'bg-green-50/40 dark:bg-green-900/10';
            return '';
        },

        cobertura(fornecedorId) {
            const f = this.calculo.fornecedores?.[fornecedorId];
            if (!f) return '';
            let texto = `cotou ${f.itens_cotados} de ${f.itens_total}`;
            // "Não trabalha" aparece separado de propósito: quem não vende o
            // item não pode ser lido como quem ignorou a cotação.
            if (f.itens_nao_trabalha > 0) texto += ` · ${f.itens_nao_trabalha} NT`;
            return texto;
        },

        /* -----------------------------------------------------------------
         | Andar pela grade pelo teclado
         |
         | Cotação é digitação em série: o comprador está ao telefone com uma
         | loja e vai descendo a lista de itens, ou tem a folha de um item e
         | percorre as lojas. As duas leituras existem, e por isso as duas
         | teclas fazem coisas diferentes:
         |
         |   ENTER → mesma LOJA, item de baixo   (desce a coluna)
         |   TAB   → mesmo ITEM, próxima loja    (anda a linha)
         |
         | No fim da linha, o Tab desce para a PRIMEIRA loja do item seguinte:
         | é a volta natural da leitura, e sem ela o comprador teria de pegar o
         | mouse a cada item. Shift+Tab faz o caminho de volta.
         |
         | O `.prevent` nos dois é o que tira o `<select>` de situação do
         | caminho — ele continua acessível pelo mouse, mas não interrompe mais
         | a digitação de preço a cada célula.
         |
         | Nada disso salva nada por si: mudar o foco dispara o `change` do
         | campo, que é quem grava. Uma tecla que salvasse por conta própria
         | teria de repetir a regra de situação da célula.
         |----------------------------------------------------------------- */

        andar(itemId, fornecedorId, direcao) {
            const itens = config.ordemItens ?? [];
            const lojas = config.ordemFornecedores ?? [];

            const i = itens.indexOf(itemId);
            const f = lojas.indexOf(fornecedorId);

            if (i < 0 || f < 0) return;

            if (direcao === 'baixo') {
                // Último item da coluna: fica onde está. Voltar ao topo faria
                // o comprador redigitar preço já digitado sem perceber.
                if (i + 1 < itens.length) this.focarCelula(itens[i + 1], fornecedorId);
                return;
            }

            if (direcao === 'frente') {
                if (f + 1 < lojas.length) {
                    this.focarCelula(itemId, lojas[f + 1]);
                } else if (i + 1 < itens.length) {
                    this.focarCelula(itens[i + 1], lojas[0]);
                }

                return;
            }

            // Shift+Tab: o mesmo caminho ao contrário.
            if (f - 1 >= 0) {
                this.focarCelula(itemId, lojas[f - 1]);
            } else if (i - 1 >= 0) {
                this.focarCelula(itens[i - 1], lojas[lojas.length - 1]);
            }
        },

        /**
         * Põe o foco na célula e SELECIONA o conteúdo: quem chega numa célula
         * já preenchida quase sempre vai substituir o número, não emendar
         * dígitos no fim dele.
         */
        focarCelula(itemId, fornecedorId) {
            const campo = document.querySelector(
                `input[data-preco][data-item="${itemId}"][data-fornecedor="${fornecedorId}"]`
            );

            if (! campo || campo.disabled) return;

            campo.focus();
            campo.select();

            // A grade rola na horizontal: sem isto, o Tab levaria o foco para
            // uma coluna fora da vista.
            campo.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        },

        /**
         * Quantos itens esta loja ganhou — a legenda do subtotal dos
         * escolhidos. Vazio quando não ganhou nenhum: a linha já mostra "—" e
         * repetir "0 itens" só ocuparia espaço.
         */
        escolhidos(fornecedorId) {
            const f = this.calculo.fornecedores?.[fornecedorId];
            if (!f || !f.itens_vencidos) return '';
            return f.itens_vencidos === 1 ? '1 item escolhido' : `${f.itens_vencidos} itens escolhidos`;
        },

        /**
         * O custo escondido da compra dividida: cada loja é um pedido e um
         * frete. Por isso o número de lojas aparece junto do total decidido.
         */
        resumoDecisao() {
            const t = this.calculo.totais;
            if (!t.itens_decididos) return 'nenhum item escolhido ainda';

            let texto = `${t.itens_decididos} de ${t.itens_total} itens`;
            texto += t.lojas_decididas === 1 ? ' · 1 loja' : ` · ${t.lojas_decididas} lojas`;

            if (t.total_decidido_frete) {
                texto += ` · frete/desc. ${this.moeda(t.total_decidido_frete)}`;
            }

            return texto;
        },

        nomeFornecedor(id) {
            if (!id) return 'nenhum fornecedor cotou o mapa inteiro';
            return config.nomes[id] ?? ('#' + id);
        },

        /**
         * Converte o que o comprador digitou ("1.234,56", "1234,56", "1234.56")
         * em número. O servidor normaliza de novo — aqui é só para decidir a
         * situação da célula.
         */
        numero(texto) {
            if (texto === null || texto === undefined) return null;
            const limpo = String(texto).trim().replace(/\./g, '').replace(',', '.');
            if (limpo === '') return null;
            const n = Number(limpo);
            return Number.isFinite(n) ? n : null;
        },

        async salvarPreco(itemId, fornecedorId, valorTexto, situacao) {
            if (!config.podeEditar) return;

            const celula = this.calculo.itens?.[itemId]?.celulas?.[fornecedorId] ?? {};

            // Chamada vinda do campo de texto: campo vazio quer dizer "sem
            // resposta"; com número, "cotado". A troca do seletor manda a
            // situação explícita e o valor que já estava na célula.
            let valor = valorTexto !== null ? this.numero(valorTexto) : celula.valor ?? null;
            let sit = situacao;

            if (sit === null) {
                sit = valor === null
                    ? (celula.situacao === 'nao_trabalha' ? 'nao_trabalha' : 'sem_resposta')
                    : 'cotado';
            }

            if (sit !== 'cotado') valor = null;
            if (sit === 'cotado' && valor === null) return; // nada a salvar ainda

            try {
                const res = await fetch(config.urlPreco, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        item_id: itemId,
                        fornecedor_id: fornecedorId,
                        situacao: sit,
                        valor_unitario: valor,
                    }),
                });

                if (!res.ok) {
                    const erro = await res.json().catch(() => ({}));
                    alert(erro.message || 'Não foi possível salvar este preço.');
                    return;
                }

                const dados = await res.json();
                // Os totais vêm recalculados do servidor: menor preço, cobertura
                // e rodapé nunca divergem do que sai no XLSX.
                this.calculo = dados.calculo;
            } catch (e) {
                alert('Falha de rede ao salvar o preço. Confira a conexão e tente de novo.');
            }
        },

        async definirVencedor(itemId, fornecedorId) {
            try {
                const res = await fetch(config.urlVencedor, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ item_id: itemId, fornecedor_id: fornecedorId }),
                });

                if (!res.ok) {
                    alert('Não foi possível registrar a decisão.');
                    return;
                }

                // O total decidido muda: recarrega para o rodapé ficar coerente
                // sem duplicar aqui a conta que o servidor já sabe fazer.
                window.location.reload();
            } catch (e) {
                alert('Falha de rede ao registrar a decisão.');
            }
        },

        async abrirHistorico(itemId) {
            this.historicoHtml = '<p class="text-gray-400">Carregando…</p>';
            this.historicoAberto = true;

            try {
                const res = await fetch(`${config.urlHistorico}/${itemId}/historico`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.historicoHtml = res.ok
                    ? await res.text()
                    : '<p class="text-red-600">Não foi possível carregar o histórico.</p>';
            } catch (e) {
                this.historicoHtml = '<p class="text-red-600">Falha de rede ao carregar o histórico.</p>';
            }
        },
    };
}
</script>
</x-slot>
</x-app-layout>
