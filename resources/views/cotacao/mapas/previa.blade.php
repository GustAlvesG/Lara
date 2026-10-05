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

    $panel = 'rounded-card bg-surface p-5 shadow-card';
    $eyebrow = 'mb-3 text-xs font-bold uppercase tracking-[0.08em] text-ink-3';
    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-3';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Buscar solicitação de compra" :back="route('cotacao.mapas.index')">
            O Questor é lido, nunca escrito. A cotação inteira fica na Lara.
        </x-page-title>

        @include('partials.alerts')

        @if ($erro)
            <div class="rounded-2xl bg-danger-soft p-4" role="alert">
                <p class="font-bold text-danger">Não deu para consultar</p>
                <p class="mt-1 text-sm text-ink-2">{{ $erro }}</p>
            </div>
        @endif

        {{-- ============ BUSCA ============ --}}
        <div class="grid gap-4 lg:grid-cols-3">
            <form method="GET" class="{{ $panel }} flex flex-col">
                <p class="{{ $eyebrow }}">Sei o número</p>
                <x-input-label for="sc-numero" value="Nº da solicitação (SC)" class="mb-1.5" />
                <x-text-input type="number" id="sc-numero" name="solicitacao" value="{{ $codigo }}" min="1" placeholder="34334" required class="font-mono" />
                <x-primary-button class="mt-4 w-full"><x-icon name="search" /> Buscar solicitação</x-primary-button>
            </form>

            <form method="GET" class="{{ $panel }} lg:col-span-2">
                <p class="{{ $eyebrow }}">Não sei o número</p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <x-input-label for="sc-de" value="De" class="mb-1.5" />
                        <x-text-input type="date" id="sc-de" name="de" value="{{ $filtros['de'] }}" class="font-mono text-sm" />
                    </div>
                    <div>
                        <x-input-label for="sc-ate" value="Até" class="mb-1.5" />
                        <x-text-input type="date" id="sc-ate" name="ate" value="{{ $filtros['ate'] }}" class="font-mono text-sm" />
                    </div>
                    <div>
                        <x-input-label for="sc-texto" value="Texto" class="mb-1.5" />
                        <x-text-input type="text" id="sc-texto" name="busca" value="{{ $filtros['busca'] }}" placeholder="solicitante, obs. ou item" />
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <x-secondary-button type="submit"><x-icon name="search" /> Procurar</x-secondary-button>
                    <p class="text-xs text-ink-3">Procura também na descrição dos itens da solicitação.</p>
                </div>
            </form>
        </div>

        {{-- ============ RESULTADOS DA BUSCA POR PERÍODO ============ --}}
        @if ($resultados->isNotEmpty())
            <section class="overflow-hidden rounded-card bg-surface shadow-card">
                <p class="border-b border-line px-5 py-4 font-bold text-ink">
                    {{ $resultados->count() }} {{ $resultados->count() === 1 ? 'solicitação encontrada' : 'solicitações encontradas' }}
                </p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">SC</th>
                                <th class="{{ $th }}">Data</th>
                                <th class="{{ $th }}">Solicitante</th>
                                <th class="{{ $th }}">Observação</th>
                                <th class="{{ $th }} text-center">Itens</th>
                                <th class="{{ $th }}">Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resultados as $r)
                                <tr class="border-b border-line transition last:border-0 hover:bg-subtle">
                                    <td class="{{ $td }}">
                                        <a href="{{ route('cotacao.mapas.previa', ['solicitacao' => $r->codigo]) }}"
                                           class="font-mono font-semibold text-grena-ink no-underline hover:underline">{{ $r->codigo }}</a>
                                    </td>
                                    <td class="{{ $td }} whitespace-nowrap font-mono text-ink-2">{{ $data($r->dataCadastro) }}</td>
                                    <td class="{{ $td }} text-ink">{{ $r->solicitante ?: '—' }}</td>
                                    <td class="{{ $td }} max-w-lg truncate text-ink-2">{{ $r->observacoes ?: '—' }}</td>
                                    <td class="{{ $td }} text-center font-mono text-ink">{{ $r->qtdItens }}</td>
                                    <td class="{{ $td }} text-ink-2">{{ $r->status ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- ============ PRÉVIA DA SOLICITAÇÃO ============ --}}
        @if ($solicitacao)
            @if ($mapaExistente)
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-warn-soft p-5" role="status">
                    <div>
                        <p class="font-bold text-warn">Já existe mapa para a SC {{ $solicitacao->codigo }}</p>
                        <p class="mt-1 text-sm text-ink-2">
                            Mapa #{{ $mapaExistente->id }} — {{ $mapaExistente->titulo }}
                            ({{ mb_strtolower($mapaExistente->statusLabel()) }}).
                            Gerar outro duplicaria o trabalho de quem já está cotando.
                        </p>
                    </div>
                    <x-primary-button-a href="{{ route('cotacao.mapas.show', $mapaExistente) }}">Abrir o mapa existente</x-primary-button-a>
                </div>
            @endif

            <section class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="border-b border-line px-5 py-5">
                    <p class="text-xs font-bold uppercase tracking-[0.08em] text-ink-3">Solicitação de compra</p>
                    <h2 class="mt-1 font-display text-2xl font-semibold tracking-tight text-ink">SC <span class="font-mono">{{ $solicitacao->codigo }}</span></h2>
                    <dl class="mt-4 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="inline text-ink-3">Solicitante:</dt> <dd class="inline font-semibold text-ink">{{ $solicitacao->solicitante ?: '—' }}</dd></div>
                        <div><dt class="inline text-ink-3">Departamento:</dt> <dd class="inline font-semibold text-ink">{{ $solicitacao->departamento ?: '—' }}</dd></div>
                        <div><dt class="inline text-ink-3">Filial:</dt> <dd class="inline font-semibold text-ink">{{ $solicitacao->filialNome ?: $solicitacao->filial }}</dd></div>
                        <div><dt class="inline text-ink-3">Cadastro:</dt> <dd class="inline font-mono font-semibold text-ink">{{ $data($solicitacao->dataCadastro) }}</dd></div>
                        <div class="sm:col-span-2 lg:col-span-4"><dt class="inline text-ink-3">Observação:</dt> <dd class="inline font-semibold text-ink">{{ $solicitacao->observacoes ?: '—' }}</dd></div>
                    </dl>
                </div>

                {{-- Itens --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Item</th>
                                <th class="{{ $th }}">Und</th>
                                <th class="{{ $th }}">Descrição</th>
                                <th class="{{ $th }} text-right">Qnt.</th>
                                <th class="{{ $th }}">Última compra</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($itens as $item)
                                @php $ultima = $ultimasCompras->get($item->item); @endphp
                                <tr class="border-b border-line last:border-0">
                                    <td class="{{ $td }} font-mono text-ink">{{ $item->item }}</td>
                                    <td class="{{ $td }} text-ink-2">{{ $item->unidade ?: '—' }}</td>
                                    <td class="{{ $td }} text-ink">
                                        {{ $item->descricao }}
                                        @if ($item->semCadastro())
                                            {{-- A armadilha nº 1, dita em voz alta antes de o mapa nascer. --}}
                                            <span title="Item digitado como texto livre na solicitação, sem cadastro em TBL_MATERIAIS. Não há histórico de compra por código.">
                                                <x-pill kind="warn" class="ml-2">sem cadastro</x-pill>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} text-right font-mono text-ink">{{ $qtd($item->quantidade) }}</td>
                                    <td class="{{ $td }} text-ink-2">
                                        @if ($ultima?->encontrada())
                                            <span class="font-mono font-semibold text-ink">{{ $brl($ultima->valorUnitario) }}</span>
                                            <span class="text-xs">· {{ $data($ultima->data) }} · {{ $ultima->fornecedorCurto() }}</span>
                                        @elseif ($item->semCadastro())
                                            <span class="text-xs italic text-ink-3">item sem cadastro — sem histórico</span>
                                        @else
                                            <span class="text-xs italic text-ink-3">sem compra anterior</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- ============ GERAR O MAPA ============ --}}
            <form method="POST" action="{{ route('cotacao.mapas.store') }}" class="{{ $panel }} sm:p-6">
                @csrf
                <input type="hidden" name="solicitacao" value="{{ $solicitacao->codigo }}">

                <h3 class="mb-4 font-display text-lg font-semibold tracking-tight text-ink">Gerar o mapa</h3>

                <div class="mb-6 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="mapa-titulo" class="mb-1.5">
                            Título <span class="font-normal text-ink-3">— vai no cabeçalho do XLSX</span>
                        </x-input-label>
                        <x-text-input type="text" id="mapa-titulo" name="titulo" maxlength="255" value="{{ old('titulo', $solicitacao->tituloSugerido()) }}" />
                        <x-input-error :messages="$errors->get('titulo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="mapa-comprador" value="Comprador" class="mb-1.5" />
                        <x-text-input type="text" id="mapa-comprador" name="comprador" maxlength="100" value="{{ old('comprador', auth()->user()->name) }}" />
                    </div>
                </div>

                {{-- Sugestão de colunas --}}
                <div class="border-t border-line pt-5">
                    <p class="text-sm font-bold text-ink">A quem pedir cotação?</p>
                    <p class="mb-4 mt-1 text-xs text-ink-2">
                        Fornecedores que já venderam estes itens à empresa. Marque quem vira coluna do mapa —
                        nenhum é marcado automaticamente, senão o mapa nasce com vinte colunas.
                        Você pode acrescentar outros depois, inclusive quem não está no cadastro do Questor.
                    </p>

                    @if ($sugestoes->isEmpty())
                        <x-empty-state icon="bag">
                            Nenhum histórico de fornecimento para os itens desta solicitação.
                            O mapa nasce sem colunas e você as acrescenta na tela dele.
                        </x-empty-state>
                    @else
                        {{-- Busca na página: a lista de sugestões chega inteira. --}}
                        <x-search-bar mode="client" target="#sugestoes" id="busca-sugestoes" placeholder="Filtrar fornecedores" class="mb-3" />

                        <div id="sugestoes" class="grid max-h-80 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($sugestoes as $s)
                                <label data-search="{{ $s['nome'] }}"
                                       class="flex cursor-pointer items-start gap-3 rounded-2xl border-[1.5px] border-line p-3 transition hover:border-line-strong has-[:checked]:border-grena has-[:checked]:bg-grena-tint">
                                    <input type="checkbox" name="fornecedores[]" value="{{ $s['codigo'] }}"
                                           class="mt-0.5 rounded border-line-strong text-grena focus:ring-grena-tint">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-bold text-ink">{{ $s['nome'] }}</span>
                                        <span class="block text-xs text-ink-2">
                                            atende {{ $s['itens_atendidos'] }} de {{ $itens->count() }} {{ $itens->count() === 1 ? 'item' : 'itens' }}
                                            · última compra {{ $data($s['ultima_compra']) }}
                                        </span>
                                        @unless ($s['ativo'])
                                            <x-pill kind="off" :icon="false" class="mt-1">inativo no Questor</x-pill>
                                        @endunless
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('fornecedores')" class="mt-2" />
                    @endif
                </div>

                <div class="mt-6 flex justify-end">
                    <x-primary-button>
                        <x-icon name="check" /> Gerar mapa com {{ $itens->count() }} {{ $itens->count() === 1 ? 'item' : 'itens' }}
                    </x-primary-button>
                </div>
            </form>
        @endif
    </x-page>
</x-app-layout>
