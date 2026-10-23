<?php

namespace App\Services\Tournament;

use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentRegistration;
use App\Models\Tournament\TournamentTeam;
use App\Models\Tournament\TournamentTeamInvite;
use App\Models\Tournament\TournamentTeamMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Times de uma categoria (F4). O time se forma depois da inscrição:
 *
 *   - organizador monta (qualquer categoria, a qualquer momento);
 *   - sorteio entre os inscritos **pagos e sem time**, quando o organizador
 *     manda; quem sobra fica sem time para ele resolver, e os times que já
 *     existem não mudam;
 *   - capitão convida (TournamentInviteService);
 *   - categoria individual: um time por inscrito, criado quando a inscrição
 *     fica paga (`afterPaid`).
 *
 * Situação: `complete` quando o time tem `team_size` pessoas e todas pagaram;
 * senão `forming`. Time sem ninguém é cancelado. Quem sai do time (desistência,
 * cancelamento, remoção) deixa de ser capitão.
 *
 * As recusas do painel saem como ValidationException (mensagem no campo); as
 * do site, como TournamentException.
 */
class TournamentTeamService
{
    /** @param  list<int>  $registrationIds */
    public function create(TournamentCategory $category, string $name, array $registrationIds, User $user, ?int $captainRegistrationId = null): TournamentTeam
    {
        return DB::transaction(function () use ($category, $name, $registrationIds, $user, $captainRegistrationId) {
            $this->lockCategory($category);
            $registrations = $this->freeRegistrations($category, $registrationIds);

            if ($registrations->count() > $category->team_size) {
                $this->fail('registration_ids', "Um time desta categoria tem no máximo {$category->team_size} pessoa(s).");
            }

            $team = TournamentTeam::create([
                'tournament_category_id' => $category->id,
                'name' => $this->uniqueName($category, $name),
                'origin' => TournamentTeam::ORIGIN_ORGANIZER,
            ]);

            foreach ($registrations as $registration) {
                $this->attach($team, $registration, userId: $user->id);
            }

            if ($captainRegistrationId !== null) {
                $this->setCaptain($team, $captainRegistrationId);
            }

            return $this->refresh($team);
        });
    }

    public function addMember(TournamentTeam $team, int $registrationId, User $user): TournamentTeam
    {
        return DB::transaction(function () use ($team, $registrationId, $user) {
            $category = $team->category;
            $this->lockCategory($category);
            $this->assertOpenTeam($team);

            $registration = $this->freeRegistrations($category, [$registrationId])->first();

            if ($this->occupied($team) >= $category->team_size) {
                $this->fail('registration_id', 'O time já está cheio (contando os convites pendentes).');
            }

            $this->attach($team, $registration, userId: $user->id);

            return $this->refresh($team);
        });
    }

    /** Tira a pessoa do time. A inscrição continua: só fica sem time. */
    public function removeMember(TournamentTeam $team, int $registrationId): TournamentTeam
    {
        return DB::transaction(function () use ($team, $registrationId) {
            $deleted = TournamentTeamMember::where('tournament_team_id', $team->id)
                ->where('tournament_registration_id', $registrationId)
                ->delete();

            if (! $deleted) {
                $this->fail('registration_id', 'Esta pessoa não está no time.');
            }

            return $this->afterLeaving($team, $registrationId);
        });
    }

    /** `null` tira o capitão. Só quem está no time pode ser capitão. */
    public function setCaptain(TournamentTeam $team, ?int $registrationId): TournamentTeam
    {
        if ($registrationId !== null && ! $team->memberships()->where('tournament_registration_id', $registrationId)->exists()) {
            $this->fail('captain_registration_id', 'O capitão precisa estar no time.');
        }

        $team->update(['captain_registration_id' => $registrationId]);

        return $team;
    }

    public function update(TournamentTeam $team, string $name, ?int $seed): TournamentTeam
    {
        $team->update([
            'name' => $name === $team->name ? $name : $this->uniqueName($team->category, $name, $team->id),
            'seed' => $seed,
        ]);

        return $team;
    }

    /** Desfaz o time: as inscrições ficam sem time e os convites pendentes caem. */
    public function cancel(TournamentTeam $team): void
    {
        DB::transaction(function () use ($team) {
            $team->memberships()->delete();
            $team->invites()->where('status', TournamentTeamInvite::STATUS_PENDING)
                ->update(['status' => TournamentTeamInvite::STATUS_CANCELLED, 'responded_at' => now()]);
            $team->update(['status' => TournamentTeam::STATUS_CANCELLED, 'captain_registration_id' => null]);
        });
    }

    /**
     * Sorteia times entre os inscritos pagos e sem time. Só fecha times
     * inteiros; quem sobra continua sem time.
     *
     * @return array{teams: Collection<int, TournamentTeam>, leftover: int}
     */
    public function draw(TournamentCategory $category, User $user): array
    {
        return DB::transaction(function () use ($category, $user) {
            $this->lockCategory($category);

            $pool = $this->unassigned($category)
                ->where('status', TournamentRegistration::STATUS_PAID)
                ->shuffle()
                ->values();

            $size = max(1, $category->team_size);
            $teams = collect();

            foreach ($pool->chunk($size) as $chunk) {
                if ($chunk->count() < $size) {
                    break;
                }

                $team = TournamentTeam::create([
                    'tournament_category_id' => $category->id,
                    'name' => $this->uniqueName($category, $this->nameFor($chunk)),
                    'origin' => TournamentTeam::ORIGIN_DRAW,
                ]);

                foreach ($chunk as $registration) {
                    $this->attach($team, $registration, userId: $user->id);
                }

                $teams->push($this->refresh($team));
            }

            return ['teams' => $teams, 'leftover' => $pool->count() % $size];
        });
    }

    /**
     * Inscrições que acabaram de ficar pagas: na categoria individual, viram
     * um time de uma pessoa; nas outras, o time delas pode ter completado.
     *
     * @param  iterable<int>  $registrationIds
     */
    public function afterPaid(iterable $registrationIds): void
    {
        $registrations = TournamentRegistration::with(['category', 'teamMembership.team'])
            ->whereIn('id', collect($registrationIds)->all())
            ->get();

        foreach ($registrations as $registration) {
            if ($registration->teamMembership) {
                $this->refresh($registration->teamMembership->team);
            } elseif ($registration->category->isIndividual()) {
                $team = TournamentTeam::create([
                    'tournament_category_id' => $registration->tournament_category_id,
                    'name' => $this->uniqueName($registration->category, $registration->name),
                    'origin' => TournamentTeam::ORIGIN_INDIVIDUAL,
                    'captain_registration_id' => $registration->id,
                ]);
                $this->attach($team, $registration);
                $this->refresh($team);
            }
        }
    }

    /** A inscrição deixou de valer (desistência, cancelamento): sai do time. */
    public function detach(TournamentRegistration $registration): void
    {
        $membership = TournamentTeamMember::with('team')->where('tournament_registration_id', $registration->id)->first();

        if (! $membership) {
            return;
        }

        $team = $membership->team;
        $membership->delete();
        $this->afterLeaving($team, $registration->id);
    }

    /** Recalcula a situação do time pelo número de pessoas e pelos pagamentos. */
    public function refresh(TournamentTeam $team): TournamentTeam
    {
        if ($team->status === TournamentTeam::STATUS_CANCELLED) {
            return $team;
        }

        $statuses = TournamentRegistration::whereIn('id', $team->memberships()->pluck('tournament_registration_id'))->pluck('status');
        $size = $team->category()->value('team_size');

        $complete = $statuses->count() === (int) $size
            && $statuses->every(fn ($s) => $s === TournamentRegistration::STATUS_PAID);

        $team->update(['status' => $complete ? TournamentTeam::STATUS_COMPLETE : TournamentTeam::STATUS_FORMING]);

        return $team;
    }

    /** Pessoas no time mais convites pendentes: as vagas já tomadas. */
    public function occupied(TournamentTeam $team): int
    {
        return $team->memberships()->count()
            + $team->invites()->where('status', TournamentTeamInvite::STATUS_PENDING)->count();
    }

    /**
     * Inscrições ativas da categoria que não estão em time nenhum.
     *
     * @return Collection<int, TournamentRegistration>
     */
    public function unassigned(TournamentCategory $category): Collection
    {
        return TournamentRegistration::active()
            ->where('tournament_category_id', $category->id)
            ->whereDoesntHave('teamMembership')
            ->orderBy('name')
            ->get();
    }

    /** Nome sugerido: os primeiros nomes, "Ana / Bruno". */
    public function nameFor(Collection $registrations): string
    {
        return $registrations->map(fn ($r) => strtok(trim($r->name), ' ') ?: $r->name)->implode(' / ');
    }

    public function attach(TournamentTeam $team, TournamentRegistration $registration, ?int $userId = null, ?int $memberId = null): TournamentTeamMember
    {
        return TournamentTeamMember::create([
            'tournament_team_id' => $team->id,
            'tournament_registration_id' => $registration->id,
            'added_by_user_id' => $userId,
            'added_by_member_id' => $memberId,
        ]);
    }

    /** Nome único na categoria: repete com " (2)", " (3)"… */
    public function uniqueName(TournamentCategory $category, string $name, ?int $ignoreId = null): string
    {
        $name = trim($name) !== '' ? trim($name) : 'Time';
        $taken = TournamentTeam::where('tournament_category_id', $category->id)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->pluck('name')
            ->all();

        $candidate = $name;
        for ($i = 2; in_array($candidate, $taken, true); $i++) {
            $candidate = "{$name} ({$i})";
        }

        return $candidate;
    }

    public function lockCategory(TournamentCategory $category): void
    {
        TournamentCategory::whereKey($category->id)->lockForUpdate()->first();
    }

    private function afterLeaving(TournamentTeam $team, int $registrationId): TournamentTeam
    {
        if ($team->captain_registration_id === $registrationId) {
            $team->update(['captain_registration_id' => null]);
        }

        if (! $team->memberships()->exists()) {
            $team->invites()->where('status', TournamentTeamInvite::STATUS_PENDING)
                ->update(['status' => TournamentTeamInvite::STATUS_CANCELLED, 'responded_at' => now()]);
            $team->update(['status' => TournamentTeam::STATUS_CANCELLED]);

            return $team;
        }

        return $this->refresh($team);
    }

    /**
     * As inscrições pedidas, ativas, da categoria e sem time.
     *
     * @return Collection<int, TournamentRegistration>
     */
    private function freeRegistrations(TournamentCategory $category, array $registrationIds): Collection
    {
        $registrationIds = array_values(array_unique(array_map('intval', $registrationIds)));

        $registrations = TournamentRegistration::active()
            ->where('tournament_category_id', $category->id)
            ->whereIn('id', $registrationIds)
            ->whereDoesntHave('teamMembership')
            ->get();

        if ($registrations->count() !== count($registrationIds)) {
            $this->fail('registration_ids', 'Escolha inscrições ativas desta categoria que ainda não estão em time.');
        }

        return $registrations;
    }

    private function assertOpenTeam(TournamentTeam $team): void
    {
        if ($team->status === TournamentTeam::STATUS_CANCELLED) {
            $this->fail('team', 'Este time foi desfeito.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
