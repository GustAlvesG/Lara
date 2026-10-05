<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Equipes">
            Agremiações — cada uma pode ter times em mais de uma modalidade.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('placar.equipes.create') }}"><x-icon name="plus" /> Nova equipe</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar name="busca" placeholder="Buscar equipe" :filters="['criado_em_campo']">
            <x-slot:controls>
                <label class="flex items-center gap-2 text-sm text-ink-2">
                    <input type="checkbox" name="criado_em_campo" value="1" @checked(request('criado_em_campo'))
                        class="rounded border-line-strong text-warn focus:ring-warn-soft">
                    Só criadas em campo
                </label>
            </x-slot:controls>
        </x-search-bar>

        @if($equipes->isEmpty())
            <x-empty-state icon="trophy">
                @if(request()->hasAny(['busca', 'criado_em_campo']))
                    Nenhuma equipe com esses filtros.
                    <a href="{{ route('placar.equipes.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                @else
                    Nenhuma equipe cadastrada.
                @endif
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($equipes as $equipe)
                    @php
                        $initials = mb_strtoupper(collect(preg_split('/\s+/', trim($equipe->nome), -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
                    @endphp
                    <x-card :href="route('placar.equipes.show', $equipe)">
                        <x-slot:media>
                            <x-media :src="$equipe->logoUrl()" :alt="'Escudo de ' . $equipe->nome" area="placar" :initials="$initials" logo ratio="wide" />
                        </x-slot:media>

                        <a href="{{ route('placar.equipes.show', $equipe) }}" class="font-display text-base font-semibold tracking-tight text-ink hover:text-grena-ink">{{ $equipe->nome }}</a>
                        <p class="text-sm text-ink-2">{{ $equipe->cidade ?? 'Cidade não informada' }}</p>
                        @if($equipe->criado_em_campo)
                            <div><x-pill kind="warn">Criada em campo</x-pill></div>
                        @endif

                        <x-slot:footer>
                            <x-pill :kind="$equipe->ativo ? 'ok' : 'off'">{{ $equipe->ativo ? 'Ativa' : 'Inativa' }}</x-pill>
                            <span class="text-xs text-ink-3">{{ $equipe->times_count }} {{ $equipe->times_count == 1 ? 'time' : 'times' }}</span>
                        </x-slot:footer>
                    </x-card>
                @endforeach
            </div>

            {{ $equipes->links() }}
        @endif
    </x-page>
</x-app-layout>
