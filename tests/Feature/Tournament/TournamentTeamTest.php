<?php

namespace Tests\Feature\Tournament;

use App\Models\Member;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate as Template;
use App\Models\Tournament\TournamentRegistration as Registration;
use App\Models\Tournament\TournamentTeam as Team;
use App\Models\User;
use App\Services\Tournament\TournamentRegistrationService;
use App\Services\Tournament\TournamentTeamService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\CreatesTournamentSchema;
use Tests\TestCase;

/**
 * Torneios, F4: times montados pelo organizador e por sorteio, o time
 * automático da categoria individual, e a situação do time (completo = cheio
 * e todos pagos). O modo capitão está em TournamentCaptainApiTest.
 */
class TournamentTeamTest extends TestCase
{
    use CreatesTournamentSchema;
    use BuildsTournaments;

    private TournamentRegistrationService $registrations;
    private TournamentTeamService $teams;
    private Tournament $tournament;
    private TournamentCategory $duplas;
    private User $organizer;

    private const PEOPLE = [
        ['39053344705', 'Ana Souza', '1001'],
        ['86288366757', 'Bruno Lima', '1002'],
        ['52998224725', 'Carla Dias', '1003'],
        ['11144477735', 'Davi Rocha', '1004'],
        ['12345678909', 'Eva Nunes', '1005'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->createTournamentSchema();
        $this->fakeIntegrations();
        $this->registrations = $this->app->make(TournamentRegistrationService::class);
        $this->teams = $this->app->make(TournamentTeamService::class);

        $this->tournament = $this->tournament();
        $this->duplas = $this->category($this->tournament, ['team_formation' => TournamentCategory::FORMATION_DRAW]);
        $this->organizer = $this->makeUser('Pessoa do Esporte');
        $this->joinSector($this->organizer, 'TI');
    }

    /** Inscreve as pessoas (índices de PEOPLE) e paga as de `$paid`. */
    private function enroll(TournamentCategory $category, array $indexes, array $paid = []): array
    {
        $result = [];

        foreach ($indexes as $i) {
            [$cpf, $name, $title] = self::PEOPLE[$i];
            $member = Member::where('cpf', $cpf)->first() ?? $this->member($cpf, $name, $title);
            $registration = $this->registrations->register($this->tournament, $member, $this->registrations->profileFor($member), [$category->id])->first();

            if (in_array($i, $paid, true)) {
                $this->registrations->pay($member, [$registration->id], ['payment_method' => 'credit', 'paid_amount' => $category->price, 'payment_integration_id' => "TID-{$category->id}-{$i}"]);
            }

            $result[$i] = $registration->fresh();
        }

        return $result;
    }

    public function test_organizador_monta_e_o_time_completa_quando_todos_pagam(): void
    {
        $regs = $this->enroll($this->duplas, [0, 1], paid: [0]);

        $team = $this->teams->create($this->duplas, '', [$regs[0]->id, $regs[1]->id], $this->organizer);
        $this->assertSame('Time', $team->name);
        $this->assertSame(Team::STATUS_FORMING, $team->status);

        $bruno = Member::where('cpf', self::PEOPLE[1][0])->first();
        $this->registrations->pay($bruno, [$regs[1]->id], ['payment_method' => 'credit', 'paid_amount' => 80, 'payment_integration_id' => 'TID-B']);

        $this->assertSame(Team::STATUS_COMPLETE, $team->fresh()->status);
    }

    public function test_time_nao_passa_do_tamanho_e_inscricao_fica_em_um_time_so(): void
    {
        $regs = $this->enroll($this->duplas, [0, 1, 2]);

        try {
            $this->teams->create($this->duplas, 'Trio', [$regs[0]->id, $regs[1]->id, $regs[2]->id], $this->organizer);
            $this->fail('Time de 3 numa dupla deveria ser recusado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('registration_ids', $e->errors());
        }

        $team = $this->teams->create($this->duplas, 'Ana e Bruno', [$regs[0]->id, $regs[1]->id], $this->organizer);

        $this->expectException(ValidationException::class);
        $this->teams->create($this->duplas, 'Outro', [$regs[1]->id], $this->organizer);
    }

    public function test_sorteio_so_entre_pagos_sem_time_e_sobra_fica(): void
    {
        $regs = $this->enroll($this->duplas, [0, 1, 2, 3, 4], paid: [0, 1, 2, 3, 4]);
        $this->teams->create($this->duplas, 'Já formado', [$regs[0]->id, $regs[1]->id], $this->organizer);

        $result = $this->teams->draw($this->duplas, $this->organizer);

        // Sobram Carla, Davi e Eva: um time de dois, e uma pessoa sem time.
        $this->assertCount(1, $result['teams']);
        $this->assertSame(1, $result['leftover']);
        $this->assertSame(Team::ORIGIN_DRAW, $result['teams']->first()->origin);
        $this->assertSame(Team::STATUS_COMPLETE, $result['teams']->first()->status);
        $this->assertCount(1, $this->teams->unassigned($this->duplas));
        $this->assertSame(2, Team::where('name', 'Já formado')->first()->memberships()->count());
    }

    public function test_sorteio_ignora_quem_nao_pagou(): void
    {
        $this->enroll($this->duplas, [0, 1, 2], paid: [0]);

        $result = $this->teams->draw($this->duplas, $this->organizer);

        $this->assertCount(0, $result['teams']);
        $this->assertSame(1, $result['leftover']);
    }

    public function test_categoria_individual_vira_time_quando_paga(): void
    {
        $simples = $this->category($this->tournament, ['name' => 'Simples', 'team_size' => 1, 'price' => 50]);
        $regs = $this->enroll($simples, [0, 1], paid: [0]);

        $team = $regs[0]->teamMembership->team;
        $this->assertSame('Ana Souza', $team->name);
        $this->assertSame(Team::ORIGIN_INDIVIDUAL, $team->origin);
        $this->assertSame(Team::STATUS_COMPLETE, $team->status);
        $this->assertSame($regs[0]->id, $team->captain_registration_id);
        $this->assertNull($regs[1]->teamMembership);
    }

    public function test_cancelar_inscricao_tira_do_time_e_do_posto_de_capitao(): void
    {
        $regs = $this->enroll($this->duplas, [0, 1], paid: [0, 1]);
        $team = $this->teams->create($this->duplas, 'Dupla', [$regs[0]->id, $regs[1]->id], $this->organizer, captainRegistrationId: $regs[0]->id);
        $this->assertSame(Team::STATUS_COMPLETE, $team->fresh()->status);

        $this->registrations->cancel($regs[0]->fresh(), $this->organizer, 'Lesão');

        $team->refresh();
        $this->assertNull($team->captain_registration_id);
        $this->assertSame(Team::STATUS_FORMING, $team->status);
        $this->assertSame(1, $team->memberships()->count());

        $this->registrations->cancel($regs[1]->fresh(), $this->organizer, 'Lesão');
        $this->assertSame(Team::STATUS_CANCELLED, $team->fresh()->status);
    }

    public function test_tela_de_times_monta_sorteia_e_desfaz(): void
    {
        $regs = $this->enroll($this->duplas, [0, 1, 2, 3], paid: [0, 1, 2, 3]);
        $params = [$this->tournament, $this->duplas];

        $this->actingAs($this->organizer)->get(route('tournaments.teams.index', $params))
            ->assertOk()
            ->assertSee('Sem time (4)')
            ->assertSee('Sortear entre os pagos');

        $this->actingAs($this->organizer)->post(route('tournaments.teams.store', $params), ['registration_ids' => [$regs[0]->id, $regs[1]->id]])
            ->assertSessionHas('success');
        $team = Team::firstOrFail();
        $this->assertSame('Ana / Bruno', $team->name);

        $this->actingAs($this->organizer)->post(route('tournaments.teams.draw', $params))->assertSessionHas('success', fn ($m) => str_starts_with($m, '1 time'));

        $this->actingAs($this->organizer)->put(route('tournaments.teams.update', [...$params, $team]), ['name' => 'As Feras', 'seed' => 1, 'captain_registration_id' => $regs[1]->id])
            ->assertSessionHasNoErrors();
        $this->assertSame('As Feras', $team->fresh()->name);
        $this->assertSame($regs[1]->id, $team->fresh()->captain_registration_id);

        $this->actingAs($this->organizer)->delete(route('tournaments.teams.members.destroy', [...$params, $team, $regs[0]->id]))->assertSessionHas('success');
        $this->assertSame(Team::STATUS_FORMING, $team->fresh()->status);

        $this->actingAs($this->organizer)->delete(route('tournaments.teams.destroy', [...$params, $team]))->assertSessionHas('success');
        $this->assertSame(Team::STATUS_CANCELLED, $team->fresh()->status);
        $this->assertCount(2, $this->teams->unassigned($this->duplas));
    }

    public function test_sem_permissao_nao_abre_times(): void
    {
        $this->actingAs($this->makeUser('Sem Acesso'))->get(route('tournaments.teams.index', [$this->tournament, $this->duplas]))->assertForbidden();
    }
}
