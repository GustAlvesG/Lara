<?php

namespace App\Console\Commands;

use App\Services\PoliBot\BotEngine;
use Illuminate\Console\Command;

/**
 * Encerra as conversas do O Lara que chegaram ao fim:
 *
 *   - fluxo concluído (estado `ending`) sem mensagem no prazo;
 *   - fluxo parado no meio há mais que o `timeout_minutes` do fluxo.
 *
 * E anda a fila de espera do transbordo devolvido: nova tentativa, em
 * silêncio, para cada conversa na hora dela; vencido o horário de
 * atendimento, avisa e encerra (BotEngine::retryQueue).
 *
 * A Poli não encerra por inatividade os atendimentos do O Lara, e o prazo do
 * fluxo só era conferido quando chegava mensagem. Agendado a cada minuto.
 */
class PoliBotExpirar extends Command
{
    protected $signature = 'poli:bot-expirar';

    protected $description = 'Encerra as conversas do O Lara concluídas ou abandonadas e anda a fila de espera';

    public function handle(BotEngine $bot): int
    {
        $contagem = $bot->expireSessions();

        if ($contagem['encerradas'] + $contagem['abandonadas'] > 0) {
            $this->info("Encerradas: {$contagem['encerradas']} concluídas, {$contagem['abandonadas']} abandonadas.");
        }

        $fila = $bot->retryQueue();

        if ($fila['tentativas'] + $fila['expiradas'] > 0) {
            $this->info("Fila de espera: {$fila['tentativas']} tentativas, {$fila['expiradas']} vencidas no fim do horário.");
        }

        return self::SUCCESS;
    }
}
