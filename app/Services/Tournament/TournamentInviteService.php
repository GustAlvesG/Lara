<?php

namespace App\Services\Tournament;

use App\Exceptions\TournamentException;
use App\Models\Member;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentRegistration;
use App\Models\Tournament\TournamentTeam;
use App\Models\Tournament\TournamentTeamInvite;
use App\Services\MultiClubes\MemberProfile;
use App\Services\MultiClubes\MemberProfileLookup;
use App\Support\Cpf;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Times montados pelo capitão, pelo site (categorias `team_formation` =
 * `captain`):
 *
 *   1. quem está inscrito na categoria cria o time e vira capitão;
 *   2. busca a pessoa por matrícula (o título traz a família: escolhe uma)
 *      ou por CPF, e convida — ela recebe e-mail;
 *   3. a pessoa aceita no site: se já está inscrita na categoria (e sem
 *      time), entra; se não, a inscrição é criada ali mesmo (com a conferência
 *      de sexo e idade) e ela entra no time **já**, aguardando pagamento.
 *
 * Tudo isso só dentro do período de inscrição; depois, a escalação é do
 * organizador. O convite conta como vaga ocupada no time até ser respondido.
 *
 * A busca devolve `ref` (CPF e matrícula cifrados) em vez do CPF: o site
 * mostra o nome e o CPF mascarado, e convida pela `ref`.
 */
class TournamentInviteService
{
    public function __construct(
        private TournamentTeamService $teams,
        private TournamentRegistrationService $registrations,
        private MemberProfileLookup $profiles,
        private TournamentNotifier $notifier,
    ) {
    }

    public function createTeam(TournamentCategory $category, Member $member, string $name): TournamentTeam
    {
        $this->assertCaptainFormation($category);
        $this->assertOpen($category);

        return DB::transaction(function () use ($category, $member, $name) {
            $this->teams->lockCategory($category);

            $registration = TournamentRegistration::active()
                ->where('tournament_category_id', $category->id)
                ->where('member_id', $member->id)
                ->first();

            if (! $registration) {
                throw new TournamentException(TournamentException::NOT_REGISTERED, 'Inscreva-se na categoria antes de criar um time.');
            }
            if ($registration->teamMembership()->exists()) {
                throw new TournamentException(TournamentException::ALREADY_IN_TEAM, 'Você já está num time desta categoria.', 409);
            }

            $team = TournamentTeam::create([
                'tournament_category_id' => $category->id,
                'name' => $this->teams->uniqueName($category, $name !== '' ? $name : $this->teams->nameFor(collect([$registration]))),
                'origin' => TournamentTeam::ORIGIN_CAPTAIN,
                'captain_registration_id' => $registration->id,
            ]);
            $this->teams->attach($team, $registration, memberId: $member->id);

            return $this->teams->refresh($team);
        });
    }

    /**
     * Pessoas para convidar, por matrícula (a família toda) ou por CPF.
     *
     * @return list<array{ref: string, name: string, cpf_masked: string, titular: bool}>
     */
    public function lookup(?string $title, ?string $cpf): array
    {
        $profiles = filled($title) ? $this->profiles->byTitle((string) $title) : $this->profiles->byCpf((string) $cpf);

        return array_map(fn (MemberProfile $p) => [
            'ref' => Crypt::encryptString($p->cpf . '|' . $p->title),
            'name' => $p->name,
            'cpf_masked' => Cpf::mask($p->cpf),
            'titular' => $p->titular,
        ], $profiles);
    }

    public function invite(TournamentTeam $team, Member $captain, string $ref): TournamentTeamInvite
    {
        $category = $team->category;
        $this->assertCaptain($team, $captain);
        $this->assertOpen($category);

        $profile = $this->profileFromRef($ref);

        $reason = $category->ineligibilityReason($profile->gender, $profile->birthDate, $category->tournament->starts_on);
        if ($reason !== null) {
            throw new TournamentException(
                TournamentException::INELIGIBLE,
                "{$profile->name} não se encaixa na categoria: " . TournamentRegistrationService::ineligibilityText($reason) . '.',
                422,
                ['detail' => $reason],
            );
        }

        $invite = DB::transaction(function () use ($team, $category, $captain, $profile) {
            $this->teams->lockCategory($category);

            if ($this->teams->occupied($team) >= $category->team_size) {
                throw new TournamentException(TournamentException::TEAM_FULL, 'O time já está completo (contando os convites pendentes).', 409);
            }

            $inTeam = TournamentRegistration::active()
                ->where('tournament_category_id', $category->id)
                ->where('cpf', $profile->cpf)
                ->whereHas('teamMembership')
                ->exists();
            $invited = $team->invites()->where('status', TournamentTeamInvite::STATUS_PENDING)->where('cpf', $profile->cpf)->exists();

            if ($inTeam || $invited) {
                throw new TournamentException(TournamentException::ALREADY_IN_TEAM, "{$profile->name} já está num time desta categoria ou já foi convidado.", 409);
            }

            return TournamentTeamInvite::create([
                'tournament_team_id' => $team->id,
                'invited_by_member_id' => $captain->id,
                'cpf' => $profile->cpf,
                'title' => $profile->title,
                'name' => $profile->name,
                'email' => $profile->email,
            ]);
        });

        $this->notifier->notifyInvite($invite);

        return $invite;
    }

    /** A pessoa convidada aceita: entra no time, inscrita (pendente, se ainda não estava). */
    public function accept(TournamentTeamInvite $invite, Member $member): TournamentRegistration
    {
        $this->assertInvitee($invite, $member);
        $team = $invite->team;
        $category = $team->category;
        $this->assertOpen($category);

        if ($team->status === TournamentTeam::STATUS_CANCELLED) {
            throw new TournamentException(TournamentException::TEAM_NOT_FOUND, 'Este time foi desfeito.', 404);
        }

        $registration = TournamentRegistration::active()
            ->where('tournament_category_id', $category->id)
            ->where('member_id', $member->id)
            ->first();

        if ($registration?->teamMembership()->exists()) {
            throw new TournamentException(TournamentException::ALREADY_IN_TEAM, 'Você já está num time desta categoria.', 409);
        }

        // Inscrição nova fora da transação do time: register() tem a sua (e
        // manda o e-mail de inscrição recebida).
        $registration ??= $this->registrations->register(
            $category->tournament,
            $member,
            $this->registrations->profileFor($member),
            [$category->id],
        )->first();

        DB::transaction(function () use ($invite, $team, $registration, $member) {
            $this->teams->lockCategory($team->category);
            $this->teams->attach($team, $registration, memberId: $member->id);
            $invite->update([
                'status' => TournamentTeamInvite::STATUS_ACCEPTED,
                'tournament_registration_id' => $registration->id,
                'responded_at' => now(),
            ]);
            $this->teams->refresh($team);
        });

        return $registration->fresh();
    }

    public function decline(TournamentTeamInvite $invite, Member $member): void
    {
        $this->assertInvitee($invite, $member);
        $invite->update(['status' => TournamentTeamInvite::STATUS_DECLINED, 'responded_at' => now()]);
    }

    /** O capitão retira um convite ainda não respondido. */
    public function cancel(TournamentTeamInvite $invite, Member $captain): void
    {
        $this->assertCaptain($invite->team, $captain);

        if ($invite->status !== TournamentTeamInvite::STATUS_PENDING) {
            throw new TournamentException(TournamentException::INVITE_NOT_FOUND, 'Este convite já foi respondido.', 409);
        }

        $invite->update(['status' => TournamentTeamInvite::STATUS_CANCELLED, 'responded_at' => now()]);
    }

    private function profileFromRef(string $ref): MemberProfile
    {
        try {
            [$cpf, $title] = explode('|', Crypt::decryptString($ref), 2) + [null, null];
        } catch (DecryptException) {
            $cpf = $title = null;
        }

        $profile = $cpf && $title ? $this->profiles->find($cpf, $title) : null;

        if (! $profile) {
            throw new TournamentException(TournamentException::MEMBER_NOT_FOUND, 'Pessoa não encontrada entre os sócios ativos. Busque de novo.');
        }

        return $profile;
    }

    private function assertCaptainFormation(TournamentCategory $category): void
    {
        if ($category->team_formation !== TournamentCategory::FORMATION_CAPTAIN || $category->isIndividual()) {
            throw new TournamentException(TournamentException::WRONG_FORMATION, 'Nesta categoria os times não são montados pelo capitão.');
        }
    }

    private function assertOpen(TournamentCategory $category): void
    {
        if (! $category->tournament->registrationOpen()) {
            throw new TournamentException(TournamentException::REGISTRATION_CLOSED, 'O período de inscrição acabou: a escalação agora é com a organização.');
        }
    }

    private function assertCaptain(TournamentTeam $team, Member $member): void
    {
        $captainMemberId = $team->captain?->member_id;

        if ($team->status === TournamentTeam::STATUS_CANCELLED || $captainMemberId === null) {
            throw new TournamentException(TournamentException::TEAM_NOT_FOUND, 'Time não encontrado.', 404);
        }
        if ($captainMemberId !== $member->id) {
            throw new TournamentException(TournamentException::NOT_CAPTAIN, 'Só o capitão do time pode fazer isso.', 403);
        }
    }

    private function assertInvitee(TournamentTeamInvite $invite, Member $member): void
    {
        if ($invite->status !== TournamentTeamInvite::STATUS_PENDING || $invite->cpf !== Cpf::digits($member->cpf)) {
            throw new TournamentException(TournamentException::INVITE_NOT_FOUND, 'Convite não encontrado.', 404);
        }
    }
}
