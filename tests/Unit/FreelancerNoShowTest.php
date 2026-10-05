<?php

namespace Tests\Unit;

use App\Models\FreelancerService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Falta do freelancer: o turno que não foi cumprido.
 *
 * A falta é um cancelamento com motivo próprio — é o que faz o contrato sair da
 * contagem semanal pela regra que sempre excluiu o cancelado, sem que nenhuma
 * dessas regras precise saber que a falta existe.
 *
 * O que estes testes protegem é a TRAVA: a falta é a única baixa que o operador
 * do balcão dá sozinho, sem coordenador, e por isso alcança menos casos que o
 * cancelamento comum. Em memória, sem banco — a regra não consulta nada.
 */
class FreelancerNoShowTest extends TestCase
{
    /** Quarta-feira. A semana vai de segunda 07/09 a domingo 13/09/2026. */
    private const HOJE = '2026-09-09 14:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::HOJE);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Contrato não persistido, só com o que a regra da falta olha. */
    private function service(string $startDate, array $attributes = []): FreelancerService
    {
        return new FreelancerService(array_merge([
            'freelancer_id' => 1,
            'start_date' => $startDate,
            'status_id' => FreelancerService::STATUS_ACTIVE,
        ], $attributes));
    }

    public function test_turno_passado_e_sem_assinatura_pode_ser_marcado_como_falta(): void
    {
        $this->assertTrue($this->service('2026-09-07')->canBeMarkedNoShow());
    }

    /**
     * O dia de hoje conta. O freelancer que não apareceu para o turno da manhã
     * é caso de falta ainda hoje — é isso que libera contratar outro dia na
     * mesma semana sem esperar a virada do dia.
     */
    public function test_turno_de_hoje_pode_ser_marcado_como_falta(): void
    {
        $this->assertTrue($this->service('2026-09-09')->canBeMarkedNoShow());
    }

    /**
     * Turno que ainda não chegou, não. Sem esta trava, bastaria declarar falta
     * dos dias seguintes para esvaziar a semana e furar o limite.
     */
    public function test_turno_futuro_nao_pode_ser_marcado_como_falta(): void
    {
        $this->assertFalse($this->service('2026-09-10')->canBeMarkedNoShow());
        $this->assertFalse($this->service('2026-09-13')->canBeMarkedNoShow());
    }

    /** Quem assinou compareceu — por qualquer uma das partes. */
    public function test_contrato_assinado_nao_pode_ser_marcado_como_falta(): void
    {
        $this->assertFalse(
            $this->service('2026-09-07', ['freelancer_signed_at' => '2026-09-07 08:10:00'])->canBeMarkedNoShow()
        );
        $this->assertFalse(
            $this->service('2026-09-07', ['coordinator_signed_at' => '2026-09-07 19:00:00'])->canBeMarkedNoShow()
        );
    }

    public function test_contrato_ja_baixado_nao_pode_ser_marcado_como_falta(): void
    {
        $cancelado = $this->service('2026-09-07', ['status_id' => FreelancerService::STATUS_CANCELLED]);

        $this->assertFalse($cancelado->canBeMarkedNoShow());
    }

    /**
     * Aditivo não é um dia de trabalho próprio: ele remenda um turno já contado
     * pelo contrato base. Se o dia não foi trabalhado, quem se baixa é o base.
     */
    public function test_aditivo_nao_pode_ser_marcado_como_falta(): void
    {
        $aditivo = $this->service('2026-09-07', ['parent_service_id' => 99]);

        $this->assertFalse($aditivo->canBeMarkedNoShow());
    }

    public function test_contrato_sem_data_nao_pode_ser_marcado_como_falta(): void
    {
        $this->assertFalse($this->service('2026-09-07', ['start_date' => null])->canBeMarkedNoShow());
    }

    /**
     * A leitura da baixa. As duas tiram o contrato de cena, mas "não apareceu"
     * e "a empresa desmarcou" não são a mesma informação para quem escala.
     */
    public function test_a_falta_se_distingue_do_cancelamento_comum(): void
    {
        $falta = $this->service('2026-09-07', [
            'status_id' => FreelancerService::STATUS_CANCELLED,
            'cancel_reason' => FreelancerService::CANCEL_REASON_NO_SHOW,
        ]);

        $cancelado = $this->service('2026-09-07', [
            'status_id' => FreelancerService::STATUS_CANCELLED,
            'cancel_reason' => FreelancerService::CANCEL_REASON_ADMIN,
        ]);

        $this->assertTrue($falta->isNoShow());
        $this->assertSame('Falta', $falta->signatureLabel());

        $this->assertFalse($cancelado->isNoShow());
        $this->assertSame('Cancelado', $cancelado->signatureLabel());
    }

    /** Baixa anterior à coluna existir: motivo nulo é cancelamento comum. */
    public function test_baixa_antiga_sem_motivo_e_lida_como_cancelamento(): void
    {
        $antigo = $this->service('2026-09-07', ['status_id' => FreelancerService::STATUS_CANCELLED]);

        $this->assertFalse($antigo->isNoShow());
        $this->assertSame('Cancelado', $antigo->signatureLabel());
    }

    /**
     * O ponto da mudança: o dia marcado como falta devolve a vaga da semana.
     * Exercitado pela versão em memória da contagem, que é a mesma regra que a
     * listagem usa.
     */
    public function test_dia_com_falta_nao_ocupa_vaga_na_semana(): void
    {
        $quarta = $this->service('2026-09-09', [
            'status_id' => FreelancerService::STATUS_CANCELLED,
            'cancel_reason' => FreelancerService::CANCEL_REASON_NO_SHOW,
        ]);
        $quarta->id = 1;

        $sexta = $this->service('2026-09-11');
        $sexta->id = 2;

        $sabado = $this->service('2026-09-12');
        $sabado->id = 3;

        $flags = FreelancerService::flagExcessWithinCollection(collect([$quarta, $sexta, $sabado]));

        // Sem a falta seriam três na mesma semana, e todos estariam em excesso.
        $this->assertFalse($flags->contains(true));
    }
}
