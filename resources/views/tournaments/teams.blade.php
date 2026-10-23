{{-- Times de uma categoria. O organizador monta com quem está sem time,
     sorteia entre os pagos sem time, troca atleta e capitão e desfaz. Time
     completo = cheio e todos pagos. --}}
@php
    use App\Models\Tournament\TournamentCategory as Category;
    use App\Models\Tournament\TournamentRegistration as Registration;
    use App\Models\Tournament\TournamentTeam as Team;

    $teamKind = fn (string $status) => match ($status) {
        Team::STATUS_COMPLETE => 'ok',
        Team::STATUS_CANCELLED => 'off',
        default => 'warn',
    };
    $paidFree = $unassigned->where('status', Registration::STATUS_PAID)->count();
    $select = 'h-9 rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Times — ' . $category->name" :back="route('tournaments.show', $tournament)">
            {{ $tournament->title }} · {{ Category::describe($category->team_size, $category->gender, $category->min_age, $category->max_age) }} · {{ Category::FORMATIONS[$category->team_formation] ?? '' }}
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('tournaments.registrations.index', [$tournament, 'category' => $category->id]) }}"><x-icon name="users" /> Inscrições</x-secondary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if ($category->team_formation === Category::FORMATION_CAPTAIN)
            <p class="rounded-2xl bg-subtle px-5 py-3 text-sm text-ink-2">Nesta categoria os capitães montam os times pelo site, convidando por CPF ou matrícula, até o fim das inscrições. Você pode ajustar qualquer time aqui.</p>
        @elseif ($category->isIndividual())
            <p class="rounded-2xl bg-subtle px-5 py-3 text-sm text-ink-2">Categoria individual: cada inscrição paga vira um time de uma pessoa sozinha.</p>
        @endif

        <section class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Sem time ({{ $unassigned->count() }})</h2>
                @if (! $category->isIndividual())
                    <form method="POST" action="{{ route('tournaments.teams.draw', [$tournament, $category]) }}" onsubmit="return confirm('Sortear times de {{ $category->team_size }} entre as {{ $paidFree }} inscrição(ões) paga(s) sem time? Os times que já existem não mudam.')">
                        @csrf
                        <x-primary-button :disabled="$paidFree < $category->team_size">Sortear entre os pagos</x-primary-button>
                    </form>
                @endif
            </div>

            @if ($unassigned->isEmpty())
                <p class="text-sm text-ink-3">Todas as inscrições ativas estão em algum time.</p>
            @else
                <form method="POST" action="{{ route('tournaments.teams.store', [$tournament, $category]) }}" class="flex flex-col gap-3">
                    @csrf
                    @if ($unassigned->count() > 8)
                        <x-search-bar mode="client" target="#sem-time" id="busca-sem-time" placeholder="Buscar inscrito" />
                    @endif
                    <div id="sem-time" class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($unassigned as $registration)
                            <label class="flex items-center gap-2 rounded-xl border border-line px-3 py-2 text-sm" data-search="{{ $registration->name }}">
                                <input type="checkbox" name="registration_ids[]" value="{{ $registration->id }}" class="h-4 w-4 rounded border-line-strong text-grena-ink">
                                <span class="flex-1 text-ink">{{ $registration->name }}</span>
                                <x-pill :kind="$registration->status === Registration::STATUS_PAID ? 'ok' : 'warn'">{{ $registration->status === Registration::STATUS_PAID ? 'paga' : 'pendente' }}</x-pill>
                            </label>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="w-full sm:w-72"><x-input-label for="team-name" value="Nome do time (opcional)" /><x-text-input id="team-name" name="name" class="mt-1" maxlength="255" placeholder="Vazio = primeiros nomes" /></div>
                        <x-secondary-button type="submit">Montar time com os marcados</x-secondary-button>
                    </div>
                </form>
            @endif
        </section>

        <section class="flex flex-col gap-4">
            <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Times ({{ $teams->where('status', '!=', Team::STATUS_CANCELLED)->count() }})</h2>

            @if ($teams->isEmpty())
                <x-empty-state icon="users">Nenhum time ainda.</x-empty-state>
            @else
                @if ($teams->count() > 6)
                    <x-search-bar mode="client" target="#times" id="busca-times" placeholder="Buscar time ou atleta" />
                @endif
                <div id="times" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @foreach ($teams as $team)
                        <article class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-card" data-search="{{ $team->name }} {{ $team->registrations->pluck('name')->implode(' ') }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-ink">{{ $team->name }}@if ($team->seed) <span class="text-xs text-ink-3">· cabeça de chave {{ $team->seed }}</span>@endif</h3>
                                    <p class="text-xs text-ink-3">{{ $team->registrations->count() }}/{{ $category->team_size }} · {{ ['organizer' => 'montado pelo organizador', 'draw' => 'sorteado', 'captain' => 'montado pelo capitão', 'individual' => 'individual'][$team->origin] ?? $team->origin }}</p>
                                </div>
                                <x-pill :kind="$teamKind($team->status)">{{ Team::STATUS_LABELS[$team->status] ?? $team->status }}</x-pill>
                            </div>

                            @if ($team->status !== Team::STATUS_CANCELLED)
                                <ul class="divide-y divide-line text-sm">
                                    @foreach ($team->registrations as $registration)
                                        <li class="flex items-center justify-between gap-2 py-2">
                                            <span class="text-ink">
                                                {{ $registration->name }}
                                                @if ($team->captain_registration_id === $registration->id)<x-pill kind="info">capitão</x-pill>@endif
                                                @if ($registration->status !== Registration::STATUS_PAID)<x-pill kind="warn">pendente</x-pill>@endif
                                            </span>
                                            <form method="POST" action="{{ route('tournaments.teams.members.destroy', [$tournament, $category, $team, $registration->id]) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs font-semibold text-danger hover:underline">Tirar</button>
                                            </form>
                                        </li>
                                    @endforeach
                                    @foreach ($team->invites as $invite)
                                        <li class="py-2 text-ink-3">{{ $invite->name }} <x-pill kind="off">convite pendente</x-pill></li>
                                    @endforeach
                                </ul>

                                <details>
                                    <summary class="cursor-pointer text-sm font-semibold text-ink-2">Ajustar</summary>
                                    <div class="mt-3 flex flex-col gap-3">
                                        <form method="POST" action="{{ route('tournaments.teams.update', [$tournament, $category, $team]) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
                                            @csrf @method('PUT')
                                            <div><x-input-label for="name-{{ $team->id }}" value="Nome" /><x-text-input id="name-{{ $team->id }}" name="name" class="mt-1" :value="$team->name" required maxlength="255" /></div>
                                            <div>
                                                <x-input-label for="captain-{{ $team->id }}" value="Capitão" />
                                                <select id="captain-{{ $team->id }}" name="captain_registration_id" class="{{ $select }} mt-1 w-full">
                                                    <option value="">Sem capitão</option>
                                                    @foreach ($team->registrations as $registration)
                                                        <option value="{{ $registration->id }}" @selected($team->captain_registration_id === $registration->id)>{{ $registration->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div><x-input-label for="seed-{{ $team->id }}" value="Cabeça de chave" /><x-text-input type="number" min="1" id="seed-{{ $team->id }}" name="seed" class="mt-1" :value="$team->seed" /></div>
                                            <div class="sm:col-span-3 sm:text-right"><x-secondary-button type="submit">Salvar</x-secondary-button></div>
                                        </form>

                                        @if ($team->registrations->count() < $category->team_size && $unassigned->isNotEmpty())
                                            <form method="POST" action="{{ route('tournaments.teams.members.store', [$tournament, $category, $team]) }}" class="flex flex-wrap items-end gap-2">
                                                @csrf
                                                <div class="flex-1">
                                                    <x-input-label for="add-{{ $team->id }}" value="Incluir atleta sem time" />
                                                    <select id="add-{{ $team->id }}" name="registration_id" class="{{ $select }} mt-1 w-full">
                                                        @foreach ($unassigned as $registration)
                                                            <option value="{{ $registration->id }}">{{ $registration->name }}{{ $registration->status === Registration::STATUS_PAID ? '' : ' (pendente)' }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <x-secondary-button type="submit">Incluir</x-secondary-button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('tournaments.teams.destroy', [$tournament, $category, $team]) }}" onsubmit="return confirm('Desfazer o time {{ addslashes($team->name) }}? As inscrições continuam, sem time.')">
                                            @csrf @method('DELETE')
                                            <x-danger-button size="sm">Desfazer time</x-danger-button>
                                        </form>
                                    </div>
                                </details>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </x-page>
</x-app-layout>
