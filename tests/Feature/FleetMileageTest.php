<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Fleet\FleetTrip;
use App\Models\Fleet\FleetVehicle;
use App\Services\FleetService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Sem RefreshDatabase pelo mesmo motivo registrado em UberAccessValidationTest:
 * a cadeia completa de migrations falha hoje. Aqui só as migrations desta
 * feature (e a de funcionários, para o vínculo do motorista) são aplicadas no
 * SQLite :memory: do phpunit.xml.
 */
class FleetMileageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::withoutForeignKeyConstraints(function () {
            (require base_path('database/migrations/2026_01_05_141304_banco_de_horas.php'))->up();
            (require base_path('database/migrations/2026_08_28_100000_create_fleet_tables.php'))->up();
        });
    }

    private function service(): FleetService
    {
        return app(FleetService::class);
    }

    private function vehicle(string $name = 'Celta', array $attributes = []): FleetVehicle
    {
        return FleetVehicle::create(array_merge(['name' => $name, 'active' => true], $attributes));
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'employee_code'  => '1234',
            'name'           => 'João da Silva',
            'cpf'            => '12345678909',
            'admission_date' => '2020-01-10',
            'position'       => 'Motorista',
            'department'     => 'Manutenção',
        ], $attributes));
    }

    public function test_saida_abre_viagem_e_atualiza_o_hodometro_do_veiculo(): void
    {
        $vehicle = $this->vehicle('Celta', ['current_odometer' => 45000]);

        $result = $this->service()->registerDeparture([
            'vehicle'     => 'celta',
            'driver'      => 'Maria Souza',
            'destination' => 'Banco - Centro',
            'odometer'    => 45210,
            'operator'    => 'Portaria 1',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('open', $result['trip']['status']);
        $this->assertSame('Maria Souza', $result['trip']['driver']['name']);
        $this->assertSame(45210, $result['trip']['departure']['odometer']);
        $this->assertSame('Portaria 1', $result['trip']['departure']['operator']);
        // O horário é o do registro, não algo que o porteiro digita.
        $this->assertNotNull($result['trip']['departure']['at']);
        $this->assertNull($result['trip']['return']);

        $this->assertSame(45210, $vehicle->fresh()->current_odometer);
        $this->assertSame('out', $result['vehicle']['status']);
    }

    public function test_veiculo_resolve_por_nome_sem_acento_e_por_placa(): void
    {
        $this->vehicle('Caminhão', ['plate' => 'ABC1D23']);

        $byName = $this->service()->resolveVehicle(['vehicle' => 'caminhao']);
        $byPlate = $this->service()->resolveVehicle(['vehicle' => 'abc-1d23']);

        $this->assertTrue($byName['ok']);
        $this->assertTrue($byPlate['ok']);
        $this->assertSame('Caminhão', $byName['vehicle']->name);
        $this->assertSame('Caminhão', $byPlate['vehicle']->name);
    }

    public function test_veiculo_desconhecido_devolve_a_lista_de_ativos(): void
    {
        $this->vehicle('Polo');

        $result = $this->service()->registerDeparture([
            'vehicle'     => 'Uno',
            'driver'      => 'Maria',
            'destination' => 'Centro',
            'odometer'    => 100,
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('vehicle_not_found', $result['error']);
        $this->assertSame(['Polo'], $result['vehicles']);
    }

    public function test_matricula_vincula_o_funcionario(): void
    {
        $this->vehicle();
        $employee = $this->employee();

        $result = $this->service()->registerDeparture([
            'vehicle'     => 'Celta',
            'driver'      => '1234',
            'destination' => 'Correios',
            'odometer'    => 10,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame($employee->id, $result['trip']['driver']['employee_id']);
        $this->assertSame('João da Silva', $result['trip']['driver']['name']);
        $this->assertSame('1234', $result['trip']['driver']['employee_code']);
    }

    public function test_numero_que_nao_e_de_ninguem_e_recusado_mas_nome_livre_passa(): void
    {
        $this->vehicle();

        $numeric = $this->service()->registerDeparture([
            'vehicle'     => 'Celta',
            'driver'      => '99999',
            'destination' => 'Correios',
            'odometer'    => 10,
        ]);

        $this->assertFalse($numeric['ok']);
        $this->assertSame('driver_not_found', $numeric['error']);

        // Quem leva o carro nem sempre está na folha: nome digitado entra.
        $free = $this->service()->registerDeparture([
            'vehicle'     => 'Celta',
            'driver'      => 'Estagiário Pedro',
            'destination' => 'Correios',
            'odometer'    => 10,
        ]);

        $this->assertTrue($free['ok']);
        $this->assertNull($free['trip']['driver']['employee_id']);
        $this->assertSame('Estagiário Pedro', $free['trip']['driver']['name']);
    }

    public function test_veiculo_na_rua_nao_sai_de_novo(): void
    {
        $this->vehicle();

        $this->service()->registerDeparture([
            'vehicle' => 'Celta', 'driver' => 'Maria', 'destination' => 'Centro', 'odometer' => 100,
        ]);

        $second = $this->service()->registerDeparture([
            'vehicle' => 'Celta', 'driver' => 'José', 'destination' => 'Bairro', 'odometer' => 120,
        ]);

        $this->assertFalse($second['ok']);
        $this->assertSame('vehicle_already_out', $second['error']);
        // A recusa devolve a viagem aberta para a portaria mostrar quem está com o carro.
        $this->assertSame('Maria', $second['trip']['driver']['name']);
    }

    public function test_saida_com_km_menor_que_a_ultima_e_recusada_e_passa_com_force(): void
    {
        $this->vehicle('Celta', ['current_odometer' => 45000]);

        $refused = $this->service()->registerDeparture([
            'vehicle' => 'Celta', 'driver' => 'Maria', 'destination' => 'Centro', 'odometer' => 44000,
        ]);

        $this->assertFalse($refused['ok']);
        $this->assertSame('odometer_below_last', $refused['error']);
        $this->assertSame(45000, $refused['last_odometer']);

        $forced = $this->service()->registerDeparture([
            'vehicle' => 'Celta', 'driver' => 'Maria', 'destination' => 'Centro', 'odometer' => 44000, 'force' => true,
        ]);

        $this->assertTrue($forced['ok']);
        $this->assertTrue($forced['trip']['odometer_alert']);
        // O piso não recua: a próxima saída continua sendo comparada com 45000.
        $this->assertSame(45000, FleetVehicle::first()->current_odometer);
    }

    public function test_retorno_fecha_a_viagem_e_calcula_a_distancia(): void
    {
        $vehicle = $this->vehicle('Polo');

        $this->service()->registerDeparture([
            'vehicle' => 'Polo', 'driver' => 'Maria', 'destination' => 'Centro', 'odometer' => 1000,
        ]);

        $result = $this->service()->registerReturn([
            'vehicle' => 'Polo', 'odometer' => 1050, 'operator' => 'Portaria 2',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('closed', $result['trip']['status']);
        $this->assertSame(50, $result['trip']['distance_km']);
        $this->assertSame(1050, $result['trip']['return']['odometer']);
        $this->assertNotNull($result['trip']['return']['at']);
        $this->assertSame(1050, $vehicle->fresh()->current_odometer);
        // Fechada a viagem, o carro volta a poder sair.
        $this->assertSame('available', $result['vehicle']['status']);
    }

    public function test_retorno_sem_saida_em_aberto(): void
    {
        $this->vehicle('Polo');

        $result = $this->service()->registerReturn(['vehicle' => 'Polo', 'odometer' => 10]);

        $this->assertFalse($result['ok']);
        $this->assertSame('no_open_trip', $result['error']);
    }

    public function test_retorno_menor_que_a_saida_e_recusado_e_marca_conferencia_com_force(): void
    {
        $this->vehicle('Celta');

        $this->service()->registerDeparture([
            'vehicle' => 'Celta', 'driver' => 'Maria', 'destination' => 'Centro', 'odometer' => 1000,
        ]);

        $refused = $this->service()->registerReturn(['vehicle' => 'Celta', 'odometer' => 900]);

        $this->assertFalse($refused['ok']);
        $this->assertSame('odometer_below_departure', $refused['error']);
        $this->assertSame(1000, $refused['departure_odometer']);

        $forced = $this->service()->registerReturn(['vehicle' => 'Celta', 'odometer' => 900, 'force' => true]);

        $this->assertTrue($forced['ok']);
        $this->assertTrue($forced['trip']['odometer_alert']);
        // Sem distância confiável, a viagem não entra na soma de km.
        $this->assertNull($forced['trip']['distance_km']);
    }

    public function test_salto_de_km_acima_do_teto_pede_confirmacao(): void
    {
        config(['fleet.max_trip_km' => 500]);
        $this->vehicle('Caminhão');

        $this->service()->registerDeparture([
            'vehicle' => 'Caminhão', 'driver' => 'Maria', 'destination' => 'Obra', 'odometer' => 1000,
        ]);

        $refused = $this->service()->registerReturn(['vehicle' => 'Caminhão', 'odometer' => 10500]);

        $this->assertFalse($refused['ok']);
        $this->assertSame('odometer_jump_too_high', $refused['error']);
        $this->assertSame(9500, $refused['distance_km']);

        $forced = $this->service()->registerReturn(['vehicle' => 'Caminhão', 'odometer' => 10500, 'force' => true]);

        $this->assertTrue($forced['ok']);
        $this->assertSame(9500, $forced['trip']['distance_km']);
        $this->assertTrue($forced['trip']['odometer_alert']);
    }

    public function test_registered_at_no_futuro_e_recusado(): void
    {
        $this->vehicle('Celta');

        $result = $this->service()->registerDeparture([
            'vehicle'       => 'Celta',
            'driver'        => 'Maria',
            'destination'   => 'Centro',
            'odometer'      => 100,
            'registered_at' => now()->addHour()->toIso8601String(),
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('future_timestamp', $result['error']);
    }

    public function test_registered_at_no_passado_e_aceito_para_lancamento_atrasado(): void
    {
        $this->vehicle('Celta');
        $at = now()->subHours(3);

        $result = $this->service()->registerDeparture([
            'vehicle'       => 'Celta',
            'driver'        => 'Maria',
            'destination'   => 'Centro',
            'odometer'      => 100,
            'registered_at' => $at->toIso8601String(),
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame($at->format('d/m/Y H:i'), $result['trip']['departure']['at_human']);
    }

    public function test_cancelar_saida_libera_o_veiculo(): void
    {
        $this->vehicle('Celta');

        $this->service()->registerDeparture([
            'vehicle' => 'Celta', 'driver' => 'Maria', 'destination' => 'Centro', 'odometer' => 100,
        ]);

        $trip = FleetTrip::first();
        $result = $this->service()->cancelTrip($trip, 'Registro em duplicidade');

        $this->assertTrue($result['ok']);
        $this->assertSame('canceled', $trip->fresh()->status);

        $again = $this->service()->registerDeparture([
            'vehicle' => 'Celta', 'driver' => 'José', 'destination' => 'Centro', 'odometer' => 100,
        ]);

        $this->assertTrue($again['ok']);
    }

    public function test_historico_filtra_por_veiculo_e_situacao(): void
    {
        $this->vehicle('Celta');
        $this->vehicle('Polo');

        $this->service()->registerDeparture([
            'vehicle' => 'Celta', 'driver' => 'Maria', 'destination' => 'Centro', 'odometer' => 100,
        ]);
        $this->service()->registerReturn(['vehicle' => 'Celta', 'odometer' => 150]);
        $this->service()->registerDeparture([
            'vehicle' => 'Polo', 'driver' => 'José', 'destination' => 'Obra', 'odometer' => 10,
        ]);

        $this->assertCount(1, $this->service()->tripsQuery(['vehicle' => 'polo'])->get());
        $this->assertCount(1, $this->service()->tripsQuery(['status' => 'open'])->get());
        $this->assertCount(1, $this->service()->tripsQuery(['driver' => 'Maria'])->get());
        // Filtro que não resolve não pode devolver a frota inteira.
        $this->assertCount(0, $this->service()->tripsQuery(['vehicle' => 'Inexistente'])->get());
    }
}
