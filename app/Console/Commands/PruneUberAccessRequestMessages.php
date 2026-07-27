<?php

namespace App\Console\Commands;

use App\Models\UberAccessRequestMessage;
use Illuminate\Console\Command;

class PruneUberAccessRequestMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-uber-access-request-messages {--days=1 : Dias de retenção das mensagens gerais}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove as mensagens gerais recebidas há mais de N dias (padrão 1). Mensagens vinculadas a um pedido de Uber (uber_access_request_id preenchido) são preservadas.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $total = 0;

        do {
            $deleted = UberAccessRequestMessage::query()
                ->whereNull('uber_access_request_id')
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();

            $total += $deleted;
        } while ($deleted > 0);

        if ($total > 0) {
            $this->info("{$total} mensagem(ns) geral(is) removida(s).");
        }

        return self::SUCCESS;
    }
}
