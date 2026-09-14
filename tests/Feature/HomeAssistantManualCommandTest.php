<?php

namespace Tests\Feature;

use App\Models\HomeAssistantOverride;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Comando manual vindo do Home Assistant (origem típica: Telegram).
 *
 * O que importa aqui é o ciclo completo: o POST grava, o GET /automation passa
 * a refletir o comando, e sozinho — sem ninguém desligar — o contator volta a
 * seguir as reservas quando o prazo vence.
 *
 * Sem RefreshDatabase pelo mesmo motivo de FleetMileageTest: só as tabelas que
 * o endpoint consulta são criadas no SQLite :memory: do phpunit.xml.
 */
class HomeAssistantManualCommandTest extends TestCase
{
    private string $token = 'token-de-teste';

    /** Sexta-feira, 04/09/2026. */
    private const DATE = '2026-09-04';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.api.token' => $this->token]);

        $this->createSchema();

        DB::table('contactors')->insert([
            ['id' => 1, 'name' => 'Beach Tennis Quadra 1', 'entity_id' => 'beach_quadra_1'],
            ['id' => 2, 'name' => 'Beach Tennis Quadra 2', 'entity_id' => 'beach_quadra_2'],
            // Nem todo entity_id é sem ponto: o do HA costuma ser switch.alguma_coisa
            ['id' => 3, 'name' => 'Piscina', 'entity_id' => 'switch.piscina'],
        ]);
        DB::table('places')->insert([
            ['id' => 10, 'name' => 'Quadra 1', 'contactor_id' => 1],
            ['id' => 20, 'name' => 'Quadra 2', 'contactor_id' => 2],
        ]);

        Carbon::setTestNow(self::DATE . ' 19:30:00');
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

        // Espelha home_assistant_overrides depois da migration
        // 2026_09_14_150000_add_manual_expiry_to_home_assistant_overrides.
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

        // As FKs em cascade são o que apaga os vínculos quando o comando é
        // removido — sem elas o teste não provaria o que o banco real faz.
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
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'];
    }

    private function manual(string $entityId, array $payload)
    {
        return $this->withHeaders($this->headers())
            ->postJson("/api/schedule/home-assistant/contactors/{$entityId}/manual", $payload);
    }

    /** Estado que o Home Assistant receberia agora. */
    private function automationState(string $entityId): bool
    {
        return $this->withHeaders($this->headers())
            ->getJson('/api/schedule/home-assistant/automation')
            ->assertOk()
            ->json('contactors')[$entityId];
    }

    private function reservation(int $placeId, string $start, string $end): void
    {
        DB::table('schedules')->insert([
            'place_id'       => $placeId,
            'start_schedule' => self::DATE . ' ' . $start,
            'end_schedule'   => self::DATE . ' ' . $end,
            'status_id'      => 1,
        ]);
    }

    /* ───────────── Token ───────────── */

    public function test_sem_token_o_comando_nao_passa(): void
    {
        $this->postJson('/api/schedule/home-assistant/contactors/beach_quadra_1/manual', ['state' => 'on'])
            ->assertStatus(401);

        $this->assertSame(0, HomeAssistantOverride::count());
    }

    public function test_token_vazio_no_servidor_nao_libera_a_rota(): void
    {
        // Antes, com API_TOKEN vazio, o header "Bearer " puro era aceito.
        config(['services.api.token' => '']);

        $this->withHeaders(['Authorization' => 'Bearer ', 'Accept' => 'application/json'])
            ->postJson('/api/schedule/home-assistant/contactors/beach_quadra_1/manual', ['state' => 'on'])
            ->assertStatus(401);
    }

    /* ───────────── Escrita ───────────── */

    public function test_comando_liga_o_contator_e_devolve_o_estado_resolvido(): void
    {
        $response = $this->manual('beach_quadra_1', [
            'state'            => 'on',
            'duration_minutes' => 120,
            'origin'           => 'Telegram (Gustavo)',
        ])->assertStatus(201);

        $override = HomeAssistantOverride::sole();

        $response->assertExactJson([
            'entity_id'    => 'beach_quadra_1',
            'on'           => true,
            'manual_until' => '2026-09-04T21:30:00-03:00',
            'override_id'  => $override->id,
        ]);

        $this->assertSame('manual_on', $override->mode);
        $this->assertTrue($override->is_quick);
        $this->assertSame(1000, $override->priority);
        $this->assertSame('Telegram (Gustavo)', $override->origin);
        // API é autenticada por token, não por usuário
        $this->assertNull($override->created_by);
        $this->assertTrue($this->automationState('beach_quadra_1'));
        $this->assertFalse($this->automationState('beach_quadra_2'), 'o comando não vaza para outro contator');
    }

    public function test_sem_duracao_vale_o_padrao_da_configuracao(): void
    {
        config(['home_assistant.manual_default_minutes' => 45]);

        $this->manual('beach_quadra_1', ['state' => 'on'])
            ->assertStatus(201)
            ->assertJsonPath('manual_until', '2026-09-04T20:15:00-03:00');
    }

    public function test_sem_o_config_publicado_vale_o_default_literal(): void
    {
        // Deploy sem `config:cache`: o config/home_assistant.php novo não existe
        // para a aplicação. O comando não pode nascer com duração 0.
        config(['home_assistant' => null]);

        $this->manual('beach_quadra_1', ['state' => 'on'])
            ->assertStatus(201)
            ->assertJsonPath('manual_until', '2026-09-04T21:30:00-03:00')
            ->assertJsonPath('on', true);
    }

    public function test_comando_desliga_mesmo_com_reserva_em_andamento(): void
    {
        $this->reservation(10, '19:00:00', '21:00:00');
        $this->assertTrue($this->automationState('beach_quadra_1'));

        $this->manual('beach_quadra_1', ['state' => 'off', 'duration_minutes' => 30])
            ->assertStatus(201)
            ->assertJsonPath('on', false);

        $this->assertFalse($this->automationState('beach_quadra_1'));
    }

    public function test_entity_id_com_ponto_e_aceito_na_url(): void
    {
        $this->manual('switch.piscina', ['state' => 'on'])
            ->assertStatus(201)
            ->assertJsonPath('entity_id', 'switch.piscina');
    }

    /* ───────────── Expiração ───────────── */

    public function test_vencido_o_prazo_o_contator_volta_sozinho_ao_estado_das_reservas(): void
    {
        $this->reservation(10, '19:00:00', '21:00:00');

        $this->manual('beach_quadra_1', ['state' => 'off', 'duration_minutes' => 30])->assertStatus(201);

        Carbon::setTestNow(self::DATE . ' 19:59:00');
        $this->assertFalse($this->automationState('beach_quadra_1'), 'dentro do prazo, ainda manual');

        Carbon::setTestNow(self::DATE . ' 20:01:00');
        $this->assertTrue($this->automationState('beach_quadra_1'), 'vencido, volta para a reserva');
    }

    public function test_prazo_e_truncado_na_virada_da_meia_noite(): void
    {
        Carbon::setTestNow(self::DATE . ' 23:00:00');

        $this->manual('beach_quadra_1', ['state' => 'on', 'duration_minutes' => 120])
            ->assertStatus(201)
            ->assertJsonPath('manual_until', '2026-09-04T23:59:59-03:00');

        Carbon::setTestNow('2026-09-05 00:05:00');
        $this->assertFalse($this->automationState('beach_quadra_1'));
    }

    /* ───────────── auto e reenvio ───────────── */

    public function test_auto_remove_a_acao_rapida(): void
    {
        $this->reservation(10, '19:00:00', '21:00:00');
        $this->manual('beach_quadra_1', ['state' => 'off'])->assertStatus(201);

        $this->manual('beach_quadra_1', ['state' => 'auto'])
            ->assertStatus(200)
            ->assertExactJson([
                'entity_id'    => 'beach_quadra_1',
                'on'           => true,
                'manual_until' => null,
                'override_id'  => null,
            ]);

        $this->assertSame(0, HomeAssistantOverride::count());
        $this->assertSame(0, DB::table('home_assistant_override_contactor')->count());
    }

    public function test_dois_comandos_seguidos_nao_acumulam_acoes_rapidas(): void
    {
        $this->manual('beach_quadra_1', ['state' => 'on'])->assertStatus(201);
        $this->manual('beach_quadra_1', ['state' => 'off'])->assertStatus(201);

        $this->assertSame(1, HomeAssistantOverride::count());
        $this->assertSame(1, DB::table('home_assistant_override_contactor')->count());
        $this->assertSame('manual_off', HomeAssistantOverride::sole()->mode);
        $this->assertFalse($this->automationState('beach_quadra_1'));
    }

    /* ───────────── Recusas ───────────── */

    public function test_entity_id_desconhecido_devolve_404(): void
    {
        $this->manual('beach_quadra_9', ['state' => 'on'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Contator não encontrado.');

        $this->assertSame(0, HomeAssistantOverride::count());
    }

    public function test_payload_invalido_devolve_422(): void
    {
        $this->manual('beach_quadra_1', ['state' => 'ligar'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('state');

        $this->manual('beach_quadra_1', ['state' => 'on', 'duration_minutes' => 721])
            ->assertStatus(422)
            ->assertJsonValidationErrors('duration_minutes');

        $this->manual('beach_quadra_1', ['state' => 'on', 'origin' => str_repeat('a', 81)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('origin');

        $this->assertSame(0, HomeAssistantOverride::count());
    }
}
