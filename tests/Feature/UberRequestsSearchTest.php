<?php

namespace Tests\Feature;

use App\Http\Controllers\Company\CompanyAccessRulesController;
use App\Models\UberAccessRequest;
use App\Services\CompanyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Busca da aba "Pedidos" e fila da tela "Aguardando acesso do motorista".
 *
 * O controller é chamado direto, e não pela rota: `view()` só monta a View,
 * não renderiza, então dá para conferir o resultado da consulta sem arrastar o
 * layout — que depende de usuário autenticado, e o User vive na conexão mysql.
 *
 * Sem RefreshDatabase pelo mesmo motivo registrado em UberAccessValidationTest:
 * a cadeia completa de migrations falha hoje em `add_columns_member` x
 * `tourments`. Aqui só as migrations desta feature são aplicadas.
 */
class UberRequestsSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::withoutForeignKeyConstraints(function () {
            (require base_path('database/migrations/2026_07_20_150000_create_uber_access_requests_tables.php'))->up();
            (require base_path('database/migrations/2026_07_21_120000_add_matricula_to_uber_access_requests.php'))->up();
            (require base_path('database/migrations/2026_08_27_170000_add_member_validation_to_uber_access_requests.php'))->up();
            (require base_path('database/migrations/2026_09_10_100000_add_member_validation_type_to_uber_access_requests.php'))->up();
        });
    }

    private function controller(): CompanyAccessRulesController
    {
        return new CompanyAccessRulesController(app(CompanyService::class));
    }

    /** Ids dos pedidos que a aba "Pedidos" devolve para os filtros dados. */
    private function search(array $query): array
    {
        $view = $this->controller()->uberRequests(new Request($query));

        return $view->getData()['requests']->pluck('id')->all();
    }

    private function makeRequest(array $attributes = []): UberAccessRequest
    {
        return UberAccessRequest::create(array_merge([
            'contact_uuid'   => 'uuid-' . uniqid(),
            'contact_phone'  => '5524999990000',
            'status'         => UberAccessRequest::STATUS_AGUARDANDO_ACESSO,
            'matricula'      => '987654',
            'requester_name' => 'Fulano de Tal',
            'club_location'  => 'Sede Campestre',
            'vehicle_plate'  => 'ABC1D23',
            'completed_at'   => now(),
            'expires_at'     => now()->addMinutes(10),
        ], $attributes));
    }

    public function test_busca_livre_encontra_por_nome_placa_matricula_telefone_e_local(): void
    {
        $alvo = $this->makeRequest([
            'requester_name' => 'Mariana Alvarenga',
            'matricula'      => '112233',
            'vehicle_plate'  => 'XYZ9A88',
            'contact_phone'  => '5524988887777',
            'club_location'  => 'Portaria da Piscina',
        ]);
        $this->makeRequest(['requester_name' => 'Outro Sócio']);

        // Cada termo entra por um campo diferente; a caixa é uma só.
        foreach (['Mariana', '112233', 'XYZ9A88', '88887777', 'Piscina'] as $termo) {
            $this->assertSame([$alvo->id], $this->search(['q' => $termo]), "falhou para: {$termo}");
        }
    }

    /**
     * A placa é guardada sem máscara, mas quem procura digita como leu.
     */
    public function test_busca_por_placa_ignora_mascara_e_caixa(): void
    {
        $alvo = $this->makeRequest(['vehicle_plate' => 'QWE4R55']);

        $this->assertSame([$alvo->id], $this->search(['plate' => 'qwe-4r55']));
        $this->assertSame([$alvo->id], $this->search(['q' => 'qwe 4r55']));
    }

    /** Parte da placa basta: lida errado no portão, o resto o porteiro confere. */
    public function test_busca_por_pedaco_da_placa(): void
    {
        $alvo = $this->makeRequest(['vehicle_plate' => 'POI8U77']);
        $this->makeRequest(['vehicle_plate' => 'LKJ1H22']);

        $this->assertSame([$alvo->id], $this->search(['plate' => '8U77']));
    }

    /** Matrícula/CPF chega do WhatsApp com e sem máscara — as duas acham. */
    public function test_busca_por_cpf_com_e_sem_mascara(): void
    {
        $comMascara = $this->makeRequest(['matricula' => '123.456.789-09']);
        $semMascara = $this->makeRequest(['matricula' => '98765432100']);

        $this->assertSame([$comMascara->id], $this->search(['matricula' => '12345678909']));
        $this->assertSame([$comMascara->id], $this->search(['matricula' => '123.456.789-09']));
        $this->assertSame([$semMascara->id], $this->search(['matricula' => '987.654.321-00']));
    }

    public function test_busca_por_nome_tambem_olha_o_contato_do_whatsapp(): void
    {
        $alvo = $this->makeRequest([
            'requester_name'        => 'Sem Nome',
            'contact_name_whatsapp' => 'Joana Coordenadora|Joana',
        ]);
        $this->makeRequest(['requester_name' => 'Roberto']);

        $this->assertSame([$alvo->id], $this->search(['name' => 'Joana']));
    }

    public function test_filtra_por_status_e_por_conferencia_do_socio(): void
    {
        $aguardando = $this->makeRequest([
            'member_validation' => UberAccessRequest::MEMBER_VALIDATION_VALIDADO,
        ]);
        $expirado = $this->makeRequest([
            'status'            => UberAccessRequest::STATUS_EXPIRADO,
            'member_validation' => UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO,
        ]);

        $this->assertSame([$aguardando->id], $this->search(['status' => UberAccessRequest::STATUS_AGUARDANDO_ACESSO]));
        $this->assertSame([$expirado->id], $this->search(['status' => UberAccessRequest::STATUS_EXPIRADO]));
        $this->assertSame(
            [$expirado->id],
            $this->search(['member_validation' => UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO])
        );
    }

    public function test_filtra_por_dia(): void
    {
        $hoje = $this->makeRequest();
        $antigo = $this->makeRequest();
        $antigo->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->assertSame([$hoje->id], $this->search(['date_from' => today()->toDateString()]));
        $this->assertSame(
            [$antigo->id],
            $this->search([
                'date_from' => today()->subDays(3)->toDateString(),
                'date_to'   => today()->subDays(3)->toDateString(),
            ])
        );
    }

    /** Filtros combinam em E: quem bate num só fica de fora. */
    public function test_filtros_combinam_entre_si(): void
    {
        $alvo = $this->makeRequest(['requester_name' => 'Ana Paula', 'vehicle_plate' => 'AAA1A11']);
        $this->makeRequest(['requester_name' => 'Ana Paula', 'vehicle_plate' => 'BBB2B22']);

        $this->assertSame([$alvo->id], $this->search(['name' => 'Ana Paula', 'plate' => 'AAA1A11']));
        $this->assertSame([], $this->search(['name' => 'Ana Paula', 'plate' => 'CCC3C33']));
    }

    /** Filtro desconhecido ou vazio não filtra nada — devolve a lista inteira. */
    public function test_status_invalido_e_campos_vazios_nao_filtram(): void
    {
        $a = $this->makeRequest();
        $b = $this->makeRequest();

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $this->search([]));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $this->search(['status' => 'inventado', 'q' => '']));
    }

    /**
     * A fila da portaria separa o que ainda está na validade do que já venceu
     * — o vencido continua na tela porque o cron que expira roda depois.
     */
    public function test_fila_separa_validos_de_vencidos_e_mostra_quem_ainda_preenche(): void
    {
        $valido = $this->makeRequest(['expires_at' => now()->addMinutes(5)]);
        $vencido = $this->makeRequest(['expires_at' => now()->subMinutes(2)]);
        $preenchendo = $this->makeRequest([
            'status'          => UberAccessRequest::STATUS_AGUARDANDO_PLACA,
            'expires_at'      => null,
            'last_message_at' => now()->subMinute(),
        ]);

        // Fora da fila: já concluído, e coleta abandonada há muito tempo.
        $this->makeRequest(['status' => UberAccessRequest::STATUS_CONCLUIDO]);
        $this->makeRequest([
            'status'          => UberAccessRequest::STATUS_AGUARDANDO_NOME,
            'expires_at'      => null,
            'last_message_at' => now()->subHour(),
        ]);

        $data = $this->controller()->uberWaiting(new Request())->getData();

        $this->assertSame([$valido->id], $data['validos']->pluck('id')->all());
        $this->assertSame([$vencido->id], $data['expirados']->pluck('id')->all());
        $this->assertSame([$preenchendo->id], $data['emPreenchimento']->pluck('id')->all());
    }

    /** O mais perto de vencer aparece primeiro: é o que o porteiro atende antes. */
    public function test_fila_ordena_pelo_que_vence_primeiro(): void
    {
        $depois = $this->makeRequest(['expires_at' => now()->addMinutes(20)]);
        $antes = $this->makeRequest(['expires_at' => now()->addMinutes(3)]);

        $data = $this->controller()->uberWaiting(new Request())->getData();

        $this->assertSame([$antes->id, $depois->id], $data['validos']->pluck('id')->all());
    }
}
