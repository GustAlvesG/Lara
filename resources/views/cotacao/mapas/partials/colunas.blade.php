{{--
    Gestão das colunas (fornecedores) e dos itens avulsos.

    A ALTERAÇÃO PEDIDA ESTÁ AQUI: dentro da Lara, o comprador pode acrescentar
    fornecedores à cotação a qualquer momento. A busca no cadastro do Questor é
    conveniência — o campo de nome basta, e um fornecedor que ainda não existe
    no ERP entra na cotação do mesmo jeito. A boa proposta muitas vezes vem de
    quem nunca vendeu para a empresa.

    Frete, prazo e condição de pagamento são texto livre de propósito: o mapa em
    uso hoje tem "CONFIRMAR", "3DU" e "Á VISTA", que não existem em
    TBL_PRAZO_ENTREGA nem em TBL_FINANCEIRO_FORMAS_PAGAMENTO.
--}}
@php
    $brl2 = fn($v) => number_format((float) $v, 2, ',', '.');
@endphp

<div class="grid gap-6 lg:grid-cols-3 mb-6"
     x-data="gestaoColunas('{{ route('cotacao.mapas.fornecedores.buscar') }}', '{{ route('cotacao.mapas.fornecedores.condicoes') }}')"
     x-init="carregarCondicoes()">

    {{-- ============ NOVA COLUNA ============ --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="font-extrabold text-gray-900 dark:text-white">Acrescentar fornecedor</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-4">
            Busque no cadastro do Questor ou digite o nome direto — não é preciso estar cadastrado no ERP.
        </p>

        <form method="POST" action="{{ route('cotacao.mapas.fornecedores.store', $mapa) }}" class="space-y-3">
            @csrf

            <div class="relative">
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Buscar no Questor</label>
                <input type="text" x-model="termo" @input.debounce.400ms="buscar()"
                       placeholder="nome, fantasia ou CNPJ"
                       class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">

                <div x-show="resultados.length" x-cloak @click.outside="resultados = []"
                     class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl">
                    <template x-for="f in resultados" :key="f.codigo">
                        <button type="button" @click="escolher(f)"
                                class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-50 dark:border-gray-700 last:border-0">
                            <span class="block text-sm font-bold text-gray-900 dark:text-white" x-text="f.nome"></span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400"
                                  x-text="[f.cnpj, f.cidade && (f.cidade + '/' + f.uf)].filter(Boolean).join(' · ')"></span>
                        </button>
                    </template>
                </div>
                <p x-show="erroBusca" x-cloak class="text-xs text-amber-600 mt-1" x-text="erroBusca"></p>
            </div>

            <input type="hidden" name="questor_cd_entidade" x-model="escolhido.codigo">

            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Nome na coluna *</label>
                <input type="text" name="nome" x-model="escolhido.nome" required maxlength="150"
                       class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                @error('nome')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">CNPJ</label>
                    <input type="text" name="cnpj" x-model="escolhido.cnpj" maxlength="20"
                           class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Telefone</label>
                    <input type="text" name="telefone" x-model="escolhido.telefone" maxlength="20"
                           class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Contato</label>
                    <input type="text" name="contato" maxlength="100"
                           class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">E-mail</label>
                    <input type="email" name="email" x-model="escolhido.email" maxlength="150"
                           class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm">
                </div>
            </div>

            <button type="submit"
                    class="w-full px-4 py-2.5 rounded-xl bg-red-800 hover:bg-red-900 text-white text-sm font-bold shadow transition">
                Acrescentar coluna
            </button>
        </form>
    </div>

    {{-- ============ COLUNAS EXISTENTES ============ --}}
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="font-extrabold text-gray-900 dark:text-white">Condições por fornecedor</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-4">
            Linhas 5, 6 e 7 do mapa impresso. Frete em reais e desconto entram no TOTAL, não no SUBTOTAL.
        </p>

        @if($mapa->fornecedores->isEmpty())
            <p class="text-sm text-gray-400 italic">Nenhuma coluna ainda.</p>
        @else
            <div class="space-y-3">
                @foreach($mapa->fornecedores as $f)
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-700">
                    {{-- Duas ações independentes (salvar e remover) e por isso
                         dois formulários irmãos: aninhar `<form>` é inválido e
                         faz o navegador descartar o de dentro em silêncio. O
                         botão "Remover" mora no formulário de exclusão e é
                         posicionado por CSS. --}}
                    <form method="POST" action="{{ route('cotacao.mapas.fornecedores.update', [$mapa, $f]) }}"
                          class="grid gap-2 md:grid-cols-12 items-end">
                        @csrf @method('PATCH')

                        <div class="md:col-span-3">
                            <label class="block text-[10px] font-bold uppercase text-gray-400">Fornecedor</label>
                            <input type="text" name="nome" value="{{ $f->nome }}" maxlength="150" required
                                   class="w-full text-sm rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white px-2 py-1">
                            @if($f->questor_cd_entidade)
                                <span class="text-[10px] text-gray-400">Questor #{{ $f->questor_cd_entidade }}</span>
                            @else
                                <span class="text-[10px] text-amber-600 dark:text-amber-400">fora do cadastro do ERP</span>
                            @endif
                        </div>

                        <div class="md:col-span-1">
                            <label class="block text-[10px] font-bold uppercase text-gray-400">Frete</label>
                            <input type="text" name="frete" value="{{ $f->frete }}" maxlength="20" list="lista-fretes"
                                   placeholder="CIF"
                                   class="w-full text-sm rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white px-2 py-1">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold uppercase text-gray-400">Prazo</label>
                            <input type="text" name="prazo_entrega" value="{{ $f->prazo_entrega }}" maxlength="30" list="lista-prazos"
                                   placeholder="3DU / CONFIRMAR"
                                   class="w-full text-sm rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white px-2 py-1">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold uppercase text-gray-400">Pagamento</label>
                            <input type="text" name="condicao_pagamento" value="{{ $f->condicao_pagamento }}" maxlength="30" list="lista-pagamentos"
                                   placeholder="Á VISTA / 28 D"
                                   class="w-full text-sm rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white px-2 py-1">
                        </div>

                        <div class="md:col-span-1">
                            <label class="block text-[10px] font-bold uppercase text-gray-400">Frete R$</label>
                            <input type="text" name="valor_frete" value="{{ $brl2($f->valor_frete) }}" inputmode="decimal"
                                   class="w-full text-sm text-right tabular-nums rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white px-2 py-1">
                        </div>

                        <div class="md:col-span-1">
                            <label class="block text-[10px] font-bold uppercase text-gray-400">Desc. R$</label>
                            <input type="text" name="desconto" value="{{ $brl2($f->desconto) }}" inputmode="decimal"
                                   class="w-full text-sm text-right tabular-nums rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white px-2 py-1">
                        </div>

                        <div class="md:col-span-1">
                            <button type="submit"
                                    class="w-full px-3 py-1.5 rounded-lg bg-gray-800 dark:bg-gray-700 hover:bg-gray-900 text-white text-xs font-bold transition">
                                Salvar
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('cotacao.mapas.fornecedores.destroy', [$mapa, $f]) }}"
                          class="mt-2 flex justify-end"
                          onsubmit="return confirm('Remover a coluna {{ addslashes($f->nome) }}? Os preços já digitados nela vão junto.')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="px-3 py-1 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-xs font-bold hover:bg-red-100 transition">
                            Remover coluna
                        </button>
                    </form>
                    </div>
                @endforeach
            </div>

            {{-- Autocomplete das condições (queries 9.a–9.c). São sugestões:
                 `datalist` não impede texto livre, que é exatamente o que o
                 mapa real precisa. --}}
            <datalist id="lista-fretes"><template x-for="v in condicoes.fretes" :key="v"><option :value="v"></option></template></datalist>
            <datalist id="lista-prazos"><template x-for="v in condicoes.prazos" :key="v"><option :value="v"></option></template></datalist>
            <datalist id="lista-pagamentos"><template x-for="v in condicoes.pagamentos" :key="v"><option :value="v"></option></template></datalist>
        @endif

        {{-- ============ ITEM AVULSO ============ --}}
        <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-700">
            <h3 class="font-extrabold text-gray-900 dark:text-white">Acrescentar item avulso</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-3">
                O que não veio na solicitação mas entra na mesma cotação.
            </p>

            <form method="POST" action="{{ route('cotacao.mapas.itens.store', $mapa) }}"
                  class="grid gap-2 md:grid-cols-12 items-end">
                @csrf
                <div class="md:col-span-6">
                    <label class="block text-[10px] font-bold uppercase text-gray-400">Descrição *</label>
                    <input type="text" name="descricao" required maxlength="255"
                           class="w-full text-sm rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white px-2 py-1">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold uppercase text-gray-400">Unidade</label>
                    <input type="text" name="unidade" maxlength="10" placeholder="UN"
                           class="w-full text-sm rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white px-2 py-1">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold uppercase text-gray-400">Qnt. *</label>
                    <input type="text" name="quantidade" required inputmode="decimal" value="1"
                           class="w-full text-sm text-right tabular-nums rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white px-2 py-1">
                </div>
                <div class="md:col-span-2">
                    <button type="submit"
                            class="w-full px-3 py-1.5 rounded-lg bg-gray-800 dark:bg-gray-700 hover:bg-gray-900 text-white text-xs font-bold transition">
                        Acrescentar
                    </button>
                </div>
            </form>

            @error('descricao')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('quantidade')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

<script>
function gestaoColunas(urlBusca, urlCondicoes) {
    return {
        termo: '',
        resultados: [],
        erroBusca: '',
        escolhido: { codigo: '', nome: '', cnpj: '', telefone: '', email: '' },
        condicoes: { fretes: [], prazos: [], pagamentos: [] },

        async buscar() {
            if (this.termo.trim().length < 2) { this.resultados = []; return; }

            try {
                const res = await fetch(`${urlBusca}?q=${encodeURIComponent(this.termo)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const dados = await res.json();
                this.resultados = dados.itens ?? [];
                // Sem o ERP a busca some, e o formulário continua servindo: o
                // nome digitado à mão é suficiente para abrir a coluna.
                this.erroBusca = dados.ok ? '' : 'Questor indisponível — digite o nome do fornecedor à mão.';
            } catch (e) {
                this.resultados = [];
                this.erroBusca = 'Não deu para buscar no Questor — digite o nome à mão.';
            }
        },

        escolher(f) {
            this.escolhido = {
                codigo: f.codigo,
                nome: f.nome,
                cnpj: f.cnpj || '',
                telefone: f.telefone || '',
                email: f.email || '',
            };
            this.resultados = [];
            this.termo = f.nome;
        },

        async carregarCondicoes() {
            try {
                const res = await fetch(urlCondicoes, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const dados = await res.json();
                this.condicoes = {
                    fretes: dados.fretes ?? [],
                    prazos: dados.prazos ?? [],
                    pagamentos: dados.pagamentos ?? [],
                };
            } catch (e) {
                // Sugestão é conveniência: sem ela os campos seguem como texto livre.
            }
        },
    };
}
</script>
