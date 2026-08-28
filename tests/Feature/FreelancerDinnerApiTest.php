<?php

namespace Tests\Feature;

use App\Exceptions\FreelancerServiceLockedException;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use App\Models\User;
use App\Services\FreelancerService as FreelancerServiceManager;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * O contrato que a COZINHA consome: quem confirmou o jantar de um dia.
 *
 * A lista traz só os "sim". Quem disse "não" e quem sequer foi perguntado não
 * viram prato, e uma lista que misturasse os três obrigaria a cozinha a filtrar
 * o que já é resposta.
 *
 * Sem `RefreshDatabase` e sem tocar no model `User` (que fixa a conexão
 * `mysql`) pelo mesmo motivo dos demais testes do módulo — ver
 * `CreatesFreelancerPixSchema`.
 */
class FreelancerDinnerApiTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    private string $token = 'token-de-teste';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();

        config(['services.api.token' => $this->token]);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'];
    }

    private function operador(): User
    {
        $user = new User();
        $user->id = 7;

        return $user;
    }

    /** Contrato já assinado pelo freelancer — é depois disso que se pergunta. */
    private function contratoAssinado(
        string $nome,
        string $startTime = '14:00:00',
        string $endTime = '20:00:00',
        string $startDate = '2026-09-03',
        ?string $endDate = null,
    ): FreelancerService {
        $freelancer = Freelancer::create([
            'name' => $nome,
            'cpf' => str_pad((string) (Freelancer::count() + 1), 11, '0', STR_PAD_LEFT),
        ]);

        $funcao = FunctionFreelancer::firstOrCreate(['name' => 'Garçom'], ['price' => 20]);

        $service = FreelancerService::create([
            'freelancer_id' => $freelancer->id,
            'function_freelancer_id' => $funcao->id,
            'location' => 'Salão',
            'start_date' => $startDate,
            'end_date' => $endDate ?? $startDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'price' => 480,
            'total_hours' => 6,
        ]);

        $service->forceFill(['freelancer_signed_at' => now()])->save();

        return $service;
    }

    private function responder(FreelancerService $service, bool $quer): FreelancerService
    {
        return app(FreelancerServiceManager::class)
            ->recordDinnerAnswer($service, $quer, $this->operador());
    }

    /**
     * A rota é ABERTA, por decisão de quem opera: o painel da cozinha consulta
     * sem carregar o token. O teste existe para que tirá-la do ar por engano —
     * pondo-a de volta atrás do `api_token` — apareça aqui, e não na cozinha.
     */
    public function test_a_lista_responde_sem_token(): void
    {
        $this->responder($this->contratoAssinado('Ana Souza'), true);

        $this->getJson('/api/freelancer/dinners?date=2026-09-03')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    /** Lista aberta não carrega documento: o CPF fica fora do payload. */
    public function test_o_cpf_nao_vai_na_lista(): void
    {
        $this->responder($this->contratoAssinado('Ana Souza'), true);

        $this->withHeaders($this->headers())->getJson('/api/freelancer/dinners?date=2026-09-03')
            ->assertOk()
            ->assertJsonPath('dinners.0.name', 'Ana Souza')
            ->assertJsonMissingPath('dinners.0.cpf');
    }

    public function test_traz_apenas_quem_confirmou_o_jantar_do_dia(): void
    {
        $sim = $this->contratoAssinado('Ana Souza');
        $nao = $this->contratoAssinado('Bruno Lima');
        // Assinado, com direito, mas ainda sem resposta: não vira prato.
        $this->contratoAssinado('Carla Dias');

        $this->responder($sim, true);
        $this->responder($nao, false);

        $response = $this->withHeaders($this->headers())->getJson('/api/freelancer/dinners?date=2026-09-03');

        $response->assertOk()
            ->assertJsonPath('date', '2026-09-03')
            ->assertJsonPath('window', '17:30 às 18:30')
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'dinners')
            ->assertJsonPath('dinners.0.name', 'Ana Souza')
            ->assertJsonPath('dinners.0.service_id', $sim->id)
            ->assertJsonPath('dinners.0.function', 'Garçom')
            ->assertJsonPath('dinners.0.start_time', '14:00')
            ->assertJsonPath('dinners.0.end_time', '20:00')
            ->assertJsonPath('dinners.0.duration', '6h');
    }

    public function test_o_jantar_de_outro_dia_nao_entra_na_lista(): void
    {
        $this->responder($this->contratoAssinado('Ana Souza'), true);

        $this->withHeaders($this->headers())->getJson('/api/freelancer/dinners?date=2026-09-04')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'dinners');
    }

    /**
     * A consulta é pela data do JANTAR, e não pela de início do contrato: um
     * turno que entra 22:00 do dia 03 e sai 20:00 do dia 04 janta no dia 04.
     */
    public function test_turno_que_vira_a_meia_noite_aparece_no_dia_em_que_janta(): void
    {
        $service = $this->contratoAssinado('Ana Souza', '22:00:00', '20:00:00', '2026-09-03', '2026-09-04');

        $this->responder($service, true);

        $this->withHeaders($this->headers())->getJson('/api/freelancer/dinners?date=2026-09-03')
            ->assertOk()->assertJsonPath('total', 0);

        $this->withHeaders($this->headers())->getJson('/api/freelancer/dinners?date=2026-09-04')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('dinners.0.shift_date', '2026-09-03')
            ->assertJsonPath('dinners.0.crosses_midnight', true);
    }

    public function test_contrato_cancelado_sai_da_lista(): void
    {
        $service = $this->contratoAssinado('Ana Souza');
        $this->responder($service, true);

        $service->forceFill(['status_id' => FreelancerService::STATUS_CANCELLED])->save();

        $this->withHeaders($this->headers())->getJson('/api/freelancer/dinners?date=2026-09-03')
            ->assertOk()->assertJsonPath('total', 0);
    }

    /**
     * O turno mudou de horário depois da assinatura: quem responde por ele é o
     * aditivo, e a resposta dada sobre o horário antigo não conta um prato.
     */
    public function test_contrato_aditivado_sai_da_lista(): void
    {
        $service = $this->contratoAssinado('Ana Souza');
        $this->responder($service, true);

        $service->forceFill(['amendment_service_id' => 999, 'amended_at' => now()])->save();

        $this->withHeaders($this->headers())->getJson('/api/freelancer/dinners?date=2026-09-03')
            ->assertOk()->assertJsonPath('total', 0);
    }

    public function test_data_invalida_e_recusada(): void
    {
        $this->withHeaders($this->headers())->getJson('/api/freelancer/dinners?date=ontem')
            ->assertStatus(422);
    }

    /* ---------------------------------------------------------------------
     | Gravação da resposta
     |---------------------------------------------------------------------*/

    public function test_a_resposta_guarda_a_data_do_jantar_e_quem_conduziu(): void
    {
        $service = $this->responder($this->contratoAssinado('Ana Souza'), true);

        $this->assertTrue($service->fresh()->wantsDinner());
        $this->assertSame('2026-09-03', $service->fresh()->dinner_date->toDateString());
        $this->assertSame(7, $service->fresh()->dinner_answered_by);
        $this->assertNotNull($service->fresh()->dinner_answered_at);
    }

    public function test_a_pergunta_so_existe_depois_da_assinatura(): void
    {
        $service = $this->contratoAssinado('Ana Souza');
        $service->forceFill(['freelancer_signed_at' => null])->save();

        $this->expectException(FreelancerServiceLockedException::class);

        $this->responder($service, true);
    }

    public function test_turno_sem_direito_nao_grava_resposta(): void
    {
        // 16:00 → 20:00: alcança a janela do jantar, mas são 4h.
        $service = $this->contratoAssinado('Ana Souza', '16:00:00', '20:00:00');

        $this->expectException(FreelancerServiceLockedException::class);

        $this->responder($service, true);
    }

    /**
     * Turno anterior à estreia do jantar (31/08/2026): a cozinha não servia
     * refeição naquele dia, e o servidor recusa a resposta ainda que alguém
     * force a chamada.
     */
    public function test_turno_anterior_a_estreia_nao_grava_resposta(): void
    {
        $service = $this->contratoAssinado('Ana Souza', '14:00:00', '20:00:00', '2026-08-30');

        $this->expectException(FreelancerServiceLockedException::class);

        $this->responder($service, true);
    }

    /**
     * A resposta é registrada uma vez só: a cozinha dimensiona os pratos por
     * ela, e uma resposta que vai e volta durante a tarde é um prato a mais ou a
     * menos sem que ninguém saiba.
     */
    public function test_a_resposta_nao_se_repete(): void
    {
        $service = $this->contratoAssinado('Ana Souza');
        $this->responder($service, true);

        $this->expectException(FreelancerServiceLockedException::class);

        $this->responder($service->fresh(), false);
    }
}
