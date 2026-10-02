<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Jogadores">
            Cada jogador é de uma equipe e uma modalidade; pode estar em vários times dessa equipe.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('placar.jogadores.create') }}"><x-icon name="plus" /> Novo jogador</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar name="busca" placeholder="Buscar jogador" :filters="['equipe_id', 'modalidade', 'criado_em_campo', 'pendentes']">
            <x-slot:controls>
                <select name="equipe_id" class="h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint w-full sm:w-auto">
                    <option value="">Todas as equipes</option>
                    @foreach($equipes as $equipeFiltro)
                        <option value="{{ $equipeFiltro->id }}" @selected((string) request('equipe_id') === (string) $equipeFiltro->id)>{{ $equipeFiltro->nome }}</option>
                    @endforeach
                </select>
                <select name="modalidade" class="h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint w-full sm:w-auto">
                    <option value="">Todas as modalidades</option>
                    @foreach($modalidades as $modalidadeFiltro)
                        <option value="{{ $modalidadeFiltro->slug }}" @selected(request('modalidade') === $modalidadeFiltro->slug)>{{ $modalidadeFiltro->nome }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-sm text-ink-2">
                    <input type="checkbox" name="criado_em_campo" value="1" @checked(request('criado_em_campo'))
                        class="rounded border-line-strong text-warn focus:ring-warn-soft">
                    Só criados em campo
                </label>
                <label class="flex items-center gap-2 text-sm text-ink-2">
                    <input type="checkbox" name="pendentes" value="1" @checked(request('pendentes'))
                        class="rounded border-line-strong text-danger focus:ring-grena-tint">
                    Só pendentes de revisão
                </label>
            </x-slot:controls>
        </x-search-bar>

        @if($jogadores->isEmpty())
            <x-empty-state icon="user">
                @if(request()->hasAny(['busca', 'equipe_id', 'modalidade', 'criado_em_campo', 'pendentes']))
                    Nenhum jogador com esses filtros.
                    <a href="{{ route('placar.jogadores.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                @else
                    Nenhum jogador cadastrado.
                @endif
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($jogadores as $jogador)
                    @php
                        $nome = $jogador->nomeExibicaoResolvido();
                        $initials = mb_strtoupper(collect(preg_split('/\s+/', trim($nome), -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
                    @endphp
                    <x-card :href="route('placar.jogadores.show', $jogador)">
                        <x-slot:media>
                            <x-media :src="$jogador->fotoUrl()" :alt="'Foto de ' . $nome" area="placar" :initials="$initials" ratio="sq" />
                        </x-slot:media>

                        <a href="{{ route('placar.jogadores.show', $jogador) }}" class="font-display text-base font-semibold tracking-tight text-ink hover:text-grena-ink">{{ $nome }}</a>
                        <p class="text-sm text-ink-2">
                            {{ $jogador->equipe?->nome ?? 'Sem equipe' }} · {{ $jogador->modalidade?->nome ?? 'sem modalidade' }}
                        </p>
                        <p class="text-xs text-ink-3">{{ $jogador->idade() !== null ? $jogador->idade() . ' anos' : 'Idade não informada' }}</p>
                        @if($jogador->criado_em_campo || $jogador->precisaDeRevisao())
                            <div class="flex flex-wrap gap-1.5">
                                @if($jogador->criado_em_campo)
                                    <x-pill kind="warn">Criado em campo</x-pill>
                                @endif
                                @if($jogador->precisaDeRevisao())
                                    <x-pill kind="danger">Sem equipe/modalidade</x-pill>
                                @endif
                            </div>
                        @endif

                        <x-slot:footer>
                            <x-pill :kind="$jogador->ativo ? 'ok' : 'off'">{{ $jogador->ativo ? 'Ativo' : 'Inativo' }}</x-pill>
                            <x-secondary-button-a size="sm" href="{{ route('placar.jogadores.show', $jogador) }}"><x-icon name="pencil" /> Editar</x-secondary-button-a>
                        </x-slot:footer>
                    </x-card>
                @endforeach
            </div>

            {{ $jogadores->links() }}
        @endif
    </x-page>
</x-app-layout>
