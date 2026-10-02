<?php

namespace Tests\Feature;

use App\Models\Company\Company;
use App\Models\Company\CompanyAccessLog;
use App\Models\Company\CompanyWorker;
use App\Models\Company\OneOffAccess;
use App\Models\Freelancer;
use App\Models\UberAccessRequest;
use App\Services\ParkingAccessCorrelationService as Correlation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * SIV — busca de placa: externos (terceirizado, freelancer, liberação pontual)
 * ligados à leitura pelo horário, e pedidos de carro de aplicativo ligados
 * pela placa.
 *
 * Sem RefreshDatabase pelo mesmo motivo de OneOffAccessTest: só as migrations
 * desta feature, no SQLite :memory: do phpunit.xml.
 */
class ParkingAccessCorrelationTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    private const LEITURA = '2026-10-01 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();

        // one_off_accesses tem FK para `users`; basta a tabela existir.
        Schema::create('users', fn ($table) => $table->id());

        Schema::withoutForeignKeyConstraints(function () {
            foreach ([
                '2026_07_20_150000_create_uber_access_requests_tables',
                '2026_07_21_120000_add_matricula_to_uber_access_requests',
                '2026_08_27_170000_add_member_validation_to_uber_access_requests',
                '2026_09_10_100000_add_member_validation_type_to_uber_access_requests',
                '2026_01_14_151220_create_companies_table',
                '2026_01_14_151348_create_company_workers_table',
                '2026_06_02_100000_create_company_access_logs_table',
                '2026_06_10_000000_create_app_drivers_table',
                '2026_06_10_000100_add_app_driver_to_company_access_logs',
                '2026_07_21_000000_add_uber_fields_to_company_access_logs',
                '2026_08_03_130000_add_freelancer_to_company_access_logs',
                '2026_09_11_140000_create_one_off_accesses_table',
                '2026_09_11_140100_add_one_off_access_to_company_access_logs',
            ] as $migration) {
                (require base_path("database/migrations/{$migration}.php"))->up();
            }
        });
    }

    private function service(): Correlation
    {
        return app(Correlation::class);
    }

    /** Registro da portaria num instante exato (created_at não é fillable). */
    private function registro(array $attributes, string $quando): CompanyAccessLog
    {
        $log = new CompanyAccessLog(array_merge(['target' => '52998224725', 'allowed' => true, 'reason' => 'access_granted'], $attributes));
        $log->created_at = Carbon::parse($quando);
        $log->updated_at = Carbon::parse($quando);
        $log->save();

        return $log;
    }

    private function terceirizado(string $name = 'Bruno Terceiro'): CompanyWorker
    {
        $company = Company::create(['name' => 'Acme Serviços']);

        return CompanyWorker::create([
            'company_id' => $company->id, 'name' => $name, 'email' => 'bruno@acme.test',
            'position' => 'Eletricista', 'telephone' => '(21) 98888-0000',
        ]);
    }

    private function pedido(array $attributes): UberAccessRequest
    {
        return UberAccessRequest::create(array_merge([
            'contact_uuid' => 'c-' . uniqid(), 'contact_phone' => '5521977770000',
            'status' => UberAccessRequest::STATUS_CONCLUIDO, 'requester_name' => 'Marina Costa',
            'matricula' => '12345', 'club_location' => 'Sede social', 'vehicle_plate' => 'RKT4F21',
        ], $attributes));
    }

    public function test_terceirizado_registrado_perto_da_leitura_entra_no_acesso(): void
    {
        $worker = $this->terceirizado();
        $this->registro(['company_id' => $worker->company_id, 'company_worker_id' => $worker->id], '2026-10-01 10:00:40');

        $rows = $this->service()->externalsAround(self::LEITURA);

        $this->assertCount(1, $rows);
        $this->assertSame(Correlation::KIND_WORKER, $rows[0]['kind']);
        $this->assertSame('Terceirizado', $rows[0]['label']);
        $this->assertSame('Bruno Terceiro', $rows[0]['name']);
        $this->assertSame('Acme Serviços', $rows[0]['ident']);
        $this->assertSame('Eletricista', $rows[0]['detail']);
        $this->assertSame('(21) 98888-0000', $rows[0]['telephone']);
        $this->assertSame('10:00:40', $rows[0]['time']);
        $this->assertTrue($rows[0]['allowed']);
    }

    public function test_registro_fora_da_janela_nao_entra(): void
    {
        $worker = $this->terceirizado();
        $ids = ['company_id' => $worker->company_id, 'company_worker_id' => $worker->id];
        $this->registro($ids, '2026-10-01 09:58:59');
        $this->registro($ids, '2026-10-01 10:01:01');

        $this->assertSame([], $this->service()->externalsAround(self::LEITURA));
    }

    public function test_freelancer_e_liberacao_pontual_entram_com_o_proprio_tipo(): void
    {
        $freelancer = Freelancer::create(['name' => 'Fábio Freela', 'cpf' => '52998224725', 'telephone' => '(21) 97777-1111']);
        $pontual = OneOffAccess::create(['cpf' => '11144477735', 'name' => 'Paula Pontual', 'reason' => 'Entrega de palco', 'access_date' => '2026-10-01']);

        $this->registro(['freelancer_id' => $freelancer->id, 'reason' => 'freelancer_access_granted'], '2026-10-01 09:59:30');
        $this->registro(['one_off_access_id' => $pontual->id, 'reason' => 'one_off_access_granted'], '2026-10-01 10:00:20');

        $rows = $this->service()->externalsAround(self::LEITURA);

        $this->assertSame(['Freelancer', 'Liberação pontual'], array_column($rows, 'label'));
        $this->assertSame(['Fábio Freela', 'Paula Pontual'], array_column($rows, 'name'));
        $this->assertSame('Entrega de palco', $rows[1]['detail']);
    }

    public function test_registro_negado_aparece_marcado_e_o_liberado_vence_o_duplicado(): void
    {
        $negado = $this->terceirizado('Nádia Negada');
        $duplo = CompanyWorker::create(['company_id' => $negado->company_id, 'name' => 'Davi Duplo', 'email' => 'd@acme.test', 'position' => 'Pintor']);

        $this->registro(['company_id' => $negado->company_id, 'company_worker_id' => $negado->id, 'allowed' => false, 'reason' => 'access_denied'], '2026-10-01 10:00:05');
        $this->registro(['company_id' => $duplo->company_id, 'company_worker_id' => $duplo->id, 'allowed' => false, 'reason' => 'access_denied'], '2026-10-01 10:00:10');
        $this->registro(['company_id' => $duplo->company_id, 'company_worker_id' => $duplo->id], '2026-10-01 10:00:30');

        $rows = collect($this->service()->externalsAround(self::LEITURA))->keyBy('name');

        $this->assertCount(2, $rows);
        $this->assertFalse($rows['Nádia Negada']['allowed']);
        $this->assertTrue($rows['Davi Duplo']['allowed']);
    }

    public function test_acesso_de_uber_no_historico_nao_conta_como_externo(): void
    {
        $pedido = $this->pedido(['accessed_at' => '2026-10-01 10:00:05']);
        $this->registro(['uber_access_request_id' => $pedido->id, 'target' => 'RKT4F21', 'reason' => 'uber_access_granted'], '2026-10-01 10:00:05');

        $this->assertSame([], $this->service()->externalsAround(self::LEITURA));
    }

    public function test_terceirizado_excluido_continua_com_o_nome(): void
    {
        $worker = $this->terceirizado();
        $this->registro(['company_id' => $worker->company_id, 'company_worker_id' => $worker->id], '2026-10-01 10:00:00');
        $worker->delete();

        $this->assertSame('Bruno Terceiro', $this->service()->externalsAround(self::LEITURA)[0]['name']);
    }

    public function test_hora_gravada_com_hifen_e_entendida(): void
    {
        $worker = $this->terceirizado();
        $this->registro(['company_id' => $worker->company_id, 'company_worker_id' => $worker->id], '2026-10-01 10:00:10');

        $this->assertCount(1, $this->service()->externalsAround('2026-10-01 10-00-00'));
        $this->assertSame([], $this->service()->externalsAround('sem data'));
    }

    public function test_pedidos_de_aplicativo_casam_pela_placa_e_pelo_dia(): void
    {
        $doDia = $this->pedido(['accessed_at' => '2026-10-01 10:02:00']);
        $doDia->forceFill(['created_at' => '2026-10-01 09:50:00'])->save();

        // Feito na véspera e liberado no dia: entra.
        $vespera = $this->pedido(['requester_name' => 'Da Véspera', 'accessed_at' => '2026-10-01 00:10:00']);
        $vespera->forceFill(['created_at' => '2026-09-30 23:55:00'])->save();

        // Feito no dia e vencido sem entrada: entra, para a portaria saber que existiu.
        $vencido = $this->pedido(['requester_name' => 'Vencido', 'status' => UberAccessRequest::STATUS_EXPIRADO]);
        $vencido->forceFill(['created_at' => '2026-10-01 15:00:00'])->save();

        $outraPlaca = $this->pedido(['vehicle_plate' => 'ABC1D23', 'accessed_at' => '2026-10-01 10:02:00']);
        $outraPlaca->forceFill(['created_at' => '2026-10-01 09:50:00'])->save();
        $outroDia = $this->pedido(['accessed_at' => '2026-10-02 10:02:00']);
        $outroDia->forceFill(['created_at' => '2026-10-02 09:50:00'])->save();

        // A placa chega como foi digitada.
        $achados = $this->service()->appCarRequests('rkt-4f21', '2026-10-01T00:00:00', '2026-10-01T23:59:59');

        $this->assertEqualsCanonicalizing(
            [$doDia->id, $vespera->id, $vencido->id],
            $achados->pluck('id')->all()
        );
    }

    public function test_pedido_liberado_vai_para_a_leitura_mais_proxima(): void
    {
        $manha = $this->pedido(['accessed_at' => '2026-10-01 10:03:00']);
        $tarde = $this->pedido(['requester_name' => 'Tarde', 'accessed_at' => '2026-10-01 17:44:00']);
        $semLeitura = $this->pedido(['requester_name' => 'Longe', 'accessed_at' => '2026-10-01 13:00:00']);
        $naoEntrou = $this->pedido(['requester_name' => 'Vencido', 'status' => UberAccessRequest::STATUS_EXPIRADO]);

        $porLeitura = $this->service()->appCarsByEntry(
            [7 => '2026-10-01 10:00:12', 8 => '2026-10-01 17:45:03'],
            collect([$manha, $tarde, $semLeitura, $naoEntrou])
        );

        $this->assertSame([7, 8], array_keys($porLeitura));
        $this->assertSame('Marina Costa', $porLeitura[7][0]['name']);
        $this->assertSame('Tarde', $porLeitura[8][0]['name']);

        $row = $porLeitura[7][0];
        $this->assertSame(Correlation::KIND_APP_CAR, $row['kind']);
        $this->assertSame('Carro de aplicativo', $row['label']);
        $this->assertSame('Mat./CPF 12345', $row['ident']);
        $this->assertSame('Pedido para Sede social', $row['detail']);
        $this->assertSame('10:03:00', $row['time']);
    }
}
