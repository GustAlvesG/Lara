<?php

namespace App\Console\Commands;

use App\Models\LaraMessage;
use Illuminate\Console\Command;

class PruneLaraMessages extends Command
{
    protected $signature = 'app:prune-lara-messages {--days=90 : Dias de retenção do histórico do chat}';

    protected $description = 'Remove as mensagens do chat da Lara com mais de N dias (padrão 90). O histórico é texto livre digitado por funcionários e não precisa ficar guardado para sempre.';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $total = 0;

        do {
            $deleted = LaraMessage::query()
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();

            $total += $deleted;
        } while ($deleted > 0);

        if ($total > 0) {
            $this->info("{$total} mensagem(ns) do chat da Lara removida(s).");
        }

        return self::SUCCESS;
    }
}
