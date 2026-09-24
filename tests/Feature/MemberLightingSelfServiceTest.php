<?php

namespace Tests\Feature;

use App\Models\HomeAssistantOverride;
use App\Models\MemberLightingActivation;
use App\Providers\Services\JwtService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Autoatendimento de iluminação: o sócio acende a luz da quadra pelo app.
 *
 * O que importa aqui é o ciclo inteiro atravessando as duas autenticações: o
 * POST grava, o `GET /automation` que o Home Assistant consulta passa a
 * responder "ligado", e a cota do sócio fica presa até ele devolver a quadra ou
 * o prazo vencer.
 *
 * Sem RefreshDatabase, como em HomeAssistantManualCommandTest: só as tabelas
 * que os endpoints tocam são criadas no SQLite :memory: do phpunit.xml.
 *
 * `members` é o caso à parte. O model está preso à conexão `mysql`, então a
 * conexão de mesmo nome é reapontada para um SQLite próprio — senão o teste
 * tentaria falar com o banco de verdade. Nenhuma consulta cruza as duas
 * conexões: o sócio é resolvido sozinho, pelo cpf do token.
 */
class MemberLightingSelfServiceTest extends TestCase
{
    private string $token = 'token-de-teste';

    /** Sábado, 26/09/2026 — dia de janela 17:00–23:00. */
    private const SABADO = '2026-09-26';

    /** Quarta-feira, sem janela nenhuma. */
    private const QUARTA = '2026-09-23';

    private const CPF_ANA  = '11111111111';
    private const CPF_BRUNO = '22222222222';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.api.token'  => $this->token,
            'services.jwt.secret' => 'segredo-de-teste',
            'home_assistant.self_service.windows' => [
                6 => ['17:00', '23:00'],
                0 => ['17:00', '21:00'],
            ],
            'home_assistant.self_service.holiday_window' => ['17:00', '21:00'],
            'home_assistant.self_service.max_minutes'    => 120,
            'home_assistant.self_service.min_minutes'    => 15,
            'home_assistant.self_service.step_minutes'   => 15,
        ]);

        $this->createSchema();
        $this->createMembersOnMysqlConnection();
        $this->seedCenario();

        Carbon::setTestNow(self::SABADO . ' 18:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ─────────────────────────── Cenário ─────────────────────────── */

    private function createSchema(): void
    {
        Schema::create('contactors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('entity_id')->unique();
            $table->timestamps();
        });

        Schema::create('place_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('contactor_id')->nullable();
            $table->boolean('self_service_lighting')->default(false);
            $table->unsignedBigInteger('place_group_id')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id');
            $table->dateTime('start_schedule');
            $table->dateTime('end_schedule');
            $table->unsignedBigInteger('status_id');
            $table->timestamps();
        });

        Schema::create('home_assistant_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('mode');
            $table->unsignedInteger('priority')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_quick')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('origin', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('home_assistant_override_contactor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_assistant_override_id')->constrained('home_assistant_overrides')->cascadeOnDelete();
            $table->foreignId('contactor_id')->constrained('contactors')->cascadeOnDelete();
        });

        Schema::create('home_assistant_override_weekday', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('home_assistant_override_id');
            $table->unsignedBigInteger('weekday_id');
        });

        Schema::create('home_assistant_override_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_assistant_override_id')->constrained('home_assistant_overrides')->cascadeOnDelete();
            $table->time('turn_on_at');
            $table->time('turn_off_at');
            $table->string('state')->default('on');
            $table->timestamps();
        });

        Schema::create('weekdays', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::create('lighting_self_service_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('mode');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('reason', 120)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('member_lighting_activations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('place_id');
            $table->unsignedBigInteger('contactor_id');
            $table->unsignedBigInteger('home_assistant_override_id')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('released_at')->nullable();
            $table->string('origin', 80)->nullable();
            $table->timestamps();
        });
    }

    /**
     * `members` num SQLite só dele, porque o model está preso à conexão mysql.
     *
     * Reapontar a conexão é mais honesto do que soltar o model: em produção ele
     * fala com o mysql mesmo, e o teste continua exercitando o caminho real de
     * resolver o sócio pelo cpf do token.
     */
    private function createMembersOnMysqlConnection(): void
    {
        config(['database.connections.mysql' => [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => false,
        ]]);

        DB::purge('mysql');

        Schema::connection('mysql')->create('members', function (Blueprint $table) {
            $table->id();
            $table->string('cpf')->nullable();
            $table->string('Name')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        DB::connection('mysql')->table('members')->insert([
            ['id' => 1, 'cpf' => self::CPF_ANA,   'Name' => 'Ana',   'title' => '1001'],
            ['id' => 2, 'cpf' => self::CPF_BRUNO, 'Name' => 'Bruno', 'title' => '1002'],
        ]);
    }

    private function seedCenario(): void
    {
        DB::table('contactors')->insert([
            ['id' => 1, 'name' => 'Beach Quadra 1', 'entity_id' => 'beach_quadra_1'],
            ['id' => 2, 'name' => 'Beach Quadra 2', 'entity_id' => 'beach_quadra_2'],
            ['id' => 3, 'name' => 'Salão', 'entity_id' => 'switch.salao'],
        ]);

        DB::table('place_groups')->insert([
            ['id' => 1, 'name' => 'Beach Tennis', 'icon' => 'beach'],
            ['id' => 2, 'name' => 'Salões', 'icon' => 'party'],
        ]);

        DB::table('places')->insert([
            ['id' => 10, 'name' => 'Quadra 1', 'contactor_id' => 1, 'self_service_lighting' => true,  'place_group_id' => 1],
            ['id' => 20, 'name' => 'Quadra 2', 'contactor_id' => 2, 'self_service_lighting' => true,  'place_group_id' => 1],
            // Liberado não é: o salão se aluga, não se acende de graça.
            ['id' => 30, 'name' => 'Salão de Festas', 'contactor_id' => 3, 'self_service_lighting' => false, 'place_group_id' => 2],
        ]);
    }

    /* ─────────────────────────── Ajudantes ─────────────────────────── */

    private function headers(?string $cpf = self::CPF_ANA): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept'        => 'application/json',
        ];

        if ($cpf !== null) {
            $headers['Session'] = (new JwtService())->generateToken([
                'username' => $cpf,
                'exp'      => Carbon::now()->addHours(4)->timestamp,
            ]);
        }

        return $headers;
    }

    private function activate(int $placeId, ?string $cpf = self::CPF_ANA, ?int $minutes = null)
    {
        return $this->withHeaders($this->headers($cpf))
            ->postJson("/api/lighting/places/{$placeId}/activate", $minutes ? ['minutes' => $minutes] : []);
    }

    private function availability(?string $cpf = self::CPF_ANA)
    {
        return $this->withHeaders($this->headers($cpf))->getJson('/api/lighting/availability');
    }

    /** Estado que o Home Assistant receberia agora. */
    private function automationState(string $entityId): bool
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'])
            ->getJson('/api/schedule/home-assistant/automation')
            ->assertOk()
            ->json('contactors')[$entityId];
    }

    private function reservation(int $placeId, string $start, string $end, int $status = 1): void
    {
        DB::table('schedules')->insert([
            'place_id'       => $placeId,
            'start_schedule' => self::SABADO . ' ' . $start,
            'end_schedule'   => self::SABADO . ' ' . $end,
            'status_id'      => $status,
        ]);
    }

    /* ─────────────────────────── Autenticação ─────────────────────────── */

    public function test_sem_api_token_nao_passa(): void
    {
        $this->postJson('/api/lighting/places/10/activate')->assertStatus(401);

        $this->assertSame(0, MemberLightingActivation::count());
    }

    public function test_com_api_token_mas_sem_sessao_do_socio_nao_passa(): void
    {
        // Sem `Session` não há de quem seja a cota — e a cota é o coração da regra.
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'])
            ->postJson('/api/lighting/places/10/activate')
            ->assertStatus(400);

        $this->assertSame(0, MemberLightingActivation::count());
    }

    public function test_sessao_de_cpf_desconhecido_devolve_404(): void
    {
        $this->activate(10, '99999999999')->assertStatus(404);
    }

    /* ─────────────────────────── Disponibilidade ─────────────────────────── */

    public function test_disponibilidade_no_sabado_a_noite_esta_aberta(): void
    {
        $this->availability()
            ->assertOk()
            ->assertJsonPath('open', true)
            ->assertJsonPath('window.start', '17:00')
            ->assertJsonPath('window.end', '23:00')
            ->assertJsonPath('max_minutes', 120)
            ->assertJsonPath('min_minutes', 15)
            ->assertJsonPath('step_minutes', 15)
            ->assertJsonPath('available_minutes', 120)
            ->assertJsonPath('activation', null);
    }

    public function test_na_quarta_esta_fechada_e_aponta_o_proximo_sabado(): void
    {
        Carbon::setTestNow(self::QUARTA . ' 19:00:00');

        $this->availability()
            ->assertOk()
            ->assertJsonPath('open', false)
            ->assertJsonPath('today_window', null)
            ->assertJsonPath('next_window.date', self::SABADO);
    }

    public function test_feriado_liberado_no_painel_abre_a_quarta(): void
    {
        DB::table('lighting_self_service_dates')->insert([
            'date' => self::QUARTA, 'mode' => 'allow', 'reason' => 'Feriado municipal',
        ]);

        Carbon::setTestNow(self::QUARTA . ' 19:00:00');

        $this->availability()
            ->assertOk()
            ->assertJsonPath('open', true)
            ->assertJsonPath('window.end', '21:00')
            ->assertJsonPath('window.reason', 'Feriado municipal');
    }

    /* ─────────────────────────── Catálogo ─────────────────────────── */

    public function test_so_os_grupos_com_quadra_liberada_aparecem(): void
    {
        $groups = $this->withHeaders($this->headers())
            ->getJson('/api/lighting/groups')
            ->assertOk()
            ->json('groups');

        $this->assertCount(1, $groups);
        $this->assertSame('Beach Tennis', $groups[0]['name']);
        $this->assertSame(2, $groups[0]['places']);
    }

    public function test_as_quadras_do_grupo_dizem_se_a_luz_ja_esta_acesa(): void
    {
        $this->activate(10)->assertStatus(201);

        $places = $this->withHeaders($this->headers(self::CPF_BRUNO))
            ->getJson('/api/lighting/groups/1/places')
            ->assertOk()
            ->json('places');

        $this->assertCount(2, $places);
        // Acesa não é indisponível: o Bruno ainda pode acionar a Quadra 1 para
        // prolongar. `lit` muda o texto do botão, não a existência dele.
        $this->assertTrue($places[0]['lit']);
        $this->assertNotNull($places[0]['lit_until']);
        $this->assertFalse($places[1]['lit']);
        $this->assertNull($places[1]['lit_until']);
    }

    /* ─────────────────────────── Acionamento ─────────────────────────── */

    public function test_acionamento_acende_a_luz_para_o_home_assistant(): void
    {
        $this->assertFalse($this->automationState('beach_quadra_1'));

        $this->activate(10)
            ->assertStatus(201)
            ->assertJsonPath('activation.place_name', 'Quadra 1')
            ->assertJsonPath('activation.minutes_remaining', 120);

        // O que prova o recurso: o HA, no polling seguinte, recebe "ligado".
        $this->assertTrue($this->automationState('beach_quadra_1'));

        $override = HomeAssistantOverride::sole();
        $this->assertTrue($override->is_quick);
        $this->assertSame(1000, $override->priority);
        $this->assertSame(self::SABADO . ' 20:00:00', $override->expires_at->format('Y-m-d H:i:s'));

        $activation = MemberLightingActivation::sole();
        $this->assertSame(1, $activation->member_id);
        $this->assertSame(10, $activation->place_id);
        $this->assertSame($override->id, $activation->home_assistant_override_id);
    }

    public function test_fora_da_janela_o_acionamento_e_recusado(): void
    {
        Carbon::setTestNow(self::QUARTA . ' 19:00:00');

        $this->activate(10)
            ->assertStatus(422)
            ->assertJsonPath('reason', 'window_closed')
            ->assertJsonPath('next_window.date', self::SABADO);

        $this->assertSame(0, MemberLightingActivation::count());
        $this->assertFalse($this->automationState('beach_quadra_1'));
    }

    public function test_quadra_nao_liberada_nao_aciona(): void
    {
        $this->activate(30)
            ->assertStatus(422)
            ->assertJsonPath('reason', 'place_not_eligible');
    }

    public function test_o_socio_so_pode_ter_uma_quadra_acesa(): void
    {
        $this->activate(10)->assertStatus(201);

        $this->activate(20)
            ->assertStatus(409)
            ->assertJsonPath('reason', 'member_limit')
            ->assertJsonPath('active_activation.place_name', 'Quadra 1');

        $this->assertFalse($this->automationState('beach_quadra_2'));
    }

    /* ───────────── Duração escolhida ───────────── */

    public function test_o_socio_escolhe_quantos_minutos_quer(): void
    {
        $this->activate(10, minutes: 45)
            ->assertStatus(201)
            ->assertJsonPath('activation.minutes_remaining', 45);

        $this->assertSame(
            self::SABADO . ' 18:45:00',
            HomeAssistantOverride::sole()->expires_at->format('Y-m-d H:i:s')
        );
    }

    public function test_pedido_acima_do_teto_nao_passa_na_validacao(): void
    {
        $this->activate(10, minutes: 180)
            ->assertStatus(422)
            ->assertJsonValidationErrors('minutes');

        $this->assertSame(0, MemberLightingActivation::count());
    }

    public function test_pedido_abaixo_do_minimo_nao_passa_na_validacao(): void
    {
        $this->activate(10, minutes: 5)
            ->assertStatus(422)
            ->assertJsonValidationErrors('minutes');
    }

    /* ───────────── Prolongamento ───────────── */

    public function test_o_socio_prolonga_a_propria_quadra_sem_a_luz_apagar(): void
    {
        $this->activate(10, minutes: 60)->assertStatus(201);

        // 50 minutos depois, faltando 10 para acabar: ele pede mais 60.
        Carbon::setTestNow(self::SABADO . ' 18:50:00');

        $this->activate(10, minutes: 60)
            // 200, não 201: é a mesma sessão de uso, prolongada.
            ->assertStatus(200)
            ->assertJsonPath('extended', true)
            ->assertJsonPath('activation.minutes_remaining', 60);

        // Uma ativação só, com o começo preservado e o fim empurrado.
        $activation = MemberLightingActivation::sole();
        $this->assertSame(self::SABADO . ' 18:00:00', $activation->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame(self::SABADO . ' 19:50:00', $activation->ends_at->format('Y-m-d H:i:s'));

        $this->assertTrue($this->automationState('beach_quadra_1'));
    }

    public function test_outro_socio_prolonga_a_luz_da_quadra(): void
    {
        // O caso que motiva tudo isto: a Ana acendeu, a partida passou do
        // tempo, e quem está em quadra prolonga — não precisa ser quem acendeu.
        $this->activate(10, self::CPF_ANA, minutes: 120)->assertStatus(201);

        Carbon::setTestNow(self::SABADO . ' 19:30:00');

        $this->activate(10, self::CPF_BRUNO, minutes: 120)
            ->assertStatus(201)
            ->assertJsonPath('extended', false);

        // A luz vai até o prazo do Bruno, e não morre às 20:00 com o da Ana.
        $this->assertSame(
            self::SABADO . ' 21:30:00',
            HomeAssistantOverride::sole()->expires_at->format('Y-m-d H:i:s')
        );

        Carbon::setTestNow(self::SABADO . ' 20:05:00');
        $this->assertTrue($this->automationState('beach_quadra_1'));
    }

    public function test_pedido_curto_nao_encurta_a_luz_de_quem_ja_estava(): void
    {
        $this->activate(10, self::CPF_ANA, minutes: 120)->assertStatus(201);

        // Bruno chega às 18:30 e pede só 20 minutos. A luz da Ana vale até
        // 20:00 e não pode virar 18:50 por causa disso.
        Carbon::setTestNow(self::SABADO . ' 18:30:00');
        $this->activate(10, self::CPF_BRUNO, minutes: 20)->assertStatus(201);

        $this->assertSame(
            self::SABADO . ' 20:00:00',
            HomeAssistantOverride::sole()->expires_at->format('Y-m-d H:i:s')
        );
    }

    public function test_quem_devolve_a_quadra_nao_apaga_a_luz_de_quem_ficou(): void
    {
        $this->activate(10, self::CPF_ANA, minutes: 120)->assertStatus(201);

        Carbon::setTestNow(self::SABADO . ' 18:30:00');
        $this->activate(10, self::CPF_BRUNO, minutes: 120)->assertStatus(201);

        // Ana vai embora. Bruno continua jogando.
        $this->withHeaders($this->headers(self::CPF_ANA))
            ->postJson('/api/lighting/release')->assertOk();

        $this->assertTrue($this->automationState('beach_quadra_1'));
        $this->assertSame(
            self::SABADO . ' 20:30:00',
            HomeAssistantOverride::sole()->expires_at->format('Y-m-d H:i:s')
        );
    }

    public function test_quadra_com_reserva_confirmada_e_recusada(): void
    {
        $this->reservation(10, '19:00:00', '20:00:00');

        // São 18:00 e a reserva é das 19:00: ainda assim recusa, porque as duas
        // horas do acionamento passariam por cima da quadra de quem reservou.
        $this->activate(10)
            ->assertStatus(409)
            ->assertJsonPath('reason', 'place_reserved');

        $this->assertSame(0, MemberLightingActivation::count());
    }

    public function test_reserva_que_nao_encosta_no_periodo_nao_atrapalha(): void
    {
        // Reserva encerrada às 17:30; o acionamento das 18:00 não a toca.
        $this->reservation(10, '16:00:00', '17:30:00');

        $this->activate(10)->assertStatus(201);
    }

    public function test_reserva_cancelada_nao_bloqueia(): void
    {
        $this->reservation(10, '19:00:00', '20:00:00', status: 3);

        $this->activate(10)->assertStatus(201);
    }

    public function test_perto_do_fechamento_a_duracao_e_aparada(): void
    {
        Carbon::setTestNow(self::SABADO . ' 22:30:00');

        $this->activate(10)
            ->assertStatus(201)
            ->assertJsonPath('activation.minutes_remaining', 30);

        $this->assertSame(
            self::SABADO . ' 23:00:00',
            HomeAssistantOverride::sole()->expires_at->format('Y-m-d H:i:s')
        );
    }

    public function test_quase_no_fechamento_o_acionamento_nao_vale_a_pena(): void
    {
        Carbon::setTestNow(self::SABADO . ' 22:50:00');

        $this->activate(10)
            ->assertStatus(422)
            ->assertJsonPath('reason', 'window_ending');

        $this->assertSame(0, MemberLightingActivation::count());
    }

    /* ─────────────────────────── Devolução ─────────────────────────── */

    public function test_devolver_a_quadra_apaga_a_luz_e_libera_a_cota(): void
    {
        $this->activate(10)->assertStatus(201);
        $this->assertTrue($this->automationState('beach_quadra_1'));

        $this->withHeaders($this->headers())
            ->postJson('/api/lighting/release')
            ->assertOk()
            ->assertJsonPath('activation.minutes_remaining', 0);

        $this->assertFalse($this->automationState('beach_quadra_1'));
        $this->assertSame(0, HomeAssistantOverride::count());

        // Cota livre de novo: sem teto diário, ele pode acionar outra quadra.
        $this->activate(20)->assertStatus(201);
    }

    public function test_devolucao_nao_apaga_um_comando_dado_no_painel_por_cima(): void
    {
        $this->activate(10, minutes: 120)->assertStatus(201);

        // Alguém do clube manda manter a quadra acesa pelo painel — o que
        // substitui o comando do autoatendimento no contator.
        app(\App\Services\HomeAssistant\ManualCommandService::class)->apply(
            contactor: \App\Models\Contactor::find(1),
            state: 'on',
            minutes: null,
            origin: 'Painel',
        );

        $this->withHeaders($this->headers())
            ->postJson('/api/lighting/release')->assertOk();

        // A luz continua: quem está no painel sabe o que está fazendo, e a
        // saída de um sócio não pode desfazer a decisão dele.
        $this->assertTrue($this->automationState('beach_quadra_1'));
        $this->assertSame('Painel', HomeAssistantOverride::sole()->origin);
    }

    public function test_devolver_sem_ter_quadra_acesa_devolve_404(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/lighting/release')
            ->assertStatus(404)
            ->assertJsonPath('reason', 'no_activation');
    }

    /* ─────────────────────────── Expiração ─────────────────────────── */

    public function test_vencido_o_prazo_a_luz_apaga_e_a_cota_volta_sozinha(): void
    {
        $this->activate(10)->assertStatus(201);

        // 20:01: as duas horas acabaram. Ninguém desligou nada.
        Carbon::setTestNow(self::SABADO . ' 20:01:00');

        $this->assertFalse($this->automationState('beach_quadra_1'));

        $this->availability()->assertJsonPath('activation', null);

        $this->activate(20)->assertStatus(201);
    }

    public function test_historico_lista_os_acionamentos_do_socio(): void
    {
        $this->activate(10)->assertStatus(201);

        $this->withHeaders($this->headers())
            ->postJson('/api/lighting/release')->assertOk();

        $this->withHeaders($this->headers())
            ->getJson('/api/lighting/activations')
            ->assertOk()
            ->assertJsonCount(1, 'activations')
            ->assertJsonPath('activations.0.place_name', 'Quadra 1');
    }
}
