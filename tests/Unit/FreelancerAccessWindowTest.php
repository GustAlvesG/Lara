<?php

namespace Tests\Unit;

use App\Models\FreelancerService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Liberação do freelancer na portaria: ter contrato registrado é o que autoriza
 * a entrada. A janela abre 30 minutos ANTES do início do turno — serviço às
 * 08:00 entra a partir das 07:30 — e fecha no horário de término do contrato.
 *
 * Sem banco — a janela é toda calculada sobre atributos do próprio serviço.
 */
class FreelancerAccessWindowTest extends TestCase
{
    /** Turno de 03/08/2026 08:00 às 12:00. */
    private function service(
        string $startTime = '08:00:00',
        string $endTime = '12:00:00',
        string $endDate = '2026-08-03'
    ): FreelancerService {
        return new FreelancerService([
            'start_date' => '2026-08-03',
            'start_time' => $startTime,
            'end_date' => $endDate,
            'end_time' => $endTime,
        ]);
    }

    public function test_a_antecedencia_e_de_trinta_minutos(): void
    {
        $this->assertSame(30, FreelancerService::ACCESS_EARLY_MINUTES);
    }

    /** Enunciado da regra: serviço às 08:00 libera a portaria às 07:30. */
    public function test_a_janela_abre_meia_hora_antes_do_inicio(): void
    {
        $service = $this->service();

        $this->assertSame('2026-08-03 07:30:00', $service->accessOpensAt()->toDateTimeString());
        $this->assertSame('07:30 → 12:00', $service->formattedAccessWindow());
    }

    public function test_antes_da_janela_nao_libera(): void
    {
        $this->assertFalse($this->service()->allowsAccessAt(Carbon::parse('2026-08-03 07:29')));
    }

    public function test_no_minuto_de_abertura_ja_libera(): void
    {
        $this->assertTrue($this->service()->allowsAccessAt(Carbon::parse('2026-08-03 07:30')));
    }

    public function test_durante_o_turno_libera(): void
    {
        $service = $this->service();

        $this->assertTrue($service->allowsAccessAt(Carbon::parse('2026-08-03 08:00')));
        $this->assertTrue($service->allowsAccessAt(Carbon::parse('2026-08-03 10:45')));
    }

    /** O término do contrato é o fim da janela — inclusive o minuto exato. */
    public function test_no_termino_ainda_libera_e_depois_nao(): void
    {
        $service = $this->service();

        $this->assertTrue($service->allowsAccessAt(Carbon::parse('2026-08-03 12:00')));
        $this->assertFalse($service->allowsAccessAt(Carbon::parse('2026-08-03 12:01')));
    }

    /** Sem instante informado, a conta é sobre agora. */
    public function test_sem_argumento_usa_o_momento_atual(): void
    {
        Carbon::setTestNow('2026-08-03 07:45');
        $this->assertTrue($this->service()->allowsAccessAt());

        Carbon::setTestNow('2026-08-03 06:00');
        $this->assertFalse($this->service()->allowsAccessAt());

        Carbon::setTestNow();
    }

    /** Turno que vira a meia-noite: a janela acompanha o término no dia seguinte. */
    public function test_turno_noturno_vale_ate_o_termino_no_dia_seguinte(): void
    {
        $service = $this->service('22:00:00', '02:00:00', endDate: '2026-08-04');

        $this->assertSame('21:30 → 02:00', $service->formattedAccessWindow());
        $this->assertFalse($service->allowsAccessAt(Carbon::parse('2026-08-03 21:29')));
        $this->assertTrue($service->allowsAccessAt(Carbon::parse('2026-08-03 21:30')));
        $this->assertTrue($service->allowsAccessAt(Carbon::parse('2026-08-04 01:59')));
        $this->assertFalse($service->allowsAccessAt(Carbon::parse('2026-08-04 02:01')));
    }

    /** Contrato cancelado não autoriza mais nada, nem dentro do horário. */
    public function test_contrato_cancelado_nao_libera(): void
    {
        $service = $this->service();
        $service->status_id = FreelancerService::STATUS_CANCELLED;

        $this->assertFalse($service->allowsAccessAt(Carbon::parse('2026-08-03 09:00')));
    }

    /** Sem período gravado não há janela a calcular — e nada é liberado. */
    public function test_contrato_sem_periodo_nao_libera(): void
    {
        $service = new FreelancerService(['start_date' => '2026-08-03']);

        $this->assertFalse($service->allowsAccessAt(Carbon::parse('2026-08-03 09:00')));
        $this->assertNull($service->formattedAccessWindow());
    }
}
