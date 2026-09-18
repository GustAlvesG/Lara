<?php

namespace App\Console\Commands;

use App\Models\Place;
use App\Models\Replay\Video;
use App\Models\Schedule;
use App\Services\Replay\MediaService;
use App\Services\Replay\ReplayResolver;
use App\Support\Replay\Orientation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Popula a galeria do Replay com clipes de mentira, para desenvolver e testar
 * o site de locação sem depender das câmeras nem de alguém apertar o botão.
 *
 * O que ele NÃO faz, de propósito:
 *
 *  - **Não dispara e-mail.** A recepção de verdade agenda um aviso para o
 *    sócio da reserva; um seed que fizesse isso mandaria mensagem para gente
 *    real por causa de dado de teste.
 *  - **Não cria nem altera reserva.** Se já existir uma reserva PAGA cobrindo
 *    o horário sorteado, o vídeo é amarrado a ela — mesma regra da produção —,
 *    mas nenhuma reserva é inventada para forçar o vínculo.
 *
 * Todo vídeo criado aqui nasce com `external_id` prefixado por `demo-`, que é
 * o que torna `--clear` seguro: ele nunca alcança um clipe de verdade.
 */
class SeedReplayDemoVideos extends Command
{
    protected $signature = 'replay:demo-videos
        {--per-place=2 : Quantos vídeos por quadra}
        {--place=* : Limita a estas quadras (id); vazio = todas}
        {--seconds=10 : Duração de cada clipe}
        {--clear : Apaga os vídeos de demonstração anteriores antes de gerar}
        {--only-clear : Só apaga os anteriores e sai}
        {--force : Não pergunta nada}';

    protected $description = 'Gera clipes de demonstração do Replay (2 por quadra, por padrão) para testar o site de locação';

    /** Prefixo que identifica o que este comando criou. */
    const DEMO_PREFIX = 'demo-';

    public function handle(ReplayResolver $resolver, MediaService $media): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Isto cria vídeos falsos na galeria que os sócios enxergam. Em produção, só com --force.');

            return self::FAILURE;
        }

        if ($this->option('clear') || $this->option('only-clear')) {
            $this->clearDemoVideos($media);
        }

        if ($this->option('only-clear')) {
            return self::SUCCESS;
        }

        $ffmpeg = (string) config('services.replay.ffmpeg', 'ffmpeg');

        if (! $this->ffmpegWorks($ffmpeg)) {
            $this->error("Não encontrei o ffmpeg em \"{$ffmpeg}\" — ele é quem gera os clipes.");
            $this->line('Aponte o caminho em REPLAY_FFMPEG_PATH no .env e rode de novo.');

            return self::FAILURE;
        }

        $places = Place::with('group')
            ->when($this->option('place'), fn ($query) => $query->whereIn('id', $this->option('place')))
            ->get();

        if ($places->isEmpty()) {
            $this->warn('Nenhuma quadra encontrada.');

            return self::SUCCESS;
        }

        $perPlace = max(1, (int) $this->option('per-place'));
        $seconds = max(1, min(Orientation::MAX_CLIP_SECONDS, (int) $this->option('seconds')));
        $total = $places->count() * $perPlace;

        if (! $this->option('force') && ! $this->confirm("Gerar {$total} clipe(s) de demonstração em " . $places->count() . ' quadra(s)?', true)) {
            return self::SUCCESS;
        }

        $this->info("Gerando {$total} clipe(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $rows = [];
        $bytes = 0;

        foreach ($places as $place) {
            $config = $resolver->settingFor($place);
            $linked = 0;

            for ($i = 0; $i < $perPlace; $i++) {
                // Espalha no passado: ~3h atrás, ~24h, ~45h... Assim a
                // galeria já nasce com mais de um dia para o filtro de data
                // ter o que filtrar, e nenhum vídeo fica com data futura.
                $recordedAt = now()->subHours(3 + $i * 21);

                $video = $this->createVideo($media, $place, $config['orientation'], $recordedAt, $seconds, $i, $ffmpeg);

                if ($video === null) {
                    $bar->advance();

                    continue;
                }

                $bytes += $video->size_bytes;
                $linked += $video->member_id ? 1 : 0;
                $bar->advance();
            }

            $rows[] = [
                $place->id,
                $place->name,
                $place->group?->name ?? '—',
                Orientation::label($config['orientation']),
                $perPlace,
                $linked > 0 ? "{$linked} com sócio" : 'sem reserva',
            ];
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Id', 'Quadra', 'Esporte', 'Orientação', 'Vídeos', 'Vínculo'], $rows);

        $this->info('Total em disco: ' . number_format($bytes / 1048576, 1, ',', '.') . ' MB');
        $this->line('Nenhum e-mail foi disparado e nenhuma reserva foi criada.');
        $this->line('Para remover tudo depois: php artisan replay:demo-videos --only-clear');

        return self::SUCCESS;
    }

    /**
     * Gera o clipe e grava a linha. Devolve null (com aviso) se o ffmpeg
     * falhar em uma quadra específica — uma falha isolada não deve abortar o
     * pacote inteiro.
     */
    private function createVideo(
        MediaService $media,
        Place $place,
        string $orientation,
        Carbon $recordedAt,
        int $seconds,
        int $index,
        string $ffmpeg,
    ): ?Video {
        $uuid = (string) Str::uuid();
        $path = MediaService::VIDEO_DIR . '/' . $recordedAt->format('Y/m') . "/{$uuid}.mp4";
        $absolute = MediaService::absolutePath($path);

        @mkdir(dirname($absolute), 0775, true);

        // 720p no lado maior: é material de teste, e resolução cheia só
        // gastaria disco e tempo de codificação sem mudar o que se testa.
        $size = $orientation === Orientation::VERTICAL ? '720x1280' : '1280x720';

        // Matiz derivada do id da quadra: cada quadra fica visivelmente
        // diferente na galeria sem depender de fonte instalada — escrever o
        // nome com drawtext exigiria um arquivo .ttf, e o caminho com letra
        // de unidade no Windows quebra o parser de filtros do ffmpeg.
        $hue = ($place->id * 37) % 360;

        $result = Process::timeout(120)->run([
            $ffmpeg, '-y', '-hide_banner', '-loglevel', 'error',
            '-f', 'lavfi', '-i', "testsrc2=size={$size}:rate=25",
            '-t', (string) $seconds,
            '-vf', "hue=h={$hue}",
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '32',
            '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
            $absolute,
        ]);

        if (! $result->successful() || ! is_file($absolute)) {
            $this->newLine();
            $this->warn("ffmpeg falhou na quadra {$place->name}: " . trim($result->errorOutput()));

            return null;
        }

        return Video::create([
            'uuid' => $uuid,
            'place_id' => $place->id,
            'place_group_id' => $place->place_group_id,
            'replay_camera_id' => null,
            'schedule_id' => ($schedule = $this->paidScheduleFor($place->id, $recordedAt))?->id,
            'member_id' => $schedule?->member_id,
            'external_id' => self::DEMO_PREFIX . $place->id . '-' . $index . '-' . $recordedAt->timestamp,
            'recorded_at' => $recordedAt,
            'duration_seconds' => $seconds,
            'orientation' => $orientation,
            'file_path' => $path,
            'size_bytes' => filesize($absolute) ?: 0,
            'expires_at' => $recordedAt->copy()->addDays(Video::RETENTION_DAYS),
        ]);
    }

    /** Mesma regra da recepção de verdade: só reserva paga vincula. */
    private function paidScheduleFor(int $placeId, Carbon $recordedAt): ?Schedule
    {
        return Schedule::withoutGlobalScopes()
            ->where('place_id', $placeId)
            ->where('status_id', 1)
            ->where('start_schedule', '<=', $recordedAt)
            ->where('end_schedule', '>=', $recordedAt)
            ->first();
    }

    private function clearDemoVideos(MediaService $media): void
    {
        $demos = Video::where('external_id', 'like', self::DEMO_PREFIX . '%')->get();

        foreach ($demos as $video) {
            $media->remove($video->file_path);
            $video->delete();
        }

        $this->info($demos->count() . ' vídeo(s) de demonstração removido(s).');
    }

    private function ffmpegWorks(string $binary): bool
    {
        try {
            return Process::timeout(15)->run([$binary, '-version'])->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
