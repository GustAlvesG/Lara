<?php

namespace Tests\Feature\Replay;

use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Replay\Video;
use App\Providers\Services\JwtService;
use App\Support\Replay\Orientation;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesReplaySchema;
use Tests\TestCase;

/**
 * O que o site de locação consome.
 *
 * A galeria da quadra é aberta a qualquer visitante — foi a decisão de quem
 * opera —, e por isso o cuidado testado aqui é o inverso do de costume: ela
 * não pode VAZAR quem é o sócio dono da reserva, só marcar que o vídeo
 * pertence a uma.
 */
class ReplayPortalApiTest extends TestCase
{
    use CreatesReplaySchema;

    private Place $place;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createReplaySchema();

        config(['services.api.token' => 'token-de-teste']);

        $group = PlaceGroup::create(['name' => 'Tênis']);
        $this->place = Place::create(['name' => 'Quadra 1', 'place_group_id' => $group->id]);
    }

    private function video(array $overrides = []): Video
    {
        return Video::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'place_id' => $this->place->id,
            'place_group_id' => $this->place->place_group_id,
            'recorded_at' => now()->subHour(),
            'duration_seconds' => 30,
            'orientation' => Orientation::HORIZONTAL,
            'file_path' => 'replay/videos/2026/09/' . Str::uuid() . '.mp4',
            'size_bytes' => 1024,
            'expires_at' => now()->addDays(6),
        ], $overrides));
    }

    private function comToken(): array
    {
        return ['Authorization' => 'Bearer token-de-teste'];
    }

    public function test_sem_token_da_integracao_recebe_401(): void
    {
        $this->getJson('/api/replay/places')->assertUnauthorized();
    }

    public function test_lista_apenas_quadras_que_tem_video_disponivel(): void
    {
        $vazia = Place::create(['name' => 'Quadra 2', 'place_group_id' => $this->place->place_group_id]);
        $this->video();

        $response = $this->getJson('/api/replay/places', $this->comToken())->assertOk();

        $response->assertJsonCount(1, 'places');
        $response->assertJsonPath('places.0.id', $this->place->id);
        $response->assertJsonMissing(['id' => $vazia->id]);
    }

    public function test_video_vencido_nao_aparece_na_galeria(): void
    {
        // Vencido mas ainda no banco: o expurgo roda uma vez por dia, e entre
        // uma rodada e outra a API não pode oferecer o que já expirou.
        $this->video(['expires_at' => now()->subMinute()]);

        $this->getJson('/api/replay/places', $this->comToken())
            ->assertOk()
            ->assertJsonCount(0, 'places');
    }

    public function test_galeria_da_quadra_marca_reserva_sem_revelar_o_socio(): void
    {
        $this->video(['member_id' => 7, 'schedule_id' => 3]);

        $response = $this->getJson("/api/replay/places/{$this->place->id}/videos", $this->comToken())->assertOk();

        $response->assertJsonPath('videos.0.has_member', true);
        // Nenhum identificador do sócio pode sair numa listagem pública.
        $response->assertJsonMissingPath('videos.0.member_id');
        $response->assertJsonMissingPath('videos.0.schedule_id');
    }

    public function test_filtro_por_data(): void
    {
        $this->video(['recorded_at' => now()->subDays(2)]);
        $this->video(['recorded_at' => now()]);

        $response = $this->getJson(
            "/api/replay/places/{$this->place->id}/videos?date=" . now()->toDateString(),
            $this->comToken(),
        )->assertOk();

        $response->assertJsonCount(1, 'videos');
    }

    public function test_meus_videos_exige_sessao_do_socio(): void
    {
        $this->getJson('/api/replay/my-videos', $this->comToken())->assertStatus(400);
    }

    /*
     | Não há teste do caminho feliz de `my-videos`: ele busca o sócio por CPF,
     | e o model Member está preso à conexão `mysql` — que não existe na suíte
     | em SQLite (mesma restrição registrada em ScheduleNotificationTest). O
     | que dá para cobrir aqui é a porta trancada, acima; o filtro por
     | member_id é a mesma consulta já exercitada em ReplayVideoIntakeTest.
     */
}
