<?php

namespace App\Services\Replay;

use App\Jobs\SendReplayVideosMail;
use App\Models\Replay\Camera;
use App\Models\Replay\MemberNotification;
use App\Models\Replay\Video;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Recebe o aviso de clipe novo do sistema de captura, traz a cópia do FTP e
 * amarra a quem for de direito.
 *
 * O clipe em si mora no servidor de arquivos (FTP): o sistema de captura
 * grava lá e manda só o caminho. O Lara baixa uma cópia para servir pelo
 * servidor web — ver RemoteVideoStore.
 *
 * Três decisões moram aqui:
 *
 *  1. IDEMPOTÊNCIA pelo `external_id` e pelo caminho no FTP. O outro sistema
 *     tem fila e retenta; avisar o mesmo clipe duas vezes não pode gerar dois
 *     registros nem dois e-mails — e dois registros do mesmo arquivo fariam o
 *     expurgo de um apagar o vídeo do outro.
 *  2. O vínculo com o sócio sai da RESERVA PAGA (status_id = 1) que cobria o
 *     instante da gravação. Reserva pendente ou cancelada não vincula: o
 *     vídeo fica só na galeria da quadra.
 *  3. A expiração conta da GRAVAÇÃO, não do envio. Um clipe que ficou preso
 *     na fila do outro lado por dois dias chega com cinco de vida, não com
 *     sete — sete dias é a promessa feita ao sócio sobre o jogo dele.
 */
class VideoIntakeService
{
    /** Formatos aceitos, conferidos pelos bytes reais do container. */
    const EXTENSIONS = ['mp4', 'webm'];

    public function __construct(
        private ReplayResolver $resolver,
        private RemoteVideoStore $remote,
    ) {
    }

    /**
     * @return array{video: Video, duplicated: bool}
     *
     * @throws InvalidArgumentException  caminho inválido, arquivo ausente no FTP ou formato não aceito
     * @throws RemoteStorageUnavailable  FTP fora do ar — o outro lado deve retentar
     */
    public function store(
        Camera $camera,
        string $remotePath,
        Carbon $recordedAt,
        int $durationSeconds,
        ?string $externalId,
    ): array {
        $remotePath = $this->remote->normalizePath($remotePath);

        $existing = Video::query()
            ->where('file_path', $remotePath)
            ->when($externalId, fn ($q) => $q->orWhere('external_id', $externalId))
            ->first();

        if ($existing) {
            return ['video' => $existing, 'duplicated' => true];
        }

        $place = $camera->place;

        if (! $place) {
            throw new InvalidArgumentException('A câmera não está vinculada a nenhuma quadra.');
        }

        $uuid = (string) Str::uuid();
        // Uma pasta por mês: sete dias de retenção cabem em duas pastas, e o
        // diretório nunca acumula dezenas de milhares de arquivos — o que
        // deixa lento até um `ls` no servidor.
        $dir = MediaService::VIDEO_DIR . '/' . $recordedAt->format('Y/m');
        $download = "{$dir}/{$uuid}.download";

        // O download que confere o formato é o mesmo que vira a cópia
        // servida: o adaptador FTP só sabe ler o arquivo inteiro.
        $this->remote->download($remotePath, $download);

        try {
            $extension = $this->detectExtension(MediaService::absolutePath($download));
        } catch (InvalidArgumentException $e) {
            MediaService::disk()->delete($download);

            throw $e;
        }

        $sizeBytes = (int) (MediaService::disk()->size($download) ?: 0);
        $localPath = "{$dir}/{$uuid}.{$extension}";
        MediaService::disk()->move($download, $localPath);

        $schedule = $this->paidScheduleFor($place->id, $recordedAt);

        $video = Video::create([
            'uuid' => $uuid,
            'place_id' => $place->id,
            // Denormalizado: a quadra pode mudar de grupo, o vídeo não muda
            // de esporte depois de gravado.
            'place_group_id' => $place->place_group_id,
            'replay_camera_id' => $camera->id,
            'schedule_id' => $schedule?->id,
            'member_id' => $schedule?->member_id,
            'external_id' => $externalId,
            'recorded_at' => $recordedAt,
            'duration_seconds' => $durationSeconds,
            'orientation' => $this->resolver->settingFor($place)['orientation'],
            'file_path' => $remotePath,
            'local_path' => $localPath,
            'size_bytes' => $sizeBytes,
            'expires_at' => $recordedAt->copy()->addDays(Video::RETENTION_DAYS),
        ]);

        if ($schedule && $schedule->member_id) {
            $this->scheduleNotification($schedule);
        }

        return ['video' => $video, 'duplicated' => false];
    }

    /**
     * A reserva paga que cobria o instante da gravação.
     *
     * `withoutGlobalScopes` porque o model Schedule esconde os expirados por
     * padrão — e a reserva que estamos procurando já terminou. O filtro que
     * importa é o status 1 (confirmada/paga), explícito aqui.
     */
    private function paidScheduleFor(int $placeId, Carbon $recordedAt): ?Schedule
    {
        return Schedule::withoutGlobalScopes()
            ->where('place_id', $placeId)
            ->where('status_id', 1)
            ->where('start_schedule', '<=', $recordedAt)
            ->where('end_schedule', '>=', $recordedAt)
            ->orderBy('start_schedule')
            ->first();
    }

    /**
     * Marca a reserva para receber UM aviso, e só então agenda o envio.
     *
     * A linha em replay_member_notifications é criada aqui, no primeiro clipe
     * — é ela que impede que os outros dezenove apertos do botão agendem
     * outros dezenove e-mails. O `unique` da tabela transforma a corrida de
     * dois clipes simultâneos em uma inserção que falha, não em dois avisos.
     *
     * O envio fica para depois do FIM da reserva, com folga: mandar no
     * primeiro clipe faria o sócio receber um link com um vídeo e perder os
     * dezenove seguintes.
     */
    private function scheduleNotification(Schedule $schedule): void
    {
        try {
            $notification = MemberNotification::firstOrCreate(
                ['schedule_id' => $schedule->id],
                ['member_id' => $schedule->member_id],
            );
        } catch (\Throwable $e) {
            // Corrida perdida: a outra requisição já criou a linha e agendou.
            Log::info('Replay: aviso da reserva já agendado por outra requisição.', [
                'schedule_id' => $schedule->id,
            ]);

            return;
        }

        if (! $notification->wasRecentlyCreated) {
            return;
        }

        $delay = $schedule->end_schedule
            ? Carbon::parse($schedule->end_schedule)->addMinutes(5)
            : now()->addMinutes(30);

        // Reserva que já terminou (clipe atrasado) manda em seguida, sem
        // esperar um horário no passado.
        SendReplayVideosMail::dispatch($notification->id)
            ->delay($delay->isPast() ? now()->addMinute() : $delay);
    }

    /**
     * O tipo sai da assinatura do container, nunca da extensão do nome — ela
     * é escolhida por quem gravou. Mesma postura do VideoService do Placar.
     */
    private function detectExtension(string $absolutePath): string
    {
        $handle = @fopen($absolutePath, 'rb');
        $header = $handle ? fread($handle, 32) : '';

        if ($handle) {
            fclose($handle);
        }

        if ($header === '' || $header === false) {
            throw new InvalidArgumentException('Arquivo de vídeo vazio ou ilegível.');
        }

        // WebM/Matroska começa com o magic EBML; MP4 traz o box `ftyp` no
        // offset 4.
        if (str_starts_with($header, "\x1A\x45\xDF\xA3")) {
            return 'webm';
        }

        if (substr($header, 4, 4) === 'ftyp') {
            return 'mp4';
        }

        throw new InvalidArgumentException('Formato não aceito — envie MP4 (H.264) ou WebM.');
    }

    /**
     * Apaga arquivo (no FTP e a cópia local) e registro dos clipes vencidos.
     *
     * Arquivo primeiro, registro depois: um registro órfão aparece na tela e
     * alguém resolve; um arquivo órfão fica ocupando disco em silêncio até o
     * servidor encher. Pelo mesmo motivo, com o FTP fora do ar o expurgo
     * PARA e deixa os registros para a rodada seguinte.
     *
     * @return array{deleted: int, missing: int, ftp_unavailable: bool}
     */
    public function prune(): array
    {
        $deleted = 0;
        $missing = 0;
        $unavailable = false;

        Video::expired()->chunkById(200, function ($videos) use (&$deleted, &$missing, &$unavailable) {
            foreach ($videos as $video) {
                try {
                    if (! $this->remote->delete($video)) {
                        $missing++;
                    }
                } catch (RemoteStorageUnavailable) {
                    $unavailable = true;

                    return false;
                }

                DB::transaction(fn () => $video->delete());
                $deleted++;
            }
        });

        return ['deleted' => $deleted, 'missing' => $missing, 'ftp_unavailable' => $unavailable];
    }
}
