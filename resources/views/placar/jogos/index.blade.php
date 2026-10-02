<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Jogos">
            O ciclo de vida (iniciar, eventos, encerrar) é operado pelo placar eletrônico — aqui é o cadastro prévio.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('placar.jogos.create') }}"><x-icon name="plus" /> Novo jogo</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar name="busca" placeholder="Buscar time, equipe, competição ou local" :filters="['status', 'modalidade', 'competicao_id', 'criado_em_campo']">
            <x-slot:controls>
                <select name="status" class="h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint w-full sm:w-auto">
                    <option value="">Todos os status</option>
                    @foreach(\App\Models\Placar\Jogo::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <select name="modalidade" class="h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint w-full sm:w-auto">
                    <option value="">Todas as modalidades</option>
                    @foreach($modalidades as $modalidade)
                        <option value="{{ $modalidade->slug }}" @selected(request('modalidade') === $modalidade->slug)>{{ $modalidade->nome }}</option>
                    @endforeach
                </select>
                <select name="competicao_id" class="h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint w-full sm:w-auto">
                    <option value="">Todas as competições</option>
                    @foreach($competicoes as $competicao)
                        <option value="{{ $competicao->id }}" @selected((string) request('competicao_id') === (string) $competicao->id)>{{ $competicao->nome }}</option>
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
            @if($jogos->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-ink-2">Nenhum jogo encontrado.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="border-b border-line text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">
                            <tr>
                                <th class="px-6 py-3">Data</th>
                                <th class="px-6 py-3">Confronto</th>
                                <th class="px-6 py-3">Modalidade</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($jogos as $jogo)
                            <tr class="hover:bg-subtle transition">
                                <td class="px-6 py-4 text-ink">{{ $jogo->data_hora->format('d/m/Y H:i') }}</td>
                                <td class="px-6 py-4 font-semibold text-ink">
                                    {{ $jogo->timeCasa->nomeExibicaoResolvido() }} x {{ $jogo->timeFora->nomeExibicaoResolvido() }}
                                    @if($jogo->criado_em_campo)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-warn-soft text-warn">Criado em campo</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-ink">{{ $jogo->modalidade->nome }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                                        @class([
                                            'bg-grena-tint text-grena-ink' => $jogo->status === 'agendado',
                                            'bg-danger-soft text-grena-ink animate-pulse' => $jogo->status === 'ao_vivo',
                                            'bg-subtle text-ink-2' => $jogo->status === 'encerrado',
                                            'bg-subtle text-ink-3 line-through' => $jogo->status === 'cancelado',
                                        ])">{{ $jogo->status }}</span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('placar.jogos.show', $jogo) }}" class="text-grena-ink hover:underline font-medium text-xs">Ver</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $jogos->links() }}</div>
            @endif
        </div>
    </x-page>
</x-app-layout>
