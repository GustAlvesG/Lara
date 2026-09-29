<?php

namespace App\Console\Commands;

use App\Services\PoliBot\BotEngine;
use App\Services\PoliBot\DefaultFlows;
use Illuminate\Console\Command;
use Throwable;

/**
 * Conversas abertas com O Lara que a Lara não está conduzindo — queda da
 * Lara, fila parada, sessão perdida — são encerradas. Agendado a cada 10
 * minutos (não age com o bot desligado).
 *
 *   php artisan poli:bot-reconciliar --simular     # só lista
 *   php artisan poli:bot-reconciliar --devolver    # rollback: tudo do O Lara para a Secretaria
 *
 * A lista vem de GET /accounts/{acc}/chats?assigned=…&status=OPEN, ainda não
 * exercitado: cada contato é conferido em current_attendance antes de agir, e
 * uma lista grande demais aborta tudo.
 */
class PoliBotReconciliar extends Command
{
    protected $signature = 'poli:bot-reconciliar
        {--simular : Só mostra o que faria}
        {--devolver= : Distribui TODAS as conversas do O Lara para este time (padrão: Secretaria), em vez de encerrar}';

    protected $description = 'Encerra (ou devolve a um time) as conversas do O Lara sem sessão ativa na Lara';

    public function handle(BotEngine $bot): int
    {
        $devolver = $this->input->hasParameterOption('--devolver')
            ? ((string) $this->option('devolver') ?: DefaultFlows::TEAM_SECRETARIA)
            : null;

        // Agendado sempre; com o bot desligado, a rodada normal não tem o que fazer.
        if ($devolver === null && $bot->mode() === BotEngine::MODE_OFF) {
            return self::SUCCESS;
        }

        if ($bot->botUserUuid() === null) {
            $this->error('POLI_BOT_USER_UUID não configurado.');

            return self::FAILURE;
        }

        try {
            $relatorio = $bot->reconcile((bool) $this->option('simular'), $devolver);
        } catch (Throwable $e) {
            $this->error('Falha ao consultar a Poli: ' . $e->getMessage());

            return self::FAILURE;
        }

        foreach ($relatorio as $linha) {
            $this->line("{$linha['contact_uuid']}  {$linha['acao']}");
        }

        return self::SUCCESS;
    }
}
