<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * O contrato que o Home Assistant consome: `GET /api/schedule/home-assistant/automation`
 * devolve `{"contactors": {"<entity_id>": bool}}`.
 *
 * As regras de decisão estão em Tests\Unit\HomeAssistant\ContactorStateResolverTest;
 * aqui fica o que só aparece com banco — a consulta das reservas — e o token.
 *
 * Sem RefreshDatabase pelo mesmo motivo de FleetMileageTest: só as tabelas que o
 * endpoint consulta são criadas no SQLite :memory: do phpunit.xml.
 */
class HomeAssistantAutomationEndpointTest extends TestCase
{
    private string $token = 'token-de-teste';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.api.token' => $this->token]);

        $this->createSchema();

        DB::table('contactors')->insert([
            ['id' => 1, 'name' => 'Quadra 1', 'entity_id' => 'switch.quadra_1'],
            ['id' => 2, 'name' => 'Quadra 2', 'entity_id' => 'switch.quadra_2'],
        ]);
        DB::table('places')->insert([
            ['id' => 10, 'name' => 'Quadra 1', 'contactor_id' => 1],
            ['id' => 20, 'name' => 'Quadra 2', 'contactor_id' => 2],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createSchema(): void
    {
        Schema::create('contactors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('entity_id')->unique();
            $table->timestamps();
        });

        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('contactor_id')->nullable();
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
            $table->boolean('is_quick')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('home_assistant_override_contactor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('home_assistant_override_id');
            $table->unsignedBigInteger('contactor_id');
        });

        Schema::create('home_assistant_override_weekday', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('home_assistant_override_id');
            $table->unsignedBigInteger('weekday_id');
        });

        Schema::create('home_assistant_override_windows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('home_assistant_override_id');
            $table->time('turn_on_at');
            $table->time('turn_off_at');
            $table->string('state')->default('on');
            $table->timestamps();
        });

        Schema::create('weekdays', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });
    }

    private function reservation(int $placeId, string $start, string $end, int $status = 1): void
    {
        DB::table('schedules')->insert([
            'place_id' => $placeId,
            'start_schedule' => $start,
            'end_schedule' => $end,
            'status_id' => $status,
        ]);
    }

    private function automation()
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'])
            ->getJson('/api/schedule/home-assistant/automation');
    }

    /** entity_id tem ponto, então não dá para usar assertJsonPath. */
    private function stateOf(string $entityId): bool
    {
        return $this->automation()->assertOk()->json('contactors')[$entityId];
    }

    public function test_sem_token_o_endpoint_nao_responde(): void
    {
        $this->getJson('/api/schedule/home-assistant/automation')->assertStatus(401);
    }

    public function test_devolve_o_estado_de_cada_contator_pelo_entity_id(): void
    {
        Carbon::setTestNow('2026-09-04 19:30:00');
        $this->reservation(10, '2026-09-04 19:00:00', '2026-09-04 20:00:00');

        $this->automation()
            ->assertOk()
            ->assertExactJson(['contactors' => ['switch.quadra_1' => true, 'switch.quadra_2' => false]]);
    }

    public function test_reserva_que_comecou_ontem_continua_acesa_depois_da_meia_noite(): void
    {
        // Antes o filtro era whereDate(start_schedule, hoje) e a luz apagava à 00:00.
        Carbon::setTestNow('2026-09-05 00:15:00');
        $this->reservation(10, '2026-09-04 23:00:00', '2026-09-05 00:30:00');

        $this->assertTrue($this->stateOf('switch.quadra_1'));
    }

    public function test_reserva_da_meia_noite_acende_cinco_minutos_antes(): void
    {
        Carbon::setTestNow('2026-09-04 23:56:00');
        $this->reservation(10, '2026-09-05 00:00:00', '2026-09-05 01:00:00');

        $this->assertTrue($this->stateOf('switch.quadra_1'));
    }

    public function test_reserva_nao_confirmada_nao_acende(): void
    {
        Carbon::setTestNow('2026-09-04 19:30:00');
        $this->reservation(10, '2026-09-04 19:00:00', '2026-09-04 20:00:00', status: 2);

        $this->assertFalse($this->stateOf('switch.quadra_1'));
    }

    public function test_agendamento_por_horario_fora_da_faixa_nao_apaga_quadra_reservada(): void
    {
        Carbon::setTestNow('2026-09-04 19:30:00');
        $this->reservation(10, '2026-09-04 19:00:00', '2026-09-04 20:00:00');

        $overrideId = DB::table('home_assistant_overrides')->insertGetId([
            'name' => 'Manhã', 'mode' => 'schedule_override', 'priority' => 10, 'is_active' => true,
        ]);
        DB::table('home_assistant_override_contactor')->insert(['home_assistant_override_id' => $overrideId, 'contactor_id' => 1]);
        DB::table('home_assistant_override_windows')->insert([
            'home_assistant_override_id' => $overrideId, 'turn_on_at' => '06:00:00', 'turn_off_at' => '08:00:00', 'state' => 'on',
        ]);

        $this->assertTrue($this->stateOf('switch.quadra_1'));
    }

    public function test_erro_interno_nao_expoe_a_mensagem_da_excecao(): void
    {
        // Uma falha real de consulta: a QueryException traz o SQL e o nome da tabela.
        Schema::drop('schedules');

        $response = $this->automation()->assertStatus(500);

        $this->assertStringNotContainsString('schedules', $response->getContent());
        $response->assertExactJson(['error' => 'Falha ao calcular o estado dos contatores.']);
    }
}
