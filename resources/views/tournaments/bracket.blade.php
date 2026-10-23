{{-- Chaveamento de uma categoria. Gera por sorteio ou à mão (de novo ou
     desfazer só antes do primeiro resultado), mostra grupos e mata-mata,
     divulga no site e marca data e local dos jogos. --}}
@php
    use App\Models\Tournament\TournamentCategory as Category;
    use App\Models\Tournament\TournamentStage as Stage;
    use App\Models\Tournament\TournamentTeam as Team;
    use App\Services\Tournament\TournamentBracketService as Bracket;

    $hasBracket = $stages->isNotEmpty();
    $isGroups = $category->format === Category::FORMAT_GROUPS_KNOCKOUT;
    $forming = $teams->where('status', '!=', Team::STATUS_COMPLETE);
    $size = Bracket::bracketSize(max(2, $teams->count()));
    $select = 'h-9 rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink';
    $published = $category->bracket_published_at;
    $canManage = auth()->user()->can(\App\Authorization\Permissions::TORNEIOS_GERENCIAR);
    $canResults = auth()->user()->can(\App\Authorization\Permissions::TORNEIOS_RESULTADOS);
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Chaveamento — ' . $category->name" :back="route('tournaments.show', $tournament)">
            {{ $tournament->title }} · {{ Category::FORMATS[$category->format] ?? $category->format }}@if ($isGroups) · {{ $category->groups_count }} grupo(s), {{ $category->qualifiers_per_group }} classificado(s) por grupo @endif
            <x-slot:actions>
                @can(\App\Authorization\Permissions::TORNEIOS_INSCRICOES)
                    <x-secondary-button-a href="{{ route('tournaments.teams.index', [$tournament, $category]) }}"><x-icon name="users" /> Times</x-secondary-button-a>
                @endcan
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-display text-lg font-semibold tracking-tight text-ink">{{ $teams->count() }} time(s)</h2>
                    @if (! $hasBracket)
                        <x-pill kind="off">sem chaveamento</x-pill>
                    @elseif ($published)
                        <x-pill kind="ok">divulgado no site em {{ $published->format('d/m H:i') }}</x-pill>
                    @else
                        <x-pill kind="warn">gerado, não divulgado</x-pill>
                    @endif
                </div>
                @if ($hasBracket && $canManage)
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('tournaments.bracket.publish', [$tournament, $category]) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="publish" value="{{ $published ? 0 : 1 }}">
                            @if ($published)
                                <x-secondary-button type="submit">Recolher do site</x-secondary-button>
                            @else
                                <x-primary-button>Divulgar no site</x-primary-button>
                            @endif
                        </form>
                        @unless ($hasResults)
                            <form method="POST" action="{{ route('tournaments.bracket.destroy', [$tournament, $category]) }}" onsubmit="return confirm('Desfazer o chaveamento? Os jogos, datas e locais marcados somem.')">
                                @csrf @method('DELETE')
                                <x-danger-button>Desfazer</x-danger-button>
                            </form>
                        @endunless
                    </div>
                @endif
            </div>

            @if ($forming->isNotEmpty())
                <p class="rounded-xl bg-warn-soft px-4 py-3 text-sm text-warn">
                    {{ $forming->count() }} time(s) ainda em formação (faltando gente ou pagamento) entram no chaveamento assim mesmo: {{ $forming->pluck('name')->implode(', ') }}.
                </p>
            @endif
            @if ($outdated['missing']->isNotEmpty() || $outdated['gone']->isNotEmpty())
                <p class="rounded-xl bg-warn-soft px-4 py-3 text-sm text-warn">
                    Os times mudaram depois da geração.
                    @if ($outdated['missing']->isNotEmpty()) Fora da chave: {{ $outdated['missing']->pluck('name')->implode(', ') }}. @endif
                    @if ($outdated['gone']->isNotEmpty()) Desfeitos, mas na chave: {{ $outdated['gone']->pluck('name')->implode(', ') }}. @endif
                    @unless ($hasResults) Gere de novo para acertar. @endunless
                </p>
            @endif
            @if ($hasResults)
                <p class="text-sm text-ink-3">Já há resultado lançado: o chaveamento não pode mais ser gerado de novo nem desfeito.</p>
            @endif

            @if (! $canManage)
                {{-- Só lança resultado: sem montagem. --}}
            @elseif (! $hasResults && $teams->count() >= 2)
                <div class="flex flex-col gap-3 border-t border-line pt-4">
                    <form method="POST" action="{{ route('tournaments.bracket.generate', [$tournament, $category]) }}" @if ($hasBracket) onsubmit="return confirm('Gerar de novo? O chaveamento atual (com datas e locais) é substituído e sai do site até você divulgar outra vez.')" @endif class="flex flex-wrap items-center gap-3">
                        @csrf
                        <input type="hidden" name="generation" value="{{ Stage::GENERATION_DRAW }}">
                        <x-secondary-button type="submit">{{ $hasBracket ? 'Sortear de novo' : 'Gerar por sorteio' }}</x-secondary-button>
                        <span class="text-xs text-ink-3">Cabeças de chave (na tela de times) primeiro, na ordem; os demais por sorteio.</span>
                    </form>

                    <details>
                        <summary class="cursor-pointer text-sm font-semibold text-ink-2">Montar à mão</summary>
                        <form method="POST" action="{{ route('tournaments.bracket.generate', [$tournament, $category]) }}" class="mt-3 flex flex-col gap-3" @if ($hasBracket) onsubmit="return confirm('Substituir o chaveamento atual por este?')" @endif>
                            @csrf
                            <input type="hidden" name="generation" value="{{ Stage::GENERATION_MANUAL }}">
                            @if ($isGroups)
                                <p class="text-xs text-ink-3">Escolha o grupo de cada time. Cada grupo precisa de ao menos 2 times e de pelo menos {{ $category->qualifiers_per_group }} (os classificados).</p>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($teams as $team)
                                        <label class="flex items-center justify-between gap-2 rounded-xl border border-line px-3 py-2 text-sm">
                                            <span class="text-ink">{{ $team->name }}</span>
                                            <select name="groups[{{ $team->id }}]" class="{{ $select }}">
                                                @for ($g = 0; $g < (int) $category->groups_count; $g++)
                                                    <option value="{{ $g }}" @selected((int) ($manual['groups'][$team->id] ?? 0) === $g)>{{ Bracket::groupName($g) }}</option>
                                                @endfor
                                            </select>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-ink-3">Chave de {{ $size }}: escolha os dois lados de cada jogo da 1ª rodada. "Passa direto" = o outro lado avança sem jogar.</p>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @for ($m = 0; $m < $size / 2; $m++)
                                        <div class="flex flex-col gap-1 rounded-xl border border-line px-3 py-2 text-sm">
                                            <span class="text-xs font-semibold text-ink-3">Jogo {{ $m + 1 }}</span>
                                            @foreach ([2 * $m, 2 * $m + 1] as $slot)
                                                <select name="slots[{{ $slot }}]" class="{{ $select }}" aria-label="Jogo {{ $m + 1 }}, lado {{ $slot % 2 + 1 }}">
                                                    <option value="">— passa direto</option>
                                                    @foreach ($teams as $team)
                                                        <option value="{{ $team->id }}" @selected((int) ($manual['slots'][$slot] ?? 0) === (int) $team->id)>{{ $team->name }}</option>
                                                    @endforeach
                                                </select>
                                            @endforeach
                                        </div>
                                    @endfor
                                </div>
                            @endif
                            <div><x-secondary-button type="submit">Gerar com esta montagem</x-secondary-button></div>
                        </form>
                    </details>
                </div>
            @elseif ($teams->count() < 2)
                <p class="text-sm text-ink-3">São precisos ao menos 2 times para gerar o chaveamento.</p>
            @endif
        </section>

        @foreach ($stages as $stage)
            @if ($stage->type === Stage::TYPE_GROUP)
                <section class="flex flex-col gap-4">
                    <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Fase de grupos</h2>
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        @foreach ($stage->groups as $group)
                            @php
                                $closed = $group->entries->whereNotNull('final_position')->isNotEmpty();
                                $allPlayed = $group->matches->every(fn ($m) => in_array($m->status, ['finished', 'walkover'], true));
                            @endphp
                            <article class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-card">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-semibold text-ink">{{ $group->name }}</h3>
                                    @if ($closed)<x-pill kind="ok">fechado</x-pill>@elseif ($allPlayed)<x-pill kind="warn">jogos encerrados, falta fechar</x-pill>@endif
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="text-left text-xs text-ink-3">
                                            <tr><th class="py-1 pr-2">#</th><th class="py-1 pr-2">Time</th><th class="py-1 pr-2 text-right">J</th><th class="py-1 pr-2 text-right">V</th><th class="py-1 pr-2 text-right">Sets</th><th class="py-1 text-right">Games</th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-line">
                                            @foreach ($standings[$group->id] as $i => $row)
                                                <tr>
                                                    <td class="py-1.5 pr-2 text-ink-3">{{ $row['entry']->final_position ?? $i + 1 }}</td>
                                                    <td class="py-1.5 pr-2 text-ink">{{ $row['team']->name }}</td>
                                                    <td class="py-1.5 pr-2 text-right font-mono">{{ $row['played'] }}</td>
                                                    <td class="py-1.5 pr-2 text-right font-mono">{{ $row['wins'] }}</td>
                                                    <td class="py-1.5 pr-2 text-right font-mono">{{ $row['sets_won'] }}-{{ $row['sets_lost'] }}</td>
                                                    <td class="py-1.5 text-right font-mono">{{ $row['games_won'] }}-{{ $row['games_lost'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if ($canResults && ($closed || $allPlayed))
                                    <details @if (! $closed) open @endif>
                                        <summary class="cursor-pointer text-sm font-semibold text-ink-2">{{ $closed ? 'Mudar a ordem final' : 'Fechar o grupo' }}</summary>
                                        <form method="POST" action="{{ route('tournaments.results.groups.close', [$tournament, $category, $group]) }}" class="mt-2 flex flex-col gap-2">
                                            @csrf @method('PUT')
                                            <p class="text-xs text-ink-3">Confira a ordem (empate é resolvido por vocês, fora do sistema). Os {{ $category->qualifiers_per_group }} primeiros vão para o mata-mata.</p>
                                            @foreach ($standings[$group->id] as $i => $row)
                                                <label class="flex items-center justify-between gap-2 text-sm">
                                                    <span class="text-ink">{{ $row['team']->name }}</span>
                                                    <select name="positions[{{ $row['team']->id }}]" class="{{ $select }}">
                                                        @for ($p = 1; $p <= $standings[$group->id]->count(); $p++)
                                                            <option value="{{ $p }}" @selected(($row['entry']->final_position ?? $i + 1) === $p)>{{ $p }}º</option>
                                                        @endfor
                                                    </select>
                                                </label>
                                            @endforeach
                                            <div><x-secondary-button type="submit" size="sm">{{ $closed ? 'Salvar a ordem' : 'Fechar e classificar' }}</x-secondary-button></div>
                                        </form>
                                        @if ($closed)
                                            <form method="POST" action="{{ route('tournaments.results.groups.reopen', [$tournament, $category, $group]) }}" class="mt-2" onsubmit="return confirm('Reabrir o {{ $group->name }}? Os classificados saem do mata-mata.')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs font-semibold text-danger hover:underline">Reabrir o grupo</button>
                                            </form>
                                        @endif
                                    </details>
                                @endif
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @foreach ($group->matches as $match)
                                        <div>
                                            <p class="mb-1 text-xs text-ink-3">{{ $match->round }}ª rodada</p>
                                            @include('tournaments.partials.match-card', ['match' => $match, 'groupClosed' => $closed])
                                        </div>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @else
                @php
                    $rounds = $stage->matches->where('third_place', false)->max('round') ?? 0;
                    $third = $stage->matches->firstWhere('third_place', true);
                @endphp
                <section class="flex flex-col gap-4">
                    <h2 class="font-display text-lg font-semibold tracking-tight text-ink">{{ $stages->count() > 1 ? 'Mata-mata' : 'Chave' }}</h2>
                    <div class="overflow-x-auto pb-2">
                        <div class="flex min-w-max gap-4">
                            @for ($round = 1; $round <= $rounds; $round++)
                                <div class="flex w-60 flex-col justify-around gap-3">
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-3">{{ Bracket::roundLabel($round, $rounds) }}</h3>
                                    @foreach ($stage->matches->where('round', $round)->where('third_place', false)->sortBy('position') as $match)
                                        @include('tournaments.partials.match-card', ['match' => $match])
                                    @endforeach
                                    @if ($round === $rounds && $third)
                                        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-3">{{ Bracket::roundLabel($round, $rounds, true) }}</h3>
                                        @include('tournaments.partials.match-card', ['match' => $third])
                                    @endif
                                </div>
                            @endfor
                        </div>
                    </div>
                </section>
            @endif
        @endforeach
    </x-page>
</x-app-layout>
