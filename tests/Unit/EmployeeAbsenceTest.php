<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeAbsence;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Regras de férias, afastamento e rescisão — sem banco.
 *
 * São informativas: não mexem na importação nem no saldo. O que importa é a
 * leitura de período, e sobretudo o **período em aberto** (sem data de fim),
 * que é como um afastamento nasce quando ainda não se sabe o retorno. Este é
 * o comportamento de que o módulo de consulta de funcionários ativos vai
 * depender.
 */
class EmployeeAbsenceTest extends TestCase
{
    private function absence(string $start, ?string $end = null, string $type = EmployeeAbsence::TYPE_LEAVE): EmployeeAbsence
    {
        $absence = new EmployeeAbsence();
        $absence->type       = $type;
        $absence->start_date = $start;
        $absence->end_date   = $end;

        return $absence;
    }

    public function test_periodo_fechado_cobre_apenas_o_intervalo(): void
    {
        $absence = $this->absence('2026-06-10', '2026-06-20');

        $this->assertFalse($absence->coversDate('2026-06-09'));
        $this->assertTrue($absence->coversDate('2026-06-10'));
        $this->assertTrue($absence->coversDate('2026-06-15'));
        $this->assertTrue($absence->coversDate('2026-06-20'));
        $this->assertFalse($absence->coversDate('2026-06-21'));
    }

    public function test_periodo_sem_fim_vale_dali_em_diante(): void
    {
        $absence = $this->absence('2026-06-10');

        $this->assertTrue($absence->isOpenEnded());
        $this->assertFalse($absence->coversDate('2026-06-09'));
        $this->assertTrue($absence->coversDate('2026-06-10'));
        $this->assertTrue($absence->coversDate('2030-01-01'));
    }

    public function test_periodo_de_um_dia_so_cobre_esse_dia(): void
    {
        $absence = $this->absence('2026-06-10', '2026-06-10');

        $this->assertTrue($absence->coversDate('2026-06-10'));
        $this->assertFalse($absence->coversDate('2026-06-11'));
    }

    public function test_rotulo_do_tipo_sai_em_portugues(): void
    {
        $this->assertSame('Férias', $this->absence('2026-06-10', null, EmployeeAbsence::TYPE_VACATION)->type_label);
        $this->assertSame('Afastamento', $this->absence('2026-06-10')->type_label);
    }

    public function test_funcionario_sem_rescisao_esta_sempre_ativo(): void
    {
        $employee = new Employee();

        $this->assertFalse($employee->isTerminated());
        $this->assertTrue($employee->isActiveOn('2030-01-01'));
    }

    public function test_rescisao_futura_ainda_conta_como_ativo(): void
    {
        $employee = new Employee();
        $employee->termination_date = '2026-12-31';

        // Já registrada, mas ainda não em vigor: a pessoa está na casa.
        $this->assertTrue($employee->isTerminated());
        $this->assertTrue($employee->isActiveOn('2026-06-01'));
        $this->assertFalse($employee->isActiveOn('2026-12-31'));
        $this->assertFalse($employee->isActiveOn('2027-01-05'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
