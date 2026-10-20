<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * Módulo Funcionários: o CRUD do RH, a planilha, o setor do funcionário
 * levando o usuário de mesma matrícula para o setor, e a regra de bloqueio
 * (desligado, férias, afastamento) que o Uber e a Frota usam.
 *
 * Banco: só as migrations de acesso e as de funcionários, no SQLite em
 * memória, com a conexão do User apontando para o mesmo banco.
 */
class EmployeeModuleTest extends TestCase
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    private User $rh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateAccessSchema();
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_01_05_141304_banco_de_horas.php',
            'database/migrations/2026_09_01_090200_add_sector_and_employment_to_employees.php',
            'database/migrations/2026_09_01_090300_create_employee_absences_table.php',
            'database/migrations/2026_10_20_100000_prepare_employees_for_registry.php',
            // O layout consulta notificações e avisos em toda tela.
            'database/migrations/2026_06_10_000001_create_notifications_table.php',
            'database/migrations/2026_06_10_000002_create_avisos_table.php',
            'database/migrations/2026_06_10_000003_avisos_privacy_and_lembretes.php',
            'database/migrations/2026_06_10_000004_create_aviso_views_table.php',
            'database/migrations/2026_06_10_000006_create_tags_tables.php',
            'database/migrations/2026_06_12_000002_create_aviso_user_table.php',
            'database/migrations/2026_07_31_120000_add_mandatory_reading_to_avisos.php',
        ]]);
        $this->shareSqliteWithUserConnection();

        // TI tem acesso total: alcança `funcionarios.gerenciar` sem configurar setor.
        $this->rh = $this->makeUser('Pessoa do RH');
        $this->joinSector($this->rh, 'TI');
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'employee_code' => '12345',
            'name' => 'Maria da Silva',
            'cpf' => '529.982.247-25',
            'sector_id' => $this->sectorId('Portaria'),
        ], $attributes));
    }

    private function isMember(User $user, string $sector): bool
    {
        return DB::table('user_sector')
            ->where('user_id', $user->id)
            ->where('sector_id', $this->sectorId($sector))
            ->exists();
    }

    // --- Acesso ----------------------------------------------------------------

    public function test_so_quem_tem_a_permissao_abre_o_modulo(): void
    {
        $this->actingAs($this->makeUser('Sem Acesso'))->get('/funcionarios')->assertForbidden();
        $this->actingAs($this->rh)->get('/funcionarios')->assertOk()->assertSee('Funcionários');
    }

    public function test_enderecos_antigos_do_banco_de_horas_redirecionam(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->rh)->get('/comp-time/employees')->assertRedirect('/funcionarios');
        $this->actingAs($this->rh)->get('/comp-time/employees/' . $employee->id)->assertRedirect('/funcionarios/' . $employee->id);
    }

    // --- Cadastro --------------------------------------------------------------

    public function test_cadastra_com_cpf_so_em_digitos(): void
    {
        $this->actingAs($this->rh)->post('/funcionarios', [
            'employee_code' => '54321',
            'name' => 'João Souza',
            'cpf' => '111.444.777-35',
            'sector_id' => $this->sectorId('Portaria'),
            'position' => 'Motorista',
        ])->assertRedirect();

        $employee = Employee::where('employee_code', '54321')->firstOrFail();
        $this->assertSame('11144477735', $employee->cpf);
        $this->assertSame(Employee::SOURCE_MANUAL, $employee->source);

        $this->actingAs($this->rh)->get('/funcionarios/' . $employee->id)
            ->assertOk()->assertSee('111.444.777-35');
    }

    public function test_recusa_cpf_invalido_setor_ausente_e_duplicados(): void
    {
        $this->employee();

        $this->actingAs($this->rh)->post('/funcionarios', [
            'employee_code' => '12345',
            'name' => 'Outra',
            'cpf' => '123.456.789-00',
        ])->assertSessionHasErrors(['employee_code', 'cpf', 'sector_id']);

        // Mesmo CPF de um ativo, com máscara diferente: duplicado.
        $this->actingAs($this->rh)->post('/funcionarios', [
            'employee_code' => '99999',
            'name' => 'Outra',
            'cpf' => '52998224725',
            'sector_id' => $this->sectorId('Portaria'),
        ])->assertSessionHasErrors(['cpf']);
    }

    public function test_excluir_e_restaurar(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->rh)->delete('/funcionarios/' . $employee->id)->assertRedirect('/funcionarios');
        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
        $this->assertSame('Cadastro excluído', Employee::withTrashed()->find($employee->id)->blockReason());

        // A ficha do excluído continua abrindo, para restaurar.
        $this->actingAs($this->rh)->get('/funcionarios/' . $employee->id)->assertOk()->assertSee('Restaurar');

        $this->actingAs($this->rh)->post('/funcionarios/' . $employee->id . '/restaurar')->assertRedirect();
        $this->assertNull($employee->fresh()->deleted_at);
    }

    // --- Setor do funcionário → usuário ---------------------------------------

    public function test_setor_do_funcionario_coloca_o_usuario_de_mesma_matricula(): void
    {
        $user = $this->makeUser('Maria Usuária', ['matricula' => '12345']);

        $this->actingAs($this->rh)->post('/funcionarios', [
            'employee_code' => '12345',
            'name' => 'Maria da Silva',
            'cpf' => '52998224725',
            'sector_id' => $this->sectorId('Portaria'),
        ])->assertSessionHas('success', fn ($message) => str_contains($message, 'entrou no setor Portaria'));

        $this->assertTrue($this->isMember($user, 'Portaria'));
        $this->assertDatabaseHas('access_audit_logs', [
            'action' => 'sector.member_added',
            'user_id' => $user->id,
            'sector_id' => $this->sectorId('Portaria'),
        ]);
    }

    public function test_mudar_o_setor_acrescenta_sem_tirar_do_antigo(): void
    {
        $user = $this->makeUser('Maria Usuária', ['matricula' => '12345']);
        $employee = $this->employee();
        app(\App\Services\Employees\EmployeeSectorSync::class)->syncEmployee($employee);

        $this->actingAs($this->rh)->put('/funcionarios/' . $employee->id, [
            'employee_code' => '12345',
            'name' => 'Maria da Silva',
            'cpf' => '52998224725',
            'sector_id' => $this->sectorId('Comercial'),
        ])->assertRedirect();

        $this->assertTrue($this->isMember($user, 'Comercial'));
        $this->assertTrue($this->isMember($user, 'Portaria'));
    }

    public function test_o_caminho_inverso_nao_existe_e_coordenador_continua_coordenador(): void
    {
        $user = $this->makeUser('Maria Usuária', ['matricula' => '12345']);
        $this->joinSector($user, 'Portaria', 'coordinator');
        $this->joinSector($user, 'Comercial');

        $employee = $this->employee();
        app(\App\Services\Employees\EmployeeSectorSync::class)->syncEmployee($employee);

        // Já estava no setor: papel mantido.
        $this->assertSame('coordinator', DB::table('user_sector')
            ->where('user_id', $user->id)->where('sector_id', $this->sectorId('Portaria'))->value('role'));
        // O setor do usuário não mexe no do funcionário.
        $this->assertSame($this->sectorId('Portaria'), $employee->fresh()->sector_id);
    }

    public function test_desligado_nao_leva_ninguem_a_setor(): void
    {
        $user = $this->makeUser('Maria Usuária', ['matricula' => '12345']);
        $employee = $this->employee(['termination_date' => now()->subDay()->toDateString()]);

        $this->assertNull(app(\App\Services\Employees\EmployeeSectorSync::class)->syncEmployee($employee));
        $this->assertFalse($this->isMember($user, 'Portaria'));
    }

    public function test_usuario_que_ganha_a_matricula_entra_no_setor(): void
    {
        $this->employee();
        $user = $this->makeUser('Maria Usuária');
        $user->matricula = '12345';
        $user->save();

        app(\App\Services\Employees\EmployeeSectorSync::class)->syncUser($user);

        $this->assertTrue($this->isMember($user, 'Portaria'));
    }

    public function test_tela_de_usuarios_aplica_a_regra_ao_trocar_a_matricula(): void
    {
        $this->employee();
        $user = $this->makeUser('Maria Usuária', ['status_id' => 1]);

        $this->actingAs($this->rh)->put(route('users.update', $user->id), [
            'name' => $user->name,
            'email' => $user->email,
            'matricula' => '12345',
            'status' => 1,
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'entrou no setor Portaria'));

        $this->assertTrue($this->isMember($user, 'Portaria'));
    }

    public function test_meu_setor_aplica_a_regra_ao_cadastrar_com_matricula(): void
    {
        $this->employee();
        $coordinator = $this->makeUser('Coordenadora');
        $this->joinSector($coordinator, 'Comercial', 'coordinator');

        $this->actingAs($coordinator)->post(route('my-sector.users.store', $this->sectorId('Comercial')), [
            'name' => 'Maria da Silva',
            'email' => 'maria@teste.local',
            'matricula' => '12345',
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
        ])->assertSessionHas('success');

        $user = User::where('email', 'maria@teste.local')->firstOrFail();
        $this->assertTrue($this->isMember($user, 'Comercial'));
        $this->assertTrue($this->isMember($user, 'Portaria'));
    }

    // --- Bloqueio --------------------------------------------------------------

    public function test_ferias_afastamento_e_rescisao_bloqueiam(): void
    {
        $apto = $this->employee();
        $ferias = $this->employee(['employee_code' => '2', 'cpf' => '11144477735', 'name' => 'Férias']);
        $afastado = $this->employee(['employee_code' => '3', 'cpf' => '12345678909', 'name' => 'Afastado']);
        $desligado = $this->employee(['employee_code' => '4', 'cpf' => '39053344705', 'name' => 'Desligado',
            'termination_date' => '2026-10-01']);
        $rescisaoFutura = $this->employee(['employee_code' => '5', 'cpf' => '86288366757', 'name' => 'Aviso prévio',
            'termination_date' => now()->addMonth()->toDateString()]);

        EmployeeAbsence::create(['employee_id' => $ferias->id, 'type' => EmployeeAbsence::TYPE_VACATION,
            'start_date' => now()->subDays(2)->toDateString(), 'end_date' => now()->addDays(5)->toDateString()]);
        EmployeeAbsence::create(['employee_id' => $afastado->id, 'type' => EmployeeAbsence::TYPE_LEAVE,
            'start_date' => now()->subDay()->toDateString()]);
        // Férias que já acabaram não bloqueiam.
        EmployeeAbsence::create(['employee_id' => $apto->id, 'type' => EmployeeAbsence::TYPE_VACATION,
            'start_date' => '2026-01-01', 'end_date' => '2026-01-30']);

        $this->assertNull($apto->fresh()->blockReason());
        $this->assertNull($rescisaoFutura->fresh()->blockReason());
        $this->assertSame('Férias até ' . now()->addDays(5)->format('d/m/Y'), $ferias->fresh()->blockReason());
        $this->assertSame('Afastamento (sem data de retorno)', $afastado->fresh()->blockReason());
        $this->assertSame('Desligado em 01/10/2026', $desligado->fresh()->blockReason());

        $this->assertEqualsCanonicalizing(
            [$apto->id, $rescisaoFutura->id],
            Employee::eligible()->pluck('id')->all()
        );
    }

    // --- Planilha --------------------------------------------------------------

    private function planilha(array $rows, ?array $header = null): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $header ??= ['Matrícula *', 'Nome *', 'CPF *', 'Setor *', 'Cargo', 'Data de admissão'];

        foreach (array_merge([$header], $rows) as $r => $row) {
            foreach (array_values($row) as $c => $value) {
                $sheet->setCellValueExplicit([$c + 1, $r + 1], (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'funcionarios') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'funcionarios.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_planilha_cria_e_atualiza_pela_matricula(): void
    {
        $existing = $this->employee(['position' => 'Porteira']);
        $user = $this->makeUser('Novo Usuário', ['matricula' => '77777']);

        $this->actingAs($this->rh)->post('/funcionarios/import', ['spreadsheet' => $this->planilha([
            ['12345', 'Maria da Silva Souza', '529.982.247-25', 'comercial', '', ''],
            ['77777', 'Carlos Lima', '11144477735', 'Portaria', 'Motorista', '15/03/2024'],
        ])])->assertRedirect('/funcionarios')
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 funcionário(s) novo(s) e 1 atualizado(s)'));

        $existing->refresh();
        $this->assertSame('Maria da Silva Souza', $existing->name);
        $this->assertSame($this->sectorId('Comercial'), $existing->sector_id);
        // Cargo em branco na planilha não apaga o gravado.
        $this->assertSame('Porteira', $existing->position);

        $novo = Employee::where('employee_code', '77777')->firstOrFail();
        $this->assertSame(Employee::SOURCE_SPREADSHEET, $novo->source);
        $this->assertSame('2024-03-15', $novo->admission_date->toDateString());
        $this->assertTrue($this->isMember($user, 'Portaria'));
    }

    public function test_planilha_com_erro_nao_grava_nada(): void
    {
        $this->actingAs($this->rh)->post('/funcionarios/import', ['spreadsheet' => $this->planilha([
            ['10001', 'Ana', '52998224725', 'Portaria', '', ''],
            ['10002', 'Bia', '52998224725', 'Portaria', '', ''],
            ['10003', 'Caio', '11144477735', 'Setor Que Não Existe', '', ''],
            ['10004', 'Davi', '123', 'Portaria', '', ''],
            ['10001', 'Eva', '12345678909', 'Portaria', '', ''],
        ])])->assertRedirect()
            ->assertSessionHas('import_errors', function (array $errors) {
                $text = implode("\n", $errors);

                return str_contains($text, 'Linha 3: CPF 529.982.247-25 repetido')
                    && str_contains($text, 'Linha 4: Setor "Setor Que Não Existe" não existe')
                    && str_contains($text, 'Linha 5: O CPF informado não é válido')
                    && str_contains($text, 'Linha 6: Matrícula 10001 repetida');
            });

        $this->assertSame(0, Employee::count());
    }

    public function test_planilha_sem_coluna_obrigatoria_e_recusada(): void
    {
        $this->actingAs($this->rh)->post('/funcionarios/import', ['spreadsheet' => $this->planilha(
            [['10001', 'Ana', '52998224725']],
            ['Matrícula', 'Nome', 'CPF'],
        )])->assertRedirect()->assertSessionHas('error', fn ($m) => str_contains($m, 'Setor'));
    }

    public function test_planilha_nao_ressuscita_excluido(): void
    {
        $this->employee()->delete();

        $this->actingAs($this->rh)->post('/funcionarios/import', ['spreadsheet' => $this->planilha([
            ['12345', 'Maria', '52998224725', 'Portaria', '', ''],
        ])])->assertSessionHas('import_errors', fn ($e) => str_contains($e[0], 'funcionário excluído'));
    }

    public function test_modelo_da_planilha_baixa(): void
    {
        $this->actingAs($this->rh)->get('/funcionarios/import/template')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    // --- Setores ---------------------------------------------------------------

    public function test_setor_com_funcionario_nao_pode_ser_excluido(): void
    {
        $sector = Sector::create(['name' => 'Manutenção']);
        $this->employee(['sector_id' => $sector->id]);

        $this->actingAs($this->rh)->delete(route('sectors.destroy', $sector->id))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'funcionário'));

        $this->assertNotNull($sector->fresh());
    }
}
