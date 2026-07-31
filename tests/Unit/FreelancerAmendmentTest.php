<?php

namespace Tests\Unit;

use App\Models\FreelancerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Contrato aditivo: quando o turno muda depois de o contrato estar assinado,
 * gera-se um contrato novo preso ao base, que passa a ser o documento válido.
 *
 * Sem banco — todas as regras exercitadas aqui são decididas por atributos do
 * próprio serviço e pela relação já carregada em memória.
 */
class FreelancerAmendmentTest extends TestCase
{
    /** Turno de 01/08/2026, 18:00 às 22:00, assinado pelo freelancer. */
    private function base(array $overrides = []): FreelancerService
    {
        $service = new FreelancerService([
            'freelancer_id' => 1,
            'function_freelancer_id' => 2,
            'location' => 'Salão Nobre',
            'start_date' => '2026-08-01',
            'start_time' => '18:00:00',
            'end_date' => '2026-08-01',
            'end_time' => '22:00:00',
            'price' => 200,
        ]);

        $service->id = 10;
        $service->freelancer_signed_at = Carbon::parse('2026-08-01 17:40');

        foreach ($overrides as $attribute => $value) {
            $service->{$attribute} = $value;
        }

        return $service;
    }

    /** Aditivo do turno acima, esticado até 00:30. */
    private function amendment(FreelancerService $base): FreelancerService
    {
        $amendment = new FreelancerService([
            'freelancer_id' => $base->freelancer_id,
            'function_freelancer_id' => $base->function_freelancer_id,
            'parent_service_id' => $base->id,
            'location' => $base->location,
            'start_date' => '2026-08-01',
            'start_time' => '18:00:00',
            'end_date' => '2026-08-02',
            'end_time' => '00:30:00',
            'price' => 325,
        ]);

        $amendment->id = 11;
        $amendment->setRelation('baseService', $base);

        return $amendment;
    }

    /* ---------------------------------------------------------------------
     | Quando cabe aditivo
     |---------------------------------------------------------------------*/

    public function test_contrato_assinado_aceita_aditivo(): void
    {
        $service = $this->base();

        $this->assertTrue($service->canBeAmended());
        $this->assertNull($service->amendmentBlockReason());
    }

    /** Sem assinatura o contrato ainda é editável: aditivo seria papel a mais. */
    public function test_contrato_sem_assinatura_nao_aceita_aditivo(): void
    {
        $service = $this->base();
        $service->freelancer_signed_at = null;

        $this->assertFalse($service->canBeAmended());
        $this->assertStringContainsString('altere os dados do próprio contrato', $service->amendmentBlockReason());
    }

    public function test_contrato_cancelado_nao_aceita_aditivo(): void
    {
        $service = $this->base(['status_id' => FreelancerService::STATUS_CANCELLED]);

        $this->assertFalse($service->canBeAmended());
        $this->assertSame('Contrato cancelado não recebe aditivo.', $service->amendmentBlockReason());
    }

    public function test_contrato_que_ja_tem_aditivo_nao_aceita_outro(): void
    {
        $service = $this->base(['amended_at' => Carbon::parse('2026-08-01 22:10')]);

        $this->assertFalse($service->canBeAmended());
        $this->assertStringContainsString('já tem um aditivo', $service->amendmentBlockReason());
    }

    public function test_contrato_pago_nao_aceita_aditivo(): void
    {
        $service = $this->base(['paid' => true]);

        $this->assertFalse($service->canBeAmended());
        $this->assertSame('Contrato já pago não recebe aditivo.', $service->amendmentBlockReason());
    }

    public function test_contrato_aprovado_pela_gerencia_nao_aceita_aditivo(): void
    {
        $service = $this->base(['manager_approved_at' => Carbon::parse('2026-08-02 10:00')]);

        $this->assertFalse($service->canBeAmended());
        $this->assertSame('Contrato já aprovado não recebe aditivo.', $service->amendmentBlockReason());
    }

    /** O aditivo é um contrato como outro qualquer: também pode ser aditado. */
    public function test_aditivo_assinado_aceita_novo_aditivo(): void
    {
        $amendment = $this->amendment($this->base());
        $amendment->freelancer_signed_at = Carbon::parse('2026-08-01 22:15');

        $this->assertTrue($amendment->canBeAmended());
    }

    /* ---------------------------------------------------------------------
     | O aditivo tira do base o pagamento — não a assinatura
     |---------------------------------------------------------------------*/

    /**
     * O contrato base é um documento firmado: recebe as duas assinaturas até o
     * fim, aditivado ou não. É o pagamento que muda de lugar.
     */
    public function test_contrato_aditivado_continua_sendo_assinado(): void
    {
        $service = $this->base(['amended_at' => Carbon::parse('2026-08-01 22:10')]);

        $this->assertTrue($service->canBeSignedByCoordinator());
        $this->assertSame('Aguardando coordenador', $service->signatureLabel());

        $semAssinatura = $this->base(['amended_at' => Carbon::parse('2026-08-01 22:10')]);
        $semAssinatura->freelancer_signed_at = null;

        $this->assertTrue($semAssinatura->canBeSignedByFreelancer());
    }

    public function test_contrato_aditivado_sai_do_lote_e_do_financeiro(): void
    {
        $service = $this->base([
            'coordinator_signed_at' => Carbon::parse('2026-08-01 23:00'),
            'manager_approved_at' => Carbon::parse('2026-08-02 10:00'),
            'director_approved_at' => Carbon::parse('2026-08-02 15:00'),
            'amended_at' => Carbon::parse('2026-08-01 22:10'),
        ]);

        $this->assertFalse($service->canBeBatched());
        $this->assertFalse($service->isPayable());
        $this->assertSame('Pago pelo aditivo', $service->approvalLabel());
    }

    /** Aditivado não é cancelado: o documento assinado continua de pé. */
    public function test_contrato_aditivado_nao_e_cancelado(): void
    {
        $service = $this->base(['amended_at' => Carbon::parse('2026-08-01 22:10')]);

        $this->assertFalse($service->isCancelled());
        $this->assertTrue($service->isSigned());
    }

    public function test_aditivo_pendente_de_assinatura_ainda_pode_ser_assinado(): void
    {
        $amendment = $this->amendment($this->base());

        $this->assertTrue($amendment->canBeSignedByFreelancer());
        $this->assertTrue($amendment->canBeSignedByCoordinator());
    }

    /* ---------------------------------------------------------------------
     | Identificação e numeração
     |---------------------------------------------------------------------*/

    public function test_numeracao_e_titulo_do_documento(): void
    {
        $base = $this->base();
        $primeiro = $this->amendment($base);

        $this->assertFalse($base->isAmendment());
        $this->assertSame(0, $base->amendmentOrder());
        $this->assertSame('Contrato Autônomo de Serviços de Freelancer', $base->documentTitle());

        $this->assertTrue($primeiro->isAmendment());
        $this->assertSame(1, $primeiro->amendmentOrder());
        $this->assertSame('Termo Aditivo ao Contrato Autônomo de Serviços de Freelancer', $primeiro->documentTitle());

        $segundo = $this->amendment($primeiro);
        $segundo->id = 12;

        $this->assertSame(2, $segundo->amendmentOrder());
        $this->assertSame('2º Termo Aditivo ao Contrato Autônomo de Serviços de Freelancer', $segundo->documentTitle());
    }

    public function test_mudanca_de_duracao_descrita_em_relacao_ao_base(): void
    {
        $amendment = $this->amendment($this->base());

        $this->assertSame('de 4h para 6h30', $amendment->amendmentDurationChange());
        $this->assertNull($this->base()->amendmentDurationChange());
    }

    /* ---------------------------------------------------------------------
     | Limite semanal
     |---------------------------------------------------------------------*/

    /**
     * O aditivo não é um dia novo de trabalho: contá-lo faria o segundo
     * documento do mesmo turno estourar o limite de 7 dias sozinho.
     */
    public function test_aditivo_nao_conta_no_limite_semanal(): void
    {
        $base = $this->base();
        $outro = $this->base();
        $outro->id = 20;
        $outro->start_date = '2026-08-03';

        $amendment = $this->amendment($base);

        $flags = FreelancerService::flagExcessWithinCollection(
            new Collection([$base, $outro, $amendment])
        );

        // Três registros na mesma semana, mas só dois turnos: ninguém estoura.
        $this->assertFalse($flags[$base->id]);
        $this->assertFalse($flags[$outro->id]);
        $this->assertFalse($flags[$amendment->id]);
    }
}
