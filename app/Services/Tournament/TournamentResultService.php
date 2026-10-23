<?php

namespace App\Services\Tournament;

use App\Exceptions\ConfirmationRequired;
use App\Exceptions\TournamentException;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentGroup;
use App\Models\Tournament\TournamentMatch;
use App\Models\Tournament\TournamentMatchResultLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resultados (F6), lançados no Lara ou no site (equipe do clube, com o
 * código de lançamento do torneio).
 *
 *   - Placar por **sets e games**, sem empate: cada set tem um vencedor, o
 *     vencedor do jogo chega a `sets_to_win` e não há set depois disso.
 *     Tie-break opcional por set (os dois lados), com o mesmo vencedor do set.
 *   - **W.O.**: vencedor sem sets jogados (conta `sets_to_win` × 0).
 *   - **O Lara prevalece**: lançado no Lara, o jogo fica `locked_by_lara` e o
 *     site não mexe mais (`match_locked`).
 *   - Correção: se o vencedor muda e o jogo seguinte (ou o 3º lugar) já tem
 *     resultado, o site é recusado (`next_match_played`); jogo de grupo
 *     fechado, idem (`group_closed`).
 *   - No Lara essas regras só avisam (ConfirmationRequired): confirmado
 *     (`$force`), o Lara reabre o grupo e apaga em cascata os resultados que
 *     dependiam do jogo, cada um com o seu registro no histórico. O mesmo
 *     vale para torneio fora de situação, fechar grupo com jogo faltando e
 *     reabrir grupo com mata-mata já jogado.
 *   - Cada lançamento, correção ou limpeza vai para `tournament_match_result_logs`.
 *   - O vencedor segue para o próximo jogo (TournamentBracketService::advance).
 *
 * Grupo: o organizador **fecha** com a ordem final (empate na classificação é
 * resolvido fora do sistema); os classificados entram no mata-mata.
 *
 * Recusas como TournamentException (o painel mostra a mensagem; o site usa
 * o `reason`).
 */
class TournamentResultService
{
    public const ORIGIN_LARA = TournamentMatch::ORIGIN_LARA;
    public const ORIGIN_SITE = TournamentMatch::ORIGIN_SITE;

    private const DONE = [TournamentMatch::STATUS_FINISHED, TournamentMatch::STATUS_WALKOVER];

    private const DOWNSTREAM_WARNING = 'O jogo seguinte (ou o de 3º lugar) já tem resultado: ele e os que dependem dele terão o resultado apagado.';

    public function __construct(private TournamentBracketService $brackets)
    {
    }

    /**
     * Lança ou corrige o resultado.
     *
     * @param  list<array{home_games: int|string, away_games: int|string, home_tiebreak?: int|string|null, away_tiebreak?: int|string|null}>  $sets
     * @param  ?int  $walkoverWinnerId  time vencedor por W.O. (os sets são ignorados)
     * @param  bool  $force  só no Lara: confirma os avisos (ver a classe)
     */
    public function record(TournamentMatch $match, array $sets, string $origin, ?User $user = null, ?string $operator = null, ?int $walkoverWinnerId = null, bool $force = false): TournamentMatch
    {
        return DB::transaction(function () use ($match, $sets, $origin, $user, $operator, $walkoverWinnerId, $force) {
            $match = $this->lock($match);
            $category = $match->stage->category;

            $warnings = [$this->openWarning($category->tournament, $origin)];

            if ($origin === self::ORIGIN_SITE && $match->locked_by_lara) {
                throw new TournamentException(TournamentException::MATCH_LOCKED, 'Este resultado foi lançado no Lara e só pode ser alterado lá.', 409);
            }
            if ($match->status === TournamentMatch::STATUS_BYE || ! $match->home_team_id || ! $match->away_team_id) {
                throw new TournamentException(TournamentException::MATCH_NOT_READY, 'Este jogo ainda não tem os dois times definidos.');
            }
            $reopen = $this->groupClosedWarnings($match, $origin);
            $warnings = array_merge($warnings, $reopen);

            $setsToWin = max(1, (int) $category->sets_to_win);

            if ($walkoverWinnerId !== null) {
                if (! in_array($walkoverWinnerId, [$match->home_team_id, $match->away_team_id], true)) {
                    throw new TournamentException(TournamentException::INVALID_SCORE, 'O vencedor do W.O. precisa ser um dos dois times do jogo.', 422, ['detail' => 'walkover_winner']);
                }
                $status = TournamentMatch::STATUS_WALKOVER;
                $winner = $walkoverWinnerId;
                $homeSets = $winner === $match->home_team_id ? $setsToWin : 0;
                $awaySets = $setsToWin - $homeSets;
                $normalized = [];
            } else {
                [$normalized, $homeSets, $awaySets] = self::score($sets, $setsToWin);
                $status = TournamentMatch::STATUS_FINISHED;
                $winner = $homeSets > $awaySets ? $match->home_team_id : $match->away_team_id;
            }

            $wasDone = in_array($match->status, self::DONE, true);
            $downstream = $wasDone && $match->winner_team_id !== $winner && $this->downstreamPlayed($match);

            if ($downstream) {
                if ($origin === self::ORIGIN_SITE) {
                    throw new TournamentException(TournamentException::NEXT_MATCH_PLAYED, 'O jogo seguinte já tem resultado: corrija ou limpe aquele antes de mudar o vencedor deste.', 409);
                }
                $warnings[] = self::DOWNSTREAM_WARNING;
            }

            ConfirmationRequired::unless($force, $warnings);

            if ($reopen !== []) {
                $this->reopen($match->group, $user);
            }
            if ($downstream) {
                $this->clearDownstream($match, $user);
            }

            $previous = $wasDone ? $this->snapshot($match) : null;

            $match->sets()->delete();
            foreach ($normalized as $i => $set) {
                $match->sets()->create(['set_number' => $i + 1] + $set);
            }

            $match->update([
                'status' => $status,
                'home_sets' => $homeSets,
                'away_sets' => $awaySets,
                'winner_team_id' => $winner,
                'result_origin' => $origin,
                'result_user_id' => $user?->id,
                'result_at' => now(),
                // Lançado no Lara trava para o site; o site nunca destrava.
                'locked_by_lara' => $match->locked_by_lara || $origin === self::ORIGIN_LARA,
            ]);

            $this->log($match, $origin, $user, [
                'action' => $previous ? 'corrected' : 'recorded',
                'status' => $status,
                'sets' => $normalized,
                'home_sets' => $homeSets,
                'away_sets' => $awaySets,
                'winner_team_id' => $winner,
                'operator' => $operator,
                'previous' => $previous,
            ]);

            if (! $match->tournament_group_id) {
                $this->brackets->advance($match->fresh());
            }

            return $match->fresh(['sets', 'homeTeam', 'awayTeam']);
        });
    }

    /**
     * Desfaz o resultado (só no Lara): o jogo volta a "A jogar" e destrava.
     * Grupo fechado ou jogo seguinte já jogado: aviso; confirmado, reabre o
     * grupo e limpa em cascata.
     */
    public function clear(TournamentMatch $match, User $user, bool $force = false): TournamentMatch
    {
        return DB::transaction(function () use ($match, $user, $force) {
            $match = $this->lock($match);

            if (! in_array($match->status, self::DONE, true)) {
                return $match;
            }

            $reopen = $this->groupClosedWarnings($match, self::ORIGIN_LARA);
            $downstream = $this->downstreamPlayed($match);
            ConfirmationRequired::unless($force, array_merge($reopen, [$downstream ? self::DOWNSTREAM_WARNING : null]));

            if ($reopen !== []) {
                $this->reopen($match->group, $user);
            }
            $this->wipe($match, $user);

            return $match->fresh();
        });
    }

    /**
     * Fecha o grupo com a ordem final (`[teamId => posição]`, 1 = primeiro) e
     * põe os classificados no mata-mata. Jogo do grupo sem resultado, ou
     * mata-mata que recebe do grupo já jogado: aviso; confirmado, fecha
     * assim mesmo (e limpa o mata-mata que dependia da ordem antiga).
     *
     * @param  array<int|string, int|string>  $positions
     */
    public function closeGroup(TournamentGroup $group, array $positions, User $user, bool $force = false): void
    {
        DB::transaction(function () use ($group, $positions, $user, $force) {
            $group = TournamentGroup::with(['entries', 'matches', 'stage.category'])->lockForUpdate()->findOrFail($group->id);

            $pending = $group->matches->whereNotIn('status', self::DONE)->count();
            $warnings = [$pending > 0 ? "Faltam {$pending} jogo(s) do {$group->name} com resultado: fechado agora, eles só recebem resultado depois de reabrir o grupo." : null];

            $teamIds = $group->entries->pluck('tournament_team_id')->sort()->values()->all();
            $order = collect($positions)->mapWithKeys(fn ($pos, $team) => [(int) $team => (int) $pos]);

            if ($order->keys()->sort()->values()->all() !== $teamIds
                || $order->values()->sort()->values()->all() !== range(1, count($teamIds))) {
                throw new TournamentException(TournamentException::INVALID_STATUS, "Dê uma posição diferente, de 1 a " . count($teamIds) . ", a cada time do {$group->name}.");
            }

            $warnings[] = $this->fedWarning($group);
            ConfirmationRequired::unless($force, $warnings);

            $this->freeFed($group, $user);
            $fed = $this->fedMatches($group);

            foreach ($group->entries as $entry) {
                $entry->update(['final_position' => $order[$entry->tournament_team_id]]);
            }

            $byPosition = $order->flip();

            foreach ($fed as $match) {
                $updates = [];
                foreach (['home', 'away'] as $side) {
                    if ($match->{"{$side}_source_group_id"} === $group->id) {
                        $updates["{$side}_team_id"] = $byPosition[$match->{"{$side}_source_position"}] ?? null;
                    }
                }
                $match->update($updates);

                // "Passa direto" vindo do grupo: o classificado avança agora.
                if ($match->status === TournamentMatch::STATUS_BYE) {
                    $winner = $match->home_team_id ?? $match->away_team_id;
                    $match->update(['winner_team_id' => $winner]);
                    $this->brackets->advance($match->fresh());
                }
            }
        });
    }

    /**
     * Reabre o grupo: tira as posições e os classificados do mata-mata.
     * Mata-mata já jogado com esses classificados: aviso; confirmado, limpa.
     */
    public function reopenGroup(TournamentGroup $group, User $user, bool $force = false): void
    {
        DB::transaction(function () use ($group, $user, $force) {
            $group = TournamentGroup::with('entries')->lockForUpdate()->findOrFail($group->id);
            ConfirmationRequired::unless($force, [$this->fedWarning($group)]);

            $this->reopen($group, $user);
        });
    }

    /** Reabre sem perguntar: limpa o mata-mata que recebia do grupo e tira as posições. */
    private function reopen(TournamentGroup $group, ?User $user): void
    {
        $this->freeFed($group, $user);

        foreach ($this->fedMatches($group) as $match) {
            if ($match->status === TournamentMatch::STATUS_BYE && $match->winner_team_id) {
                $this->retract($match);
                $match->winner_team_id = null;
            }
            foreach (['home', 'away'] as $side) {
                if ($match->{"{$side}_source_group_id"} === $group->id) {
                    $match->{"{$side}_team_id"} = null;
                }
            }
            $match->save();
        }

        $group->entries()->update(['final_position' => null]);
    }

    /**
     * Confere e normaliza os sets.
     *
     * @return array{0: list<array{home_games: int, away_games: int, home_tiebreak: ?int, away_tiebreak: ?int}>, 1: int, 2: int}
     */
    public static function score(array $sets, int $setsToWin): array
    {
        $sets = array_values(array_filter($sets, fn ($s) => is_array($s) && (filled($s['home_games'] ?? null) || filled($s['away_games'] ?? null))));
        $fail = fn (string $detail, string $message) => throw new TournamentException(TournamentException::INVALID_SCORE, $message, 422, ['detail' => $detail]);

        if ($sets === []) {
            $fail('no_sets', 'Informe o placar de ao menos um set.');
        }

        $home = 0;
        $away = 0;
        $normalized = [];

        foreach ($sets as $i => $set) {
            $number = $i + 1;

            if ($home === $setsToWin || $away === $setsToWin) {
                $fail('extra_set', "O jogo já estava decidido antes do {$number}º set.");
            }

            $hg = $set['home_games'] ?? null;
            $ag = $set['away_games'] ?? null;
            if (! is_numeric($hg) || ! is_numeric($ag) || (int) $hg < 0 || (int) $ag < 0 || (int) $hg > 99 || (int) $ag > 99) {
                $fail('games', "Games do {$number}º set inválidos.");
            }
            $hg = (int) $hg;
            $ag = (int) $ag;
            if ($hg === $ag) {
                $fail('tied_set', "O {$number}º set está empatado ({$hg} × {$ag}): todo set tem um vencedor.");
            }

            $ht = $set['home_tiebreak'] ?? null;
            $at = $set['away_tiebreak'] ?? null;
            $ht = filled($ht) ? $ht : null;
            $at = filled($at) ? $at : null;
            if (($ht === null) !== ($at === null)) {
                $fail('tiebreak', "Informe o tie-break do {$number}º set dos dois lados, ou de nenhum.");
            }
            if ($ht !== null) {
                if (! is_numeric($ht) || ! is_numeric($at) || (int) $ht < 0 || (int) $at < 0 || (int) $ht === (int) $at
                    || (((int) $ht > (int) $at) !== ($hg > $ag))) {
                    $fail('tiebreak', "O tie-break do {$number}º set não combina com o vencedor do set.");
                }
                $ht = (int) $ht;
                $at = (int) $at;
            }

            $hg > $ag ? $home++ : $away++;
            $normalized[] = ['home_games' => $hg, 'away_games' => $ag, 'home_tiebreak' => $ht, 'away_tiebreak' => $at];
        }

        if ($home !== $setsToWin && $away !== $setsToWin) {
            $fail('undecided', "O jogo é de {$setsToWin} set(s) para vencer: o placar ({$home} × {$away}) não fecha o jogo.");
        }

        return [$normalized, $home, $away];
    }

    private function lock(TournamentMatch $match): TournamentMatch
    {
        return TournamentMatch::with(['stage.category.tournament', 'group.entries'])->lockForUpdate()->findOrFail($match->id);
    }

    /** Situação do torneio: o site é recusado; o Lara recebe o aviso (ou null). */
    private function openWarning(Tournament $tournament, string $origin): ?string
    {
        $allowed = $origin === self::ORIGIN_SITE
            ? [Tournament::STATUS_PUBLISHED, Tournament::STATUS_IN_PROGRESS]
            : [Tournament::STATUS_PUBLISHED, Tournament::STATUS_IN_PROGRESS, Tournament::STATUS_FINISHED];

        if (in_array($tournament->status, $allowed, true)) {
            return null;
        }

        $label = Tournament::STATUS_LABELS[$tournament->status] ?? $tournament->status;

        if ($origin === self::ORIGIN_SITE) {
            throw new TournamentException(TournamentException::RESULTS_CLOSED, "Este torneio não aceita lançamento de resultado na situação atual ({$label}).");
        }

        return 'O torneio está ' . mb_strtolower($label) . ': normalmente não recebe resultado.';
    }

    /** Jogo de grupo já fechado: o site é recusado; o Lara recebe os avisos de reabrir. */
    private function groupClosedWarnings(TournamentMatch $match, string $origin): array
    {
        if (! $match->tournament_group_id || $match->group?->entries->whereNotNull('final_position')->isEmpty()) {
            return [];
        }

        if ($origin === self::ORIGIN_SITE) {
            throw new TournamentException(TournamentException::GROUP_CLOSED, "O {$match->group->name} já foi fechado: reabra o grupo no Lara para mudar este resultado.", 409);
        }

        return array_values(array_filter([
            "O {$match->group->name} já foi fechado: ele será reaberto (a classificação sai e os classificados saem do mata-mata). Feche de novo depois.",
            $this->fedWarning($match->group),
        ]));
    }

    /** O jogo seguinte e o 3º lugar (se for semifinal). */
    private function downstream(TournamentMatch $match): array
    {
        if ($match->tournament_group_id) {
            return [];
        }

        $next = $match->next_match_id ? TournamentMatch::find($match->next_match_id) : null;
        $third = $match->third_place ? null : TournamentMatch::where('tournament_stage_id', $match->tournament_stage_id)
            ->where('third_place', true)
            ->where('round', $match->round + 1)
            ->first();

        return array_values(array_filter([$next, $third]));
    }

    private function downstreamPlayed(TournamentMatch $match): bool
    {
        foreach ($this->downstream($match) as $after) {
            if (in_array($after->status, self::DONE, true)) {
                return true;
            }
        }

        return false;
    }

    /** Apaga os resultados dos jogos seguintes, dos últimos para trás. */
    private function clearDownstream(TournamentMatch $match, ?User $user): void
    {
        foreach ($this->downstream($match) as $after) {
            if (in_array($after->status, self::DONE, true)) {
                $this->wipe($after, $user, cascade: true);
            }
        }
    }

    /** O jogo volta a "A jogar", destravado, e o que ele mandou adiante sai. */
    private function wipe(TournamentMatch $match, ?User $user, bool $cascade = false): void
    {
        $this->clearDownstream($match, $user);
        $match->refresh();
        $previous = $this->snapshot($match);

        if (! $match->tournament_group_id) {
            $this->retract($match);
        }

        $match->sets()->delete();
        $match->update([
            'status' => TournamentMatch::STATUS_PENDING,
            'home_sets' => null,
            'away_sets' => null,
            'winner_team_id' => null,
            'result_origin' => null,
            'result_user_id' => null,
            'result_at' => null,
            'locked_by_lara' => false,
        ]);

        $this->log($match, self::ORIGIN_LARA, $user, array_filter(['action' => 'cleared', 'cascade' => $cascade ?: null, 'previous' => $previous]));
    }

    /** Tira o vencedor (e o perdedor do 3º lugar) que este jogo tinha mandado adiante. */
    private function retract(TournamentMatch $match): void
    {
        if ($match->next_match_id && $match->next_slot && $match->winner_team_id) {
            TournamentMatch::whereKey($match->next_match_id)
                ->where("{$match->next_slot}_team_id", $match->winner_team_id)
                ->update(["{$match->next_slot}_team_id" => null]);
        }

        $loser = $match->winner_team_id === $match->home_team_id ? $match->away_team_id : $match->home_team_id;
        if ($loser && ! $match->third_place) {
            TournamentMatch::where('tournament_stage_id', $match->tournament_stage_id)
                ->where('third_place', true)
                ->where('round', $match->round + 1)
                ->each(function (TournamentMatch $third) use ($loser) {
                    foreach (['home', 'away'] as $side) {
                        if ($third->{"{$side}_team_id"} === $loser) {
                            $third->update(["{$side}_team_id" => null]);
                        }
                    }
                });
        }
    }

    /** Jogos do mata-mata que recebem classificado deste grupo. */
    private function fedMatches(TournamentGroup $group)
    {
        return TournamentMatch::where('home_source_group_id', $group->id)
            ->orWhere('away_source_group_id', $group->id)
            ->get();
    }

    /**
     * Os jogos já jogados que dependem da classificação do grupo: o que
     * recebe o classificado e, se ele "passa direto", o jogo seguinte.
     *
     * @return list<TournamentMatch>
     */
    private function playedFromGroup(TournamentGroup $group): array
    {
        $played = [];

        foreach ($this->fedMatches($group) as $match) {
            $next = $match->status === TournamentMatch::STATUS_BYE && $match->next_match_id ? TournamentMatch::find($match->next_match_id) : null;

            foreach ([$match, $next] as $candidate) {
                if ($candidate && in_array($candidate->status, self::DONE, true)) {
                    $played[] = $candidate;
                }
            }
        }

        return $played;
    }

    private function fedWarning(TournamentGroup $group): ?string
    {
        $count = count($this->playedFromGroup($group));

        return $count > 0
            ? "O mata-mata que recebe os classificados do {$group->name} já tem {$count} jogo(s) com resultado: esses resultados (e os que dependem deles) serão apagados."
            : null;
    }

    /** Apaga os resultados do mata-mata que dependem da classificação do grupo. */
    private function freeFed(TournamentGroup $group, ?User $user): void
    {
        foreach ($this->playedFromGroup($group) as $match) {
            $match->refresh();
            if (in_array($match->status, self::DONE, true)) {
                $this->wipe($match, $user, cascade: true);
            }
        }
    }

    private function snapshot(TournamentMatch $match): array
    {
        return [
            'status' => $match->status,
            'home_sets' => $match->home_sets,
            'away_sets' => $match->away_sets,
            'winner_team_id' => $match->winner_team_id,
            'origin' => $match->result_origin,
            'sets' => $match->sets()->get(['home_games', 'away_games', 'home_tiebreak', 'away_tiebreak'])->toArray(),
        ];
    }

    private function log(TournamentMatch $match, string $origin, ?User $user, array $payload): void
    {
        TournamentMatchResultLog::create([
            'tournament_match_id' => $match->id,
            'origin' => $origin,
            'user_id' => $user?->id,
            'payload' => $payload,
        ]);
    }
}
