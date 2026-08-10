<?php

namespace Tests\Unit;

use App\Models\FreelancerService;
use App\Models\FreelancerServiceBatch;
use Tests\TestCase;

/**
 * Etapa do trâmite — a leitura que a tela de acompanhamento do Comercial faz de
 * cada contrato e de cada lote. É a única resposta que atravessa as quatro
 * etapas (assinaturas, gerência, diretoria, pagamento), e a ordem de
 * precedência entre elas é justamente o que pode sair errado. Sem banco.
 */
class FreelancerTrackingStageTest extends TestCase
{
    /**
     * Contrato assinado pelas duas partes, dentro de um lote no estado pedido.
     *
     * `forceFill` porque as colunas do trâmite (aprovações, recusas,
     * `amended_at`) não são preenchíveis em massa — quem as escreve é o serviço,
     * nunca um formulário.
     */
    private function service(array $attributes = [], ?string $batchStatus = null): FreelancerService
    {
        $service = (new FreelancerService())->forceFill(array_merge([
            'freelancer_signed_at' => '2026-08-01 10:00:00',
            'coordinator_signed_at' => '2026-08-01 11:00:00',
        ], $attributes));

        if ($batchStatus !== null) {
            $batch = new FreelancerServiceBatch(['status' => $batchStatus]);
            $batch->id = 7;
            $service->batch_id = 7;
            $service->setRelation('batch', $batch);
        }

        return $service;
    }

    public function test_contrato_sem_as_duas_assinaturas_esta_na_primeira_etapa(): void
    {
        $service = $this->service(['coordinator_signed_at' => null]);

        $this->assertSame('awaiting_signatures', $service->trackingStage());
        $this->assertSame('Aguardando assinaturas', $service->trackingStageLabel());
    }

    public function test_assinado_e_fora_de_lote_aguarda_o_lote(): void
    {
        $this->assertSame('awaiting_batch', $this->service()->trackingStage());
    }

    public function test_lote_em_rascunho_e_lote_enviado(): void
    {
        $this->assertSame('in_draft', $this->service([], FreelancerServiceBatch::STATUS_DRAFT)->trackingStage());
        $this->assertSame('awaiting_manager', $this->service([], FreelancerServiceBatch::STATUS_SENT)->trackingStage());
    }

    public function test_aprovado_pela_gerencia_aguarda_a_diretoria(): void
    {
        $service = $this->service(
            ['manager_approved_at' => '2026-08-02 09:00:00'],
            FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR,
        );

        $this->assertSame('awaiting_director', $service->trackingStage());
    }

    public function test_aprovado_pela_diretoria_aguarda_pagamento_e_depois_fica_pago(): void
    {
        $aprovado = $this->service([
            'manager_approved_at' => '2026-08-02 09:00:00',
            'director_approved_at' => '2026-08-03 09:00:00',
        ], FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED);

        $this->assertSame('awaiting_payment', $aprovado->trackingStage());

        $aprovado->paid = true;

        $this->assertSame('paid', $aprovado->trackingStage());
    }

    /**
     * O desfecho tem precedência sobre a etapa: um contrato pago não está
     * "aguardando pagamento", e um cancelado não está aguardando nada.
     */
    public function test_recusas_cancelamento_e_aditivo_vencem_a_etapa(): void
    {
        $recusadoGerencia = $this->service([
            'manager_rejected_at' => '2026-08-02 09:00:00',
        ], FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR);
        $this->assertSame('manager_rejected', $recusadoGerencia->trackingStage());

        $recusadoDiretoria = $this->service([
            'manager_approved_at' => '2026-08-02 09:00:00',
            'director_rejected_at' => '2026-08-03 09:00:00',
        ], FreelancerServiceBatch::STATUS_DIRECTOR_REJECTED);
        $this->assertSame('director_rejected', $recusadoDiretoria->trackingStage());

        $this->assertSame('cancelled', $this->service(['status_id' => FreelancerService::STATUS_CANCELLED])->trackingStage());
        $this->assertSame('amended', $this->service(['amended_at' => '2026-08-02 09:00:00'])->trackingStage());
    }

    /** Todo estado possível tem rótulo — senão a tela mostraria a chave crua. */
    public function test_todo_estado_tem_rotulo(): void
    {
        foreach (FreelancerService::TRACKING_STAGES as $stage => $label) {
            $this->assertNotEmpty($label, "Etapa {$stage} sem rótulo.");
        }
    }

    /* ---------------------------------------------------------------------
     | Lote
     |---------------------------------------------------------------------*/

    private function batch(string $status, int $payable = 0, int $paid = 0): FreelancerServiceBatch
    {
        $batch = new FreelancerServiceBatch(['status' => $status]);
        // Agregados que a consulta da tela carrega; sem eles o model iria ao
        // banco contar os contratos.
        $batch->payable_count = $payable;
        $batch->paid_count = $paid;
        $batch->services_count = $payable;

        return $batch;
    }

    public function test_etapa_do_lote_acompanha_o_status_ate_a_diretoria(): void
    {
        $this->assertSame('in_draft', $this->batch(FreelancerServiceBatch::STATUS_DRAFT)->trackingStage());
        $this->assertSame('awaiting_manager', $this->batch(FreelancerServiceBatch::STATUS_SENT)->trackingStage());
        $this->assertSame('awaiting_director', $this->batch(FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR)->trackingStage());
        $this->assertSame('director_rejected', $this->batch(FreelancerServiceBatch::STATUS_DIRECTOR_REJECTED)->trackingStage());
        $this->assertSame('closed', $this->batch(FreelancerServiceBatch::STATUS_CLOSED)->trackingStage());
    }

    /**
     * O rótulo de TODO status possível. A tela chama isto em cada lote listado,
     * e um estado sem rótulo é a diferença entre a página abrir e dar erro 500.
     */
    public function test_todo_status_de_lote_tem_rotulo(): void
    {
        $esperado = [
            FreelancerServiceBatch::STATUS_DRAFT => 'Em lote (rascunho)',
            FreelancerServiceBatch::STATUS_SENT => 'Aguardando gerência',
            FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR => 'Aguardando diretoria',
            FreelancerServiceBatch::STATUS_DIRECTOR_REJECTED => 'Recusado pela diretoria',
            FreelancerServiceBatch::STATUS_CLOSED => 'Encerrado sem aprovação',
            FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED => 'Sem contratos a pagar',
        ];

        foreach ($esperado as $status => $rotulo) {
            $this->assertSame($rotulo, $this->batch($status)->trackingStageLabel(), "Rótulo do status {$status}.");
        }

        $this->assertSame(
            'Aguardando pagamento',
            $this->batch(FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED, payable: 2)->trackingStageLabel()
        );
    }

    /**
     * Lote aprovado sem nada a pagar — a gerência recusou todos os contratos, ou
     * eles saíram do lote. Dizer "aguardando pagamento" mandaria o Comercial
     * cobrar do financeiro um pagamento que não existe.
     */
    public function test_lote_aprovado_e_vazio_nao_fica_aguardando_pagamento(): void
    {
        $lote = $this->batch(FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED, payable: 0);

        $this->assertSame('empty', $lote->trackingStage());

        $steps = collect($lote->trackingSteps())->pluck('state', 'key');

        $this->assertSame('done', $steps['director']);
        $this->assertSame('done', $steps['payment'], 'Sem nada a pagar, a fila do lote acabou.');
    }

    /** Depois da diretoria quem responde é o dinheiro, contado nos contratos. */
    public function test_apos_a_diretoria_a_etapa_do_lote_vem_do_pagamento(): void
    {
        $aprovado = FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED;

        $this->assertSame('awaiting_payment', $this->batch($aprovado, payable: 4, paid: 0)->trackingStage());
        $this->assertSame('partially_paid', $this->batch($aprovado, payable: 4, paid: 1)->trackingStage());
        $this->assertSame('paid', $this->batch($aprovado, payable: 4, paid: 4)->trackingStage());
        $this->assertSame('Parcialmente pago', $this->batch($aprovado, payable: 4, paid: 1)->trackingStageLabel());
    }

    public function test_linha_do_tempo_marca_o_cumprido_o_atual_e_o_pendente(): void
    {
        $steps = collect($this->batch(FreelancerServiceBatch::STATUS_SENT, payable: 2)->trackingSteps())
            ->pluck('state', 'key');

        $this->assertSame('done', $steps['signatures']);
        $this->assertSame('current', $steps['manager']);
        $this->assertSame('pending', $steps['director']);
        $this->assertSame('pending', $steps['payment']);
    }

    public function test_linha_do_tempo_marca_a_etapa_recusada(): void
    {
        $steps = collect($this->batch(FreelancerServiceBatch::STATUS_DIRECTOR_REJECTED, payable: 2)->trackingSteps())
            ->pluck('state', 'key');

        $this->assertSame('done', $steps['manager']);
        $this->assertSame('rejected', $steps['director']);
        $this->assertSame('pending', $steps['payment']);

        // A gerência recusou tudo: nem chegou à diretoria.
        $encerrado = collect($this->batch(FreelancerServiceBatch::STATUS_CLOSED, payable: 0)->trackingSteps())
            ->pluck('state', 'key');

        $this->assertSame('rejected', $encerrado['manager']);
        $this->assertSame('pending', $encerrado['director']);
    }

    public function test_linha_do_tempo_completa_quando_tudo_foi_pago(): void
    {
        $steps = collect($this->batch(FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED, payable: 3, paid: 3)->trackingSteps())
            ->pluck('state', 'key');

        $this->assertSame(['done', 'done', 'done', 'done'], array_values($steps->all()));
    }
}
