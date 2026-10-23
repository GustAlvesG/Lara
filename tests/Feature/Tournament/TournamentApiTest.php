<?php

namespace Tests\Feature\Tournament;

use App\Exceptions\TournamentException;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate as Template;
use App\Models\Tournament\TournamentRegistration as Registration;
use App\Providers\Services\JwtService;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\CreatesTournamentSchema;
use Tests\TestCase;

/**
 * `/api/tournaments/*` pelo site de locação: consulta só com o `api_token`;
 * inscrição, pagamento, "minhas inscrições" e desistência com o JWT do sócio.
 * O sócio sai do token, nunca do corpo; inscrição alheia é 404; recusa vem
 * com `reason` estável.
 */
class TournamentApiTest extends TestCase
{
    use CreatesTournamentSchema;
    use BuildsTournaments;

    private string $token = 'token-de-teste';
    private const CPF_ANA = '39053344705';
    private const CPF_BRUNO = '86288366757';

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

        $this->published = $this->tournament();
        $this->duplas = $this->category($this->published, ['max_registrations' => 8]);
        $this->tournament(['title' => 'Rascunho', 'status' => Tournament::STATUS_DRAFT]);
    }

    private function appHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'];
    }

    private function headers(string $cpf = self::CPF_ANA): array
    {
        // `exp` no relógio real: o JWT não enxerga o Carbon::setTestNow.
        return $this->appHeaders() + ['Session' => (new JwtService())->generateToken(['username' => $cpf, 'exp' => time() + 3600])];
    }

    private function register(string $cpf = self::CPF_ANA)
    {
        return $this->withHeaders($this->headers($cpf))->postJson("/api/tournaments/{$this->published->id}/registrations", ['category_ids' => [$this->duplas->id]]);
    }

    public function test_lista_e_detalhe_mostram_so_o_publicado(): void
    {
        $this->withHeaders($this->appHeaders())->getJson('/api/tournaments')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Copa de Verão')
            ->assertJsonPath('0.registration_open', true)
            ->assertJsonPath('0.categories.0.price', 80)
            ->assertJsonPath('0.categories.0.vacancies_left', 8);

        $draft = Tournament::where('title', 'Rascunho')->first();
        $this->withHeaders($this->appHeaders())->getJson("/api/tournaments/{$draft->id}")
            ->assertNotFound()
            ->assertJsonPath('reason', TournamentException::TOURNAMENT_NOT_FOUND);
    }

    public function test_sem_api_token_nao_responde(): void
    {
        $this->getJson('/api/tournaments')->assertUnauthorized();
    }

    public function test_inscricao_pede_sessao_do_socio(): void
    {
        $url = "/api/tournaments/{$this->published->id}/registrations";
        $body = ['category_ids' => [$this->duplas->id]];

        // Sem o header `Session` o JwtMiddleware responde 400; com token inválido, 401.
        $this->withHeaders($this->appHeaders())->postJson($url, $body)->assertStatus(400);
        $this->withHeaders($this->appHeaders() + ['Session' => 'nao-e-um-jwt'])->postJson($url, $body)->assertUnauthorized();
        $this->assertSame(0, Registration::count());
    }

    public function test_elegibilidade_por_categoria(): void
    {
        $masculina = $this->category($this->published, ['name' => 'Masculina', 'gender' => Template::GENDER_MALE]);

        $this->withHeaders($this->headers())->getJson("/api/tournaments/{$this->published->id}/eligibility")
            ->assertOk()
            ->assertJsonPath('categories.0.category_id', $this->duplas->id)
            ->assertJsonPath('categories.0.eligible', true)
            ->assertJsonPath('categories.1.category_id', $masculina->id)
            ->assertJsonPath('categories.1.eligible', false)
            ->assertJsonPath('categories.1.reason', TournamentCategory::INELIGIBLE_GENDER);
    }

    public function test_inscreve_paga_e_aparece_em_minhas_inscricoes(): void
    {
        $id = $this->register()->assertCreated()
            ->assertJsonPath('total_due', 80)
            ->assertJsonPath('registrations.0.status', Registration::STATUS_PENDING_PAYMENT)
            ->json('registrations.0.id');

        $this->register()->assertStatus(409)->assertJsonPath('reason', TournamentException::ALREADY_REGISTERED);

        $pay = ['registration_ids' => [$id], 'payment_method' => 'credit', 'paid_amount' => 80, 'payment_integration_id' => 'TID-9'];
        $this->withHeaders($this->headers())->postJson('/api/tournaments/payment', $pay)
            ->assertCreated()
            ->assertJsonPath('registrations.0.status', Registration::STATUS_PAID);
        $this->withHeaders($this->headers())->postJson('/api/tournaments/payment', $pay)->assertOk();

        $this->withHeaders($this->headers())->getJson('/api/tournaments/my-registrations')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.tournament.title', 'Copa de Verão')
            ->assertJsonPath('0.payment.manual', false);

        $this->withHeaders($this->appHeaders())->getJson('/api/tournaments')->assertJsonPath('0.categories.0.vacancies_left', 7);
    }

    public function test_socio_vem_do_token_e_inscricao_alheia_e_404(): void
    {
        $id = $this->register()->json('registrations.0.id');

        // CPF no corpo é ignorado: quem se inscreve é o Bruno, dono do token.
        $this->withHeaders($this->headers(self::CPF_BRUNO))
            ->postJson("/api/tournaments/{$this->published->id}/registrations", ['category_ids' => [$this->duplas->id], 'cpf' => self::CPF_ANA])
            ->assertCreated()
            ->assertJsonPath('registrations.0.id', fn ($bruno) => $bruno !== $id);

        $this->withHeaders($this->headers(self::CPF_BRUNO))->deleteJson("/api/tournaments/registrations/{$id}")->assertNotFound();
        $this->withHeaders($this->headers(self::CPF_BRUNO))
            ->postJson('/api/tournaments/payment', ['registration_ids' => [$id], 'payment_method' => 'credit', 'paid_amount' => 80, 'payment_integration_id' => 'TID-X'])
            ->assertForbidden()
            ->assertJsonPath('reason', TournamentException::NOT_OWNER);
    }

    public function test_desiste_antes_de_pagar(): void
    {
        $id = $this->register()->json('registrations.0.id');

        $this->withHeaders($this->headers())->deleteJson("/api/tournaments/registrations/{$id}")
            ->assertOk()
            ->assertJsonPath('status', Registration::STATUS_WITHDRAWN);
    }

    public function test_inscricao_fechada_vem_com_reason(): void
    {
        $this->published->update(['registration_ends_at' => now()->subMinute()]);

        $this->register()->assertStatus(422)->assertJsonPath('reason', TournamentException::REGISTRATION_CLOSED);
    }
}
