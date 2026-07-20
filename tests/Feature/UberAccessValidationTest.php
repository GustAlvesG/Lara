<?php

namespace Tests\Feature;

use App\Models\UberAccessRequest;
use App\Services\CompanyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UberAccessValidationTest extends TestCase
{
    use DatabaseTransactions;

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

        $fresh = $request->fresh();
        $this->assertSame(UberAccessRequest::STATUS_CONCLUIDO, $fresh->status);
        $this->assertNotNull($fresh->accessed_at);
        // expires_at foi vencido no ato do acesso.
        $this->assertTrue($fresh->expires_at->lessThanOrEqualTo(now()));

        $this->assertDatabaseHas('company_access_logs', [
            'target'  => $plate,
            'allowed' => true,
            'reason'  => 'uber_access_granted',
        ]);

        // Segunda tentativa com a mesma placa não é mais liberada.
        $second = $this->service()->validateTryToAccess(['target' => $plate]);
        $this->assertFalse($second['found']);
    }

    public function test_expire_command_marks_unaccessed_as_expired(): void
    {
        $plate = $this->uniquePlate();
        $request = $this->makeRequest($plate, now()->subMinute());

        $this->artisan('app:expire-uber-access-requests')->assertSuccessful();

        $this->assertSame(UberAccessRequest::STATUS_EXPIRADO, $request->fresh()->status);
    }
}
