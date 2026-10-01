{{--
    Vitrine dos componentes do rebrand. Dados fictícios, só para conferir o
    desenho nos dois temas e o comportamento da busca e do Cartões/Lista.
--}}
@php
    $veiculos = [
        ['nome' => 'Fiat Strada 2023', 'placa' => 'FUN1A23', 'setor' => 'Manutenção', 'status' => ['ok', 'No clube']],
        ['nome' => 'VW Saveiro 2021', 'placa' => 'FUN2B45', 'setor' => 'Jardinagem', 'status' => ['warn', 'Em rota']],
        ['nome' => 'Renault Master 2022', 'placa' => 'FUN3C67', 'setor' => 'Eventos', 'status' => ['ok', 'No clube']],
        ['nome' => 'Toyota Hilux 2020', 'placa' => 'FUN4D89', 'setor' => 'Segurança', 'status' => ['danger', 'Na oficina']],
    ];
@endphp

<x-app-layout :bootstrap-grid="false">
    <div class="bg-canvas text-ink">
        <x-area-cover area="info" title="Catálogo de componentes" icon="grid" eyebrow="Rebrand"
            :tabs="[
                ['label' => 'Componentes', 'href' => route('design.components'), 'active' => true],
                ['label' => 'Com contador', 'href' => '#cartoes', 'badge' => 3],
            ]">
            <x-secondary-button size="sm" x-data x-on:click="window.laraTheme.set(document.documentElement.classList.contains('dark') ? 'light' : 'dark')">
                <x-icon name="moon" /> Alternar tema
            </x-secondary-button>
            <x-secondary-button size="sm" x-data x-on:click="window.laraTheme.set('system')">Seguir o sistema</x-secondary-button>
        </x-area-cover>

        <x-page>
            <section class="flex flex-col gap-3">
                <h2 class="font-display text-lg font-semibold">Botões</h2>
                <div class="flex flex-wrap items-center gap-2.5">
                    <x-primary-button type="button"><x-icon name="plus" /> Ação principal</x-primary-button>
                    <x-secondary-button>Secundário</x-secondary-button>
                    <x-green-button type="button"><x-icon name="check" /> Liberar</x-green-button>
                    <x-danger-button type="button"><x-icon name="trash" /> Excluir</x-danger-button>
                    <x-primary-button-a href="#" size="sm">Link pequeno</x-primary-button-a>
                </div>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="font-display text-lg font-semibold">Status e placa</h2>
                <div class="flex flex-wrap items-center gap-2.5">
                    <x-pill kind="ok">Liberado</x-pill>
                    <x-pill kind="warn">Aguardando</x-pill>
                    <x-pill kind="danger">Negado</x-pill>
                    <x-pill kind="info">Agendado</x-pill>
                    <x-pill kind="off">Inativo</x-pill>
                    <x-plate plate="rkt-4f21" size="sm" />
                    <x-plate plate="RKT4F21" />
                    <x-plate plate="RKT4F21" size="lg" />
                </div>
            </section>

            <section class="flex max-w-2xl flex-col gap-3">
                <h2 class="font-display text-lg font-semibold">Campos</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <x-input-label for="demo-nome" value="Nome" />
                        <x-text-input id="demo-nome" placeholder="Nome completo" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <x-input-label for="demo-setor" value="Setor" />
                        <x-select-input id="demo-setor"><option>Informática</option><option>Portaria</option></x-select-input>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <x-input-label for="demo-data" value="Data" />
                        <x-datetime-input id="demo-data" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <x-input-label for="demo-arquivo" value="Arquivo" />
                        <x-input-file id="demo-arquivo" />
                    </div>
                </div>
                <x-input-error :messages="['Exemplo de mensagem de erro: o CPF tem 11 dígitos.']" />
            </section>

            <section class="flex flex-col gap-3" id="cartoes">
                <h2 class="font-display text-lg font-semibold">Busca na página + Cartões/Lista</h2>
                <x-view-switch key="design-catalogo" id="demo-veiculos">
                    <x-slot:toolbar>
                        <x-search-bar mode="client" target="#demo-veiculos" placeholder="Buscar veículo, placa ou setor" />
                    </x-slot:toolbar>

                    <x-slot:cards>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($veiculos as $v)
                                <x-card :data-search="$v['nome'] . ' ' . $v['placa'] . ' ' . $v['setor']">
                                    <x-slot:media>
                                        <x-media area="portaria" icon="car">
                                            <x-media-tag side="right">{{ $v['setor'] }}</x-media-tag>
                                        </x-media>
                                    </x-slot:media>
                                    <b class="text-[15.5px]">{{ $v['nome'] }}</b>
                                    <span class="text-[13.5px] text-ink-2"><x-plate :plate="$v['placa']" size="sm" /></span>
                                    <x-slot:footer>
                                        <x-pill :kind="$v['status'][0]">{{ $v['status'][1] }}</x-pill>
                                    </x-slot:footer>
                                </x-card>
                            @endforeach
                            <x-card data-search="Marina Costa Titular">
                                <x-slot:media><x-media area="freela" initials="MC" ratio="sq" /></x-slot:media>
                                <b>Marina Costa</b>
                                <span class="text-[13.5px] text-ink-2">Foto que falhou ao carregar mostra as iniciais</span>
                            </x-card>
                        </div>
                    </x-slot:cards>

                    <x-slot:list>
                        <div class="overflow-x-auto rounded-2xl bg-surface shadow-card">
                            <table class="w-full text-sm">
                                <thead><tr class="border-b border-line text-left text-xs text-ink-3"><th class="px-4 py-3">Veículo</th><th class="px-4 py-3">Placa</th><th class="px-4 py-3">Setor</th><th class="px-4 py-3">Situação</th></tr></thead>
                                <tbody>
                                    @foreach ($veiculos as $v)
                                        <tr class="border-b border-line last:border-0" data-search="{{ $v['nome'] }} {{ $v['placa'] }} {{ $v['setor'] }}">
                                            <td class="px-4 py-3 font-semibold">{{ $v['nome'] }}</td>
                                            <td class="px-4 py-3"><x-plate :plate="$v['placa']" size="sm" /></td>
                                            <td class="px-4 py-3 text-ink-2">{{ $v['setor'] }}</td>
                                            <td class="px-4 py-3"><x-pill :kind="$v['status'][0]">{{ $v['status'][1] }}</x-pill></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-slot:list>
                </x-view-switch>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="font-display text-lg font-semibold">Busca pelo servidor e estado vazio</h2>
                <x-search-bar placeholder="Buscar (vai para ?q= nesta URL)" />
                <x-empty-state>Nenhum aviso encontrado para “piscina”. Tente outra palavra ou limpe a busca.</x-empty-state>
            </section>
        </x-page>
    </div>
</x-app-layout>
