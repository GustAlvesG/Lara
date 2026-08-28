<?php

namespace Tests\Feature;

use App\Models\Fleet\FleetVehicle;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * O contrato que o sistema da portaria consome: rota, token e — principalmente
 * — o status HTTP de cada recusa, que é por onde o cliente decide entre
 * repetir, confirmar com force ou avisar o operador.
 *
 * Migrations aplicadas à mão pelo mesmo motivo de FleetMileageTest.
 */
class FleetApiEndpointsTest extends TestCase
{
    private string $token = 'token-de-teste';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.api.token' => $this->token]);

        Schema::withoutForeignKeyConstraints(function () {
            (require base_path('database/migrations/2026_01_05_141304_banco_de_horas.php'))->up();
            (require base_path('database/migrations/2026_08_28_100000_create_fleet_tables.php'))->up();
        });
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'];
    }

    public function test_sem_token_a_api_nao_responde(): void
    {
        $this->getJson('/api/fleet/vehicles')->assertStatus(401);
    }

    public function test_lista_de_veiculos_traz_a_situacao_de_cada_um(): void
    {
        FleetVehicle::create(['name' => 'Celta', 'current_odometer' => 45000, 'active' => true]);
        FleetVehicle::create(['name' => 'Fusca', 'active' => false]);

        $response = $this->withHeaders($this->headers())->getJson('/api/fleet/vehicles');

        $response->assertOk()
            ->assertJsonPath('vehicles.0.name', 'Celta')
            ->assertJsonPath('vehicles.0.status', 'available')
            ->assertJsonPath('vehicles.0.current_odometer', 45000)
            // Inativo fica fora da lista da portaria por padrão.
            ->assertJsonCount(1, 'vehicles');

        $this->withHeaders($this->headers())->getJson('/api/fleet/vehicles?all=1')
            ->assertOk()
            ->assertJsonCount(2, 'vehicles');
    }

    public function test_ciclo_completo_de_saida_e_retorno(): void
    {
        FleetVehicle::create(['name' => 'Polo', 'active' => true]);

        $departure = $this->withHeaders($this->headers())->postJson('/api/fleet/departure', [
            'vehicle'     => 'polo',
            'driver'      => 'Maria Souza',
            'destination' => 'Cartório',
            'odometer'    => 1000,
            'operator'    => 'Portaria 1',
        ]);

        $departure->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('trip.status', 'open')
            ->assertJsonPath('trip.driver.name', 'Maria Souza');

        // A segunda saída do mesmo carro é conflito de estado, não erro de dado.
        $this->withHeaders($this->headers())->postJson('/api/fleet/departure', [
            'vehicle' => 'polo', 'driver' => 'José', 'destination' => 'Centro', 'odometer' => 1010,
        ])->assertStatus(409)->assertJsonPath('error', 'vehicle_already_out');

        // O retorno é resolvido pelo veículo: a portaria não guarda o id.
        $this->withHeaders($this->headers())->postJson('/api/fleet/return', [
            'vehicle' => 'polo', 'odometer' => 1080, 'operator' => 'Portaria 2',
        ])->assertOk()
            ->assertJsonPath('trip.status', 'closed')
            ->assertJsonPath('trip.distance_km', 80)
            ->assertJsonPath('vehicle.status', 'available');
    }

    public function test_veiculo_desconhecido_responde_404(): void
    {
        FleetVehicle::create(['name' => 'Celta', 'active' => true]);

        $this->withHeaders($this->headers())->postJson('/api/fleet/departure', [
            'vehicle' => 'Ferrari', 'driver' => 'Maria', 'destination' => 'Centro', 'odometer' => 10,
        ])->assertStatus(404)->assertJsonPath('error', 'vehicle_not_found');
    }

    public function test_km_de_retorno_menor_pede_confirmacao_e_passa_com_force(): void
    {
        FleetVehicle::create(['name' => 'Celta', 'active' => true]);

        $this->withHeaders($this->headers())->postJson('/api/fleet/departure', [
            'vehicle' => 'Celta', 'driver' => 'Maria', 'destination' => 'Centro', 'odometer' => 1000,
        ])->assertStatus(201);

        $this->withHeaders($this->headers())->postJson('/api/fleet/return', [
            'vehicle' => 'Celta', 'odometer' => 900,
        ])->assertStatus(422)->assertJsonPath('error', 'odometer_below_departure');

        $this->withHeaders($this->headers())->postJson('/api/fleet/return', [
            'vehicle' => 'Celta', 'odometer' => 900, 'force' => true,
        ])->assertOk()->assertJsonPath('trip.odometer_alert', true);
    }

    public function test_viagens_abertas_e_historico(): void
    {
        FleetVehicle::create(['name' => 'Caminhão', 'active' => true]);

        $this->withHeaders($this->headers())->postJson('/api/fleet/departure', [
            'vehicle' => 'caminhao', 'driver' => 'José', 'destination' => 'Obra', 'odometer' => 500,
        ])->assertStatus(201);

        $this->withHeaders($this->headers())->getJson('/api/fleet/trips/open')
            ->assertOk()
            ->assertJsonCount(1, 'trips')
            ->assertJsonPath('trips.0.vehicle.name', 'Caminhão');

        $this->withHeaders($this->headers())->getJson('/api/fleet/trips?status=open')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_cancelamento_libera_o_veiculo(): void
    {
        FleetVehicle::create(['name' => 'Celta', 'active' => true]);

        $departure = $this->withHeaders($this->headers())->postJson('/api/fleet/departure', [
            'vehicle' => 'Celta', 'driver' => 'Maria', 'destination' => 'Centro', 'odometer' => 10,
        ])->assertStatus(201);

        $tripId = $departure->json('trip.id');

        $this->withHeaders($this->headers())->postJson("/api/fleet/trips/{$tripId}/cancel", [
            'reason' => 'Registro em duplicidade',
        ])->assertOk()->assertJsonPath('trip.status', 'canceled');

        $this->withHeaders($this->headers())->getJson('/api/fleet/vehicles')
            ->assertJsonPath('vehicles.0.status', 'available');
    }
}
