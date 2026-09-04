{{--
    Drill-down de um item — conteúdo do modal, injetado por fetch.

    Cinco respostas, cada uma de uma pergunta que o comprador faz de verdade:
      · quanto se pagou nas últimas compras (query 4)
      · quem já forneceu, e por quanto (query 5)
      · quem está homologado, mesmo sem nunca ter fornecido (query 6)
      · o que já se cotou disto dentro do Questor (query 7.b)
      · o que já foi PEDIDO e ainda não faturou (query 8) — um preço acertado
        semana passada só vira NF quando a mercadoria chega

    A ARMADILHA Nº 1 APARECE AQUI: item sem CD_MATERIAL não tem histórico por
    código. Isso não é falha — é o item digitado como texto livre na
    solicitação. A tela diz isso e segue.
--}}
@php
    $brl = fn($v) => $v === null ? '—' : 'R$ ' . number_format((float) $v, 2, ',', '.');
    $data = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
    $num = fn($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
@endphp

<div class="space-y-6">

    {{-- Cabeçalho do item --}}
    <div>
        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">
            Item {{ $item->questor_cd_item ?? 'avulso' }}
            @if($item->questor_cd_material)
                · material {{ $item->questor_cd_material }}
            @endif
        </p>
        <h4 class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $item->descricao }}</h4>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $num($item->quantidade) }} {{ $item->unidade }}
            @if($item->temUltimaCompra())
                · última compra {{ $brl($item->ult_compra_valor) }}
                em {{ $data($item->ult_compra_data) }}
                de {{ $item->ult_compra_fornecedor_nome }}
                (NF {{ $item->ult_compra_nf ?: '—' }})
            @endif
        </p>
    </div>

    @if(! $item->temCadastroNoQuestor())
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-5">
            <p class="font-bold text-amber-800 dark:text-amber-300">Item sem cadastro — sem histórico</p>
            <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                Este item foi digitado como texto livre na solicitação, sem vínculo com o cadastro de materiais
                do Questor (<code class="font-mono text-xs">CD_MATERIAL</code> nulo). Não existe histórico de
                compra por código para ele — o que não impede cotá-lo normalmente.
            </p>
            <p class="text-xs text-amber-600 dark:text-amber-500 mt-2">
                Para ganhar histórico nas próximas vezes, o item precisa ser cadastrado em
                <code class="font-mono">TBL_MATERIAIS</code> pelo próprio Questor.
            </p>
        </div>
    @elseif($erro)
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-5">
            <p class="font-bold text-red-800 dark:text-red-300">O Questor não respondeu</p>
            <p class="text-sm text-red-700 dark:text-red-400 mt-1">{{ $erro }}</p>
        </div>
    @else

        {{-- ============ 1. ÚLTIMAS COMPRAS ============ --}}
        <div>
            <h5 class="font-extrabold text-gray-900 dark:text-white mb-2">Últimas compras</h5>
            @if($historico->isEmpty())
                <p class="text-sm text-gray-400 italic">Nenhuma entrada de compra na janela consultada.</p>
            @else
                <div class="overflow-x-auto border border-gray-100 dark:border-gray-700 rounded-xl">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-3 py-2 text-left font-bold">Data</th>
                                <th class="px-3 py-2 text-left font-bold">NF</th>
                                <th class="px-3 py-2 text-left font-bold">Fornecedor</th>
                                <th class="px-3 py-2 text-right font-bold">Qtd</th>
                                <th class="px-3 py-2 text-right font-bold">Vl. unit.</th>
                                <th class="px-3 py-2 text-right font-bold">Custo</th>
                                <th class="px-3 py-2 text-left font-bold">Operação</th>
                                <th class="px-3 py-2 text-left font-bold">Pagamento</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($historico as $h)
                                <tr>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $data($h->DT_ENTRADA) }}</td>
                                    <td class="px-3 py-2 tabular-nums">{{ $h->NR_DOCUMENTO }}</td>
                                    <td class="px-3 py-2">
                                        {{ $h->DS_FANTASIA ?: $h->DS_ENTIDADE }}
                                        @if($h->DS_CIDADE)
                                            <span class="text-gray-400">· {{ $h->DS_CIDADE }}/{{ $h->DS_UF }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $num($h->NR_QUANTIDADE) }} {{ $h->DS_UNIDADE }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-bold">{{ $brl($h->VL_UNITARIO) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ $brl($h->VL_CUSTO_COMPRA) }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $h->DS_CME }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $h->DS_FORMA_PAGAMENTO ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] text-gray-400 mt-1">
                    Só operações que o Questor conta como compra
                    (<code class="font-mono">X_ATUALIZA_DT_ULTIMA_COMPRA = 1</code>) — devolução, transferência
                    e remessa ficam de fora.
                </p>
            @endif
        </div>

        {{-- ============ 2. QUEM JÁ FORNECEU ============ --}}
        <div>
            <h5 class="font-extrabold text-gray-900 dark:text-white mb-2">Fornecedores que já venderam este item</h5>
            @if($fornecedores->isEmpty())
                <p class="text-sm text-gray-400 italic">Nenhum.</p>
            @else
                <div class="overflow-x-auto border border-gray-100 dark:border-gray-700 rounded-xl">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-3 py-2 text-left font-bold">Fornecedor</th>
                                <th class="px-3 py-2 text-left font-bold">Contato</th>
                                <th class="px-3 py-2 text-center font-bold">Compras</th>
                                <th class="px-3 py-2 text-left font-bold">Última</th>
                                <th class="px-3 py-2 text-right font-bold">Últ. preço</th>
                                <th class="px-3 py-2 text-right font-bold">Médio</th>
                                <th class="px-3 py-2 text-right font-bold">Mínimo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($fornecedores as $f)
                                <tr>
                                    <td class="px-3 py-2">
                                        {{ $f->nomeCurto() }}
                                        @unless($f->ativo)
                                            <span class="ml-1 px-1 py-0.5 rounded text-[10px] font-bold bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">inativo</span>
                                        @endunless
                                    </td>
                                    <td class="px-3 py-2 text-gray-500">
                                        {{ collect([$f->telefone, $f->email])->filter()->implode(' · ') ?: '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-center tabular-nums">{{ $f->qtdCompras }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $data($f->ultimaCompra) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-bold">{{ $brl($f->valorUnitarioUltimo) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ $brl($f->valorUnitarioMedio) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ $brl($f->valorUnitarioMinimo) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ============ 3. HOMOLOGADOS ============ --}}
        @if($homologados->isNotEmpty())
            <div>
                <h5 class="font-extrabold text-gray-900 dark:text-white mb-2">
                    Fornecedores homologados
                    <span class="text-xs font-normal text-gray-400">— quem PODE fornecer, mesmo sem histórico</span>
                </h5>
                <div class="flex flex-wrap gap-2">
                    @foreach($homologados as $h)
                        <span class="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-xs text-gray-700 dark:text-gray-200">
                            {{ $h->DS_FANTASIA ?: $h->DS_ENTIDADE }}
                            @if($h->DS_NOME_PRODUTO_FORNECEDOR)
                                <span class="text-gray-400">· {{ $h->DS_NOME_PRODUTO_FORNECEDOR }}</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ============ 4. COTAÇÕES ANTERIORES NO QUESTOR ============ --}}
        @if($cotacoes->isNotEmpty())
            <div>
                <h5 class="font-extrabold text-gray-900 dark:text-white mb-2">Cotações anteriores registradas no Questor</h5>
                <div class="overflow-x-auto border border-gray-100 dark:border-gray-700 rounded-xl max-h-56 overflow-y-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 uppercase tracking-wider sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left font-bold">Cotação</th>
                                <th class="px-3 py-2 text-left font-bold">Data</th>
                                <th class="px-3 py-2 text-left font-bold">Fornecedor</th>
                                <th class="px-3 py-2 text-right font-bold">Qtd</th>
                                <th class="px-3 py-2 text-right font-bold">Vl. unit.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($cotacoes as $c)
                                <tr>
                                    <td class="px-3 py-2 tabular-nums">{{ $c->CD_COTACAO }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $data($c->DT_EMISSAO) }}</td>
                                    <td class="px-3 py-2">{{ $c->DS_ENTIDADE }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $num($c->NR_QUANTIDADE) }} {{ $c->DS_UNIDADE }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-bold">{{ $brl($c->VL_UNITARIO) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ============ 5. ORDENS EM ABERTO ============ --}}
        @if($ordens->isNotEmpty())
            <div>
                <h5 class="font-extrabold text-gray-900 dark:text-white mb-2">
                    Ordens de compra
                    <span class="text-xs font-normal text-gray-400">— pedido feito, nota ainda não entrou</span>
                </h5>
                <div class="overflow-x-auto border border-gray-100 dark:border-gray-700 rounded-xl max-h-56 overflow-y-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 uppercase tracking-wider sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left font-bold">OC</th>
                                <th class="px-3 py-2 text-left font-bold">Emissão</th>
                                <th class="px-3 py-2 text-left font-bold">Fornecedor</th>
                                <th class="px-3 py-2 text-right font-bold">Qtd</th>
                                <th class="px-3 py-2 text-right font-bold">Saldo</th>
                                <th class="px-3 py-2 text-right font-bold">Vl. unit.</th>
                                <th class="px-3 py-2 text-left font-bold">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($ordens as $o)
                                <tr>
                                    <td class="px-3 py-2 tabular-nums">{{ $o->CD_ORDEM_COMPRA }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $data($o->DT_EMISSAO) }}</td>
                                    <td class="px-3 py-2">{{ $o->DS_FANTASIA ?: $o->DS_ENTIDADE }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $num($o->NR_QUANTIDADE) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $num($o->NR_SALDO) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-bold">{{ $brl($o->VL_UNITARIO) }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $o->DS_STATUS ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
