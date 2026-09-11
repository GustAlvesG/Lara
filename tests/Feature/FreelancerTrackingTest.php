<?php

namespace Tests\Feature;

use App\Exceptions\FreelancerServiceLockedException;
use App\Http\Controllers\Freelancer\TrackingController;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FreelancerServiceBatch;
use App\Models\FunctionFreelancer;
use App\Models\User;
use App\Services\FreelancerBatchService;
use App\Services\FreelancerService as FreelancerServiceManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * As consultas da tela de acompanhamento do Comercial.
 *
 * O que estes testes protegem é a promessa da tela: **nenhum contrato do fluxo
 * fica sem etapa, e nenhum aparece em duas**. Os contadores do topo vêm de
 * escopos SQL e o rótulo de cada linha vem de `trackingStage()`, em PHP — duas
 * implementações da mesma regra, que é exatamente o par que costuma divergir em
 * silêncio. Aqui elas são conferidas uma contra a outra.
 *
 * Como nos demais testes do módulo, nada autentica ninguém nem passa pela rota:
 * o model User fixa a conexão `mysql` e tocá-lo levaria a suíte para fora do
 * SQLite.
 */
class FreelancerTrackingTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ---------------------------------------------------------------------
     | Cada etapa pega o que é dela
     |---------------------------------------------------------------------*/

    public function test_contrato_sem_assinatura_conta_na_etapa_de_assinaturas(): void
    {
        $semAssinatura = $this->contrato(100.00, null, [
            'freelancer_signed_at' => null,
            'coordinator_signed_at' => null,
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $assinado = $this->contrato(100.00, null, [
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $ids = FreelancerService::awaitingSignature()->pluck('id');

        $this->assertTrue($ids->contains($semAssinatura->id));
        $this->assertFalse($ids->contains($assinado->id));
        $this->assertSame('awaiting_signatures', $semAssinatura->trackingStage());
    }

    public function test_lote_enviado_conta_na_gerencia_e_lote_com_aval_conta_na_diretoria(): void
    {
        $naGerencia = $this->contrato(100.00, $this->lote(FreelancerServiceBatch::STATUS_SENT), [
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $naDiretoria = $this->contrato(100.00, $this->lote(FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR), [
            'director_approved_at' => null,
        ]);

        $this->assertSame(
            [$naGerencia->id],
            FreelancerService::awaitingManagerReview()->pluck('id')->all()
        );
        $this->assertSame(
            [$naDiretoria->id],
            FreelancerService::awaitingDirectorReview()->pluck('id')->all()
        );
    }

    public function test_aprovado_conta_em_pagamento_e_sai_de_la_quando_pago(): void
    {
        $lote = $this->loteAprovado();
        $aPagar = $this->contrato(100.00, $lote);
        $pago = $this->contrato(200.00, $lote, ['paid' => true, 'paid_at' => now()]);

        $this->assertSame([$aPagar->id], FreelancerService::awaitingPayment()->pluck('id')->all());
        $this->assertSame([$pago->id], FreelancerService::paidServices()->pluck('id')->all());
    }

    /** Cancelado e aditivado saem de todas as filas: o fluxo não conta com eles. */
    public function test_cancelado_e_aditivado_ficam_fora_das_filas(): void
    {
        $cancelado = $this->contrato(100.00, null, [
            'status_id' => FreelancerService::STATUS_CANCELLED,
            'freelancer_signed_at' => null,
            'coordinator_signed_at' => null,
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $aditivado = $this->contrato(100.00, $this->loteAprovado(), ['amended_at' => now()]);

        $this->assertFalse(FreelancerService::awaitingSignature()->pluck('id')->contains($cancelado->id));
        $this->assertFalse(FreelancerService::awaitingPayment()->pluck('id')->contains($aditivado->id));
        $this->assertSame('cancelled', $cancelado->trackingStage());
        $this->assertSame('amended', $aditivado->trackingStage());
    }

    /**
     * A conferência que dá sentido à tela: somando as filas, cada contrato vivo
     * aparece uma vez e só uma. Se um escopo passar a se sobrepor a outro, os
     * cartões do topo somam mais do que existe — e ninguém percebe olhando.
     */
    public function test_as_filas_nao_se_sobrepoem_e_cobrem_o_fluxo(): void
    {
        $esperado = [
            $this->contrato(100.00, null, [
                'freelancer_signed_at' => null, 'coordinator_signed_at' => null,
                'manager_approved_at' => null, 'director_approved_at' => null,
            ])->id => 'awaiting_signatures',

            $this->contrato(100.00, null, [
                'manager_approved_at' => null, 'director_approved_at' => null,
            ])->id => 'awaiting_batch',

            $this->contrato(100.00, $this->lote(FreelancerServiceBatch::STATUS_SENT), [
                'manager_approved_at' => null, 'director_approved_at' => null,
            ])->id => 'awaiting_manager',

            $this->contrato(100.00, $this->lote(FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR), [
                'director_approved_at' => null,
            ])->id => 'awaiting_director',

            $this->contrato(100.00, $this->loteAprovado())->id => 'awaiting_payment',

            $this->contrato(100.00, $this->loteAprovado(), [
                'paid' => true, 'paid_at' => now(),
            ])->id => 'paid',
        ];

        $filas = [
            'awaiting_signatures' => FreelancerService::awaitingSignature(),
            'awaiting_batch' => FreelancerService::availableForBatch(),
            'awaiting_manager' => FreelancerService::awaitingManagerReview(),
            'awaiting_director' => FreelancerService::awaitingDirectorReview(),
            'awaiting_payment' => FreelancerService::awaitingPayment(),
            'paid' => FreelancerService::paidServices(),
        ];

        $encontrados = [];

        foreach ($filas as $stage => $query) {
            foreach ($query->pluck('id') as $id) {
                $this->assertArrayNotHasKey(
                    $id,
                    $encontrados,
                    "Contrato #{$id} apareceu em duas filas: " . ($encontrados[$id] ?? '?') . " e {$stage}."
                );

                $encontrados[$id] = $stage;
            }
        }

        $this->assertSame($esperado, $encontrados,
            'A fila em que o contrato é contado tem de ser a mesma etapa que a linha dele exibe.');

        // E o rótulo de cada linha concorda com a fila que o contou.
        foreach ($esperado as $id => $stage) {
            $service = FreelancerService::with('batch')->find($id);

            $this->assertSame($stage, $service->trackingStage(), "Etapa divergente no contrato #{$id}.");
        }
    }

    /* ---------------------------------------------------------------------
     | A consulta dos lotes
     |---------------------------------------------------------------------*/

    /** Os agregados que a tela usa para dizer "3 de 8 pagos" e o total do lote. */
    public function test_consulta_de_lotes_traz_contagens_e_total(): void
    {
        $lote = $this->loteAprovado();
        $this->contrato(100.00, $lote, ['paid' => true, 'paid_at' => now()]);
        $this->contrato(200.00, $lote);
        // Recusado pela gerência: continua no lote, mas não é pagável.
        $this->contrato(50.00, $lote, [
            'manager_approved_at' => null,
            'manager_rejected_at' => now(),
            'director_approved_at' => null,
        ]);

        $carregado = FreelancerServiceBatch::withCount([
            'services',
            'services as payable_count' => fn($q) => $q->awaitingFinance(),
            'services as paid_count' => fn($q) => $q->awaitingFinance()->where('paid', true),
        ])->withSum('services', 'price')->find($lote->id);

        $this->assertSame(3, $carregado->services_count);
        $this->assertSame(2, $carregado->payable_count);
        $this->assertSame(1, $carregado->paid_count);
        $this->assertEqualsWithDelta(350.00, (float) $carregado->services_sum_price, 0.001);
        $this->assertSame('partially_paid', $carregado->trackingStage());
        $this->assertTrue($carregado->isPartiallyPaid());
    }

    public function test_linha_do_tempo_do_lote_quitado_esta_toda_cumprida(): void
    {
        $lote = $this->loteAprovado();
        $this->contrato(100.00, $lote, ['paid' => true, 'paid_at' => now()]);

        $carregado = FreelancerServiceBatch::withCount([
            'services',
            'services as payable_count' => fn($q) => $q->awaitingFinance(),
            'services as paid_count' => fn($q) => $q->awaitingFinance()->where('paid', true),
        ])->find($lote->id);

        $estados = collect($carregado->trackingSteps())->pluck('state')->unique();

        $this->assertSame(['done'], $estados->all());
        $this->assertSame('paid', $carregado->trackingStage());
    }

    /* ---------------------------------------------------------------------
     | Liberação às 08h do dia seguinte
     |---------------------------------------------------------------------*/

    /**
     * O contrato do turno de hoje não aparece para a coordenação nem para o
     * lote: até as 08h de amanhã ele ainda pode receber aditivo.
     */
    public function test_turno_de_hoje_nao_entra_na_fila_do_coordenador_nem_no_lote(): void
    {
        Carbon::setTestNow('2026-08-06 22:00:00');

        // Redação 1: a fila do tablet só tem os contratos que o coordenador
        // assina com o traço. A da redação 2 (validação pela web) segue a mesma
        // regra de horário — ver FreelancerCoordinatorValidationTest.
        $hoje = $this->contrato(100.00, null, [
            'start_date' => '2026-08-06',
            'contract_version' => 1,
            'coordinator_signed_at' => null,
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $ontem = $this->contrato(100.00, null, [
            'start_date' => '2026-08-05',
            'contract_version' => 1,
            'coordinator_signed_at' => null,
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $filaDoCoordenador = FreelancerService::awaitingCoordinator()->pluck('id');

        $this->assertFalse($filaDoCoordenador->contains($hoje->id), 'O turno de hoje ainda pode mudar.');
        $this->assertTrue($filaDoCoordenador->contains($ontem->id));

        // Já assinado pelas duas partes (contrato antigo, anterior à regra):
        // mesmo assim o lote não o aceita antes da hora.
        $assinadoHoje = $this->contrato(100.00, null, [
            'start_date' => '2026-08-06',
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $this->assertFalse(FreelancerService::availableForBatch()->pluck('id')->contains($assinadoHoje->id));
        $this->assertFalse($assinadoHoje->canBeBatched());
    }

    /** Passadas as 08h, o mesmo contrato aparece nas duas listas. */
    public function test_depois_das_oito_da_manha_seguinte_o_contrato_aparece(): void
    {
        Carbon::setTestNow('2026-08-06 22:00:00');

        $contrato = $this->contrato(100.00, null, [
            'start_date' => '2026-08-06',
            'contract_version' => 1,
            'coordinator_signed_at' => null,
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $this->assertFalse(FreelancerService::awaitingCoordinator()->pluck('id')->contains($contrato->id));

        Carbon::setTestNow('2026-08-07 08:00:00');

        $this->assertTrue(FreelancerService::awaitingCoordinator()->pluck('id')->contains($contrato->id));
    }

    /** O lote ignora em silêncio o que ainda não maturou — a lista nem o oferece. */
    public function test_montagem_de_lote_recusa_contrato_nao_liberado(): void
    {
        Carbon::setTestNow('2026-08-06 22:00:00');

        $hoje = $this->contrato(100.00, null, [
            'start_date' => '2026-08-06',
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $lote = $this->lote(FreelancerServiceBatch::STATUS_DRAFT);

        $incluidos = app(FreelancerBatchService::class)->addServices($lote, [$hoje->id]);

        $this->assertSame(0, $incluidos);
        $this->assertNull($hoje->refresh()->batch_id);
    }

    /** A assinatura do coordenador é recusada com o motivo certo, não com "já assinado". */
    public function test_assinatura_do_coordenador_e_recusada_antes_da_hora(): void
    {
        Carbon::setTestNow('2026-08-06 22:00:00');

        $hoje = $this->contrato(100.00, null, [
            'start_date' => '2026-08-06',
            'coordinator_signed_at' => null,
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $this->expectException(FreelancerServiceLockedException::class);
        $this->expectExceptionMessage('liberado para a coordenação em 07/08/2026 às 08:00');

        // Usuário só de fachada: a recusa acontece antes de ele ser usado, e
        // tocar a tabela `users` levaria a suíte para a conexão mysql.
        app(FreelancerServiceManager::class)->signAsCoordinator($hoje, (new User())->forceFill(['id' => 7]));
    }

    /**
     * A fila de "aguardando o fim do dia" não pode se sobrepor à de
     * "aguardando assinaturas": as duas contam contratos sem as duas
     * assinaturas, e a diferença entre elas é só o relógio.
     */
    public function test_aguardando_liberacao_e_aguardando_assinaturas_nao_se_misturam(): void
    {
        Carbon::setTestNow('2026-08-06 22:00:00');

        $esperandoRelogio = $this->contrato(100.00, null, [
            'start_date' => '2026-08-06',
            'coordinator_signed_at' => null,
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $esperandoGente = $this->contrato(100.00, null, [
            'start_date' => '2026-08-04',
            'coordinator_signed_at' => null,
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $semNinguem = $this->contrato(100.00, null, [
            'start_date' => '2026-08-06',
            'freelancer_signed_at' => null,
            'coordinator_signed_at' => null,
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);

        $this->assertSame(
            [$esperandoRelogio->id],
            FreelancerService::awaitingRelease()->pluck('id')->all()
        );

        $this->assertEqualsCanonicalizing(
            [$esperandoGente->id, $semNinguem->id],
            FreelancerService::awaitingSignature()->pluck('id')->all(),
            'Quem só espera o relógio sai da fila de assinaturas — e quem nem assinou continua nela.'
        );

        $this->assertSame('awaiting_release', $esperandoRelogio->trackingStage());
        $this->assertSame('awaiting_signatures', $esperandoGente->trackingStage());
        $this->assertSame('awaiting_signatures', $semNinguem->trackingStage());
    }

    /* ---------------------------------------------------------------------
     | A tela inteira
     |---------------------------------------------------------------------*/

    /**
     * Monta o que a tela recebe, chamando o controller de verdade — é assim que
     * as consultas com agregados e eager loads são exercitadas. A view não é
     * renderizada de propósito: o layout autentica um usuário, e o model User
     * fixa a conexão `mysql`.
     */
    public function test_a_tela_monta_resumo_lotes_e_soltos(): void
    {
        $lote = $this->lote(FreelancerServiceBatch::STATUS_SENT, createdBy: null);
        $noLote = $this->contrato(100.00, $lote, [
            'manager_approved_at' => null,
            'director_approved_at' => null,
        ]);
        $semAssinatura = $this->contrato(80.00, null, [
            'freelancer_signed_at' => null, 'coordinator_signed_at' => null,
            'manager_approved_at' => null, 'director_approved_at' => null,
        ]);

        $view = app(TrackingController::class)->index(
            Request::create(route('freelancer-services.tracking'))
        );

        $data = $view->getData();

        $this->assertSame('90', $data['period']);
        $this->assertCount(7, $data['summary'], 'Um cartão por etapa da fila.');
        $this->assertSame(1, collect($data['summary'])->firstWhere('stage', 'awaiting_manager')['count']);

        $this->assertCount(1, $data['batches']);
        $carregado = $data['batches']->first();
        $this->assertSame(1, $carregado->services_count);
        $this->assertSame('awaiting_manager', $carregado->trackingStage());
        // O lote foi injetado em cada contrato: a etapa da linha sai daqui, e
        // sem a relação carregada cada linha iria ao banco buscá-lo de novo.
        $this->assertTrue($carregado->services->first()->relationLoaded('batch'));
        $this->assertSame('awaiting_manager', $carregado->services->first()->trackingStage());
        $this->assertSame($noLote->id, $carregado->services->first()->id);

        $this->assertSame([$semAssinatura->id], $data['loose']->pluck('id')->all(),
            'Fora de lote é o começo da fila: o que espera assinatura e o que espera entrar num lote.');
    }

    /* ---------------------------------------------------------------------
     | Auxiliares
     |---------------------------------------------------------------------*/

    /**
     * `$createdBy` nulo quando o teste passa pelo controller: ele carrega as
     * relações de User, e o model User fixa a conexão `mysql` — com a chave
     * vazia o Eloquent nem chega a consultar a tabela.
     */
    private function lote(string $status, ?int $createdBy = 7): FreelancerServiceBatch
    {
        return FreelancerServiceBatch::create(['status' => $status, 'created_by' => $createdBy]);
    }

    private function loteAprovado(): FreelancerServiceBatch
    {
        $lote = $this->lote(FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED);

        $lote->forceFill([
            'reviewed_by' => 7,
            'reviewed_at' => now()->subDay(),
            'director_decision' => FreelancerServiceBatch::DECISION_APPROVED,
            'director_decided_at' => now()->subHours(2),
        ])->save();

        return $lote;
    }

    /** Contrato assinado pelas duas partes e aprovado nos dois níveis, por padrão. */
    private function contrato(float $valor, ?FreelancerServiceBatch $lote, array $sobrescreve = []): FreelancerService
    {
        $freelancer = Freelancer::create([
            'name' => 'Freelancer ' . Str()->random(6),
            'cpf' => (string) random_int(10000000000, 99999999999),
        ]);

        $funcao = FunctionFreelancer::create(['name' => 'Garçom', 'price' => 10.00]);

        $service = FreelancerService::create([
            'freelancer_id' => $freelancer->id,
            'function_freelancer_id' => $funcao->id,
            'location' => 'Salão',
            'start_date' => now()->subDays(4)->toDateString(),
            'start_time' => '18:00',
            'end_date' => now()->subDays(4)->toDateString(),
            'end_time' => '22:00',
            'price' => $valor,
            'total_hours' => 4,
        ]);

        $service->forceFill(array_merge([
            'batch_id' => $lote?->id,
            'freelancer_signed_at' => now()->subDays(3),
            'coordinator_signed_at' => now()->subDays(3),
            'manager_approved_at' => now()->subDays(2),
            'director_approved_at' => now()->subDay(),
        ], $sobrescreve))->save();

        return $service->refresh();
    }
}
