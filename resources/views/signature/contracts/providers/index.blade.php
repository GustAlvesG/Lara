{{--
    Contratos → Prestadores (05/11/2026): o cadastro de contratadas e fiadores. Dá para
    cadastrar sem contrato; salvar um contrato também atualiza o cadastro. Não se exclui,
    só se inativa: o inativo some da busca do editor (ContractProviderController).
--}}
@php
    $th = 'px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Prestadores">
            Empresas e pessoas que prestam serviço ao clube. Cadastre uma vez e, no contrato, é só buscar.
            Mudar o cadastro não altera contrato já redigido.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('contract-providers.create') }}"><x-icon name="plus" /> Novo prestador</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar :filters="['situacao']" placeholder="Nome, representante, CNPJ ou CPF">
            <x-slot:controls>
                <label for="situacao" class="sr-only">Situação</label>
                <select name="situacao" id="situacao" class="{{ $field }} w-full sm:w-48">
                    <option value="ativos" @selected($situacao === 'ativos')>Ativos</option>
                    <option value="inativos" @selected($situacao === 'inativos')>Inativos</option>
                    <option value="todos" @selected($situacao === 'todos')>Todos</option>
                </select>
            </x-slot:controls>
        </x-search-bar>

        @if($providers->isEmpty())
            <x-empty-state icon="doc">
                @if(filled($busca) || $situacao !== 'ativos')
                    Nenhum prestador com esses filtros.
                    <a href="{{ route('contract-providers.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                @else
                    Nenhum prestador cadastrado ainda.
                    <a href="{{ route('contract-providers.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar o primeiro</a>.
                @endif
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Prestador</th>
                                <th class="{{ $th }}">Documento</th>
                                <th class="{{ $th }}">Contato</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $th }}"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($providers as $p)
                                <tr @class(['transition hover:bg-subtle', 'opacity-60' => !$p->active])>
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('contract-providers.edit', $p) }}" class="font-semibold text-ink hover:text-grena-ink">{{ $p->name }}</a>
                                        <div class="text-xs text-ink-2">
                                            {{ $p->kind === 'pf' ? 'Pessoa física' : 'Empresa' }}
                                            @if($p->kind === 'pj' && $p->field('rep_name') !== '') · representante {{ $p->field('rep_name') }} @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-xs text-ink whitespace-nowrap">{{ $p->formattedDocument() }}</td>
                                    <td class="px-5 py-3.5 text-xs text-ink-2">
                                        {{ $p->field('email') ?: '—' }}
                                        @if($p->field('phone') !== '')<br>{{ $p->field('phone') }}@endif
                                    </td>
                                    <td class="px-5 py-3.5"><x-pill :kind="$p->active ? 'ok' : 'off'">{{ $p->active ? 'Ativo' : 'Inativo' }}</x-pill></td>
                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        <a href="{{ route('contract-providers.edit', $p) }}" class="text-xs font-bold text-grena-ink hover:underline">Editar</a>
                                        <form method="POST" action="{{ route('contract-providers.toggle', $p) }}" class="ml-3 inline"
                                              onsubmit="return confirm('{{ $p->active ? 'Inativar? O prestador some da busca do contrato.' : 'Reativar o prestador?' }}')">
                                            @csrf
                                            <button type="submit" class="text-xs font-bold {{ $p->active ? 'text-danger' : 'text-ok' }} hover:underline">{{ $p->active ? 'Inativar' : 'Reativar' }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $providers->links() }}
        @endif
    </x-page>
</x-app-layout>
