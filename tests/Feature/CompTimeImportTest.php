<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Sector;
use App\Models\TimeAdjustment;
use App\Models\TimeEntry;
use App\Services\CompTimeService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Importação do espelho de ponto: parsing, duplicatas e compensação.
 *
 * Sem RefreshDatabase pelo mesmo motivo registrado em FleetMileageTest: a
 * cadeia completa de migrations falha hoje. Aqui só as migrations do Banco de
 * Horas são aplicadas no SQLite :memory: do phpunit.xml.
 */
class CompTimeImportTest extends TestCase
{
    private string $tempDir;

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

        $this->tempDir = sys_get_temp_dir() . '/comp-time-tests-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);

        parent::tearDown();
    }

    private function service(): CompTimeService
    {
        // Instância nova a cada chamada: o parse fica memorizado por instância,
        // e um teste que reescreve o arquivo precisa de leitura limpa.
        return new CompTimeService();
    }

    // ------------------------------------------------------------------
    // Fixture: um espelho de ponto mínimo, no formato que o parser espera
    // ------------------------------------------------------------------

    /**
     * @param array $days cada item: ['date','schedule','punches','observation','debit','credit','approval']
     */
    private function mirrorFile(array $employees): string
    {
        $html = '<html><body>';

        foreach ($employees as $employee) {
            $html .= $this->infoTable($employee);
            $html .= $this->scheduleTable($employee['days'] ?? []);
            // O relatório repete um bloco de 4 tabelas por funcionário; as duas
            // últimas são rodapé e o parser as pula.
            $html .= '<table><tr><td>rodape</td></tr></table>';
            $html .= '<table><tr><td>rodape</td></tr></table>';
        }

        $html .= '</body></html>';

        $path = $this->tempDir . '/' . uniqid('mirror') . '.html';
        file_put_contents($path, $html);

        return $path;
    }

    private function infoTable(array $employee): string
    {
        return '<table><tr><td><div data-bind="with: InfoFuncionario">'
            . '<span data-bind="text: Nome">' . ($employee['name'] ?? 'João da Silva') . '</span>'
            . '<span data-bind="text: Cargo">' . ($employee['position'] ?? 'Analista') . '</span>'
            . '<span data-bind="text: Matricula">' . ($employee['code'] ?? '1001') . '</span>'
            . '<span data-bind="text: Estrutura">' . ($employee['department'] ?? 'Manutenção') . '</span>'
            . '<span data-bind="text: CPF">' . ($employee['cpf'] ?? '12345678909') . '</span>'
            . '<span data-bind="text: DataAdmissao">' . ($employee['admission'] ?? '10/01/2020') . '</span>'
            . '</div></td></tr></table>';
    }

    private function scheduleTable(array $days): string
    {
        $rows = '';

        foreach ($days as $day) {
            // Doze <td> por linha: o parser lê débito, crédito, observação e
            // aval por posição fixa (ver as constantes COL_* do service).
            $rows .= '<tr class="relatorioEspelhoPontoBodyRow">'
                . '<td data-bind="text: Data">' . $day['date'] . ' Segunda</td>'
                . '<td data-bind="text: Horario">' . ($day['schedule'] ?? '08:00 as 18:00') . '</td>'
                . '<td data-bind="html: Apontamentos">' . ($day['punches'] ?? '08:00 12:00 13:00 18:00') . '</td>'
                . '<td></td>'
                . '<td>' . ($day['credit_fallback'] ?? '') . '</td>'
                . '<td></td><td></td><td></td>'
                . '<td>' . ($day['observation'] ?? '') . '</td>'
                . '<td>' . ($day['debit'] ?? '') . '</td>'
                . '<td>' . ($day['credit'] ?? '') . '</td>'
                . '<td>' . ($day['approval'] ?? '') . '</td>'
                . '</tr>';
        }

        return '<table>' . $rows . '</table>';
    }

    // ------------------------------------------------------------------
    // Parsing
    // ------------------------------------------------------------------

    public function test_importa_credito_e_debito_e_cria_o_funcionario(): void
    {
        $path = $this->mirrorFile([[
            'code' => '1001',
            'name' => 'João da Silva',
            'days' => [
                ['date' => '01/06/2026', 'credit' => '01:30'],
                ['date' => '02/06/2026', 'debit'  => '00:45'],
            ],
        ]]);

        $summary = $this->service()->importFile($path);

        $employee = Employee::where('employee_code', '1001')->first();
        $this->assertNotNull($employee);
        $this->assertSame('João da Silva', $employee->name);

        $this->assertSame(2, $summary['created']);
        $this->assertSame(90, TimeEntry::where('type', 'CREDIT')->value('amount_minutes'));
        $this->assertSame(45, TimeEntry::where('type', 'DEBIT')->value('amount_minutes'));
    }

    public function test_dia_sem_credito_nem_debito_nao_vira_lancamento(): void
    {
        $path = $this->mirrorFile([[
            'days' => [
                ['date' => '01/06/2026'],
                ['date' => '02/06/2026'],
            ],
        ]]);

        $this->service()->importFile($path);

        // Antes cada dia normal virava um registro de tipo 'Padrão' com zero
        // minuto, que não entrava em conta nenhuma.
        $this->assertSame(0, TimeEntry::count());
        $this->assertSame(0, TimeEntry::where('type', 'Padrão')->count());
    }

    public function test_feriado_e_dsr_sao_ignorados(): void
    {
        $path = $this->mirrorFile([[
            'days' => [
                ['date' => '01/06/2026', 'schedule' => 'Feriado', 'credit' => '02:00', 'approval' => 'Creditar'],
                ['date' => '02/06/2026', 'debit' => '01:00', 'observation' => 'DSR nao trabalhado'],
                ['date' => '03/06/2026', 'debit' => '00:30'],
            ],
        ]]);

        $this->service()->importFile($path);

        $this->assertSame(1, TimeEntry::count());
        $this->assertSame(30, TimeEntry::where('type', 'DEBIT')->value('amount_minutes'));
    }

    public function test_credito_acima_do_teto_so_entra_com_aval(): void
    {
        $path = $this->mirrorFile([[
            'days' => [
                ['date' => '01/06/2026', 'credit' => '03:00'],
                ['date' => '02/06/2026', 'credit' => '03:00', 'approval' => 'Creditar'],
                ['date' => '03/06/2026', 'credit' => '01:00'],
            ],
        ]]);

        $this->service()->importFile($path);

        $credits = TimeEntry::where('type', 'CREDIT')->orderBy('entry_date')->pluck('amount_minutes')->all();

        // 03:00 sem aval cai fora; 03:00 com aval e 01:00 (abaixo do teto) ficam.
        $this->assertSame([180, 60], $credits);
    }

    public function test_linhas_repetidas_no_mesmo_arquivo_colapsam_na_ultima(): void
    {
        $path = $this->mirrorFile([[
            'days' => [
                ['date' => '01/06/2026', 'credit' => '01:00'],
                ['date' => '01/06/2026', 'credit' => '02:00', 'approval' => 'Creditar'],
            ],
        ]]);

        $result = $this->service()->detectDuplicates($path);

        // A colisão morre no parse: a detecção só vê uma linha, e é a última.
        $this->assertCount(1, $result['new_entries']);
        $this->assertCount(0, $result['duplicate_entries']);
        $this->assertSame(120, $result['new_entries'][0]['amount_minutes']);
    }

    // ------------------------------------------------------------------
    // Setor
    // ------------------------------------------------------------------

    public function test_importacao_cria_o_setor_do_departamento(): void
    {
        $path = $this->mirrorFile([[
            'code' => '1001', 'department' => 'Manutenção',
            'days' => [['date' => '01/06/2026', 'credit' => '01:00']],
        ]]);

        $this->service()->importFile($path);

        $sector = Sector::where('name', 'Manutenção')->first();
        $this->assertNotNull($sector);
        $this->assertSame($sector->id, Employee::where('employee_code', '1001')->value('sector_id'));
    }

    public function test_setor_existente_e_reaproveitado_sem_diferenciar_caixa(): void
    {
        $existing = Sector::create(['name' => 'Manutenção']);

        $path = $this->mirrorFile([[
            'code' => '1001', 'department' => '  manutenção ',
            'days' => [['date' => '01/06/2026', 'credit' => '01:00']],
        ]]);

        $this->service()->importFile($path);

        $this->assertSame(1, Sector::where('name', 'like', '%anuten%')->count());
        $this->assertSame($existing->id, Employee::where('employee_code', '1001')->value('sector_id'));
    }

    // ------------------------------------------------------------------
    // Duplicatas contra o banco
    // ------------------------------------------------------------------

    public function test_detecta_duplicata_contra_o_que_ja_esta_no_banco(): void
    {
        $first = $this->mirrorFile([[
            'code' => '1001',
            'days' => [['date' => '01/06/2026', 'credit' => '01:00']],
        ]]);
        $this->service()->importFile($first);

        $second = $this->mirrorFile([[
            'code' => '1001',
            'days' => [
                ['date' => '01/06/2026', 'credit' => '01:30'],
                ['date' => '02/06/2026', 'credit' => '00:30'],
            ],
        ]]);

        $result = $this->service()->detectDuplicates($second);

        $this->assertCount(1, $result['duplicate_entries']);
        $this->assertCount(1, $result['new_entries']);
        $this->assertSame(60, $result['duplicate_entries'][0]['old_amount_minutes']);
        $this->assertSame(90, $result['duplicate_entries'][0]['amount_minutes']);
    }

    public function test_duplicata_recusada_na_revisao_nao_sobrescreve(): void
    {
        $first = $this->mirrorFile([[
            'code' => '1001',
            'days' => [['date' => '01/06/2026', 'credit' => '01:00']],
        ]]);
        $this->service()->importFile($first);

        $second = $this->mirrorFile([[
            'code' => '1001',
            'days' => [
                ['date' => '01/06/2026', 'credit' => '01:30'],
                ['date' => '02/06/2026', 'credit' => '00:30'],
            ],
        ]]);

        // Nenhum id aceito: a duplicata é ignorada, a linha nova entra.
        $summary = $this->service()->importWithDecisions($second, []);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(0, $summary['updated']);
        $this->assertSame(1, $summary['skipped']);

        $employee = Employee::where('employee_code', '1001')->first();
        $this->assertSame(60, TimeEntry::where('employee_id', $employee->id)
            ->whereDate('entry_date', '2026-06-01')->value('amount_minutes'));
    }

    public function test_duplicata_aceita_na_revisao_sobrescreve(): void
    {
        $first = $this->mirrorFile([[
            'code' => '1001',
            'days' => [['date' => '01/06/2026', 'credit' => '01:00']],
        ]]);
        $this->service()->importFile($first);

        $existingId = TimeEntry::first()->id;

        $second = $this->mirrorFile([[
            'code' => '1001',
            'days' => [['date' => '01/06/2026', 'credit' => '01:30']],
        ]]);

        $summary = $this->service()->importWithDecisions($second, [$existingId]);

        $this->assertSame(1, $summary['updated']);
        $this->assertSame(90, TimeEntry::find($existingId)->amount_minutes);
    }

    // ------------------------------------------------------------------
    // Compensação
    // ------------------------------------------------------------------

    public function test_credito_compensa_debito_e_registra_o_ajuste(): void
    {
        $path = $this->mirrorFile([[
            'code' => '1001',
            'days' => [
                ['date' => '01/06/2026', 'debit'  => '01:00'],
                ['date' => '02/06/2026', 'credit' => '00:40'],
            ],
        ]]);

        $this->service()->importFile($path);

        $debit  = TimeEntry::where('type', 'DEBIT')->first();
        $credit = TimeEntry::where('type', 'CREDIT')->first();

        // 40 do crédito abatem 40 dos 60 do débito.
        $this->assertSame(0, $credit->balance_minutes);
        $this->assertSame(20, $debit->balance_minutes);

        // Um ajuste de cada lado, para que as duas fichas contem a mesma história.
        $this->assertSame(2, TimeAdjustment::count());
        $this->assertSame(40, TimeAdjustment::where('entry_time_to_adjust_id', $credit->id)->value('amount_minutes'));
        $this->assertSame(40, TimeAdjustment::where('entry_time_to_adjust_id', $debit->id)->value('amount_minutes'));
    }

    public function test_recalculo_nao_duplica_ajustes(): void
    {
        $path = $this->mirrorFile([[
            'code' => '1001',
            'days' => [
                ['date' => '01/06/2026', 'debit'  => '01:00'],
                ['date' => '02/06/2026', 'credit' => '00:40'],
            ],
        ]]);

        $service = $this->service();
        $service->importFile($path);

        $adjustmentsAfterImport = TimeAdjustment::count();

        $service->recalculateAllBalances();

        $this->assertSame($adjustmentsAfterImport, TimeAdjustment::count());
        $this->assertSame(20, TimeEntry::where('type', 'DEBIT')->value('balance_minutes'));
    }

    public function test_importar_o_mesmo_arquivo_duas_vezes_nao_dobra_o_saldo(): void
    {
        $path = $this->mirrorFile([[
            'code' => '1001',
            'days' => [
                ['date' => '01/06/2026', 'debit'  => '01:00'],
                ['date' => '02/06/2026', 'credit' => '00:40'],
            ],
        ]]);

        $this->service()->importFile($path);
        $this->service()->importFile($path);

        $this->assertSame(2, TimeEntry::count());
        $this->assertSame(2, TimeAdjustment::count());
        $this->assertSame(20, TimeEntry::where('type', 'DEBIT')->value('balance_minutes'));
    }
}
