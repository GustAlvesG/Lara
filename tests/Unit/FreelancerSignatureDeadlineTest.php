<?php

namespace Tests\Unit;

use App\Models\FreelancerService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Prazo da assinatura: o contrato é para ser assinado ANTES de o turno começar,
 * com 30 minutos de tolerância. Passou disso, a interface web marca o contrato.
 *
 * Sem banco — a conta é toda sobre atributos do próprio serviço.
 */
class FreelancerSignatureDeadlineTest extends TestCase
{
    /** Turno de 01/08/2026 16:00 às 20:00, assinado em $signedAt (ou não). */
    private function service(?string $signedAt, string $startTime = '16:00:00'): FreelancerService
    {
        $service = new FreelancerService([
            'start_date' => '2026-08-01',
            'start_time' => $startTime,
            'end_date' => '2026-08-01',
            'end_time' => '20:00:00',
        ]);

        $service->freelancer_signed_at = $signedAt ? Carbon::parse($signedAt) : null;

        return $service;
    }

    public function test_a_tolerancia_e_de_trinta_minutos(): void
    {
        $this->assertSame(30, FreelancerService::SIGNATURE_TOLERANCE_MINUTES);
    }

    public function test_contrato_sem_assinatura_nao_e_marcado(): void
    {
        $service = $this->service(null);

        $this->assertFalse($service->isSignedAfterStart());
        $this->assertNull($service->minutesFromStartToSignature());
        $this->assertNull($service->formattedSignatureDelay());
    }

    public function test_assinado_antes_do_inicio_nao_e_marcado(): void
    {
        $service = $this->service('2026-08-01 15:20');

        $this->assertFalse($service->isSignedAfterStart());
        $this->assertSame(-40, $service->minutesFromStartToSignature());
        $this->assertNull($service->formattedSignatureDelay());
    }

    public function test_assinado_no_minuto_do_inicio_nao_e_marcado(): void
    {
        $this->assertFalse($this->service('2026-08-01 16:00')->isSignedAfterStart());
    }

    /** Enunciado do prazo: turno às 16:00 pode ser assinado até 16:30. */
    public function test_assinado_dentro_da_tolerancia_nao_e_marcado(): void
    {
        $this->assertFalse($this->service('2026-08-01 16:29')->isSignedAfterStart());
        $this->assertFalse($this->service('2026-08-01 16:30')->isSignedAfterStart());
    }

    public function test_um_minuto_alem_da_tolerancia_ja_e_marcado(): void
    {
        $service = $this->service('2026-08-01 16:31');

        $this->assertTrue($service->isSignedAfterStart());
        // O atraso mostrado é em relação ao INÍCIO, não à tolerância.
        $this->assertSame(31, $service->minutesFromStartToSignature());
        $this->assertSame('31min', $service->formattedSignatureDelay());
    }

    public function test_atraso_de_horas_cheias(): void
    {
        $this->assertSame('2h', $this->service('2026-08-01 18:00')->formattedSignatureDelay());
    }

    public function test_atraso_com_horas_e_minutos(): void
    {
        $service = $this->service('2026-08-01 18:15');

        $this->assertTrue($service->isSignedAfterStart());
        $this->assertSame('2h15', $service->formattedSignatureDelay());
    }

    /** Assinatura no dia seguinte continua sendo atraso, não vira negativo. */
    public function test_assinado_no_dia_seguinte(): void
    {
        $service = $this->service('2026-08-02 09:00');

        $this->assertTrue($service->isSignedAfterStart());
        $this->assertSame('17h', $service->formattedSignatureDelay());
    }

    /** Turno que vira a meia-noite: o prazo conta do início, não do término. */
    public function test_turno_noturno_conta_do_horario_de_inicio(): void
    {
        $service = $this->service('2026-08-01 22:10', startTime: '22:00:00');

        $this->assertFalse($service->isSignedAfterStart());

        $atrasado = $this->service('2026-08-01 23:00', startTime: '22:00:00');

        $this->assertTrue($atrasado->isSignedAfterStart());
        $this->assertSame('1h', $atrasado->formattedSignatureDelay());
    }
}
