<?php

namespace Tests\Feature;

use App\Jobs\SendPoliTextMessage;
use App\Models\UberAccessRequest;
use App\Services\CompanyService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Sem RefreshDatabase pelo mesmo motivo registrado em LaraMessageHistoryTest:
 * a cadeia completa de migrations falha hoje em `add_columns_member` x
 * `tourments`. Aqui só as migrations desta feature são aplicadas, no SQLite
 * :memory: do phpunit.xml — cada teste recebe um banco novo.
 */
class UberAccessValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sem FKs: company_access_logs referencia empresas/trabalhadores que
        // não têm papel no acesso de Uber, e exigi-las arrastaria meia dúzia
        // de migrations sem relação com o que está sendo testado aqui.
        Schema::withoutForeignKeyConstraints(function () {
            (require base_path('database/migrations/2026_07_20_150000_create_uber_access_requests_tables.php'))->up();
            (require base_path('database/migrations/2026_07_21_120000_add_matricula_to_uber_access_requests.php'))->up();
            (require base_path('database/migrations/2026_08_27_170000_add_member_validation_to_uber_access_requests.php'))->up();

            // registerUberAccess grava no histórico unificado de acessos, cujo
            // schema referencia as tabelas de empresa/trabalhador.
            (require base_path('database/migrations/2026_01_14_151220_create_companies_table.php'))->up();
            (require base_path('database/migrations/2026_01_14_151348_create_company_workers_table.php'))->up();
            (require base_path('database/migrations/2026_06_02_100000_create_company_access_logs_table.php'))->up();
            (require base_path('database/migrations/2026_06_10_000000_create_app_drivers_table.php'))->up();
            (require base_path('database/migrations/2026_06_10_000100_add_app_driver_to_company_access_logs.php'))->up();
            (require base_path('database/migrations/2026_07_21_000000_add_uber_fields_to_company_access_logs.php'))->up();
        });
    }

    /**
     * Placa Mercosul aleatória por teste. Como a suíte roda no mesmo banco de
     * desenvolvimento (DatabaseTransactions apenas desfaz o que o teste cria,
     * não linhas pré-existentes), usar uma placa única evita colisão com
     * pedidos de Uber reais que já estejam cadastrados.
     */
    private function uniquePlate(): string
    {
        $letters = fn (int $n) => collect(range(1, $n))
            ->map(fn () => chr(random_int(65, 90)))
            ->implode('');

        return $letters(3) . random_int(0, 9) . $letters(1) . random_int(10, 99);
    }

    private function service(): CompanyService
    {
        return app(CompanyService::class);
    }

    private function makeRequest(
        string $plate,
        ?string $expiresAt,
        string $status = UberAccessRequest::STATUS_AGUARDANDO_ACESSO
    ): UberAccessRequest {
        return UberAccessRequest::create([
            'contact_uuid'   => 'uuid-' . $plate,
            'contact_phone'  => '5524999990000',
            'status'         => $status,
            'matricula'      => '987654',
            'requester_name' => 'Fulano',
            'club_location'  => 'Sede',
            'vehicle_plate'  => $plate,
            'screenshot_url' => 'https://cdn.example.com/prints/' . $plate . '.jpg',
            'completed_at'   => now(),
            'expires_at'     => $expiresAt,
        ]);
    }

    public function test_grants_access_for_exact_plate_within_validity(): void
    {
        $plate = $this->uniquePlate();
        $this->makeRequest($plate, now()->addMinutes(10));

        // Envia com pontuação/minúscula para exercitar a normalização.
        $result = $this->service()->validateTryToAccess([
            'target' => strtolower(substr($plate, 0, 3) . '-' . substr($plate, 3)),
        ]);

        $this->assertTrue($result['found']);
        $this->assertSame('uber', $result['type']);
        $this->assertSame($plate, $result['plate']);
        $this->assertSame('https://cdn.example.com/prints/' . $plate . '.jpg', $result['uber']['screenshot_url']);
        $this->assertSame('987654', $result['uber']['matricula']);
        $this->assertSame('987654', $result['workers'][0]['matricula']);
        $this->assertTrue($result['workers'][0]['allowed']);
    }

    public function test_denies_access_when_expired(): void
    {
        $plate = $this->uniquePlate();
        $this->makeRequest($plate, now()->subMinute());

        $result = $this->service()->validateTryToAccess(['target' => $plate]);

        $this->assertFalse($result['found']);
        $this->assertSame('uber_not_found', $result['reason']);
    }

    public function test_denies_access_when_plate_not_registered(): void
    {
        // Placa válida no formato, porém sem nenhum pedido cadastrado.
        $result = $this->service()->validateTryToAccess(['target' => $this->uniquePlate()]);

        $this->assertFalse($result['found']);
        $this->assertSame('uber_not_found', $result['reason']);
    }

    public function test_denies_access_when_already_concluded(): void
    {
        $plate = $this->uniquePlate();
        // Pedido já acessado (concluido) não pode liberar de novo, mesmo dentro
        // do que seria a validade.
        $this->makeRequest($plate, now()->addMinutes(10), UberAccessRequest::STATUS_CONCLUIDO);

        $result = $this->service()->validateTryToAccess(['target' => $plate]);

        $this->assertFalse($result['found']);
        $this->assertSame('uber_not_found', $result['reason']);
    }

    public function test_register_concludes_expires_and_logs(): void
    {
        $plate = $this->uniquePlate();
        $request = $this->makeRequest($plate, now()->addMinutes(10));

        $result = $this->service()->registerAccess(['target' => $plate]);

        $this->assertTrue($result['found']);
        $this->assertSame('987654', $result['uber']['matricula']);

        $fresh = $request->fresh();
        $this->assertSame(UberAccessRequest::STATUS_CONCLUIDO, $fresh->status);
        $this->assertNotNull($fresh->accessed_at);
        // expires_at foi vencido no ato do acesso.
        $this->assertTrue($fresh->expires_at->lessThanOrEqualTo(now()));

        // O log anexa a imagem da solicitação e o vínculo ao pedido, para
        // consulta posterior na aba de carros de aplicativo.
        $this->assertDatabaseHas('company_access_logs', [
            'target'                 => $plate,
            'allowed'                => true,
            'reason'                 => 'uber_access_granted',
            'uber_access_request_id' => $request->id,
            'screenshot_url'         => 'https://cdn.example.com/prints/' . $plate . '.jpg',
        ]);

        // Segunda tentativa com a mesma placa não é mais liberada.
        $this->assertFalse($this->service()->validateTryToAccess(['target' => $plate])['found']);
    }

    /**
     * O aviso de chegada sai do mesmo ato que libera o acesso, mas por fila:
     * o porteiro não espera a Poli responder.
     */
    public function test_register_enfileira_o_aviso_de_chegada(): void
    {
        Queue::fake();

        $plate = $this->uniquePlate();
        $request = $this->makeRequest($plate, now()->addMinutes(10));

        $this->service()->registerAccess(['target' => $plate]);

        Queue::assertPushed(
            SendPoliTextMessage::class,
            fn (SendPoliTextMessage $job) => $job->phone === '5524999990000'
                && $job->contactUuid === 'uuid-' . $plate
                && $job->uberAccessRequestId === $request->id
                && $job->text === 'Olá, Fulano! Seu carro de aplicativo, placa ' . $plate
                    . ', chegou à portaria e o acesso foi liberado. Ele está a caminho de Sede.'
        );
    }

    /**
     * Placa não encontrada não avisa ninguém — não há a quem avisar.
     */
    public function test_placa_desconhecida_nao_enfileira_aviso(): void
    {
        Queue::fake();

        $this->service()->registerAccess(['target' => $this->uniquePlate()]);

        Queue::assertNotPushed(SendPoliTextMessage::class);
    }

    /**
     * Pedido sem telefone gravado: o acesso é liberado igual, só não há aviso.
     * A coluna é NOT NULL, então "sem telefone" na prática é string vazia.
     */
    public function test_pedido_sem_telefone_libera_acesso_sem_aviso(): void
    {
        Queue::fake();

        $plate = $this->uniquePlate();
        $request = $this->makeRequest($plate, now()->addMinutes(10));
        $request->update(['contact_phone' => '']);

        $result = $this->service()->registerAccess(['target' => $plate]);

        $this->assertTrue($result['found']);
        $this->assertSame(UberAccessRequest::STATUS_CONCLUIDO, $request->fresh()->status);
        Queue::assertNotPushed(SendPoliTextMessage::class);
    }

    public function test_expire_command_marks_unaccessed_as_expired(): void
    {
        $plate = $this->uniquePlate();
        $request = $this->makeRequest($plate, now()->subMinute());

        $this->artisan('app:expire-uber-access-requests')->assertSuccessful();

        $this->assertSame(UberAccessRequest::STATUS_EXPIRADO, $request->fresh()->status);
    }
}
