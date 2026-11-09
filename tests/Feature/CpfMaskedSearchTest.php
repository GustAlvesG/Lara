<?php

namespace Tests\Feature;

use App\Models\Company\CompanyAccessLog;
use App\Services\CompanyService;
use App\Services\FreelancerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * Busca por CPF com e sem máscara (09/11/2026).
 *
 * Prestadores cadastrados em 06/2026 ficaram com o documento gravado como
 * 062.134.687-06; a portaria e o Lara Externos digitam só os dígitos, e a
 * pessoa "não existia". Agora as buscas casam os dois formatos, nos dois
 * sentidos.
 *
 * Sem RefreshDatabase (regra do projeto): só as migrations desta feature, no
 * SQLite :memory: do phpunit.xml, como em OneOffAccessTest.
 */
class CpfMaskedSearchTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    /** CPF válido — a consulta da portaria confere os dígitos verificadores. */
    private const DIGITS = '52998224725';
    private const MASKED = '529.982.247-25';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();
        Schema::create('users', fn ($table) => $table->id());

        Schema::withoutForeignKeyConstraints(function () {
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

        DB::table('companies')->insert(['id' => 1, 'name' => 'Escola Parceira', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function worker(string $document): int
    {
        return DB::table('company_workers')->insertGetId([
            'name' => 'Pedro Professor',
            'document' => $document,
            'company_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_portaria_acha_prestador_gravado_com_mascara_digitando_so_digitos(): void
    {
        $id = $this->worker(self::MASKED);

        $result = app(CompanyService::class)->validateTryToAccess(['target' => self::DIGITS]);

        $this->assertTrue($result['found']);
        $this->assertSame('worker', $result['type']);
        $this->assertSame($id, $result['workers'][0]['id']);
    }

    public function test_portaria_acha_prestador_gravado_sem_mascara_digitando_com_mascara(): void
    {
        $id = $this->worker(self::DIGITS);

        $result = app(CompanyService::class)->validateTryToAccess(['target' => self::MASKED]);

        $this->assertTrue($result['found']);
        $this->assertSame($id, $result['workers'][0]['id']);
    }

    public function test_api_do_lara_externos_acha_freelancer_gravado_com_mascara(): void
    {
        DB::table('freelancers')->insert(['name' => 'Ana Garçom', 'cpf' => self::MASKED, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('Ana Garçom', app(FreelancerService::class)->get(self::DIGITS)?->name);
        $this->assertSame('Ana Garçom', app(FreelancerService::class)->get(self::MASKED)?->name);
    }

    public function test_portaria_acha_freelancer_gravado_com_mascara(): void
    {
        DB::table('freelancers')->insert(['name' => 'Ana Garçom', 'cpf' => self::MASKED, 'created_at' => now(), 'updated_at' => now()]);

        $result = app(CompanyService::class)->validateTryToAccess(['target' => self::DIGITS]);

        $this->assertTrue($result['found']);
        $this->assertSame('freelancer', $result['workers'][0]['type']);
    }

    public function test_historico_acha_acesso_pelo_cpf_em_qualquer_formato(): void
    {
        $workerId = $this->worker(self::MASKED);
        DB::table('company_access_logs')->insert([
            'company_id' => 1, 'company_worker_id' => $workerId, 'target' => self::MASKED,
            'allowed' => true, 'reason' => 'access_granted', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(1, CompanyAccessLog::search(self::DIGITS)->count());
        $this->assertSame(1, CompanyAccessLog::search('52998224')->count());
        $this->assertSame(1, CompanyAccessLog::search(self::MASKED)->count());
    }
}
