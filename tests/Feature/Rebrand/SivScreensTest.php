<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\Fleet\FleetTrip;
use App\Models\Fleet\FleetVehicle;
use App\Models\ParkingAuthorization;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * SIV repaginado: busca de placa, resultado do acesso (com busca no
 * histórico do dia), frota, viagens, veículos e placas da diretoria. Veículo
 * aparece pelo nome (e placa), sem foto — decisão do rebrand.
 *
 * Models não salvos, sem banco; usuário mock.
 */
class SivScreensTest extends TestCase
{
    use RendersScreens;

    private function portaria()
    {
        return $this->usuario(new UserAccess([P::SIV_BUSCA, P::SIV_PLACAS_DIRETORIA, P::SIV_FROTA, P::SIV_VIAGENS, P::SIV_VEICULOS]));
    }

    private function veiculo(int $id, array $attributes = []): FleetVehicle
    {
        return (new FleetVehicle)->forceFill(array_merge([
            'id' => $id, 'name' => 'Strada', 'plate' => 'FUN1A23', 'description' => 'Fiat Strada 2023',
            'current_odometer' => 15230, 'active' => true,
        ], $attributes));
    }

    private function viagem(int $id, FleetVehicle $vehicle, array $attributes = []): FleetTrip
    {
        $trip = (new FleetTrip)->forceFill(array_merge([
            'id' => $id, 'fleet_vehicle_id' => $vehicle->id, 'driver_name' => 'João Portaria', 'destination' => 'Centro',
            'departure_at' => Carbon::now()->subHours(30), 'departure_odometer' => 15000, 'status' => FleetTrip::STATUS_OPEN,
        ], $attributes));
        $trip->setRelation('vehicle', $vehicle);
        $trip->setRelation('employee', null);

        return $trip;
    }

    public function test_busca_de_placa_com_indicadores_e_capa_do_siv(): void
    {
        $html = $this->tela($this->portaria(), 'parking.search', [], 'parking.search', [
            'todayParkingCount' => 40, 'todayParkingNoPlate' => 4,
        ]);

        $this->assertStringContainsString('action="' . route('parking.show') . '"', $html);
        $this->assertStringContainsString('name="plate"', $html);
        $this->assertStringContainsString('(90%)', $html);
        $this->assertStringContainsString(route('parking-authorizations.index'), $html);
        $this->assertStringContainsString('aria-label="Páginas de SIV"', $html);
        $this->assertStringNotContainsString('[#A00001]', $html, 'O vermelho fixo saiu da tela; no logo o carmim fica.');
        $this->assertStringNotContainsString('bootstrap-grid', $html);
    }

    public function test_resultado_do_acesso_fica_na_aba_busca_e_filtra_o_historico(): void
    {
        $condutor = (object) ['TitleCode' => '12345', 'Name' => 'Marina Costa', 'Telephone' => '(21) 99999-0000', 'date' => '10:02'];

        $html = $this->tela($this->portaria(), 'parking.show', [], 'parking.show', [
            'data' => [
                ['entry_date' => '10:00:12 01/10/2026', 'file' => 'RKT4F21/20261001100012.jpg', 'access' => [$condutor]],
                // FTP fora do ar: `file` vem falso e fica só o substituto.
                ['entry_date' => '17:45:03 01/10/2026', 'file' => false, 'access' => []],
            ],
            'car' => ['plate' => 'rkt4f21', 'color' => 'prata'],
            'probaly' => ['Marina Costa | (21) 99999-0000' => 80.0, 'Outro Sócio | ' => 20.0],
            'datetime' => '2026-10-01',
        ]);

        // A tela de resultado é outra rota, mas a aba Busca fica marcada.
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('parking.search'), '#') . '"\s+aria-current="page"#', $html);

        $this->assertStringContainsString('RKT4F21', $html);
        $this->assertStringContainsString('bg-plate-band', $html);
        $this->assertStringContainsString('PRATA', $html);
        $this->assertStringContainsString('80.0%', $html);
        $this->assertStringContainsString('laraSearch(', $html);
        $this->assertStringContainsString('data-search="10:00:12 01/10/2026"', $html);
        $this->assertStringContainsString('Nenhum condutor associado a este acesso.', $html);
        $this->assertStringContainsString('2 registros', $html);
        // A foto da câmera voltou à Busca: a que veio do FTP abre em tamanho cheio.
        $this->assertStringContainsString('src="' . asset('storage/img_car/RKT4F21/20261001100012.jpg') . '"', $html);
        $this->assertSame(1, substr_count($html, '>Ampliar<'));
        $this->assertStringContainsString('Sem foto deste acesso.', $html);
        $this->assertStringNotContainsString('placehold.co', $html);
    }

    public function test_resultado_junta_associado_externo_e_carro_de_aplicativo(): void
    {
        $associado = (object) ['TitleCode' => '12345', 'Name' => 'Marina Costa', 'Telephone' => '(21) 99999-0000', 'date' => '10:00:10'];
        $terceirizado = [
            'key' => 'terceirizado:3', 'kind' => 'terceirizado', 'label' => 'Terceirizado', 'ident' => 'Acme Serviços',
            'name' => 'Bruno Terceiro', 'detail' => 'Eletricista', 'telephone' => '(21) 98888-0000', 'time' => '10:00:40', 'allowed' => true,
        ];
        $negado = array_merge($terceirizado, ['key' => 'terceirizado:4', 'name' => 'Nádia Negada', 'telephone' => null, 'allowed' => false]);
        $aplicativo = [
            'key' => 'aplicativo:8', 'kind' => 'aplicativo', 'label' => 'Carro de aplicativo', 'ident' => 'Mat./CPF 777',
            'name' => 'Paulo Passageiro', 'detail' => 'Pedido para Sede social', 'telephone' => '5521977770000', 'time' => '10:03:00', 'allowed' => true,
        ];

        $concluido = (new \App\Models\UberAccessRequest)->forceFill([
            'id' => 8, 'status' => \App\Models\UberAccessRequest::STATUS_CONCLUIDO, 'requester_name' => 'Paulo Passageiro',
            'matricula' => '777', 'club_location' => 'Sede social', 'vehicle_plate' => 'RKT4F21', 'contact_phone' => '5521977770000',
            'screenshot_url' => 'https://exemplo.test/print.jpg', 'member_validation' => 'validado',
            'created_at' => Carbon::parse('2026-10-01 09:50:00'), 'accessed_at' => Carbon::parse('2026-10-01 10:03:00'),
        ]);
        $vencido = (new \App\Models\UberAccessRequest)->forceFill([
            'id' => 9, 'status' => \App\Models\UberAccessRequest::STATUS_EXPIRADO, 'requester_name' => 'Vera Vencida',
            'vehicle_plate' => 'RKT4F21', 'created_at' => Carbon::parse('2026-10-01 15:00:00'),
        ]);

        $dados = [
            'data' => [[
                'entry_date' => '10:00:12 01/10/2026', 'file' => false,
                'access' => [$associado], 'externals' => [$terceirizado, $negado], 'app_cars' => [$aplicativo],
            ]],
            'car' => ['plate' => 'rkt4f21', 'color' => 'prata'],
            'probaly' => ['Bruno Terceiro | (21) 98888-0000 | Terceirizado' => 60.0, 'Marina Costa | (21) 99999-0000' => 40.0],
            'datetime' => '2026-10-01',
            'appCarRequests' => collect([$concluido, $vencido]),
        ];

        $html = $this->tela($this->portaria(), 'parking.show', [], 'parking.show', $dados);

        // Uma tabela só, com o tipo de cada pessoa.
        $this->assertStringContainsString('4 condutores', $html);
        foreach (['Associado', 'Terceirizado', 'Carro de aplicativo', 'Mat. 12345', 'Acme Serviços', 'Eletricista', 'Mat./CPF 777', 'Pedido para Sede social', 'Nádia Negada', 'Negado'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }

        // Pedidos da placa: o liberado e o que venceu sem entrada.
        $this->assertStringContainsString('Pedidos de carro de aplicativo', $html);
        $this->assertStringContainsString('Vera Vencida', $html);
        $this->assertStringContainsString('Expirado', $html);
        $this->assertStringContainsString('10:03 01/10', $html);
        $this->assertStringContainsString('Sócio confere', $html);
        $this->assertStringContainsString('href="https://exemplo.test/print.jpg"', $html);

        // O atalho para Externos só aparece para quem tem a permissão de lá.
        $this->assertStringNotContainsString('Ver em Carros de aplicativo', $html);
        $comExternos = $this->usuario(new UserAccess([P::SIV_BUSCA, P::EXTERNOS_CARROS_APLICATIVO]));
        $this->assertStringContainsString(
            'href="' . route('company.uber.requests', ['q' => 'RKT4F21']) . '"',
            $this->tela($comExternos, 'parking.show', [], 'parking.show', $dados)
        );
    }

    public function test_frota_por_nome_sem_foto_com_busca_e_alerta_de_baixa(): void
    {
        $emRota = $this->veiculo(1);
        $emRota->setRelation('openTrip', $this->viagem(9, $emRota));
        $livre = $this->veiculo(2, ['name' => 'Master', 'plate' => null]);
        $livre->setRelation('openTrip', null);

        $html = $this->tela($this->portaria(), 'fleet.index', [], 'fleet.index', [
            'stats' => ['open' => 1, 'open_overdue' => 1, 'departures_today' => 3, 'km_today' => 120, 'km_month' => 2400],
            'alertHours' => 12,
            'vehicles' => collect([$emRota, $livre]),
        ]);

        $this->assertStringContainsString('Strada', $html);
        $this->assertStringContainsString('FUN1A23', $html);
        $this->assertStringContainsString('sem placa cadastrada', $html);
        $this->assertStringContainsString('Aguardando baixa', $html);
        $this->assertStringContainsString('No Clube', $html);
        $this->assertStringContainsString('action="' . route('fleet.return') . '"', $html);
        $this->assertStringContainsString('action="' . route('fleet.departure') . '"', $html);
        $this->assertStringContainsString(route('fleet.trips.cancel', 9), $html);
        $this->assertStringContainsString('laraSearch(', $html);
        // Sem foto do veículo.
        $this->assertDoesNotMatchRegularExpression('#<img[^>]+(veiculo|vehicle|frota)#i', $html);
        $this->assertStringContainsString('aria-label="Páginas de SIV"', $html);
    }

    public function test_viagens_com_filtros_no_servidor(): void
    {
        $strada = $this->veiculo(1);
        $fechada = $this->viagem(5, $strada, [
            'status' => FleetTrip::STATUS_CLOSED, 'return_at' => Carbon::now(), 'return_odometer' => 15080,
            'distance_km' => 80, 'odometer_alert' => true,
        ]);

        $html = $this->tela($this->portaria(), 'fleet.trips', [], 'fleet.trips', [
            'trips' => new LengthAwarePaginator([$fechada], 1, 25, 1, ['path' => route('fleet.trips')]),
            'vehicles' => collect([$strada]),
            'statuses' => FleetTrip::STATUS_LABELS,
            'filters' => ['vehicle_id' => '1', 'driver' => 'João'],
        ]);

        $this->assertMatchesRegularExpression('#<option value="1"\s+selected#', $html);
        $this->assertStringContainsString('value="João"', $html);
        $this->assertStringContainsString('Limpar', $html);
        $this->assertStringContainsString('Concluída', $html);
        $this->assertStringContainsString('km a conferir', $html);
        $this->assertStringContainsString('name="only_alerts"', $html);
    }

    public function test_veiculos_com_busca_e_formulario(): void
    {
        $strada = $this->veiculo(1);
        $strada->trips_count = 12;

        $lista = $this->tela($this->portaria(), 'fleet.vehicles', [], 'fleet.vehicles.index', ['vehicles' => collect([$strada])]);
        $this->assertStringContainsString('data-search="Strada FUN1A23 Fiat Strada 2023 ativo"', $lista);
        $this->assertStringContainsString(route('fleet.vehicles.edit', 1), $lista);
        $this->assertStringContainsString(route('fleet.vehicles.create'), $lista);

        $edit = $this->tela($this->portaria(), 'fleet.vehicles.edit', [1], 'fleet.vehicles.edit', ['vehicle' => $strada]);
        $this->assertStringContainsString('action="' . route('fleet.vehicles.update', 1) . '"', $edit);
        $this->assertStringContainsString('value="Strada"', $edit);
        $this->assertStringContainsString('aria-label="Voltar"', $edit);
    }

    public function test_placas_da_diretoria_com_busca_ordenacao_e_push_manual(): void
    {
        $placa = (new ParkingAuthorization)->forceFill([
            'id' => 3, 'plate' => 'DIR1A00', 'name' => 'Presidente', 'expiration_date' => Carbon::today()->subDay(),
        ]);

        $html = $this->tela($this->portaria(), 'parking-authorizations.index', [], 'parking.authorizations.index', [
            'authorizations' => new LengthAwarePaginator([$placa], 1, 20, 1, ['path' => route('parking-authorizations.index')]),
            'search' => '', 'sort' => 'plate', 'direction' => 'asc',
        ], ['sort' => 'plate', 'direction' => 'asc']);

        $this->assertStringContainsString('DIR1A00', $html);
        $this->assertStringContainsString('Expirada', $html);
        $this->assertStringContainsString('aria-sort="ascending"', $html);
        // A busca leva a ordenação junto.
        $this->assertStringContainsString('type="hidden" name="sort" value="plate"', $html);
        $this->assertStringContainsString('id="manual-push-btn"', $html);
        $this->assertStringContainsString('aria-label="Páginas de SIV"', $html);
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('parking-authorizations.index'), '#') . '"\s+aria-current="page"#', $html);
    }
}
