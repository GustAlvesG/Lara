<?php

namespace Tests\Feature;

use App\Jobs\SendFreelancerPixPayment;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use App\Models\PixPayment;
use App\Models\User;
use App\Services\FreelancerService as FreelancerServiceManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * O que o botão "Dar baixa" faz com o Pix ligado.
 *
 * A regra que estes testes protegem: o clique NÃO paga o contrato. Ele
 * enfileira um pedido, e a baixa (`paid`) só aparece quando o banco confirmar.
 * Marcar no clique deixaria a tela dizendo "pago" para um Pix que ainda pode
 * ser recusado.
 *
 * Nenhum teste aqui autentica ninguém nem passa pela rota HTTP: o model User
 * fixa a conexão `mysql`, e tocá-lo levaria a suíte para fora do SQLite. O
 * usuário existe só em memória, porque só o `id` dele é usado.
 */
class FreelancerPixOnPaymentTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();

        config([
            'sicoob.enabled' => true,
            'sicoob.environment' => 'sandbox',
            'sicoob.pix.max_amount' => 5000,
        ]);

        Bus::fake();
    }

    public function test_clique_enfileira_o_pix_sem_marcar_o_contrato_como_pago(): void
    {
        $service = $this->contratoAprovado(250.00);

        $resultado = app(FreelancerServiceManager::class)->requestPixForMany([$service->id], $this->usuario());

        $this->assertSame(1, $resultado['queued']);
        Bus::assertDispatched(SendFreelancerPixPayment::class);

        // O ponto do teste: o contrato continua PENDENTE. Quem dá a baixa é a
        // confirmação do banco, não o clique.
        $this->assertFalse($service->fresh()->isPaid());

        $pix = PixPayment::where('freelancer_service_id', $service->id)->firstOrFail();

        $this->assertSame(PixPayment::STATUS_PENDING, $pix->status);
        $this->assertSame('250.00', (string) $pix->amount);
        $this->assertSame('28133847044', $pix->pix_key);
        $this->assertSame(7, $pix->requested_by, 'A responsabilidade é de quem clicou.');
        // Dados congelados no clique: o cadastro pode mudar amanhã, a trilha não.
        $this->assertSame('sandbox', $pix->environment);
        $this->assertNotNull($pix->idempotency_key);
    }

    public function test_lote_enfileira_um_pix_por_contrato(): void
    {
        $a = $this->contratoAprovado(100.00);
        $b = $this->contratoAprovado(200.00);
        $c = $this->contratoAprovado(300.00);

        $resultado = app(FreelancerServiceManager::class)
            ->requestPixForMany([$a->id, $b->id, $c->id], $this->usuario());

        $this->assertSame(3, $resultado['queued']);
        // Um job por contrato: um pagamento que falha não derruba os outros.
        Bus::assertDispatchedTimes(SendFreelancerPixPayment::class, 3);
        $this->assertSame(3, PixPayment::count());
    }

    public function test_nao_envia_segundo_pix_para_contrato_que_ja_tem_um_em_andamento(): void
    {
        $service = $this->contratoAprovado(250.00);
        $manager = app(FreelancerServiceManager::class);

        $manager->requestPixForMany([$service->id], $this->usuario());

        // Segundo clique — tela aberta em duas abas, duplo clique, F5 no POST.
        $segundo = $manager->requestPixForMany([$service->id], $this->usuario());

        $this->assertSame(0, $segundo['queued'], 'Um contrato não pode gerar duas transferências.');
        $this->assertSame(1, PixPayment::count());
        Bus::assertDispatchedTimes(SendFreelancerPixPayment::class, 1);
        $this->assertStringContainsString('já existe um Pix', $segundo['problems'][0]);
    }

    public function test_pix_rejeitado_libera_nova_tentativa(): void
    {
        $service = $this->contratoAprovado(250.00);
        $manager = app(FreelancerServiceManager::class);

        $manager->requestPixForMany([$service->id], $this->usuario());

        // O banco recusou: a conta não foi debitada, então refazer é seguro.
        PixPayment::where('freelancer_service_id', $service->id)
            ->update(['status' => PixPayment::STATUS_REJECTED]);

        $segundo = $manager->requestPixForMany([$service->id], $this->usuario());

        $this->assertSame(1, $segundo['queued']);
        // As duas tentativas ficam no histórico — nada é sobrescrito.
        $this->assertSame(2, PixPayment::where('freelancer_service_id', $service->id)->count());
    }

    public function test_pix_com_desfecho_desconhecido_bloqueia_nova_tentativa(): void
    {
        $service = $this->contratoAprovado(250.00);
        $manager = app(FreelancerServiceManager::class);

        $manager->requestPixForMany([$service->id], $this->usuario());

        // O caso perigoso: pode ter saído. Refazer é o jeito de pagar duas vezes.
        PixPayment::where('freelancer_service_id', $service->id)
            ->update(['status' => PixPayment::STATUS_UNKNOWN]);

        $segundo = $manager->requestPixForMany([$service->id], $this->usuario());

        $this->assertSame(0, $segundo['queued']);
        $this->assertSame(1, PixPayment::where('freelancer_service_id', $service->id)->count());
    }

    public function test_freelancer_sem_chave_pix_nao_gera_envio(): void
    {
        $service = $this->contratoAprovado(250.00);
        // O model preenche a chave com o CPF ao salvar, então o cenário só
        // existe em cadastro antigo — mas existe.
        Freelancer::where('id', $service->freelancer_id)->update(['pix_key' => null]);

        $resultado = app(FreelancerServiceManager::class)
            ->requestPixForMany([$service->id], $this->usuario());

        $this->assertSame(0, $resultado['queued']);
        $this->assertStringContainsString('sem chave PIX', $resultado['problems'][0]);
        Bus::assertNothingDispatched();
    }

    public function test_valor_acima_do_teto_nao_gera_envio(): void
    {
        config(['sicoob.pix.max_amount' => 1000]);

        $service = $this->contratoAprovado(4500.00);

        $resultado = app(FreelancerServiceManager::class)
            ->requestPixForMany([$service->id], $this->usuario());

        $this->assertSame(0, $resultado['queued']);
        $this->assertStringContainsString('acima do teto', $resultado['problems'][0]);
        // A barreira é local: nada foi criado nem enfileirado.
        $this->assertSame(0, PixPayment::count());
        Bus::assertNothingDispatched();
    }

    public function test_contrato_sem_aprovacao_da_diretoria_e_ignorado(): void
    {
        $service = $this->contratoAprovado(250.00);
        $service->forceFill(['director_approved_at' => null])->save();

        $resultado = app(FreelancerServiceManager::class)
            ->requestPixForMany([$service->id], $this->usuario());

        $this->assertSame(0, $resultado['queued']);
        $this->assertSame(1, $resultado['skipped']);
        Bus::assertNothingDispatched();
    }

    public function test_baixa_manual_continua_funcionando_com_o_pix_desligado(): void
    {
        config(['sicoob.enabled' => false]);

        $service = $this->contratoAprovado(250.00);

        $pagos = app(FreelancerServiceManager::class)->markManyAsPaid([$service->id], $this->usuario());

        $this->assertSame(1, $pagos);
        $this->assertTrue($service->fresh()->isPaid());
        // Desligado, o fluxo é o de sempre: marcação, sem tocar no banco.
        $this->assertSame(0, PixPayment::count());
        Bus::assertNothingDispatched();
    }

    public function test_baixa_e_registrada_quando_o_pix_finaliza(): void
    {
        $service = $this->contratoAprovado(250.00);
        $manager = app(FreelancerServiceManager::class);

        $manager->requestPixForMany([$service->id], $this->usuario());

        $pix = PixPayment::where('freelancer_service_id', $service->id)->firstOrFail();
        $pix->forceFill([
            'status' => PixPayment::STATUS_FINALIZED,
            'finalized_at' => now(),
        ])->save();

        $manager->markAsPaidFromPix($pix);

        $service->refresh();

        $this->assertTrue($service->isPaid());
        $this->assertSame(7, $service->paid_by, 'A baixa fica no nome de quem autorizou, não do processo.');
        $this->assertNotNull($service->paid_at);
    }

    public function test_baixa_a_partir_do_pix_e_idempotente(): void
    {
        $service = $this->contratoAprovado(250.00);
        $manager = app(FreelancerServiceManager::class);

        $manager->requestPixForMany([$service->id], $this->usuario());

        $pix = PixPayment::where('freelancer_service_id', $service->id)->firstOrFail();
        $pix->forceFill(['status' => PixPayment::STATUS_FINALIZED, 'finalized_at' => now()])->save();

        $manager->markAsPaidFromPix($pix);
        $primeiraBaixa = $service->fresh()->paid_at;

        // O job e a reconciliação podem chegar aqui para o mesmo pagamento.
        $manager->markAsPaidFromPix($pix);

        $this->assertEquals($primeiraBaixa, $service->fresh()->paid_at, 'A segunda passagem não deve mexer na baixa.');
    }

    public function test_pix_nao_finalizado_nao_da_baixa(): void
    {
        $service = $this->contratoAprovado(250.00);
        $manager = app(FreelancerServiceManager::class);

        $manager->requestPixForMany([$service->id], $this->usuario());

        $pix = PixPayment::where('freelancer_service_id', $service->id)->firstOrFail();
        $pix->forceFill(['status' => PixPayment::STATUS_SENT])->save();

        $manager->markAsPaidFromPix($pix);

        // EM_PROCESSAMENTO ainda não é dinheiro na conta do freelancer.
        $this->assertFalse($service->fresh()->isPaid());
    }

    public function test_reconciliacao_libera_contrato_preso_por_tentativa_orfa(): void
    {
        config(['sicoob.enabled' => true]);

        $service = $this->contratoAprovado(250.00);
        $manager = app(FreelancerServiceManager::class);

        $manager->requestPixForMany([$service->id], $this->usuario());

        // Job perdido entre o commit e a fila (worker parado, queue:flush,
        // deploy no meio). A linha nunca saiu de `pending`.
        PixPayment::where('freelancer_service_id', $service->id)
            ->update(['created_at' => now()->subHour()]);

        $this->assertSame(0, $manager->requestPixForMany([$service->id], $this->usuario())['queued'],
            'Antes da limpeza, o contrato está preso.');

        $this->artisan('sicoob:pix-reconciliar')->assertSuccessful();

        // Sem endToEndId, a confirmação nunca pôde ter sido enviada: nada saiu,
        // e o contrato tem de voltar a aceitar pagamento.
        $orfa = PixPayment::where('freelancer_service_id', $service->id)->firstOrFail();
        $this->assertSame(PixPayment::STATUS_FAILED, $orfa->status);

        $this->assertSame(1, $manager->requestPixForMany([$service->id], $this->usuario())['queued']);
    }

    public function test_reconciliacao_nao_mexe_em_tentativa_pendente_recente(): void
    {
        config(['sicoob.enabled' => true]);

        $service = $this->contratoAprovado(250.00);

        app(FreelancerServiceManager::class)->requestPixForMany([$service->id], $this->usuario());

        // Fila só um pouco atrasada não pode ser confundida com job perdido —
        // liberar cedo demais permitiria um segundo envio do mesmo contrato.
        $this->artisan('sicoob:pix-reconciliar')->assertSuccessful();

        $this->assertSame(
            PixPayment::STATUS_PENDING,
            PixPayment::where('freelancer_service_id', $service->id)->firstOrFail()->status
        );
    }

    /* ---------------------------------------------------------------------
     | Apoio
     |---------------------------------------------------------------------*/

    /** Usuário só em memória: nunca é salvo, então a conexão mysql não é tocada. */
    private function usuario(): User
    {
        $user = new User();
        $user->id = 7;

        return $user;
    }

    /** Contrato assinado pelas duas partes e aprovado nos dois níveis. */
    private function contratoAprovado(float $valor): FreelancerService
    {
        $sufixo = str_pad((string) (Freelancer::count() + 1), 3, '0', STR_PAD_LEFT);

        $freelancer = Freelancer::create([
            'name' => 'Freelancer ' . $sufixo,
            'cpf' => '28133847044',
            'pix_key' => '28133847044',
        ]);

        $funcao = FunctionFreelancer::firstOrCreate(['name' => 'Garçom'], ['price' => 20]);

        $service = FreelancerService::create([
            'freelancer_id' => $freelancer->id,
            'function_freelancer_id' => $funcao->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-01',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'price' => $valor,
            'total_hours' => 4,
        ]);

        $service->forceFill([
            'freelancer_signed_at' => now()->subDays(3),
            'coordinator_signed_at' => now()->subDays(3),
            'manager_approved_at' => now()->subDays(2),
            'director_approved_at' => now()->subDay(),
        ])->save();

        return $service;
    }
}
