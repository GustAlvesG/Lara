<?php

namespace Tests\Feature\Tournament;

use App\Exceptions\TournamentException;
use App\Mail\ContactMail;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate as Template;
use App\Models\Tournament\TournamentRegistration as Registration;
use App\Models\Tournament\TournamentTeam as Team;
use App\Models\Tournament\TournamentTeamInvite as Invite;
use App\Providers\Services\JwtService;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\CreatesTournamentSchema;
use Tests\TestCase;

/**
 * Torneios, F4, modo capitão pelo site: criar o time, achar a pessoa por
 * matrícula, convidar (e-mail), aceitar já inscrevendo, recusar, cancelar.
 */
class TournamentCaptainApiTest extends TestCase
{
    use CreatesTournamentSchema;
    use BuildsTournaments;

    private string $token = 'token-de-teste';
    private const CPF_ANA = '39053344705';
    private const CPF_BRUNO = '86288366757';
    private const CPF_CARLA = '52998224725';

    private Tournament $published;
    private TournamentCategory $duplas;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.api.token' => $this->token, 'services.jwt.secret' => 'segredo-de-teste']);

        $this->createTournamentSchema();
        $this->fakeIntegrations();

        $this->member(self::CPF_ANA, 'Ana Souza', '1001');
        $this->member(self::CPF_BRUNO, 'Bruno Lima', '1002', Template::GENDER_MALE);
        $this->member(self::CPF_CARLA, 'Carla Dias', '1003');

        $this->published = $this->tournament();
        $this->duplas = $this->category($this->published, ['team_formation' => TournamentCategory::FORMATION_CAPTAIN]);
    }

    private function as(string $cpf)
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            // `exp` no relógio real: o JWT não enxerga o Carbon::setTestNow.
            'Session' => (new JwtService())->generateToken(['username' => $cpf, 'exp' => time() + 3600]),
        ]);
    }

    private function anaCaptain(): int
    {
        $this->as(self::CPF_ANA)->postJson("/api/tournaments/{$this->published->id}/registrations", ['category_ids' => [$this->duplas->id]])->assertCreated();

        return $this->as(self::CPF_ANA)->postJson("/api/tournaments/categories/{$this->duplas->id}/teams", ['name' => 'As Feras'])
            ->assertCreated()
            ->assertJsonPath('is_captain', true)
            ->json('id');
    }

    private function refFor(string $title): string
    {
        return $this->as(self::CPF_ANA)->getJson("/api/tournaments/member-lookup?title={$title}")->assertOk()->json('0.ref');
    }

    public function test_criar_time_pede_inscricao_na_categoria_e_modo_capitao(): void
    {
        $this->as(self::CPF_ANA)->postJson("/api/tournaments/categories/{$this->duplas->id}/teams", ['name' => 'X'])
            ->assertStatus(422)->assertJsonPath('reason', TournamentException::NOT_REGISTERED);

        $sorteio = $this->category($this->published, ['name' => 'Sorteio', 'team_formation' => TournamentCategory::FORMATION_DRAW]);
        $this->as(self::CPF_ANA)->postJson("/api/tournaments/categories/{$sorteio->id}/teams", ['name' => 'X'])
            ->assertStatus(422)->assertJsonPath('reason', TournamentException::WRONG_FORMATION);
    }

    public function test_busca_por_matricula_nao_expoe_o_cpf(): void
    {
        $this->as(self::CPF_ANA)->getJson('/api/tournaments/member-lookup?title=1002')
            ->assertOk()
            ->assertJsonPath('0.name', 'Bruno Lima')
            ->assertJsonPath('0.cpf_masked', fn ($masked) => ! str_contains($masked, self::CPF_BRUNO))
            ->assertJsonMissing(['cpf' => self::CPF_BRUNO]);
    }

    public function test_convidado_nao_inscrito_aceita_e_entra_pendente(): void
    {
        $teamId = $this->anaCaptain();

        $this->as(self::CPF_ANA)->postJson("/api/tournaments/teams/{$teamId}/invites", ['ref' => $this->refFor('1003')])
            ->assertCreated()
            ->assertJsonPath('name', 'Carla Dias');
        Mail::assertQueued(ContactMail::class, fn ($mail) => $mail->type === 'tournament.invite' && $mail->hasTo('carla@mc.local'));

        // Dupla: Ana + convite pendente da Carla = cheio.
        $this->as(self::CPF_ANA)->postJson("/api/tournaments/teams/{$teamId}/invites", ['ref' => $this->refFor('1002')])
            ->assertStatus(409)->assertJsonPath('reason', TournamentException::TEAM_FULL);

        $inviteId = $this->as(self::CPF_CARLA)->getJson('/api/tournaments/my-invites')
            ->assertOk()
            ->assertJsonPath('0.team.name', 'As Feras')
            ->assertJsonPath('0.invited_by', 'Ana Souza')
            ->json('0.id');

        $this->as(self::CPF_CARLA)->postJson("/api/tournaments/invites/{$inviteId}/accept")
            ->assertOk()
            ->assertJsonPath('registration.status', Registration::STATUS_PENDING_PAYMENT)
            ->assertJsonPath('registration.team.name', 'As Feras')
            ->assertJsonPath('team.status', Team::STATUS_FORMING);

        $this->assertSame(Invite::STATUS_ACCEPTED, Invite::find($inviteId)->status);
        $this->assertSame(2, Team::find($teamId)->memberships()->count());
    }

    public function test_convite_respeita_a_categoria_e_so_o_capitao_convida(): void
    {
        $feminina = $this->category($this->published, ['name' => 'Feminina', 'gender' => Template::GENDER_FEMALE, 'team_formation' => TournamentCategory::FORMATION_CAPTAIN]);
        $this->as(self::CPF_ANA)->postJson("/api/tournaments/{$this->published->id}/registrations", ['category_ids' => [$feminina->id]])->assertCreated();
        $teamId = $this->as(self::CPF_ANA)->postJson("/api/tournaments/categories/{$feminina->id}/teams")->assertCreated()->json('id');

        $this->as(self::CPF_ANA)->postJson("/api/tournaments/teams/{$teamId}/invites", ['ref' => $this->refFor('1002')])
            ->assertStatus(422)->assertJsonPath('reason', TournamentException::INELIGIBLE);

        // A ref antes: as() troca o header de sessão de todas as requisições seguintes.
        $carla = $this->refFor('1003');
        $this->as(self::CPF_CARLA)->postJson("/api/tournaments/teams/{$teamId}/invites", ['ref' => $carla])
            ->assertForbidden()->assertJsonPath('reason', TournamentException::NOT_CAPTAIN);

        $this->as(self::CPF_ANA)->postJson("/api/tournaments/teams/{$teamId}/invites", ['ref' => 'adulterada'])
            ->assertStatus(422)->assertJsonPath('reason', TournamentException::MEMBER_NOT_FOUND);
    }

    public function test_recusar_cancelar_e_convite_de_outra_pessoa(): void
    {
        $teamId = $this->anaCaptain();
        $inviteId = $this->as(self::CPF_ANA)->postJson("/api/tournaments/teams/{$teamId}/invites", ['ref' => $this->refFor('1003')])->json('id');

        // Bruno não foi convidado: 404.
        $this->as(self::CPF_BRUNO)->postJson("/api/tournaments/invites/{$inviteId}/accept")->assertNotFound();

        $this->as(self::CPF_CARLA)->postJson("/api/tournaments/invites/{$inviteId}/decline")->assertOk();
        $this->assertSame(Invite::STATUS_DECLINED, Invite::find($inviteId)->status);

        // Vaga liberou: convida o Bruno e cancela.
        $second = $this->as(self::CPF_ANA)->postJson("/api/tournaments/teams/{$teamId}/invites", ['ref' => $this->refFor('1002')])->assertCreated()->json('id');
        $this->as(self::CPF_ANA)->deleteJson("/api/tournaments/invites/{$second}")->assertOk();
        $this->assertSame(Invite::STATUS_CANCELLED, Invite::find($second)->status);
    }

    public function test_depois_das_inscricoes_o_capitao_nao_mexe(): void
    {
        $teamId = $this->anaCaptain();
        $ref = $this->refFor('1003');
        $this->published->update(['registration_ends_at' => now()->subMinute()]);

        $this->as(self::CPF_ANA)->postJson("/api/tournaments/teams/{$teamId}/invites", ['ref' => $ref])
            ->assertStatus(422)->assertJsonPath('reason', TournamentException::REGISTRATION_CLOSED);
    }

    public function test_email_de_convite_renderiza(): void
    {
        $teamId = $this->anaCaptain();
        $inviteId = $this->as(self::CPF_ANA)->postJson("/api/tournaments/teams/{$teamId}/invites", ['ref' => $this->refFor('1003')])->json('id');

        $data = $this->app->make(\App\Services\Tournament\TournamentNotifier::class)->buildInvite(Invite::find($inviteId));
        $html = view('emails.tournament.status', ['data' => $data])->render();

        $this->assertStringContainsString('Ana Souza convidou você para o time As Feras', $html);
        $this->assertStringContainsString('Responda até', $html);
        $this->assertStringContainsString('Duplas Livre', $html);
    }

    public function test_meus_times_e_times_no_detalhe_do_torneio(): void
    {
        $this->anaCaptain();

        $this->as(self::CPF_ANA)->getJson('/api/tournaments/my-teams')
            ->assertOk()
            ->assertJsonPath('0.name', 'As Feras')
            ->assertJsonPath('0.members.0.name', 'Ana Souza')
            ->assertJsonPath('0.members.0.captain', true);

        $this->as(self::CPF_ANA)->getJson("/api/tournaments/{$this->published->id}")
            ->assertOk()
            ->assertJsonPath('categories.0.teams.0.name', 'As Feras')
            ->assertJsonPath('categories.0.teams.0.members.0', 'Ana Souza');
    }
}
