{{-- Um jogo do chaveamento: os dois lados (time, origem "1º do Grupo A" ou
     a definir), placar em sets, situação, data e local; com
     `torneios.resultados`, lançar/corrigir placar ou W.O., limpar e ver o
     histórico. Espera: $match, $tournament, $category, $groupNames
     (id => nome) e, opcional, $groupClosed. --}}
@php
    use App\Authorization\Permissions as P;
    use App\Models\Tournament\TournamentMatch as Game;

    $groupClosed ??= false;
    $side = function (string $side) use ($match, $groupNames) {
        $team = $side === 'home' ? $match->homeTeam : $match->awayTeam;
        if ($team) {
            return ['label' => $team->name, 'muted' => false];
        }
        $group = $match->{"{$side}_source_group_id"};
        if ($group) {
            return ['label' => $match->{"{$side}_source_position"} . 'º do ' . ($groupNames[$group] ?? 'grupo'), 'muted' => true];
        }
        if ($match->status === Game::STATUS_BYE) {
            return ['label' => '— passa direto', 'muted' => true];
        }
        return ['label' => 'A definir', 'muted' => true];
    };
    $done = in_array($match->status, [Game::STATUS_FINISHED, Game::STATUS_WALKOVER], true);
    $ready = $match->status !== Game::STATUS_BYE && $match->home_team_id && $match->away_team_id;
    $statusKind = match ($match->status) {
        Game::STATUS_FINISHED, Game::STATUS_WALKOVER => 'ok',
        Game::STATUS_BYE => 'off',
        default => 'info',
    };
    $setRows = max(1, 2 * (int) $category->sets_to_win - 1);
    $num = 'h-8 w-14 rounded-lg border border-line-strong bg-surface px-2 text-center text-sm text-ink';
    $actions = ['recorded' => 'lançou', 'corrected' => 'corrigiu', 'cleared' => 'limpou'];
@endphp
<article class="flex flex-col gap-2 rounded-xl border border-line bg-surface p-3 text-sm">
    @foreach (['home', 'away'] as $s)
        @php $row = $side($s); $won = $match->winner_team_id && $match->winner_team_id === $match->{"{$s}_team_id"}; @endphp
        <div class="flex items-center justify-between gap-2">
            <span class="{{ $row['muted'] ? 'text-ink-3 italic' : 'text-ink' }} {{ $won ? 'font-semibold' : '' }}">{{ $row['label'] }}</span>
            @if ($done)
                <span class="font-mono {{ $won ? 'font-semibold text-ink' : 'text-ink-3' }}">
                    {{ $match->{"{$s}_sets"} ?? '–' }}
                    @if ($match->sets->isNotEmpty())<span class="text-xs font-normal text-ink-3">({{ $match->sets->pluck("{$s}_games")->implode(' ') }})</span>@endif
                </span>
            @endif
        </div>
    @endforeach
    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line pt-2 text-xs text-ink-3">
        <span>
            @if ($match->scheduled_at){{ $match->scheduled_at->format('d/m H:i') }}@endif
            @if ($match->location_note) · {{ $match->location_note }}@endif
            @if (! $match->scheduled_at && ! $match->location_note) Sem data @endif
        </span>
        <span class="flex items-center gap-1">
            @if ($match->locked_by_lara)<x-pill kind="off" :icon="false">Lara</x-pill>@elseif ($match->result_origin === Game::ORIGIN_SITE)<x-pill kind="info" :icon="false">site</x-pill>@endif
            <x-pill :kind="$statusKind">{{ Game::STATUS_LABELS[$match->status] ?? $match->status }}</x-pill>
        </span>
    </div>

    @can(P::TORNEIOS_RESULTADOS)
        @if ($ready)
            <details>
                <summary class="cursor-pointer text-xs font-semibold text-ink-2">{{ $done ? 'Corrigir resultado' : 'Lançar resultado' }}</summary>
                @if ($groupClosed)<p class="mt-2 text-xs text-warn">Grupo fechado: gravar ou limpar reabre o grupo (o Lara avisa antes).</p>@endif
                <form method="POST" action="{{ route('tournaments.results.record', [$tournament, $category, $match]) }}" class="mt-2 flex flex-col gap-2">
                    @csrf @method('PUT')
                    <p class="text-xs text-ink-3">Games de cada set ({{ $category->sets_to_win }} set(s) para vencer). Tie-break opcional.</p>
                    @for ($i = 0; $i < $setRows; $i++)
                        @php $set = $match->sets->get($i); @endphp
                        <div class="flex flex-wrap items-center gap-1 text-xs text-ink-3">
                            <span class="w-10">{{ $i + 1 }}º</span>
                            <input type="number" min="0" max="99" name="sets[{{ $i }}][home_games]" value="{{ $set?->home_games }}" class="{{ $num }}" aria-label="{{ $i + 1 }}º set, games de {{ $match->homeTeam?->name }}">
                            <span>×</span>
                            <input type="number" min="0" max="99" name="sets[{{ $i }}][away_games]" value="{{ $set?->away_games }}" class="{{ $num }}" aria-label="{{ $i + 1 }}º set, games de {{ $match->awayTeam?->name }}">
                            <span class="ml-1">TB</span>
                            <input type="number" min="0" max="99" name="sets[{{ $i }}][home_tiebreak]" value="{{ $set?->home_tiebreak }}" class="{{ $num }} w-12" aria-label="Tie-break de {{ $match->homeTeam?->name }}">
                            <input type="number" min="0" max="99" name="sets[{{ $i }}][away_tiebreak]" value="{{ $set?->away_tiebreak }}" class="{{ $num }} w-12" aria-label="Tie-break de {{ $match->awayTeam?->name }}">
                        </div>
                    @endfor
                    <label class="flex items-center gap-2 text-xs text-ink-2">
                        W.O.
                        <select name="walkover" class="h-8 rounded-lg border border-line-strong bg-surface px-2 text-xs text-ink">
                            <option value="">Não (vale o placar)</option>
                            <option value="home" @selected($match->status === Game::STATUS_WALKOVER && $match->winner_team_id === $match->home_team_id)>Vitória de {{ $match->homeTeam?->name }}</option>
                            <option value="away" @selected($match->status === Game::STATUS_WALKOVER && $match->winner_team_id === $match->away_team_id)>Vitória de {{ $match->awayTeam?->name }}</option>
                        </select>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <x-secondary-button type="submit" size="sm">Gravar</x-secondary-button>
                    </div>
                </form>
                @if ($done)
                    <form method="POST" action="{{ route('tournaments.results.clear', [$tournament, $category, $match]) }}" class="mt-2" onsubmit="return confirm('Limpar o resultado deste jogo? Ele volta para A jogar e destrava para o site.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-danger hover:underline">Limpar resultado</button>
                    </form>
                @endif
            </details>
        @endif
    @endcan

    @if ($match->status !== Game::STATUS_BYE)
        @can(P::TORNEIOS_GERENCIAR)
            <details>
                <summary class="cursor-pointer text-xs font-semibold text-ink-2">Data e local</summary>
                <form method="POST" action="{{ route('tournaments.bracket.schedule', [$tournament, $category, $match]) }}" class="mt-2 flex flex-col gap-2">
                    @csrf @method('PUT')
                    <x-text-input type="datetime-local" name="scheduled_at" :value="$match->scheduled_at?->format('Y-m-d\TH:i')" aria-label="Data e hora" />
                    <x-text-input name="location_note" :value="$match->location_note" maxlength="255" placeholder="Quadra 1" aria-label="Local" />
                    <x-secondary-button type="submit" size="sm">Salvar</x-secondary-button>
                </form>
            </details>
        @endcan
    @endif

    @if ($match->resultLogs->isNotEmpty())
        <details>
            <summary class="cursor-pointer text-xs font-semibold text-ink-2">Histórico ({{ $match->resultLogs->count() }})</summary>
            <ul class="mt-2 flex flex-col gap-1 text-xs text-ink-3">
                @foreach ($match->resultLogs as $log)
                    <li>
                        {{ $log->created_at?->format('d/m H:i') }} ·
                        {{ $log->origin === Game::ORIGIN_SITE ? 'site' : 'Lara' }} ·
                        {{ $log->user?->name ?? data_get($log->payload, 'operator') ?? '—' }}
                        {{ $actions[data_get($log->payload, 'action')] ?? '' }}
                        @if (data_get($log->payload, 'home_sets') !== null) {{ data_get($log->payload, 'home_sets') }} × {{ data_get($log->payload, 'away_sets') }}@endif
                        @if (data_get($log->payload, 'status') === Game::STATUS_WALKOVER) (W.O.)@endif
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</article>
