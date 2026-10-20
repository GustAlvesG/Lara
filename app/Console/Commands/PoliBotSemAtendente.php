<?php

namespace App\Console\Commands;

use App\Services\PoliBot\BotEngine;
use Illuminate\Console\Command;

/**
 * Conversas "Sem atendente" no painel da Poli: a aberta que fica sem ninguém
 * em duas verificações seguidas vai para a Secretaria
 * (BotEngine::checkUnassigned). Agendado a cada 3 minutos, só age no horário
 * de atendimento.
 */
class PoliBotSemAtendente extends Command
{
    protected $signature = 'poli:bot-sem-atendente';

    protected $description = 'Direciona para a Secretaria as conversas abertas que ficam sem atendente';

    public function handle(BotEngine $bot): int
    {
        $contagem = $bot->checkUnassigned();

        if ($contagem['sem_atendente'] > 0) {
            $this->info("Abertas: {$contagem['abertas']}; sem atendente: {$contagem['sem_atendente']}; direcionadas: {$contagem['direcionadas']}.");
        }

        return self::SUCCESS;
    }
}
