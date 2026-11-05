<?php

namespace Tests\Feature;

use App\Authorization\Permissions;
use App\Authorization\UserAccess;
use App\Http\Controllers\ScheduleLightingController;
use App\Models\HomeAssistantOverride;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * "Acender" a luz de uma quadra pela agenda de reservas (/schedule).
 *
 * Vira o comando manual do Home Assistant com prazo em minutos; acender de
 * novo renova o prazo para a nova minutagem, contada a partir de agora.
 *
 * Sem RefreshDatabase (ver HomeAssistantManualCommandTest): só as tabelas
 * usadas são criadas no SQLite :memory: do phpunit.xml. O usuário é mockado
 * porque o model User está preso à conexão mysql.
 */
class ScheduleLightingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();

        DB::table('contactors')->insert([
            ['id' => 1, 'name' => 'Tênis Quadra 1', 'entity_id' => 'switch.tenis_1'],
        ]);
        DB::table('places')->insert([
            ['id' => 10, 'name' => 'Quadra 1', 'contactor_id' => 1],
            ['id' => 20, 'name' => 'Quadra sem luz', 'contactor_id' => null],
        ]);

        Carbon::setTestNow('2026-11-05 19:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_acende_pelo_prazo_informado(): void
    {
        $this->acender(10, 45)
            ->assertRedirect()
            ->assertSessionHas('success', 'Luz de Quadra 1 acesa até 19:45.');

        $override = $this->comandoDaQuadra1();
        $this->assertSame('manual_on', $override->mode);
        $this->assertTrue($override->is_quick);
        $this->assertSame('2026-11-05 19:45:00', $override->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(ScheduleLightingController::ORIGIN, $override->origin);
        $this->assertSame(7, $override->created_by);
    }

    public function test_acender_quadra_acesa_renova_para_a_nova_minutagem(): void
    {
        $this->acender(10, 120);

        Carbon::setTestNow('2026-11-05 19:30:00');
        $this->acender(10, 30)->assertSessionHas('success', 'Luz de Quadra 1 acesa até 20:00.');

        $this->assertSame(1, HomeAssistantOverride::where('is_quick', true)->count());
        $this->assertSame('2026-11-05 20:00:00', $this->comandoDaQuadra1()->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_prazo_nao_passa_da_meia_noite(): void
    {
        Carbon::setTestNow('2026-11-05 23:30:00');

        $this->acender(10, 120)->assertSessionHas('success', 'Luz de Quadra 1 acesa até 23:59.');
    }

    public function test_quadra_sem_contator_e_recusada(): void
    {
        $this->acender(20, 30)->assertSessionHas('warning', 'Quadra sem luz não tem luz ligada ao sistema.');

        $this->assertSame(0, HomeAssistantOverride::count());
    }

    public function test_minutos_invalidos_sao_recusados(): void
    {
        $this->acender(10, 0)->assertSessionHas('warning', 'Informe ao menos 1 minuto.');
        $this->acender(10, 9999)->assertSessionHas('warning');

        $this->assertSame(0, HomeAssistantOverride::count());
    }

    public function test_sem_permissao_de_luz_nao_acende(): void
    {
        $this->actingAs($this->usuario([Permissions::RESERVAS_AGENDAMENTOS]))
            ->post(route('schedule.lights.store', 10), ['minutes' => 30])
            ->assertForbidden();

        $this->assertSame(0, HomeAssistantOverride::count());
    }

    private function acender(int $placeId, int $minutes)
    {
        return $this->actingAs($this->usuario([Permissions::RESERVAS_AGENDAMENTOS, Permissions::RESERVAS_LUZ]))
            ->from('/schedule')
            ->post(route('schedule.lights.store', $placeId), ['minutes' => $minutes]);
    }

    private function comandoDaQuadra1(): HomeAssistantOverride
    {
        return HomeAssistantOverride::whereHas('contactors', fn ($q) => $q->where('contactors.id', 1))
            ->where('is_quick', true)
            ->firstOrFail();
    }

    /** @param list<string> $permissions */
    private function usuario(array $permissions): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('access')->andReturn(new UserAccess($permissions));
        $user->shouldReceive('isCoordinator')->andReturn(false);
        $user->id = 7;
        $user->name = 'Atendente';

        return $user;
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
    }
}
