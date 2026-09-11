<?php

namespace Tests\Unit;

use App\Models\FreelancerService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Liberação do contrato para a coordenação: só a partir das 08h da manhã
 * seguinte ao dia do turno.
 *
 * A razão da espera é o aditivo. O freelancer assina no começo do serviço, e o
 * turno ainda pode esticar, encurtar ou ganhar comissão até o fim do dia —
 * assinar antes disso fecharia um documento que ainda vai mudar.
 *
 * Sem banco: a regra é uma conta sobre `start_date` e o relógio.
 */
class FreelancerReleaseWindowTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Turno de 05/08/2026, assinado pelo freelancer no início do serviço. */
    private function service(array $overrides = []): FreelancerService
    {
        $service = (new FreelancerService([
            'start_date' => '2026-08-05',
            'start_time' => '18:00:00',
            'end_date' => '2026-08-05',
            'end_time' => '22:00:00',
            'price' => 200,
        ]))->forceFill(array_merge([
            'freelancer_signed_at' => '2026-08-05 17:50:00',
        ], $overrides));

        $service->id = 10;

        return $service;
    }

    public function test_a_liberacao_e_as_oito_da_manha_do_dia_seguinte_ao_turno(): void
    {
        $this->assertSame(
            '2026-08-06 08:00:00',
            $this->service()->releasesAt()->format('Y-m-d H:i:s')
        );
    }

    /**
     * O turno que vira a meia-noite pertence ao dia em que COMEÇOU — é o dia do
     * turno em todo o módulo. Um serviço 22:00→02:00 do dia 5 é liberado na
     * manhã do dia 6, e não na do 7.
     */
    public function test_turno_que_vira_o_dia_conta_pelo_dia_de_inicio(): void
    {
        $viraODia = $this->service();
        $viraODia->end_date = '2026-08-06';
        $viraODia->end_time = '02:00:00';

        $this->assertSame(
            '2026-08-06 08:00:00',
            $viraODia->releasesAt()->format('Y-m-d H:i:s')
        );
    }

    public function test_antes_das_oito_o_contrato_esta_travado(): void
    {
        Carbon::setTestNow('2026-08-06 07:59:59');
        $service = $this->service();

        $this->assertFalse($service->hasBeenReleased());
        // A espera vale para as duas redações: assinatura no tablet (1) e
        // validação pela web (2).
        $this->assertFalse($this->service(['contract_version' => 1])->canBeSignedByCoordinator());
        $this->assertFalse($this->service(['contract_version' => 2])->canBeValidatedByCoordinator());
        $this->assertFalse($service->canBeBatched());
        $this->assertNotNull($service->releaseBlockReason());
        $this->assertStringContainsString('06/08/2026', $service->releaseBlockReason());
        $this->assertStringContainsString('08:00', $service->releaseBlockReason());
    }

    public function test_as_oito_em_ponto_ja_esta_liberado(): void
    {
        Carbon::setTestNow('2026-08-06 08:00:00');
        $service = $this->service();

        $this->assertTrue($service->hasBeenReleased());
        $this->assertTrue($this->service(['contract_version' => 1])->canBeSignedByCoordinator());
        $this->assertTrue($this->service(['contract_version' => 2])->canBeValidatedByCoordinator());
        $this->assertNull($service->releaseBlockReason());
    }

    /** No mesmo dia do turno, nem de madrugada nem à noite: o dia não acabou. */
    public function test_no_dia_do_turno_nunca_esta_liberado(): void
    {
        foreach (['2026-08-05 09:00:00', '2026-08-05 23:59:59'] as $agora) {
            Carbon::setTestNow($agora);

            $this->assertFalse($this->service()->hasBeenReleased(), "Liberado cedo demais em {$agora}.");
        }
    }

    /** Assinado pelo freelancer e esperando o relógio — fila própria no acompanhamento. */
    public function test_etapa_de_acompanhamento_diz_que_espera_o_fim_do_dia(): void
    {
        Carbon::setTestNow('2026-08-05 23:00:00');

        $this->assertSame('awaiting_release', $this->service()->trackingStage());
        $this->assertSame('Aguardando o fim do dia', $this->service()->trackingStageLabel());
    }

    /**
     * Sem a assinatura do freelancer o contrato não está esperando o relógio:
     * está esperando gente. A distinção é o que faz a fila de "aguardando
     * liberação" significar "não cobre ninguém por isto".
     */
    public function test_sem_a_assinatura_do_freelancer_a_etapa_continua_sendo_assinaturas(): void
    {
        Carbon::setTestNow('2026-08-05 23:00:00');
        $service = $this->service(['freelancer_signed_at' => null]);

        $this->assertFalse($service->awaitsRelease());
        $this->assertSame('awaiting_signatures', $service->trackingStage());
    }

    /** Depois de o coordenador assinar, a espera acabou — não se volta a ela. */
    public function test_contrato_ja_assinado_pelos_dois_nao_espera_liberacao(): void
    {
        Carbon::setTestNow('2026-08-05 23:00:00');
        $service = $this->service(['coordinator_signed_at' => '2026-08-05 22:30:00']);

        $this->assertFalse($service->awaitsRelease());
    }

    /**
     * A trava vale por registro (`hasBeenReleased`) e no banco
     * (`lastReleasedDate`, que a compara só por data). Duas implementações da
     * mesma regra — se elas discordarem, a tela lista o que o servidor recusa.
     */
    public function test_a_conta_do_banco_e_a_do_php_dao_a_mesma_resposta(): void
    {
        $momentos = [
            '2026-08-06 00:00:00', '2026-08-06 07:59:59', '2026-08-06 08:00:00',
            '2026-08-06 12:00:00', '2026-08-06 23:59:59', '2026-08-07 03:00:00',
        ];

        $dias = ['2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07'];

        foreach ($momentos as $agora) {
            Carbon::setTestNow($agora);
            $ultimoLiberado = FreelancerService::lastReleasedDate();

            foreach ($dias as $dia) {
                $service = $this->service();
                $service->start_date = $dia;

                $this->assertSame(
                    $service->hasBeenReleased(),
                    // O que o `scopeReleased` faz em SQL: start_date <= último liberado.
                    Carbon::parse($dia)->lessThanOrEqualTo($ultimoLiberado),
                    "PHP e banco discordam sobre o turno de {$dia} às {$agora}."
                );
            }
        }
    }

    /** Contrato sem data de turno não fica travado para sempre. */
    public function test_contrato_sem_data_de_turno_nao_trava(): void
    {
        $service = $this->service();
        $service->start_date = null;

        $this->assertNull($service->releasesAt());
        $this->assertTrue($service->hasBeenReleased());
        $this->assertNull($service->releaseBlockReason());
    }
}
