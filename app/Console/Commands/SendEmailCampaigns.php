<?php

namespace App\Console\Commands;

use App\Services\EmailCampaign\CampaignSender;
use Illuminate\Console\Command;

/**
 * Motor do Disparo de E-mails: roda a cada minuto pelo scheduler
 * (routes/console.php) e manda o próximo lote das campanhas em andamento —
 * até `email_campaigns.per_minute` e-mails, em no máximo ~50 segundos, para
 * não emendar na rodada seguinte.
 *
 * É o scheduler, e não a fila, porque o worker do sistema é um só e atende o
 * bot do WhatsApp: uma campanha de milhares de e-mails ali seguraria o
 * atendimento. Ver docs/funcionalidades/disparo-emails.md.
 */
class SendEmailCampaigns extends Command
{
    protected $signature = 'email-campaigns:send
        {--limit= : Máximo de e-mails nesta rodada (padrão: email_campaigns.per_minute)}
        {--seconds=50 : Tempo máximo da rodada, em segundos}';

    protected $description = 'Manda o próximo lote das campanhas do Disparo de E-mails (agendadas cuja hora chegou e em andamento).';

    public function handle(CampaignSender $sender): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $stats = $sender->run($limit, max(1, (int) $this->option('seconds')));

        if ($stats['sent'] + $stats['failed'] + $stats['skipped'] + $stats['retry'] + $stats['finished'] > 0) {
            $this->info("Enviados: {$stats['sent']} · falharam: {$stats['failed']} · descadastrados: {$stats['skipped']} · a tentar de novo: {$stats['retry']} · campanhas concluídas: {$stats['finished']}.");
        }

        if ($stats['stopped']) {
            $this->warn('Rodada interrompida: ' . $stats['stopped']);
        }

        return self::SUCCESS;
    }
}
