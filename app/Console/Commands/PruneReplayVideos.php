<?php

namespace App\Console\Commands;

use App\Models\Replay\Video;
use App\Services\Replay\VideoIntakeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Expurgo dos clipes vencidos — a contrapartida da promessa de 7 dias.
 *
 * Roda uma vez por dia. Não há vídeo isento: quem quer guardar um clipe para
 * campanha baixa pela galeria antes do prazo, e foi assim que se decidiu para
 * que o disco tenha um teto conhecido em vez de crescer para sempre.
 */
class PruneReplayVideos extends Command
{
    protected $signature = 'replay:prune {--dry-run : Só mostra o que seria apagado}';

    protected $description = 'Apaga os vídeos do Replay que passaram dos 7 dias contados da gravação';

    public function handle(VideoIntakeService $intake): int
    {
        $expired = Video::expired()->count();

        if ($expired === 0) {
            $this->info('Nenhum vídeo vencido.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("{$expired} vídeo(s) seriam apagados (--dry-run).");

            return self::SUCCESS;
        }

        $result = $intake->prune();

        // No log também: o expurgo roda sozinho de madrugada, e quando alguém
        // perguntar "cadê o vídeo de sexta" a resposta precisa estar escrita
        // em algum lugar.
        Log::info('Replay: expurgo diário concluído.', $result);

        $this->info("Apagados: {$result['deleted']} vídeo(s).");

        if ($result['missing'] > 0) {
            $this->warn("{$result['missing']} registro(s) já estavam sem arquivo em disco.");
        }

        return self::SUCCESS;
    }
}
