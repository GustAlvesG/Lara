<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Sector;
use App\Services\CompTimeService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Recorte de visibilidade do Banco de Horas.
 *
 * Testa o que a restrição de acesso faz depois de decidida — o escopo aplicado
 * à consulta e a checagem de uma ficha isolada. Quem **decide** a restrição
 * (CompTimeService::accessFor) depende do model User, preso à conexão `mysql`,
 * e por isso fica fora da suíte em SQLite.
 *
 * Sem RefreshDatabase pelo mesmo motivo registrado em FleetMileageTest.
 */
class CompTimeAccessScopeTest extends TestCase
{
    private Sector $manutencao;
    private Sector $portaria;
    private Employee $joao;
    private Employee $maria;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::withoutForeignKeyConstraints(function () {
            (require base_path('database/migrations/2026_06_05_102714_create_sectors_table.php'))->up();
            (require base_path('database/migrations/2026_01_05_141304_banco_de_horas.php'))->up();
            (require base_path('database/migrations/2026_06_05_114847_add_written_off_to_time_entries.php'))->up();
            (require base_path('database/migrations/2026_09_01_090200_add_sector_and_employment_to_employees.php'))->up();
            (require base_path('database/migrations/2026_09_01_090300_create_employee_absences_table.php'))->up();
        });

        $this->manutencao = Sector::create(['name' => 'Manutenção']);
        $this->portaria   = Sector::create(['name' => 'Portaria']);

        $this->joao  = $this->employee('1001', 'João', $this->manutencao);
        $this->maria = $this->employee('1002', 'Maria', $this->portaria);
    }

    private function employee(string $code, string $name, Sector $sector): Employee
    {
        return Employee::create([
            'employee_code'  => $code,
            'name'           => $name,
            'cpf'            => '12345678909',
            'admission_date' => '2020-01-10',
            'position'       => 'Analista',
            'department'     => $sector->name,
            'sector_id'      => $sector->id,
        ]);
    }

    private function service(): CompTimeService
    {
        return new CompTimeService();
    }

    public function test_acesso_total_enxerga_todo_mundo(): void
    {
        $codes = $this->service()
            ->applyAccessScope(Employee::query(), ['type' => 'all'])
            ->pluck('employee_code')->all();

        $this->assertEqualsCanonicalizing(['1001', '1002'], $codes);
    }

    public function test_coordenador_enxerga_apenas_os_setores_que_coordena(): void
    {
        $access = ['type' => 'sectors', 'values' => [$this->manutencao->id]];

        $codes = $this->service()
            ->applyAccessScope(Employee::query(), $access)
            ->pluck('employee_code')->all();

        $this->assertSame(['1001'], $codes);
    }

    public function test_colaborador_enxerga_apenas_a_propria_ficha(): void
    {
        $access = ['type' => 'employee_code', 'value' => '1002'];

        $codes = $this->service()
            ->applyAccessScope(Employee::query(), $access)
            ->pluck('employee_code')->all();

        $this->assertSame(['1002'], $codes);
    }

    public function test_sem_vinculo_nao_enxerga_nada(): void
    {
        $codes = $this->service()
            ->applyAccessScope(Employee::query(), ['type' => 'none'])
            ->pluck('employee_code')->all();

        $this->assertSame([], $codes);
    }

    public function test_coordenador_nao_abre_ficha_de_outro_setor(): void
    {
        $service = $this->service();
        $access  = ['type' => 'sectors', 'values' => [$this->manutencao->id]];

        $this->assertTrue($service->canViewEmployee($access, $this->joao));
        $this->assertFalse($service->canViewEmployee($access, $this->maria));
    }

    public function test_colaborador_nao_abre_ficha_de_outro(): void
    {
        $service = $this->service();
        $access  = ['type' => 'employee_code', 'value' => '1001'];

        $this->assertTrue($service->canViewEmployee($access, $this->joao));
        $this->assertFalse($service->canViewEmployee($access, $this->maria));
    }

    public function test_funcionario_sem_setor_fica_invisivel_para_coordenador(): void
    {
        $orfao = Employee::create([
            'employee_code'  => '1003',
            'name'           => 'Sem Setor',
            'cpf'            => '12345678909',
            'admission_date' => '2020-01-10',
            'position'       => 'Analista',
            'department'     => 'Departamento Extinto',
            'sector_id'      => null,
        ]);

        $access = ['type' => 'sectors', 'values' => [$this->manutencao->id, $this->portaria->id]];

        $this->assertFalse($this->service()->canViewEmployee($access, $orfao));
    }

    public function test_setores_listados_saem_dos_funcionarios_visiveis(): void
    {
        $access = ['type' => 'sectors', 'values' => [$this->portaria->id]];

        $sectors = $this->service()->getSectors($access);

        // Só o setor de quem ele enxerga — não a lista inteira de setores.
        $this->assertSame([$this->portaria->id => 'Portaria'], $sectors->all());
    }
}
