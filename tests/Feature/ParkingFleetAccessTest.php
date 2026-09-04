<?php

namespace Tests\Feature;

use App\Models\Fleet\FleetVehicle;
use App\Models\ParkingAuthorization;
use App\Services\ParkingAuthorizationService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Carro da frota na cancela da diretoria: a autorização dele é ser da empresa,
 * não uma validade que alguém precisa renovar todo ano.
 *
 * Migrations aplicadas à mão pelo mesmo motivo de FleetMileageTest.
 */
class ParkingFleetAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::withoutForeignKeyConstraints(function () {
            (require base_path('database/migrations/2026_06_02_000000_create_parking_authorizations_table.php'))->up();
            (require base_path('database/migrations/2026_08_28_100000_create_fleet_tables.php'))->up();
        });
    }

    private function service(): ParkingAuthorizationService
    {
        return app(ParkingAuthorizationService::class);
    }

    public function test_placa_da_frota_libera_a_cancela(): void
    {
        FleetVehicle::create(['name' => 'Celta', 'plate' => 'ABC1D23', 'active' => true]);

        $result = $this->service()->checkPlate('ABC1D23');

        $this->assertTrue($result['valid']);
        $this->assertSame('Celta', $result['name']);
    }

    public function test_placa_da_frota_com_separador_tambem_libera(): void
    {
        FleetVehicle::create(['name' => 'Polo', 'plate' => 'XYZ4321', 'active' => true]);

        $this->assertTrue($this->service()->checkPlate('xyz-4321')['valid']);
    }

    /** Veículo desativado saiu da frota: a liberação sai junto. */
    public function test_veiculo_inativo_nao_libera(): void
    {
        FleetVehicle::create(['name' => 'Fusca', 'plate' => 'OLD1234', 'active' => false]);

        $result = $this->service()->checkPlate('OLD1234');

        $this->assertFalse($result['valid']);
        $this->assertSame('not_found', $result['reason']);
    }

    /**
     * A frota entra por cima da lista da diretoria, mas não pode encobrir o que
     * já existia: autorização válida e vencida continuam decidindo pela data.
     */
    public function test_autorizacao_da_diretoria_continua_funcionando(): void
    {
        ParkingAuthorization::create([
            'plate' => 'DIR1234', 'name' => 'Diretor', 'expiration_date' => now()->addYear(),
        ]);
        ParkingAuthorization::create([
            'plate' => 'OLD9999', 'name' => 'Ex-diretor', 'expiration_date' => now()->subDay(),
        ]);

        $this->assertTrue($this->service()->checkPlate('DIR1234')['valid']);

        $expired = $this->service()->checkPlate('OLD9999');
        $this->assertFalse($expired['valid']);
        $this->assertSame('expired', $expired['reason']);
    }

    public function test_placa_desconhecida_continua_negada(): void
    {
        $result = $this->service()->checkPlate('QQQ9999');

        $this->assertFalse($result['valid']);
        $this->assertSame('not_found', $result['reason']);
    }

    /**
     * A lista que a câmera baixa para operar offline. Sem a frota aqui, o
     * acesso livre sumiria justamente quando a API cai.
     */
    public function test_lista_offline_soma_frota_e_diretoria(): void
    {
        config(['fleet.gate_validity_years' => 10]);

        ParkingAuthorization::create([
            'plate' => 'DIR1234', 'name' => 'Diretor', 'expiration_date' => now()->addYear(),
        ]);
        ParkingAuthorization::create([
            'plate' => 'OLD9999', 'name' => 'Ex-diretor', 'expiration_date' => now()->subDay(),
        ]);
        FleetVehicle::create(['name' => 'Celta', 'plate' => 'ABC1D23', 'active' => true]);
        FleetVehicle::create(['name' => 'Fusca', 'plate' => 'OLD1234', 'active' => false]);
        FleetVehicle::create(['name' => 'Caminhão', 'active' => true]);

        $plates = $this->service()->getValidAuthorizations();
        $byPlate = $plates->keyBy('plate');

        // Vencida da diretoria e inativo da frota ficam de fora; veículo sem
        // placa cadastrada não tem o que entrar na lista.
        $this->assertSame(['DIR1234', 'ABC1D23'], $plates->pluck('plate')->all());

        $this->assertSame('Celta', $byPlate['ABC1D23']['name']);
        $this->assertSame(
            now()->addYears(10)->toDateString(),
            $byPlate['ABC1D23']['expiration_date'],
            'A frota entra com validade sintética bem à frente.'
        );
        // O formato do que já existia não muda.
        $this->assertSame(now()->addYear()->toDateString(), $byPlate['DIR1234']['expiration_date']);
    }

    public function test_placa_repetida_nas_duas_listas_aparece_uma_vez(): void
    {
        ParkingAuthorization::create([
            'plate' => 'ABC1D23', 'name' => 'Diretor', 'expiration_date' => now()->addYear(),
        ]);
        FleetVehicle::create(['name' => 'Celta', 'plate' => 'ABC1D23', 'active' => true]);

        $plates = $this->service()->getValidAuthorizations();

        $this->assertCount(1, $plates);
        $this->assertSame('Diretor', $plates->first()['name']);
    }
}
