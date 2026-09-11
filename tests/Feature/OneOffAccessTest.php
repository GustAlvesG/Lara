<?php

namespace Tests\Feature;

use App\Models\Company\CompanyAccessLog;
use App\Models\Company\OneOffAccess;
use App\Services\CompanyService;
use App\Services\OneOffAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * Liberação pontual: uma entrada, no dia da criação, sem empresa. A portaria
 * a encontra pelo CPF, no mesmo resultado de terceirizado e freelancer.
 *
 * Sem RefreshDatabase pelo mesmo motivo de UberAccessValidationTest: só as
 * migrations desta feature, no SQLite :memory: do phpunit.xml. Sem autor nas
 * liberações criadas aqui — a model User fixa a conexão `mysql`, que não
 * existe na suíte, e o belongsTo com chave nula nem chega a consultá-la.
 */
class OneOffAccessTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    /** PNG 1×1 válido. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** CPF válido — a consulta da portaria confere os dígitos verificadores. */
    private const CPF = '52998224725';

    private string $publico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();

        // A migration de one_off_accesses tem FK para `users`, e o SQLite
        // confere a tabela referenciada a cada insert. Basta ela existir: a
        // model User nunca é consultada aqui (fixa a conexão `mysql`).
        Schema::create('users', fn ($table) => $table->id());

        Schema::withoutForeignKeyConstraints(function () {
            // company_access_logs tem FK para os pedidos de Uber.
            (require base_path('database/migrations/2026_07_20_150000_create_uber_access_requests_tables.php'))->up();
            (require base_path('database/migrations/2026_01_14_151220_create_companies_table.php'))->up();
            (require base_path('database/migrations/2026_01_14_151348_create_company_workers_table.php'))->up();
            (require base_path('database/migrations/2026_01_14_151811_create_company_access_rules_table.php'))->up();
            (require base_path('database/migrations/2026_06_02_100000_create_company_access_logs_table.php'))->up();
            (require base_path('database/migrations/2026_06_10_000000_create_app_drivers_table.php'))->up();
            (require base_path('database/migrations/2026_06_10_000100_add_app_driver_to_company_access_logs.php'))->up();
            (require base_path('database/migrations/2026_07_21_000000_add_uber_fields_to_company_access_logs.php'))->up();
            (require base_path('database/migrations/2026_08_03_130000_add_freelancer_to_company_access_logs.php'))->up();
            (require base_path('database/migrations/2026_09_11_140000_create_one_off_accesses_table.php'))->up();
            (require base_path('database/migrations/2026_09_11_140100_add_one_off_access_to_company_access_logs.php'))->up();
        });

        $this->publico = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lpr-one-off-' . uniqid();
        File::ensureDirectoryExists($this->publico . DIRECTORY_SEPARATOR . 'images');
        $this->app->usePublicPath($this->publico);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publico);

        parent::tearDown();
    }

    private function companyService(): CompanyService
    {
        return app(CompanyService::class);
    }

    private function oneOffService(): OneOffAccessService
    {
        return app(OneOffAccessService::class);
    }

    private function grant(array $extra = []): OneOffAccess
    {
        return $this->oneOffService()->create(array_merge([
            'cpf' => self::CPF,
            'name' => 'João Técnico',
            'reason' => 'Pane no elevador social',
        ], $extra));
    }

    public function test_a_portaria_encontra_a_liberacao_pelo_cpf_mascarado(): void
    {
        $access = $this->grant();

        $result = $this->companyService()->validateTryToAccess(['target' => '529.982.247-25']);

        $this->assertTrue($result['found']);
        $this->assertSame('one_off', $result['type']);
        $this->assertSame(CompanyService::ONE_OFF_LABEL, $result['company']);
        $this->assertCount(1, $result['workers']);

        $entry = $result['workers'][0];
        $this->assertSame($access->id, $entry['id']);
        $this->assertSame('one_off', $entry['type']);
        $this->assertSame('João Técnico', $entry['name']);
        $this->assertTrue($entry['allowed']);
        $this->assertSame('one_off_access_granted', $entry['reason']);
        $this->assertSame('Pane no elevador social', $entry['one_off']['reason']);
        $this->assertNull($entry['one_off']['used_at']);
    }

    public function test_consultar_nao_gasta_a_liberacao(): void
    {
        $access = $this->grant();

        $this->companyService()->validateTryToAccess(['target' => self::CPF]);
        $this->companyService()->validateTryToAccess(['target' => self::CPF]);

        $this->assertNull($access->fresh()->used_at);
        $this->assertSame(0, CompanyAccessLog::count());
    }

    public function test_registrar_gasta_a_liberacao_e_a_segunda_entrada_e_negada(): void
    {
        $access = $this->grant();

        $first = $this->companyService()->registerAccess(['target' => self::CPF]);

        $this->assertTrue($first['workers'][0]['allowed']);
        $this->assertNotNull($first['workers'][0]['one_off']['used_at']);
        $this->assertNotNull($access->fresh()->used_at);
        $this->assertDatabaseHas('company_access_logs', [
            'one_off_access_id' => $access->id,
            'company_id'        => null,
            'target'            => self::CPF,
            'allowed'           => true,
            'reason'            => 'one_off_access_granted',
        ]);

        // A pessoa volta: a portaria ainda a encontra, mas negada e sabendo por quê.
        $again = $this->companyService()->validateTryToAccess(['target' => self::CPF]);
        $this->assertTrue($again['found']);
        $this->assertFalse($again['workers'][0]['allowed']);
        $this->assertSame('one_off_access_used', $again['workers'][0]['reason']);

        $this->companyService()->registerAccess(['target' => self::CPF]);
        $this->assertDatabaseHas('company_access_logs', [
            'one_off_access_id' => $access->id,
            'allowed'           => false,
            'reason'            => 'one_off_access_used',
        ]);
    }

    public function test_botao_do_monitor_registra_uma_vez_so(): void
    {
        $access = $this->grant();

        $first = $this->companyService()->registerOneOffAccess($access->id);
        $second = $this->companyService()->registerOneOffAccess($access->id);

        $this->assertTrue($first['workers'][0]['allowed']);
        $this->assertFalse($second['workers'][0]['allowed']);
        $this->assertSame('one_off_access_used', $second['workers'][0]['reason']);
        $this->assertSame(1, CompanyAccessLog::where('allowed', true)->count());
    }

    /**
     * Validar e registrar são dois momentos. Se outro registro gastou a
     * liberação no meio, o segundo grava — e devolve — negado.
     */
    public function test_liberacao_gasta_entre_a_consulta_e_o_registro_vira_negada(): void
    {
        $access = $this->grant();

        $this->assertTrue($this->oneOffService()->consume($access));
        $this->assertFalse($this->oneOffService()->consume($access));

        $result = $this->companyService()->registerOneOffAccess($access->id);

        $this->assertFalse($result['workers'][0]['allowed']);
        $this->assertSame(0, CompanyAccessLog::where('allowed', true)->count());
    }

    public function test_liberacao_de_outro_dia_nao_existe_para_a_portaria(): void
    {
        $access = $this->grant();
        $access->forceFill(['access_date' => today()->subDay()])->save();

        $result = $this->companyService()->validateTryToAccess(['target' => self::CPF]);

        $this->assertFalse($result['found']);
        $this->assertSame('worker_not_found', $result['reason']);
        $this->assertFalse($this->oneOffService()->consume($access->fresh()));
        $this->assertSame(OneOffAccess::STATUS_EXPIRED, $access->fresh()->status());
    }

    public function test_liberacao_cancelada_nao_existe_para_a_portaria(): void
    {
        $access = $this->grant();

        $this->oneOffService()->cancel($access);

        $this->assertFalse($this->companyService()->validateTryToAccess(['target' => self::CPF])['found']);
        $this->assertSame(OneOffAccess::STATUS_CANCELED, $access->fresh()->status());
    }

    public function test_liberacao_usada_nao_pode_ser_cancelada(): void
    {
        $access = $this->grant();
        $this->companyService()->registerOneOffAccess($access->id);

        $this->expectException(ValidationException::class);

        $this->oneOffService()->cancel($access->fresh());
    }

    public function test_nao_cria_duas_liberacoes_abertas_para_o_mesmo_cpf_no_dia(): void
    {
        $this->grant();

        try {
            $this->grant(['cpf' => '529.982.247-25']);
            $this->fail('A segunda liberação aberta deveria ser recusada.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cpf', $e->errors());
        }

        $this->assertSame(1, OneOffAccess::count());
    }

    /** Depois de usada, a nova entrada é outra autorização — e pode ser criada. */
    public function test_depois_de_usada_uma_nova_liberacao_pode_ser_criada(): void
    {
        $first = $this->grant();
        $this->companyService()->registerOneOffAccess($first->id);

        $second = $this->grant(['reason' => 'Voltou para buscar a ferramenta']);

        $entry = $this->companyService()->validateTryToAccess(['target' => self::CPF])['workers'][0];
        $this->assertSame($second->id, $entry['id']);
        $this->assertTrue($entry['allowed']);
    }

    /** Terceirizado negado pelas regras e liberação pontual: as duas linhas aparecem. */
    public function test_soma_com_o_terceirizado_do_mesmo_cpf(): void
    {
        $companyId = DB::table('companies')->insertGetId(['name' => 'Parceira', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_workers')->insert([
            'company_id' => $companyId,
            'name'       => 'João Terceirizado',
            'email'      => 'joao@example.com',
            'position'   => 'Técnico',
            'document'   => self::CPF,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $access = $this->grant();

        $result = $this->companyService()->validateTryToAccess(['target' => self::CPF]);

        $this->assertSame('worker', $result['type']);
        $this->assertSame('Parceira', $result['company']);
        $this->assertSame(['worker', 'one_off'], array_column($result['workers'], 'type'));
        $this->assertFalse($result['workers'][0]['allowed']); // sem regra de inclusão
        $this->assertTrue($result['workers'][1]['allowed']);

        // Registrar grava as duas, cada uma na sua coluna.
        $this->companyService()->registerAccess(['target' => self::CPF]);
        $this->assertDatabaseHas('company_access_logs', ['company_id' => $companyId, 'allowed' => false]);
        $this->assertDatabaseHas('company_access_logs', ['one_off_access_id' => $access->id, 'company_worker_id' => null, 'allowed' => true]);
    }

    public function test_foto_opcional_vira_arquivo_em_public_images(): void
    {
        $access = $this->grant(['image' => 'data:image/png;base64,' . self::PNG]);

        $this->assertStringStartsWith('one_off_', $access->image);
        $this->assertFileExists(public_path('images/' . $access->image));
        $this->assertSame(asset('images/' . $access->image), $access->imageUrl());

        $entry = $this->companyService()->validateTryToAccess(['target' => self::CPF])['workers'][0];
        $this->assertSame($access->imageUrl(), $entry['image']);
    }

    public function test_sem_foto_nada_e_gravado(): void
    {
        $access = $this->grant(['image' => null]);

        $this->assertNull($access->image);
        $this->assertSame([], File::files(public_path('images')));
    }

    public function test_foto_invalida_e_recusada(): void
    {
        $this->expectException(ValidationException::class);

        $this->grant(['image' => 'data:image/png;base64,' . base64_encode('não é imagem')]);
    }
}
