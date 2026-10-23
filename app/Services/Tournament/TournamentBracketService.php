<?php

namespace App\Services\Tournament;

use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentGroup;
use App\Models\Tournament\TournamentGroupTeam;
use App\Models\Tournament\TournamentMatch;
use App\Models\Tournament\TournamentStage;
use App\Models\Tournament\TournamentTeam;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chaveamento de uma categoria (F5).
 *
 *   - Entram **todos os times não desfeitos** (completos e em formação); o
 *     organizador resolve os incompletos caso a caso.
 *   - Pode ser gerado **a qualquer momento**, e gerado de novo (ou desfeito)
 *     enquanto nenhum jogo tiver resultado. Gerar de novo tira do site.
 *   - **Sorteio**: cabeças de chave (`seed`) primeiro, na ordem; os demais
 *     embaralhados. **Manual**: o organizador diz o lugar de cada time.
 *   - **Eliminatória simples**: chave do tamanho da potência de 2 seguinte;
 *     as vagas que sobram são "passa direto" e caem com os primeiros da
 *     ordem (posições-padrão de chave: 1 e 2 só se cruzam na final).
 *   - **Grupos + mata-mata**: grupos em serpentina, todos contra todos em
 *     turno único; o mata-mata nasce com os lados apontando para "Nº do
 *     Grupo X", no **cruzado padrão** (1º A × 2º B, 1º B × 2º A…; os 1ºs em
 *     lados opostos da chave). Os times entram quando o grupo fecha (F6).
 *   - **3º lugar** (se a categoria pede e há ao menos 4 times): jogo extra na
 *     rodada da final, com `third_place` ligado; recebe os perdedores das
 *     semifinais.
 *   - Só aparece no site depois de **divulgado** (`bracket_published_at`).
 *
 * Recusas saem como ValidationException (painel).
 */
class TournamentBracketService
{
    public const GROUP_NAMES = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /** Times que entram no chaveamento: os não desfeitos, cabeças de chave primeiro. */
    public function teamsFor(TournamentCategory $category): Collection
    {
        return $category->teams()
            ->where('status', '!=', TournamentTeam::STATUS_CANCELLED)
            ->get()
            ->sortBy([
                fn ($a, $b) => ($a->seed === null) <=> ($b->seed === null),
                fn ($a, $b) => $a->seed <=> $b->seed,
                fn ($a, $b) => strcmp($a->name, $b->name),
            ])
            ->values();
    }

    public function hasBracket(TournamentCategory $category): bool
    {
        return $category->stages()->exists();
    }

    /** Algum jogo já tem resultado (passar direto não conta). */
    public function hasResults(TournamentCategory $category): bool
    {
        return TournamentMatch::whereIn('tournament_stage_id', $category->stages()->pluck('id'))
            ->whereIn('status', [TournamentMatch::STATUS_FINISHED, TournamentMatch::STATUS_WALKOVER])
            ->exists();
    }

    /**
     * Gera (ou gera de novo) o chaveamento.
     *
     * `$manual`, quando `$generation` é manual:
     *   - eliminatória: `['slots' => [0 => teamId|null, 1 => …]]`, um lugar por
     *     lado dos jogos da 1ª rodada, na ordem (jogo 1 casa, jogo 1 fora…);
     *   - grupos: `['groups' => [teamId => índice do grupo (0 = A)]]`.
     */
    public function generate(TournamentCategory $category, string $generation, User $user, array $manual = []): void
    {
        if (! in_array($generation, [TournamentStage::GENERATION_DRAW, TournamentStage::GENERATION_MANUAL], true)) {
            $this->fail('generation', 'Escolha sorteio ou montagem à mão.');
        }

        DB::transaction(function () use ($category, $generation, $manual) {
            TournamentCategory::whereKey($category->id)->lockForUpdate()->first();

            if ($this->hasResults($category)) {
                $this->fail('bracket', 'Já há resultado lançado nesta categoria: o chaveamento não pode mais ser gerado de novo.');
            }

            $teams = $this->teamsFor($category);

            if ($teams->count() < 2) {
                $this->fail('bracket', 'São precisos ao menos 2 times para o chaveamento.');
            }

            $this->wipe($category);

            if ($category->format === TournamentCategory::FORMAT_GROUPS_KNOCKOUT) {
                $this->buildGroups($category, $teams, $generation, $manual['groups'] ?? []);
            } else {
                $this->buildElimination($category, $teams, $generation, $manual['slots'] ?? []);
            }

            $category->update(['bracket_published_at' => null]);
        });
    }

    /** Desfaz o chaveamento (só antes do primeiro resultado). */
    public function clear(TournamentCategory $category): void
    {
        DB::transaction(function () use ($category) {
            TournamentCategory::whereKey($category->id)->lockForUpdate()->first();

            if ($this->hasResults($category)) {
                $this->fail('bracket', 'Já há resultado lançado nesta categoria: o chaveamento não pode ser desfeito.');
            }

            $this->wipe($category);
            $category->update(['bracket_published_at' => null]);
        });
    }

    /** Divulga no site (ou recolhe). */
    public function publish(TournamentCategory $category, bool $publish): void
    {
        if ($publish && ! $this->hasBracket($category)) {
            $this->fail('bracket', 'Gere o chaveamento antes de divulgar.');
        }

        $category->update(['bracket_published_at' => $publish ? now() : null]);
    }

    /** Data, hora e local de um jogo (informativos: não reservam espaço). */
    public function schedule(TournamentMatch $match, ?string $scheduledAt, ?string $locationNote): void
    {
        $match->update([
            'scheduled_at' => $scheduledAt ?: null,
            'location_note' => filled($locationNote) ? trim($locationNote) : null,
        ]);
    }

    /**
     * O que mudou nos times desde a geração: `missing` (times ativos fora da
     * chave) e `gone` (times da chave que foram desfeitos).
     *
     * @return array{missing: Collection, gone: Collection}
     */
    public function outdated(TournamentCategory $category): array
    {
        $stageIds = $category->stages()->pluck('id');

        if ($stageIds->isEmpty()) {
            return ['missing' => collect(), 'gone' => collect()];
        }

        $inBracket = TournamentGroupTeam::whereIn('tournament_group_id', TournamentGroup::whereIn('tournament_stage_id', $stageIds)->pluck('id'))
            ->pluck('tournament_team_id')
            ->merge(TournamentMatch::whereIn('tournament_stage_id', $stageIds)->pluck('home_team_id'))
            ->merge(TournamentMatch::whereIn('tournament_stage_id', $stageIds)->pluck('away_team_id'))
            ->filter()
            ->unique();

        $active = $this->teamsFor($category);

        return [
            'missing' => $active->whereNotIn('id', $inBracket)->values(),
            'gone' => $category->teams()
                ->whereIn('id', $inBracket)
                ->where('status', TournamentTeam::STATUS_CANCELLED)
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * Classificação do grupo: jogos, vitórias, sets e games, contando os jogos
     * com resultado. A ordem final é a que o organizador fixou
     * (`final_position`); sem ela, vitórias, saldo de sets e de games.
     *
     * @return Collection<int, array{team: TournamentTeam, entry: TournamentGroupTeam, played: int, wins: int, losses: int, sets_won: int, sets_lost: int, games_won: int, games_lost: int}>
     */
    public function standings(TournamentGroup $group): Collection
    {
        $group->loadMissing(['entries.team', 'matches.sets']);

        $rows = $group->entries->mapWithKeys(fn (TournamentGroupTeam $entry) => [$entry->tournament_team_id => [
            'team' => $entry->team,
            'entry' => $entry,
            'played' => 0, 'wins' => 0, 'losses' => 0,
            'sets_won' => 0, 'sets_lost' => 0, 'games_won' => 0, 'games_lost' => 0,
        ]])->all();

        foreach ($group->matches as $match) {
            if (! in_array($match->status, [TournamentMatch::STATUS_FINISHED, TournamentMatch::STATUS_WALKOVER], true)) {
                continue;
            }

            foreach ([['home', 'away'], ['away', 'home']] as [$side, $other]) {
                $teamId = $match->{"{$side}_team_id"};

                if (! isset($rows[$teamId])) {
                    continue;
                }

                $rows[$teamId]['played']++;
                $rows[$teamId][$match->winner_team_id === $teamId ? 'wins' : 'losses']++;
                $rows[$teamId]['sets_won'] += (int) $match->{"{$side}_sets"};
                $rows[$teamId]['sets_lost'] += (int) $match->{"{$other}_sets"};
                $rows[$teamId]['games_won'] += (int) $match->sets->sum("{$side}_games");
                $rows[$teamId]['games_lost'] += (int) $match->sets->sum("{$other}_games");
            }
        }

        return collect($rows)->sortBy([
            fn ($a, $b) => ($a['entry']->final_position === null) <=> ($b['entry']->final_position === null),
            fn ($a, $b) => $a['entry']->final_position <=> $b['entry']->final_position,
            fn ($a, $b) => $b['wins'] <=> $a['wins'],
            fn ($a, $b) => ($b['sets_won'] - $b['sets_lost']) <=> ($a['sets_won'] - $a['sets_lost']),
            fn ($a, $b) => ($b['games_won'] - $b['games_lost']) <=> ($a['games_won'] - $a['games_lost']),
            fn ($a, $b) => strcmp($a['team']->name, $b['team']->name),
        ])->values();
    }

    /**
     * Leva o vencedor do jogo para o próximo (lado `next_slot`) e, no jogo de
     * semifinal, o perdedor para o 3º lugar. Usado nos "passa direto" e,
     * na F6, a cada resultado.
     */
    public function advance(TournamentMatch $match): void
    {
        if (! $match->winner_team_id) {
            return;
        }

        if ($match->next_match_id && $match->next_slot) {
            TournamentMatch::whereKey($match->next_match_id)->update(["{$match->next_slot}_team_id" => $match->winner_team_id]);
        }

        $loser = $match->winner_team_id === $match->home_team_id ? $match->away_team_id : $match->home_team_id;
        $third = $this->thirdPlaceFor($match);

        if ($loser && $third) {
            // Semifinal de cima manda para a casa; a de baixo, para fora.
            $third->update([($match->position % 2 === 1 ? 'home' : 'away') . '_team_id' => $loser]);
        }
    }

    /** Tamanho da chave: a potência de 2 que cabe `$teams`. */
    public static function bracketSize(int $teams): int
    {
        $size = 2;

        while ($size < $teams) {
            $size *= 2;
        }

        return $size;
    }

    /**
     * Posições-padrão da chave: para cada lugar (na ordem dos jogos da 1ª
     * rodada), qual posição da ordem ocupa. 8 → [1, 8, 4, 5, 2, 7, 3, 6]:
     * 1 × 8, 4 × 5, 2 × 7, 3 × 6; 1 e 2 só se cruzam na final.
     *
     * @return list<int>
     */
    public static function seedOrder(int $size): array
    {
        $order = [1];

        while (count($order) < $size) {
            $double = count($order) * 2;
            $next = [];

            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $double + 1 - $seed;
            }

            $order = $next;
        }

        return $order;
    }

    /**
     * Ordem dos classificados no cruzado padrão: os 1ºs de cada grupo, depois
     * os 2ºs… cada um posto de modo que, nas posições-padrão, o 1º do A pegue
     * o 2º do B, o 1º do B pegue o 2º do A (C × D, D × C…; com número ímpar
     * de grupos, A × B, B × C, C × A) e ninguém reencontre o próprio grupo na
     * 1ª rodada quando há como evitar.
     *
     * @return list<array{0: int, 1: int}> [índice do grupo, posição no grupo]
     */
    public static function crossRanks(int $groups, int $qualifiers): array
    {
        $size = self::bracketSize($groups * $qualifiers);
        $ranks = [];

        for ($g = 0; $g < $groups; $g++) {
            $ranks[] = [$g, 1];
        }

        for ($position = 2; $position <= $qualifiers; $position++) {
            $free = range(0, $groups - 1);
            $tier = array_fill(0, $groups, null);
            $base = count($ranks);

            // 1º: o par do cruzado, quando o adversário é um 1º colocado.
            for ($k = 0; $k < $groups; $k++) {
                $opponent = $size - ($base + $k);

                if ($opponent >= 1 && $opponent <= $groups) {
                    $want = self::crossPartner($opponent - 1, $groups);

                    if ($want !== null && in_array($want, $free, true)) {
                        $tier[$k] = $want;
                        $free = array_values(array_diff($free, [$want]));
                    }
                }
            }

            // Depois, o resto, fugindo do grupo do adversário.
            for ($k = 0; $k < $groups; $k++) {
                if ($tier[$k] !== null) {
                    continue;
                }

                $opponent = $size - ($base + $k);
                $avoid = $opponent >= 1 && $opponent <= $base ? $ranks[$opponent - 1][0] : null;
                $pick = collect($free)->first(fn ($g) => $g !== $avoid) ?? $free[0];
                $tier[$k] = $pick;
                $free = array_values(array_diff($free, [$pick]));
            }

            foreach ($tier as $g) {
                $ranks[] = [$g, $position];
            }
        }

        return $ranks;
    }

    /** "Final", "Semifinal", "Quartas de final", "Oitavas de final", "1ª rodada". */
    public static function roundLabel(int $round, int $rounds, bool $thirdPlace = false): string
    {
        if ($thirdPlace) {
            return 'Disputa de 3º lugar';
        }

        return match ($rounds - $round) {
            0 => 'Final',
            1 => 'Semifinal',
            2 => 'Quartas de final',
            3 => 'Oitavas de final',
            default => "{$round}ª rodada",
        };
    }

    public static function groupName(int $index): string
    {
        return 'Grupo ' . (self::GROUP_NAMES[$index] ?? (string) ($index + 1));
    }

    private static function crossPartner(int $group, int $groups): ?int
    {
        if ($groups < 2) {
            return null;
        }

        if ($groups % 2 === 0) {
            return $group ^ 1;
        }

        return ($group + 1) % $groups;
    }

    private function buildElimination(TournamentCategory $category, Collection $teams, string $generation, array $manualSlots): void
    {
        $size = self::bracketSize($teams->count());

        if ($generation === TournamentStage::GENERATION_MANUAL) {
            $slots = $this->manualSlots($teams, $size, $manualSlots);
        } else {
            $ranked = $this->drawOrder($teams);
            $slots = array_map(fn (int $rank) => $ranked->get($rank - 1)?->id, self::seedOrder($size));
        }

        $stage = $category->stages()->create([
            'type' => TournamentStage::TYPE_KNOCKOUT,
            'order' => 1,
            'generation' => $generation,
            'generated_at' => now(),
        ]);

        $sides = array_map(fn ($teamId) => ['team' => $teamId, 'group' => null, 'position' => null], $slots);
        $this->buildKnockout($stage, $sides, $category->third_place_match && $teams->count() >= 4);
    }

    private function buildGroups(TournamentCategory $category, Collection $teams, string $generation, array $manualGroups): void
    {
        $groupsCount = (int) $category->groups_count;
        $qualifiers = (int) $category->qualifiers_per_group;

        if ($groupsCount < 1 || $qualifiers < 1) {
            $this->fail('bracket', 'Defina o número de grupos e de classificados por grupo na categoria.');
        }
        if ($groupsCount > strlen(self::GROUP_NAMES)) {
            $this->fail('bracket', 'São no máximo ' . strlen(self::GROUP_NAMES) . ' grupos.');
        }
        if ($groupsCount * $qualifiers < 2) {
            $this->fail('bracket', 'Com um grupo só, classifique ao menos 2 times para o mata-mata.');
        }

        if ($generation === TournamentStage::GENERATION_MANUAL) {
            $buckets = $this->manualGroups($teams, $groupsCount, $manualGroups);
        } else {
            // Serpentina: A B C C B A A B C…
            $buckets = array_fill(0, $groupsCount, []);

            foreach ($this->drawOrder($teams)->values() as $i => $team) {
                $lap = intdiv($i, $groupsCount);
                $at = $i % $groupsCount;
                $buckets[$lap % 2 === 0 ? $at : $groupsCount - 1 - $at][] = $team->id;
            }
        }

        foreach ($buckets as $index => $teamIds) {
            if (count($teamIds) < max(2, $qualifiers)) {
                $this->fail('bracket', self::groupName($index) . ' ficaria com ' . count($teamIds) . " time(s): cada grupo precisa de ao menos 2 e de pelo menos {$qualifiers} (os classificados). Diminua o número de grupos ou de classificados.");
            }
        }

        $groupStage = $category->stages()->create([
            'type' => TournamentStage::TYPE_GROUP,
            'order' => 1,
            'generation' => $generation,
            'generated_at' => now(),
        ]);

        $groups = [];
        $positions = [];

        foreach ($buckets as $index => $teamIds) {
            $group = $groupStage->groups()->create(['name' => self::groupName($index), 'order' => $index + 1]);
            $groups[$index] = $group;

            foreach ($teamIds as $teamId) {
                $group->entries()->create(['tournament_team_id' => $teamId]);
            }

            foreach ($this->roundRobin($teamIds) as $round => $pairs) {
                foreach ($pairs as [$home, $away]) {
                    $positions[$round] = ($positions[$round] ?? 0) + 1;
                    TournamentMatch::create([
                        'tournament_stage_id' => $groupStage->id,
                        'tournament_group_id' => $group->id,
                        'round' => $round,
                        'position' => $positions[$round],
                        'home_team_id' => $home,
                        'away_team_id' => $away,
                    ]);
                }
            }
        }

        $knockout = $category->stages()->create([
            'type' => TournamentStage::TYPE_KNOCKOUT,
            'order' => 2,
            'generation' => $generation,
            'generated_at' => now(),
        ]);

        $ranks = self::crossRanks($groupsCount, $qualifiers);
        $size = self::bracketSize(count($ranks));
        $sides = array_map(function (int $rank) use ($ranks, $groups) {
            $source = $ranks[$rank - 1] ?? null;

            return ['team' => null, 'group' => $source ? $groups[$source[0]]->id : null, 'position' => $source[1] ?? null];
        }, self::seedOrder($size));

        $this->buildKnockout($knockout, $sides, $category->third_place_match && count($ranks) >= 4);
    }

    /**
     * Cria os jogos do mata-mata a partir dos lados da 1ª rodada. Lado vazio
     * = passa direto: com o time conhecido, ele já avança; vindo de grupo,
     * avança quando o grupo fechar.
     *
     * @param  list<array{team: ?int, group: ?int, position: ?int}>  $sides
     */
    private function buildKnockout(TournamentStage $stage, array $sides, bool $thirdPlace): void
    {
        $size = count($sides);
        $rounds = (int) log($size, 2);
        $matches = [];

        for ($round = 1; $round <= $rounds; $round++) {
            $count = intdiv($size, 2 ** $round);

            for ($position = 1; $position <= $count; $position++) {
                $data = ['tournament_stage_id' => $stage->id, 'round' => $round, 'position' => $position];

                if ($round === 1) {
                    $home = $sides[2 * $position - 2];
                    $away = $sides[2 * $position - 1];
                    $data += [
                        'home_team_id' => $home['team'],
                        'away_team_id' => $away['team'],
                        'home_source_group_id' => $home['group'],
                        'home_source_position' => $home['position'],
                        'away_source_group_id' => $away['group'],
                        'away_source_position' => $away['position'],
                    ];
                }

                $matches[$round][$position] = TournamentMatch::create($data);
            }
        }

        for ($round = 1; $round < $rounds; $round++) {
            foreach ($matches[$round] as $position => $match) {
                $match->update([
                    'next_match_id' => $matches[$round + 1][(int) ceil($position / 2)]->id,
                    'next_slot' => $position % 2 === 1 ? TournamentMatch::SLOT_HOME : TournamentMatch::SLOT_AWAY,
                ]);
            }
        }

        if ($thirdPlace && $rounds >= 2) {
            TournamentMatch::create([
                'tournament_stage_id' => $stage->id,
                'round' => $rounds,
                'position' => 2,
                'third_place' => true,
            ]);
        }

        foreach ($matches[1] as $match) {
            $hasHome = $match->home_team_id || $match->home_source_group_id;
            $hasAway = $match->away_team_id || $match->away_source_group_id;

            if ($hasHome && $hasAway) {
                continue;
            }

            $match->update([
                'status' => TournamentMatch::STATUS_BYE,
                'winner_team_id' => $match->home_team_id ?? $match->away_team_id,
            ]);
            $this->advance($match);
        }
    }

    /**
     * Todos contra todos (método do círculo): uma rodada por volta, cada time
     * joga no máximo uma vez por rodada.
     *
     * @param  list<int>  $teamIds
     * @return array<int, list<array{0: int, 1: int}>>
     */
    private function roundRobin(array $teamIds): array
    {
        $list = array_values($teamIds);

        if (count($list) % 2 === 1) {
            $list[] = null;
        }

        $n = count($list);
        $rounds = [];

        for ($round = 1; $round < $n; $round++) {
            for ($i = 0; $i < $n / 2; $i++) {
                $home = $list[$i];
                $away = $list[$n - 1 - $i];

                if ($home !== null && $away !== null) {
                    $rounds[$round][] = $round % 2 === 0 ? [$away, $home] : [$home, $away];
                }
            }

            // Gira tudo menos o primeiro.
            $last = array_pop($list);
            array_splice($list, 1, 0, [$last]);
        }

        return $rounds;
    }

    /** Cabeças de chave na ordem; os demais embaralhados. */
    private function drawOrder(Collection $teams): Collection
    {
        return $teams->whereNotNull('seed')->sortBy('seed')->values()
            ->concat($teams->whereNull('seed')->shuffle()->values())
            ->values();
    }

    /** @return list<?int> */
    private function manualSlots(Collection $teams, int $size, array $input): array
    {
        $slots = [];

        for ($i = 0; $i < $size; $i++) {
            $value = $input[$i] ?? null;
            $slots[] = filled($value) ? (int) $value : null;
        }

        $placed = array_values(array_filter($slots));

        if (array_diff($placed, $teams->pluck('id')->all())) {
            $this->fail('slots', 'Há um time na chave que não está entre os times da categoria.');
        }
        if (count($placed) !== count(array_unique($placed))) {
            $this->fail('slots', 'Um time aparece em mais de um lugar da chave.');
        }

        $left = $teams->whereNotIn('id', $placed)->pluck('name');

        if ($left->isNotEmpty()) {
            $this->fail('slots', 'Faltou pôr na chave: ' . $left->implode(', ') . '.');
        }

        for ($i = 0; $i < $size; $i += 2) {
            if ($slots[$i] === null && $slots[$i + 1] === null) {
                $this->fail('slots', 'O jogo ' . ($i / 2 + 1) . ' da 1ª rodada ficou sem nenhum time.');
            }
        }

        return $slots;
    }

    /** @return array<int, list<int>> */
    private function manualGroups(Collection $teams, int $groupsCount, array $input): array
    {
        $buckets = array_fill(0, $groupsCount, []);
        $missing = [];

        foreach ($teams as $team) {
            $index = $input[$team->id] ?? null;

            if (! is_numeric($index) || (int) $index < 0 || (int) $index >= $groupsCount) {
                $missing[] = $team->name;

                continue;
            }

            $buckets[(int) $index][] = $team->id;
        }

        if ($missing) {
            $this->fail('groups', 'Escolha o grupo de: ' . implode(', ', $missing) . '.');
        }

        return $buckets;
    }

    /** Apaga fases, grupos e jogos da categoria. */
    private function wipe(TournamentCategory $category): void
    {
        $stageIds = $category->stages()->pluck('id');

        if ($stageIds->isEmpty()) {
            return;
        }

        TournamentMatch::whereIn('tournament_stage_id', $stageIds)->update(['next_match_id' => null]);
        TournamentMatch::whereIn('tournament_stage_id', $stageIds)->delete();
        TournamentGroupTeam::whereIn('tournament_group_id', TournamentGroup::whereIn('tournament_stage_id', $stageIds)->pluck('id'))->delete();
        TournamentGroup::whereIn('tournament_stage_id', $stageIds)->delete();
        TournamentStage::whereIn('id', $stageIds)->delete();
    }

    /** O jogo de 3º lugar da fase, se `$match` é uma semifinal. */
    private function thirdPlaceFor(TournamentMatch $match): ?TournamentMatch
    {
        if ($match->third_place) {
            return null;
        }

        $third = TournamentMatch::where('tournament_stage_id', $match->tournament_stage_id)->where('third_place', true)->first();

        return $third && $match->round === $third->round - 1 ? $third : null;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
