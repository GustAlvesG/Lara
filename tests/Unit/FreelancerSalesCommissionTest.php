<?php

namespace Tests\Unit;

use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Comissão de venda — o segundo tipo de aditivo, exclusivo das funções marcadas
 * (hoje, Garçom) e assinado ao final do expediente.
 *
 * Sem banco: a conta é pura, e a elegibilidade se apoia em atributos do próprio
 * serviço. As regras que precisam consultar a cadeia do turno
 * (`shiftHasCommission`) ficam de fora daqui de propósito.
 */
class FreelancerSalesCommissionTest extends TestCase
{
    /* ---------------------------------------------------------------------
     | A conta
     |---------------------------------------------------------------------*/

    /**
     * Blocos fechados: R$ 50 a cada R$ 1.000 integralmente vendidos, o resto
     * desprezado. É o que diferencia este critério do percentual — proporcional,
     * R$ 50 por R$ 1.000 seriam os mesmos 5%.
     */
    public function test_criterio_por_blocos_arredonda_para_baixo(): void
    {
        $this->assertSame(0.0, FreelancerService::commissionFor('block', 999));
        $this->assertSame(50.0, FreelancerService::commissionFor('block', 1000));
        $this->assertSame(50.0, FreelancerService::commissionFor('block', 1999.99));
        $this->assertSame(600.0, FreelancerService::commissionFor('block', 12400));
    }

    public function test_criterio_percentual(): void
    {
        $this->assertSame(49.95, FreelancerService::commissionFor('percent', 999));
        $this->assertSame(50.0, FreelancerService::commissionFor('percent', 1000));
        $this->assertSame(95.0, FreelancerService::commissionFor('percent', 1900));
        $this->assertSame(620.0, FreelancerService::commissionFor('percent', 12400));
    }

    /** Os dois critérios só coincidem no múltiplo exato de R$ 1.000. */
    public function test_os_dois_criterios_divergem_fora_do_bloco_cheio(): void
    {
        $this->assertSame(
            FreelancerService::commissionFor('block', 3000),
            FreelancerService::commissionFor('percent', 3000)
        );

        $this->assertNotSame(
            FreelancerService::commissionFor('block', 3500),
            FreelancerService::commissionFor('percent', 3500)
        );
    }

    public function test_venda_zerada_ou_negativa_nao_gera_comissao(): void
    {
        $this->assertSame(0.0, FreelancerService::commissionFor('block', 0));
        $this->assertSame(0.0, FreelancerService::commissionFor('percent', 0));
        $this->assertSame(0.0, FreelancerService::commissionFor('percent', -100));
    }

    public function test_criterio_desconhecido_nao_paga(): void
    {
        $this->assertSame(0.0, FreelancerService::commissionFor('qualquer', 5000));
    }

    public function test_conta_demonstrada(): void
    {
        $this->assertSame(
            '12 blocos de R$ 1.000,00 × R$ 50,00',
            FreelancerService::commissionExplanationFor('block', 12400)
        );
        $this->assertSame('1 bloco de R$ 1.000,00 × R$ 50,00', FreelancerService::commissionExplanationFor('block', 1500));
        $this->assertSame('5% de R$ 12.400,00', FreelancerService::commissionExplanationFor('percent', 12400));
    }

    /* ---------------------------------------------------------------------
     | Quem pode receber
     |---------------------------------------------------------------------*/

    /** Contrato de garçom, assinado pelo freelancer — o caso que aceita comissão. */
    private function contract(bool $eligibleFunction = true, array $attributes = []): FreelancerService
    {
        $service = new FreelancerService(array_merge([
            'start_date' => '2026-08-01',
            'start_time' => '14:00:00',
            'end_date' => '2026-08-01',
            'end_time' => '19:00:00',
        ], $attributes));

        // array_key_exists, e não ??: passar `null` de propósito significa
        // "não assinado", e o ?? trataria isso como "não informado".
        $service->freelancer_signed_at = array_key_exists('freelancer_signed_at', $attributes)
            ? $attributes['freelancer_signed_at']
            : Carbon::parse('2026-08-01 13:40');
        $service->setRelation('functionFreelancer', new FunctionFreelancer([
            'name' => $eligibleFunction ? 'Garçom' : 'Segurança',
            'allows_sales_commission' => $eligibleFunction,
        ]));

        return $service;
    }

    public function test_funcao_nao_habilitada_nao_recebe_comissao(): void
    {
        $service = $this->contract(eligibleFunction: false);

        $this->assertFalse($service->canReceiveCommission());
        $this->assertStringContainsString('não recebe comissão', $service->commissionBlockReason());
    }

    public function test_contrato_sem_assinatura_do_freelancer_nao_recebe_comissao(): void
    {
        $service = $this->contract(attributes: ['freelancer_signed_at' => null]);

        $this->assertFalse($service->canReceiveCommission());
        $this->assertStringContainsString('ainda não assinou', $service->commissionBlockReason());
    }

    public function test_contrato_cancelado_nao_recebe_comissao(): void
    {
        $service = $this->contract();
        $service->status_id = FreelancerService::STATUS_CANCELLED;

        $this->assertFalse($service->canReceiveCommission());
    }

    /** Substituído por aditivo de horário: a comissão se faz sobre o vigente. */
    public function test_contrato_aditivado_manda_a_comissao_para_o_aditivo(): void
    {
        $service = $this->contract();
        $service->amended_at = Carbon::parse('2026-08-01 18:00');

        $this->assertFalse($service->canReceiveCommission());
        $this->assertStringContainsString('aditivo vigente', $service->commissionBlockReason());
    }

    public function test_comissao_nao_recebe_comissao_nem_aditivo_de_horario(): void
    {
        $commission = $this->contract();
        $commission->parent_service_id = 10;
        $commission->amendment_type = FreelancerService::AMENDMENT_COMMISSION;

        $this->assertFalse($commission->canReceiveCommission());
        $this->assertFalse($commission->canBeAmended());
        $this->assertStringContainsString('não recebe aditivo de horário', $commission->amendmentBlockReason());
    }

    /* ---------------------------------------------------------------------
     | O que a comissão NÃO faz
     |---------------------------------------------------------------------*/

    /**
     * A regra do dinheiro: a comissão acresce, não substitui. Lote enviado,
     * aprovação e até o pagamento do contrato do turno não a impedem — ela é um
     * documento novo, que segue sozinho para o lote seguinte.
     */
    public function test_lote_aprovacao_e_pagamento_do_contrato_nao_impedem_a_comissao(): void
    {
        $service = $this->contract();
        $service->batch_id = 7;
        $service->manager_approved_at = Carbon::parse('2026-08-02 10:00');
        $service->director_approved_at = Carbon::parse('2026-08-02 15:00');
        $service->paid = true;

        $this->assertNull($service->commissionBlockReason());
    }

    public function test_titulo_do_documento(): void
    {
        $commission = $this->contract();
        $commission->parent_service_id = 10;
        $commission->amendment_type = FreelancerService::AMENDMENT_COMMISSION;

        $this->assertSame('Termo Aditivo de Comissão sobre Vendas', $commission->documentTitle());
    }

    /* ---------------------------------------------------------------------
     | Prazo da assinatura não se aplica a aditivo
     |---------------------------------------------------------------------*/

    /**
     * A comissão é assinada ao FIM do expediente e o aditivo de horário nasce
     * durante o turno: cobrar deles o prazo do contrato marcaria todos como
     * atrasados e encheria o filtro de falhas de ruído.
     */
    public function test_aditivo_nao_e_marcado_como_assinatura_em_atraso(): void
    {
        $commission = $this->contract();
        $commission->parent_service_id = 10;
        $commission->amendment_type = FreelancerService::AMENDMENT_COMMISSION;
        $commission->freelancer_signed_at = Carbon::parse('2026-08-01 19:30');

        $this->assertFalse($commission->isSignedAfterStart());
        $this->assertNull($commission->minutesFromStartToSignature());

        $schedule = $this->contract();
        $schedule->parent_service_id = 10;
        $schedule->amendment_type = FreelancerService::AMENDMENT_SCHEDULE;
        $schedule->freelancer_signed_at = Carbon::parse('2026-08-02 09:00');

        $this->assertFalse($schedule->isSignedAfterStart());
    }

    public function test_aditivo_sem_assinatura_nao_entra_na_falha_de_turno_comecado(): void
    {
        Carbon::setTestNow('2026-08-02 10:00');

        $commission = $this->contract(attributes: ['freelancer_signed_at' => null]);
        $commission->parent_service_id = 10;
        $commission->amendment_type = FreelancerService::AMENDMENT_COMMISSION;

        $this->assertFalse($commission->isUnsignedAfterStart());

        // O contrato comum, no mesmo instante, continua sendo marcado.
        $contract = $this->contract(attributes: ['freelancer_signed_at' => null]);

        $this->assertTrue($contract->isUnsignedAfterStart());

        Carbon::setTestNow();
    }
}
