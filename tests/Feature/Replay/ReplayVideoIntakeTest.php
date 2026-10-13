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
use App\Services\Replay\MediaService;
use App\Services\Replay\RemoteVideoStore;
use App\Support\Replay\ReplayAbilities;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use League\Flysystem\Ftp\UnableToConnectToFtpHost;
use Mockery;
use Tests\Concerns\CreatesReplaySchema;
use Tests\TestCase;

/**
 * Recepção dos clipes: o arquivo no FTP, o vínculo com a reserva, a
 * idempotência, a expiração e o expurgo.
 *
 * O clipe é gravado no FTP pelo sistema de captura, que avisa o Lara só com o
 * caminho. Os dois discos são falsos aqui: `replay_ftp` faz o papel do
 * servidor de arquivos e `replay` o da cópia local servida pelo Apache.
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
        Storage::fake(RemoteVideoStore::DISK);
        config(['filesystems.disks.' . RemoteVideoStore::DISK . '.root' => 'Lara/Repet']);

        $group = PlaceGroup::create(['name' => 'Tênis']);
        $this->place = Place::create(['name' => 'Quadra 1', 'place_group_id' => $group->id]);
        $this->camera = Camera::create([
            'place_id' => $this->place->id,
            'external_id' => 'cam-quadra1',
            'name' => 'Quadra 1',
        ]);

        Sanctum::actingAs(ApiClient::create(['name' => 'captura-teste']), [ReplayAbilities::OPERATE]);
    }

    /** Grava no FTP falso um MP4 de mentira, com a assinatura real do container. */
    private function noFtp(string $path, ?string $conteudo = null): string
    {
        Storage::disk(RemoteVideoStore::DISK)->put(
            $path,
            $conteudo ?? "\x00\x00\x00\x20ftypisom" . str_repeat("\x00", 512),
        );

        return $path;
    }

    private function enviar(array $overrides = [])
    {
        return $this->postJson(
            route('api.replay.videos.store', ['external_id' => $this->camera->external_id]),
            array_merge([
                'path' => 'quadra1/clip-001.mp4',
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

    /** O FTP passa a recusar conexão. */
    private function ftpForaDoAr(): void
    {
        $ftp = Mockery::mock(Filesystem::class);
        $ftp->shouldReceive('exists')->andThrow(UnableToConnectToFtpHost::forHost('ftp.teste', 21, false));

        Storage::set(RemoteVideoStore::DISK, $ftp);
    }

    public function test_clipe_do_ftp_e_registrado_e_copiado_para_o_servidor_web(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');

        $response = $this->enviar()->assertCreated();

        $video = Video::first();

        $this->assertNotNull($video);
        $this->assertSame($this->place->id, $video->place_id);
        $this->assertSame('quadra1/clip-001.mp4', $video->file_path);
        $this->assertStringEndsWith('.mp4', $video->local_path);
        Storage::disk('replay')->assertExists($video->local_path);
        $this->assertGreaterThan(0, $video->size_bytes);
        // O original continua no FTP: a cópia não o substitui.
        Storage::disk(RemoteVideoStore::DISK)->assertExists('quadra1/clip-001.mp4');
        // Com a cópia presente, a URL é o arquivo estático.
        $this->assertStringContainsString('storage/' . $video->local_path, $video->url());
        $response->assertJson(['uuid' => $video->uuid, 'path' => 'quadra1/clip-001.mp4', 'duplicated' => false]);
    }

    public function test_caminho_com_a_pasta_raiz_na_frente_chega_ao_mesmo_arquivo(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');

        $this->enviar(['path' => '/Lara/Repet/quadra1/clip-001.mp4'])->assertCreated();

        $this->assertSame('quadra1/clip-001.mp4', Video::first()->file_path);
    }

    public function test_caminho_que_sai_da_pasta_do_replay_e_recusado(): void
    {
        $this->enviar(['path' => 'quadra1/../../lpr/foto.jpg'])->assertStatus(422);

        $this->assertSame(0, Video::count());
    }

    public function test_clipe_que_nao_esta_no_ftp_e_recusado(): void
    {
        $this->enviar(['path' => 'quadra1/nao-existe.mp4'])->assertStatus(422);

        $this->assertSame(0, Video::count());
    }

    public function test_ftp_fora_do_ar_responde_503_para_o_outro_lado_retentar(): void
    {
        $this->ftpForaDoAr();

        $this->enviar()->assertStatus(503);

        $this->assertSame(0, Video::count());
    }

    public function test_expiracao_conta_da_gravacao_e_nao_do_envio(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');

        // Clipe atrasado: gravado há cinco dias, avisado só agora. Precisa
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
        $this->noFtp('quadra1/clip-001.mp4');

        $schedule = $this->reserva(1, memberId: 7);

        $this->enviar();

        $video = Video::first();

        $this->assertSame($schedule->id, $video->schedule_id);
        $this->assertSame(7, $video->member_id);
    }

    public function test_reserva_pendente_de_pagamento_nao_vincula(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');

        // status 3 = aguardando pagamento.
        $this->reserva(3, memberId: 7);

        $this->enviar();

        $video = Video::first();

        $this->assertNull($video->schedule_id);
        $this->assertNull($video->member_id);
    }

    public function test_reenvio_do_mesmo_clipe_nao_duplica(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');

        $this->enviar()->assertCreated();

        // Mesma `external_id`: é o retry do outro lado, não um clipe novo.
        $this->enviar()->assertOk()->assertJson(['duplicated' => true]);

        $this->assertSame(1, Video::count());
    }

    public function test_mesmo_arquivo_sem_external_id_tambem_nao_duplica(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');

        $this->enviar(['external_id' => null])->assertCreated();
        $this->enviar(['external_id' => null])->assertOk()->assertJson(['duplicated' => true]);

        $this->assertSame(1, Video::count());
    }

    public function test_varios_clipes_da_mesma_reserva_geram_um_unico_aviso(): void
    {
        Queue::fake();

        $this->reserva(1, memberId: 7);

        foreach (['clip-001', 'clip-002', 'clip-003'] as $clip) {
            $this->noFtp("quadra1/{$clip}.mp4");
            $this->enviar(['external_id' => $clip, 'path' => "quadra1/{$clip}.mp4"]);
        }

        $this->assertSame(3, Video::count());
        // Um aviso por RESERVA — não por clipe.
        $this->assertSame(1, MemberNotification::count());
        Queue::assertPushed(SendReplayVideosMail::class, 1);
    }

    public function test_clipe_sem_reserva_nao_agenda_aviso(): void
    {
        Queue::fake();
        $this->noFtp('quadra1/clip-001.mp4');

        $this->enviar();

        $this->assertSame(0, MemberNotification::count());
        Queue::assertNotPushed(SendReplayVideosMail::class);
    }

    public function test_camera_desconhecida_recebe_404(): void
    {
        $this->postJson(route('api.replay.videos.store', ['external_id' => 'cam-inexistente']), [
            'path' => 'quadra1/clip-001.mp4',
            'recorded_at' => '2026-09-17T14:30:00-03:00',
            'duration_seconds' => 30,
        ])->assertNotFound();
    }

    public function test_arquivo_que_nao_e_video_e_recusado_e_nao_deixa_copia(): void
    {
        $this->noFtp('quadra1/clip-001.mp4', 'isto aqui e texto puro');

        $this->enviar()->assertStatus(422);

        $this->assertSame(0, Video::count());
        $this->assertSame([], Storage::disk('replay')->allFiles(MediaService::VIDEO_DIR));
    }

    public function test_copia_local_que_sumiu_e_refeita_a_partir_do_ftp(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');
        $this->enviar(['recorded_at' => now()->subHour()->toIso8601String()])->assertCreated();

        $video = Video::first();
        Storage::disk('replay')->delete($video->local_path);

        // Sem a cópia, a URL passa pela rota que a refaz.
        $this->assertSame(route('replay.videos.file', ['uuid' => $video->uuid]), $video->url());

        $this->get($video->url())->assertRedirect(MediaService::url($video->local_path));

        Storage::disk('replay')->assertExists($video->local_path);
    }

    public function test_rota_do_arquivo_nao_serve_clipe_vencido(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');
        $this->enviar(['recorded_at' => now()->subDays(10)->toIso8601String()]);

        $video = Video::first();

        $this->get(route('replay.videos.file', ['uuid' => $video->uuid]))->assertNotFound();
    }

    public function test_rota_do_arquivo_com_ftp_fora_responde_503(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');
        $this->enviar(['recorded_at' => now()->subHour()->toIso8601String()])->assertCreated();

        $video = Video::first();
        Storage::disk('replay')->delete($video->local_path);
        $this->ftpForaDoAr();

        $this->get(route('replay.videos.file', ['uuid' => $video->uuid]))->assertStatus(503);
    }

    public function test_expurgo_apaga_do_ftp_a_copia_local_e_o_registro_dos_vencidos(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');
        $this->enviar(['recorded_at' => now()->subDays(10)->toIso8601String()]);

        $video = Video::first();

        $this->artisan('replay:prune')->assertSuccessful();

        $this->assertSame(0, Video::count());
        Storage::disk(RemoteVideoStore::DISK)->assertMissing($video->file_path);
        Storage::disk('replay')->assertMissing($video->local_path);
    }

    public function test_expurgo_com_ftp_fora_preserva_o_registro_para_a_proxima_rodada(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');
        $this->enviar(['recorded_at' => now()->subDays(10)->toIso8601String()]);

        $this->ftpForaDoAr();

        $this->artisan('replay:prune')->assertFailed();

        $this->assertSame(1, Video::count());
    }

    public function test_expurgo_poupa_o_que_ainda_esta_no_prazo(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');
        $this->enviar(['recorded_at' => now()->subDay()->toIso8601String()]);

        $this->artisan('replay:prune')->assertSuccessful();

        $this->assertSame(1, Video::count());
    }

    public function test_exclusao_manual_com_ftp_fora_nao_apaga_o_registro(): void
    {
        $this->noFtp('quadra1/clip-001.mp4');
        $this->enviar()->assertCreated();

        $this->ftpForaDoAr();

        $video = Video::first();
        $this->app->make(\App\Http\Controllers\Replay\Web\VideoController::class)->destroy($video);

        $this->assertSame(1, Video::count());
    }
}
