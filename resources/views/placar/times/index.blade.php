<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Times">
            Recorte de uma equipe por modalidade e categoria.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('placar.times.create') }}"><x-icon name="plus" /> Novo time</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar name="busca" placeholder="Buscar time, categoria ou equipe" :filters="['modalidade', 'equipe_id', 'criado_em_campo']">
            <x-slot:controls>
                <select name="modalidade" class="h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint w-full sm:w-auto">
                    <option value="">Todas as modalidades</option>
                    @foreach($modalidades as $modalidade)
                        <option value="{{ $modalidade->slug }}" @selected(request('modalidade') === $modalidade->slug)>{{ $modalidade->nome }}</option>
                    @endforeach
                </select>
                <select name="equipe_id" class="h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint w-full sm:w-auto">
                    <option value="">Todas as equipes</option>
                    @foreach($equipes as $equipe)
                        <option value="{{ $equipe->id }}" @selected((string) request('equipe_id') === (string) $equipe->id)>{{ $equipe->nome }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-sm text-ink-2">
                    <input type="checkbox" name="criado_em_campo" value="1" @checked(request('criado_em_campo'))
                        class="rounded border-line-strong text-warn focus:ring-warn-soft">
                    Só criados em campo
                </label>
            </x-slot:controls>
        </x-search-bar>

        <div class="overflow-hidden rounded-card bg-surface shadow-card">
            @if($times->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-ink-2">Nenhum time cadastrado.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="border-b border-line text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">
                            <tr>
                                <th class="px-6 py-3">Nome</th>
                                <th class="px-6 py-3">Equipe</th>
                                <th class="px-6 py-3">Modalidade</th>
                                <th class="px-6 py-3">Categoria</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($times as $time)
                            <tr class="hover:bg-subtle transition">
                                <td class="px-6 py-4 font-semibold text-ink">
                                    {{ $time->nomeExibicaoResolvido() }}
                                    @if($time->criado_em_campo)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-warn-soft text-warn">Criado em campo</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-ink">{{ $time->equipe->nome }}</td>
                                <td class="px-6 py-4 text-ink">{{ $time->modalidade->nome }}</td>
                                <td class="px-6 py-4 text-ink">{{ $time->categoria }}</td>
                                <td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('placar.times.show', $time) }}" class="text-grena-ink hover:underline font-medium text-xs">Ver / Editar</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $times->links() }}</div>
            @endif
        </div>
    </x-page>
</x-app-layout>
