<?php

namespace Tests\Unit;

use App\Models\FreelancerService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Direito ao jantar do turno noturno.
 *
 * São dois critérios, e valem juntos: 6 horas ou mais de turno E estar em
 * serviço em algum momento da janela do jantar (17:30 às 18:30). Meia janta é
 * janta — quem sai 18:00 come. Os primeiros casos são os enunciados pela regra;
 * os demais fecham as bordas.
 *
 * Vale só a partir de `DINNER_STARTS_ON`, o dia em que a cozinha passa a servir
 * o jantar — por isso os turnos daqui são de setembro, depois da estreia. O
 * corte em si tem os seus próprios casos, no fim.
 *
 * Sem banco — tudo sai dos atributos do próprio contrato.
 */
class FreelancerDinnerEligibilityTest extends TestCase
{
    /** Turno de 03/09/2026. `endDate` é o dia seguinte quando vira a meia-noite. */
    private function service(string $startTime, string $endTime, ?string $endDate = null): FreelancerService
    {
        return $this->serviceOn('2026-09-03', $startTime, $endTime, $endDate);
    }

    private function serviceOn(
        string $startDate,
        string $startTime,
        string $endTime,
        ?string $endDate = null,
    ): FreelancerService {
        return new FreelancerService([
            'start_date' => $startDate,
            'start_time' => $startTime . ':00',
            'end_date' => $endDate ?? (FreelancerService::crossesMidnight($startTime, $endTime)
                ? Carbon::parse($startDate)->addDay()->toDateString()
                : $startDate),
            'end_time' => $endTime . ':00',
        ]);
    }

    public function test_a_janela_e_das_dezessete_e_meia_as_dezoito_e_meia(): void
    {
        $this->assertSame('17:30', FreelancerService::DINNER_START);
        $this->assertSame('18:30', FreelancerService::DINNER_END);
        $this->assertSame('17:30 às 18:30', FreelancerService::dinnerWindowLabel());
    }

    public function test_a_jornada_minima_e_de_seis_horas(): void
    {
        $this->assertSame(360, FreelancerService::DINNER_MIN_MINUTES);
    }

    /** 14:00 → 20:00: 6h e cobre a janela. Pergunta. */
    public function test_das_quatorze_as_vinte_tem_direito(): void
    {
        $this->assertTrue($this->service('14:00', '20:00')->isDinnerEligible());
    }

    /** 16:00 → 22:00: 6h e cobre a janela. Pergunta. */
    public function test_das_dezesseis_as_vinte_e_duas_tem_direito(): void
    {
        $this->assertTrue($this->service('16:00', '22:00')->isDinnerEligible());
    }

    /** 16:00 → 20:00: cobre a janela, mas são 4h. Não pergunta. */
    public function test_menos_de_seis_horas_nao_tem_direito(): void
    {
        $service = $this->service('16:00', '20:00');

        $this->assertFalse($service->isDinnerEligible());
        $this->assertStringContainsString('6 horas ou mais', $service->dinnerBlockReason());
    }

    /** 19:00 → 03:00: são 8h, mas o turno começa depois das 18:30. Não pergunta. */
    public function test_turno_que_comeca_depois_da_janela_nao_tem_direito(): void
    {
        $service = $this->service('19:00', '03:00');

        $this->assertSame(480, $service->durationInMinutes());
        $this->assertFalse($service->isDinnerEligible());
        $this->assertStringContainsString('17:30', $service->dinnerBlockReason());
    }

    /** Meia janta é janta: quem sai 18:00 pegou meia hora de jantar e come. */
    public function test_turno_que_termina_dentro_da_janela_tem_direito(): void
    {
        $this->assertTrue($this->service('12:00', '18:00')->isDinnerEligible());
    }

    /** Vale para a outra ponta: quem entra 18:00 também alcança o jantar. */
    public function test_turno_que_comeca_dentro_da_janela_tem_direito(): void
    {
        $this->assertTrue($this->service('18:00', '00:30')->isDinnerEligible());
    }

    /** Um minuto de janela basta. */
    public function test_um_minuto_dentro_da_janela_basta(): void
    {
        $this->assertTrue($this->service('11:29', '17:31')->isDinnerEligible());
        $this->assertTrue($this->service('18:29', '00:30')->isDinnerEligible());
    }

    /** Nas pontas exatas, com a janela inteira dentro do turno, também tem. */
    public function test_com_a_janela_inteira_dentro_do_turno_tem_direito(): void
    {
        $this->assertTrue($this->service('17:30', '23:30')->isDinnerEligible());
        $this->assertTrue($this->service('12:30', '18:30')->isDinnerEligible());
    }

    /**
     * Encostar na borda não é meia janta: quem sai às 17:30 em ponto sai quando
     * o jantar começa, e quem entra às 18:30 chega quando ele acabou — nenhum
     * dos dois esteve ali em minuto nenhum da janela.
     */
    public function test_encostar_na_borda_da_janela_nao_tem_direito(): void
    {
        $this->assertFalse($this->service('11:30', '17:30')->isDinnerEligible());
        $this->assertFalse($this->service('18:30', '00:30')->isDinnerEligible());
    }

    /** Turno diurno longo: 8h, mas termina muito antes do jantar. */
    public function test_turno_da_manha_nao_tem_direito(): void
    {
        $this->assertFalse($this->service('08:00', '16:00')->isDinnerEligible());
    }

    /** O jantar é do dia em que ele acontece — aqui, o dia do próprio turno. */
    public function test_a_data_do_jantar_e_a_do_dia_em_que_ele_acontece(): void
    {
        $this->assertSame('2026-09-03', $this->service('14:00', '20:00')->dinnerDate()->toDateString());
    }

    /**
     * Turno que vira a meia-noite e alcança a janela do dia SEGUINTE: janta no
     * dia 04, e é no dia 04 que a cozinha precisa vê-lo — não no 03, em que o
     * contrato começou.
     */
    public function test_turno_que_vira_a_meia_noite_janta_no_dia_seguinte(): void
    {
        $service = $this->service('22:00', '20:00', '2026-09-04');

        $this->assertTrue($service->isDinnerEligible());
        $this->assertSame('2026-09-04', $service->dinnerDate()->toDateString());
    }

    /** A comissão de venda copia o horário do turno, mas não é turno nenhum. */
    public function test_comissao_de_venda_nao_pede_jantar(): void
    {
        $commission = $this->service('14:00', '20:00');
        $commission->amendment_type = FreelancerService::AMENDMENT_COMMISSION;

        $this->assertFalse($commission->isDinnerEligible());
        $this->assertStringContainsString('comissão', mb_strtolower($commission->dinnerBlockReason()));
    }

    /* ---------------------------------------------------------------------
     | Estreia do jantar (31/08/2026)
     |
     | Antes dessa data a cozinha não servia jantar: turno nenhum dá direito, por
     | mais que cumpra os dois critérios.
     |---------------------------------------------------------------------*/

    public function test_a_estreia_do_jantar_e_em_trinta_e_um_de_agosto(): void
    {
        $this->assertSame('2026-08-31', FreelancerService::DINNER_STARTS_ON);
        $this->assertSame('2026-08-31', FreelancerService::dinnerStartsOn()->toDateString());
    }

    public function test_na_vespera_da_estreia_nao_ha_jantar(): void
    {
        $service = $this->serviceOn('2026-08-30', '14:00', '20:00');

        $this->assertFalse($service->isDinnerEligible());
        $this->assertFalse($service->needsDinnerAnswer());
        $this->assertStringContainsString('31/08/2026', $service->dinnerBlockReason());
    }

    public function test_no_dia_da_estreia_ja_ha_jantar(): void
    {
        $service = $this->serviceOn('2026-08-31', '14:00', '20:00');

        $this->assertTrue($service->isDinnerEligible());
        $this->assertNull($service->dinnerBlockReason());
    }

    /**
     * O corte é pelo DIA DO JANTAR, não pelo de início do turno: quem entra
     * 22:00 do dia 30 janta no dia 31 — e nesse dia já há jantar.
     */
    public function test_turno_da_vespera_que_janta_na_estreia_tem_direito(): void
    {
        $service = $this->serviceOn('2026-08-30', '22:00', '20:00', '2026-08-31');

        $this->assertTrue($service->isDinnerEligible());
        $this->assertSame('2026-08-31', $service->dinnerDate()->toDateString());
    }

    /** E o contrário também: turno do dia 30 que janta no dia 30 fica de fora. */
    public function test_turno_da_vespera_que_jantaria_na_vespera_fica_de_fora(): void
    {
        $service = $this->serviceOn('2026-08-30', '12:00', '22:00');

        $this->assertSame('2026-08-30', $service->dinnerDate()->toDateString());
        $this->assertFalse($service->isDinnerEligible());
    }

    /* ---------------------------------------------------------------------
     | Quando a pergunta é feita
     |---------------------------------------------------------------------*/

    public function test_antes_da_assinatura_nao_se_pergunta(): void
    {
        $this->assertFalse($this->service('14:00', '20:00')->needsDinnerAnswer());
    }

    public function test_depois_de_assinar_a_pergunta_esta_de_pe(): void
    {
        $service = $this->service('14:00', '20:00');
        $service->freelancer_signed_at = now();

        $this->assertTrue($service->needsDinnerAnswer());
        $this->assertSame('Não respondeu', $service->dinnerLabel());
    }

    public function test_respondida_a_pergunta_nao_se_repete(): void
    {
        $service = $this->service('14:00', '20:00');
        $service->freelancer_signed_at = now();
        $service->dinner_wanted = true;

        $this->assertFalse($service->needsDinnerAnswer());
        $this->assertTrue($service->wantsDinner());
        $this->assertSame('Sim', $service->dinnerLabel());
    }

    public function test_o_nao_tambem_e_resposta(): void
    {
        $service = $this->service('14:00', '20:00');
        $service->freelancer_signed_at = now();
        $service->dinner_wanted = false;

        $this->assertFalse($service->needsDinnerAnswer());
        $this->assertTrue($service->dinnerWasAnswered());
        $this->assertFalse($service->wantsDinner());
        $this->assertSame('Não', $service->dinnerLabel());
    }

    public function test_contrato_cancelado_nao_pergunta(): void
    {
        $service = $this->service('14:00', '20:00');
        $service->freelancer_signed_at = now();
        $service->status_id = FreelancerService::STATUS_CANCELLED;

        $this->assertFalse($service->needsDinnerAnswer());
    }

    public function test_turno_sem_direito_nao_pergunta_mesmo_assinado(): void
    {
        $service = $this->service('16:00', '20:00');
        $service->freelancer_signed_at = now();

        $this->assertFalse($service->needsDinnerAnswer());
        $this->assertSame('Sem direito', $service->dinnerLabel());
    }
}
