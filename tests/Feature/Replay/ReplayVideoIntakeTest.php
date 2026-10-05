<?php

namespace Tests\Feature\Replay;

use App\Jobs\SendReplayVideosMail;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Replay\ApiClient;
use App\Models\Replay\Camera;
use App\Models\Replay\MemberNotification;
use App\Models\Replay\Video;
use App\Models\Schedule;
use App\Support\Replay\ReplayAbilities;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesReplaySchema;
use Tests\TestCase;

/**
 * Recepção dos clipes: vínculo com a reserva, idempotência e expiração.
 *
 * O vínculo com o sócio é a parte que não pode errar em nenhuma direção:
 * vincular a quem não pagou entrega o vídeo de um jogo a quem não estava
 * nele; não vincular a quem pagou deixa o sócio sem o e-mail e sem os vídeos
 * na área dele.
 */
class ReplayVideoIntakeTest extends TestCase
{
    use CreatesReplaySchema;

    private Place $place;
    private Camera $camera;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createReplaySchema();
        Storage::fake('replay');

        $group = PlaceGroup::create(['name' => 'Tênis']);
        $this->place = Place::create(['name' => 'Quadra 1', 'place_group_id' => $group->id]);
        $this->camera = Camera::create([
            'place_id' => $this->place->id,
            'external_id' => 'cam-quadra1',
            'name' => 'Quadra 1',
        ]);

        Sanctum::actingAs(ApiClient::create(['name' => 'captura-teste']), [ReplayAbilities::OPERATE]);
    }

    /** MP4 de mentira, mas com a assinatura real do container (`ftyp` no offset 4). */
    private function clipe(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'clipe.mp4',
            "\x00\x00\x00\x20ftypisom" . str_repeat("\x00", 512),
        );
    }

    private function enviar(array $overrides = [])
    {
        return $this->post(
            route('api.replay.videos.store', ['external_id' => $this->camera->external_id]),
            array_merge([
                'file' => $this->clipe(),
                'recorded_at' => '2026-09-17T14:30:00-03:00',
                'duration_seconds' => 30,
                'external_id' => 'clip-001',
            ], $overrides),
        );
    }

    /**
     * O sócio NÃO é persistido: o model Member está preso à conexão `mysql` e
     * a suíte roda em SQLite (mesma restrição registrada em
     * ScheduleNotificationTest). Para o que se testa aqui basta o
     * `member_id` gravado na reserva — o envio do e-mail em si roda com a
     * fila falsa.
     */
    private function reserva(int $statusId, ?int $memberId = null): Schedule
    {
        return Schedule::create([
            'member_id' => $memberId,
            'place_id' => $this->place->id,
            'start_schedule' => '2026-09-17 14:00:00',
            'end_schedule' => '2026-09-17 15:00:00',
            'status_id' => $statusId,
        ]);
    }

    public function test_clipe_e_guardado_e_fica_disponivel(): void
    {
        $response = $this->enviar()->assertCreated();

        $video = Video::first();

        $this->assertNotNull($video);
        $this->assertSame($this->place->id, $video->place_id);
        Storage::disk('replay')->assertExists($video->file_path);
        $response->assertJson(['uuid' => $video->uuid, 'duplicated' => false]);
    }

    public function test_expiracao_conta_da_gravacao_e_nao_do_envio(): void
    {
        // Clipe atrasado: gravado há cinco dias, enviado só agora. Precisa
        // chegar com dois dias de vida, não com sete.
        $this->enviar(['recorded_at' => now()->subDays(5)->toIso8601String()]);

        $video = Video::first();

        $this->assertSame(
            now()->subDays(5)->addDays(Video::RETENTION_DAYS)->toDateString(),
            $video->expires_at->toDateString(),
        );
    }

    public function test_vincula_ao_socio_quando_a_reserva_esta_paga(): void
    {
        Queue::fake();

        $schedule = $this->reserva(1, memberId: 7);

        $this->enviar();

        $video = Video::first();

        $this->assertSame($schedule->id, $video->schedule_id);
        $this->assertSame(7, $video->member_id);
    }

    public function test_reserva_pendente_de_pagamento_nao_vincula(): void
    {
        // status 3 = aguardando pagamento.
        $this->reserva(3, memberId: 7);

        $this->enviar();

        $video = Video::first();

        $this->assertNull($video->schedule_id);
        $this->assertNull($video->member_id);
    }

    public function test_reenvio_do_mesmo_clipe_nao_duplica(): void
    {
        $this->enviar()->assertCreated();

        // Mesma `external_id`: é o retry do outro lado, não um clipe novo.
        $this->enviar()->assertOk()->assertJson(['duplicated' => true]);

        $this->assertSame(1, Video::count());
    }

    public function test_varios_clipes_da_mesma_reserva_geram_um_unico_aviso(): void
    {
        Queue::fake();

        $this->reserva(1, memberId: 7);

        $this->enviar(['external_id' => 'clip-001']);
        $this->enviar(['external_id' => 'clip-002']);
        $this->enviar(['external_id' => 'clip-003']);

        $this->assertSame(3, Video::count());
        // Um aviso por RESERVA — não por clipe.
        $this->assertSame(1, MemberNotification::count());
        Queue::assertPushed(SendReplayVideosMail::class, 1);
    }

    public function test_clipe_sem_reserva_nao_agenda_aviso(): void
    {
        Queue::fake();

        $this->enviar();

        $this->assertSame(0, MemberNotification::count());
        Queue::assertNotPushed(SendReplayVideosMail::class);
    }

    public function test_camera_desconhecida_recebe_404(): void
    {
        $this->post(route('api.replay.videos.store', ['external_id' => 'cam-inexistente']), [
            'file' => $this->clipe(),
            'recorded_at' => '2026-09-17T14:30:00-03:00',
            'duration_seconds' => 30,
        ])->assertNotFound();
    }

    public function test_arquivo_que_nao_e_video_e_recusado(): void
    {
        $this->enviar([
            'file' => UploadedFile::fake()->createWithContent('clipe.mp4', 'isto aqui e texto puro'),
        ])->assertStatus(422);

        $this->assertSame(0, Video::count());
    }

    public function test_expurgo_apaga_arquivo_e_registro_dos_vencidos(): void
    {
        $this->enviar(['recorded_at' => now()->subDays(10)->toIso8601String()]);

        $video = Video::first();
        $path = $video->file_path;

        $this->artisan('replay:prune')->assertSuccessful();

        $this->assertSame(0, Video::count());
        Storage::disk('replay')->assertMissing($path);
    }

    public function test_expurgo_poupa_o_que_ainda_esta_no_prazo(): void
    {
        $this->enviar(['recorded_at' => now()->subDay()->toIso8601String()]);

        $this->artisan('replay:prune')->assertSuccessful();

        $this->assertSame(1, Video::count());
    }
}
