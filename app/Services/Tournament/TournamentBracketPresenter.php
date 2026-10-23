<?php

namespace App\Services\Tournament;

use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentMatch;
use App\Models\Tournament\TournamentStage;

/**
 * O chaveamento de uma categoria em JSON, para o site: o público
 * (`GET …/bracket`, só depois de divulgado) e a equipe que lança resultado
 * (`$staff`: cada jogo diz se está travado pelo Lara e se pode ser lançado).
 */
class TournamentBracketPresenter
{
    public function __construct(private TournamentBracketService $brackets)
    {
    }

    public function category(TournamentCategory $category, bool $staff = false): array
    {
        $matchRelations = ['homeTeam.registrations', 'awayTeam.registrations', 'sets'];
        $stages = $category->stages()->with([
            'groups.entries.team',
            'groups.matches' => fn ($q) => $q->with($matchRelations),
            'matches' => fn ($q) => $q->whereNull('tournament_group_id')->with($matchRelations),
        ])->get();
        $groupNames = $stages->flatMap->groups->pluck('name', 'id')->all();

        return [
            'category_id' => $category->id,
            'category_name' => $category->name,
            'format' => $category->format,
            'sets_to_win' => $category->sets_to_win,
            'published_at' => $category->bracket_published_at?->toIso8601String(),
            'stages' => $stages->map(function (TournamentStage $stage) use ($groupNames, $staff) {
                if ($stage->type === TournamentStage::TYPE_GROUP) {
                    return [
                        'type' => $stage->type,
                        'groups' => $stage->groups->map(function ($group) use ($groupNames, $staff) {
                            $closed = $group->entries->whereNotNull('final_position')->isNotEmpty();

                            return [
                                'id' => $group->id,
                                'name' => $group->name,
                                'closed' => $closed,
                                'standings' => $this->brackets->standings($group)->map(fn (array $row, int $i) => [
                                    'position' => $row['entry']->final_position ?? $i + 1,
                                    'final' => $row['entry']->final_position !== null,
                                    'team' => ['id' => $row['team']->id, 'name' => $row['team']->name],
                                    'played' => $row['played'],
                                    'wins' => $row['wins'],
                                    'losses' => $row['losses'],
                                    'sets_won' => $row['sets_won'],
                                    'sets_lost' => $row['sets_lost'],
                                    'games_won' => $row['games_won'],
                                    'games_lost' => $row['games_lost'],
                                ])->values(),
                                'matches' => $group->matches->map(fn ($m) => $this->match($m, $groupNames, null, $staff, $closed))->values(),
                            ];
                        })->values(),
                    ];
                }

                $rounds = (int) $stage->matches->where('third_place', false)->max('round');

                return [
                    'type' => $stage->type,
                    'rounds' => $rounds,
                    'matches' => $stage->matches->map(fn ($m) => $this->match(
                        $m,
                        $groupNames,
                        TournamentBracketService::roundLabel($m->round, $rounds, (bool) $m->third_place),
                        $staff,
                    ))->values(),
                ];
            })->values(),
        ];
    }

    public function match(TournamentMatch $match, array $groupNames, ?string $roundLabel = null, bool $staff = false, bool $groupClosed = false): array
    {
        $data = [
            'id' => $match->id,
            'round' => $match->round,
            'round_label' => $roundLabel ?? "{$match->round}ª rodada",
            'position' => $match->position,
            'third_place' => (bool) $match->third_place,
            'status' => $match->status,
            'status_label' => TournamentMatch::STATUS_LABELS[$match->status] ?? $match->status,
            'home' => $this->side($match, 'home', $groupNames),
            'away' => $this->side($match, 'away', $groupNames),
            'winner_team_id' => $match->winner_team_id,
            'sets' => $match->sets->map(fn ($set) => [
                'number' => $set->set_number,
                'home_games' => $set->home_games,
                'away_games' => $set->away_games,
                'home_tiebreak' => $set->home_tiebreak,
                'away_tiebreak' => $set->away_tiebreak,
            ])->values(),
            'next_match_id' => $match->next_match_id,
            'scheduled_at' => $match->scheduled_at?->toIso8601String(),
            'location' => $match->location_note,
        ];

        if ($staff) {
            $data['locked'] = (bool) $match->locked_by_lara;
            $data['result_origin'] = $match->result_origin;
            $data['editable'] = ! $match->locked_by_lara
                && ! $groupClosed
                && $match->status !== TournamentMatch::STATUS_BYE
                && $match->home_team_id !== null
                && $match->away_team_id !== null;
        }

        return $data;
    }

    /** Lado de um jogo: o time, ou de onde ele vem ("1º do Grupo A"), ou nada. */
    private function side(TournamentMatch $match, string $side, array $groupNames): array
    {
        $team = $side === 'home' ? $match->homeTeam : $match->awayTeam;
        $sourceGroup = $match->{"{$side}_source_group_id"};

        return [
            'team' => $team ? [
                'id' => $team->id,
                'name' => $team->name,
                'members' => $team->registrations->pluck('name')->values(),
            ] : null,
            'source' => ! $team && $sourceGroup ? [
                'group' => $groupNames[$sourceGroup] ?? null,
                'position' => $match->{"{$side}_source_position"},
                'label' => $match->{"{$side}_source_position"} . 'º do ' . ($groupNames[$sourceGroup] ?? 'grupo'),
            ] : null,
            'sets' => $match->{"{$side}_sets"},
        ];
    }
}
