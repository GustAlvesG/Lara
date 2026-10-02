{{--
    Lista dos mapas de cotação.

    O Questor NÃO é consultado nesta tela: tudo aqui é da Lara. É de propósito —
    a lista precisa abrir mesmo com o ERP fora do ar, e só a criação de um mapa
    novo depende dele.

    Tabela, e não cartões: aqui se compara linha com linha (SC, itens,
    colunas, situação), que é o caso em que a tabela ganha.
--}}
@php
    $situacoes = ['rascunho' => 'Rascunho', 'em_cotacao' => 'Em cotação', 'fechado' => 'Fechado', 'cancelado' => 'Cancelado'];
    $pill = ['rascunho' => 'off', 'em_cotacao' => 'info', 'fechado' => 'ok', 'cancelado' => 'danger'];
    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-3';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Mapas de cotação">
            Comparação de fornecedores a partir das solicitações de compra do Questor. Nada é gravado no ERP.

            @can('create', App\Models\CotacaoMapa::class)
                <x-slot:actions>
                    <x-primary-button-a href="{{ route('cotacao.mapas.previa') }}">
                        <x-icon name="plus" /> Nova cotação
                    </x-primary-button-a>
                </x-slot:actions>
            @endcan
        </x-page-title>

        @include('partials.alerts')

        @unless ($config['enabled'])
            <div class="rounded-2xl bg-warn-soft p-4" role="status">
                <p class="font-bold text-warn">Integração com o Questor desligada</p>
                <p class="mt-1 text-sm text-ink-2">
                    Os mapas já criados continuam abrindo e editáveis. Gerar um mapa novo ou consultar histórico
                    precisa de <code class="font-mono">QUESTOR_ENABLED</code> ligado no <code class="font-mono">.env</code>.
                </p>
            </div>
        @endunless

        <x-search-bar name="busca" :filters="['status']" placeholder="Nº da SC, título, solicitante ou comprador">
            <x-slot:controls>
                <label for="filtro-status" class="sr-only">Situação</label>
                <div class="w-full sm:w-52">
                <x-select-input id="filtro-status" name="status">
                    <option value="">Todas as situações</option>
                    @foreach ($situacoes as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($filtros['status'] === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </x-select-input>
                </div>
            </x-slot:controls>
        </x-search-bar>

        @if ($mapas->isEmpty())
            <x-empty-state icon="bag">
                @if (filled($filtros['busca']) || filled($filtros['status']))
                    Nenhum mapa com esses filtros.
                    <a href="{{ route('cotacao.mapas.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                @else
                    Nenhum mapa por aqui ainda.
                    @can('create', App\Models\CotacaoMapa::class)
                        <a href="{{ route('cotacao.mapas.previa') }}" class="font-bold text-grena-ink hover:underline">Buscar uma solicitação de compra</a>
                        para começar.
                    @endcan
                @endif
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Mapa</th>
                                <th class="{{ $th }}">SC</th>
                                <th class="{{ $th }}">Título</th>
                                <th class="{{ $th }}">Solicitante</th>
                                <th class="{{ $th }}">Comprador</th>
                                <th class="{{ $th }} text-center">Itens</th>
                                <th class="{{ $th }} text-center">Colunas</th>
                                <th class="{{ $th }}">Data</th>
                                <th class="{{ $th }}">Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mapas as $mapa)
                                <tr class="border-b border-line transition last:border-0 hover:bg-subtle">
                                    <td class="{{ $td }}">
                                        <a href="{{ route('cotacao.mapas.show', $mapa) }}" class="font-mono font-semibold text-grena-ink no-underline hover:underline">#{{ $mapa->id }}</a>
                                    </td>
                                    <td class="{{ $td }} font-mono text-ink">{{ $mapa->questor_solicitacao }}</td>
                                    <td class="{{ $td }} max-w-md truncate font-semibold text-ink" title="{{ $mapa->titulo }}">
                                        <a href="{{ route('cotacao.mapas.show', $mapa) }}" class="text-ink no-underline hover:underline">{{ $mapa->titulo }}</a>
                                    </td>
                                    <td class="{{ $td }} text-ink-2">{{ $mapa->solicitante ?: '—' }}</td>
                                    <td class="{{ $td }} text-ink-2">{{ $mapa->comprador ?: '—' }}</td>
                                    <td class="{{ $td }} text-center font-mono text-ink">{{ $mapa->itens_count }}</td>
                                    <td class="{{ $td }} text-center font-mono text-ink">{{ $mapa->fornecedores_count }}</td>
                                    <td class="{{ $td }} whitespace-nowrap font-mono text-ink-2">{{ $mapa->data_mapa?->format('d/m/Y') }}</td>
                                    <td class="{{ $td }}"><x-pill :kind="$pill[$mapa->status] ?? 'off'">{{ $mapa->statusLabel() }}</x-pill></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $mapas->links() }}</div>
        @endif
    </x-page>
</x-app-layout>
