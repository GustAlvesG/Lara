<?php

namespace Tests\Feature\Replay;

use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Replay\ApiClient;
use App\Models\Replay\Camera;
use App\Models\Replay\Layout;
use App\Models\Replay\Setting;
use App\Support\Replay\Orientation;
use App\Support\Replay\ReplayAbilities;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesReplaySchema;
use Tests\TestCase;

/**
 * O contrato que o sistema de captura consome.
 *
 * Duas coisas são testadas aqui porque quebrá-las estraga a gravação sem
 * avisar ninguém: a configuração precisa chegar JÁ RESOLVIDA (o outro lado
 * não sabe nada de herança) e o `config_hash` precisa mudar quando — e só
 * quando — a configuração muda.
 */
class ReplayApiConfigTest extends TestCase
{
    use CreatesReplaySchema;

    private PlaceGroup $group;
    private Place $place;
    private Camera $camera;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createReplaySchema();

        $this->group = PlaceGroup::create(['name' => 'Tênis']);
        $this->place = Place::create(['name' => 'Quadra 1', 'place_group_id' => $this->group->id]);
        $this->camera = Camera::create([
            'place_id' => $this->place->id,
            'external_id' => 'cam-quadra1',
            'name' => 'Quadra 1',
            'position' => 'Lado A',
        ]);
    }

    private function autenticar(array $abilities = [ReplayAbilities::OPERATE]): void
    {
        Sanctum::actingAs(ApiClient::create(['name' => 'captura-teste']), $abilities);
    }

    public function test_sem_token_recebe_401(): void
    {
        $this->getJson('/api/replay/cameras')->assertUnauthorized();
    }

    public function test_token_sem_a_ability_recebe_403(): void
    {
        $this->autenticar(['outra-ability-qualquer']);

        $this->getJson('/api/replay/cameras')->assertForbidden();
    }

    public function test_a_configuracao_chega_resolvida_pela_heranca(): void
    {
        $this->autenticar();

        Setting::create([
            'place_group_id' => $this->group->id,
            'orientation' => Orientation::VERTICAL,
            'clip_seconds' => 20,
        ]);

        $response = $this->getJson('/api/replay/cameras')->assertOk();

        $response->assertJsonPath('cameras.0.external_id', 'cam-quadra1');
        $response->assertJsonPath('cameras.0.orientation', Orientation::VERTICAL);
        $response->assertJsonPath('cameras.0.clip_seconds', 20);
        // O outro lado não precisa saber de onde veio — mas o diagnóstico sim.
        $response->assertJsonPath('cameras.0.sources.settings', 'group');
    }

    public function test_camera_sem_layout_recebe_overlay_nulo(): void
    {
        $this->autenticar();

        $this->getJson('/api/replay/cameras')
            ->assertOk()
            ->assertJsonPath('cameras.0.overlay', null);
    }

    public function test_overlay_vem_com_as_dimensoes_da_orientacao(): void
    {
        $this->autenticar();

        Setting::create([
            'place_group_id' => $this->group->id,
            'orientation' => Orientation::VERTICAL,
            'clip_seconds' => 30,
        ]);

        Layout::create([
            'place_group_id' => $this->group->id,
            'orientation' => Orientation::VERTICAL,
            'name' => 'Patrocínio',
            'overlay_path' => 'replay/overlays/1-abc.png',
            'overlay_hash' => 'abc',
        ]);

        $response = $this->getJson('/api/replay/cameras')->assertOk();

        $response->assertJsonPath('cameras.0.overlay.width', 1080);
        $response->assertJsonPath('cameras.0.overlay.height', 1920);
        $response->assertJsonPath('cameras.0.overlay.hash', 'abc');
        // Sem GIF animado não há WebM — e o consumidor precisa tratar isso
        // como "use o PNG", não como erro.
        $response->assertJsonPath('cameras.0.overlay.animated_url', null);
    }

    public function test_camera_inativa_nao_aparece_na_listagem(): void
    {
        $this->autenticar();

        $this->camera->update(['active' => false]);

        $this->getJson('/api/replay/cameras')->assertOk()->assertJsonCount(0, 'cameras');
    }

    public function test_heartbeat_marca_o_contato_sem_mexer_no_config_hash(): void
    {
        $this->autenticar();

        $hashAntes = $this->getJson('/api/replay/cameras')->json('config_hash');

        $this->postJson('/api/replay/cameras/cam-quadra1/heartbeat')->assertOk();

        $this->assertNotNull($this->camera->fresh()->last_seen_at);

        // Se o heartbeat mexesse no hash, o parque inteiro reprocessaria a
        // configuração a cada minuto.
        $this->assertSame($hashAntes, $this->getJson('/api/replay/cameras')->json('config_hash'));
    }

    public function test_consulta_de_uma_camera_so(): void
    {
        $this->autenticar();

        $this->getJson('/api/replay/cameras/cam-quadra1')
            ->assertOk()
            ->assertJsonPath('camera.place.name', 'Quadra 1')
            ->assertJsonPath('camera.position', 'Lado A');

        $this->getJson('/api/replay/cameras/cam-inexistente')->assertNotFound();
    }
}
