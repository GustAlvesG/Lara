<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('avisos:process-notifications')->everyMinute();
Schedule::command('app:expire-pending-schedules')->everyMinute();
Schedule::command('app:expire-uber-access-requests')->everyMinute();
Schedule::command('app:prune-uber-access-request-messages')->dailyAt('03:00');

// Assinatura eletrônica: QR não lido, sessão de tablet parada e documento
// congelado que ninguém assinou. A cada minuto porque o prazo do QR é de
// minutos — de hora em hora, a tela do atendente ficaria com contagem
// regressiva de um código que já não vale.
Schedule::command('signature:expire')->everyMinute()->withoutOverlapping();

// Rede do arquivamento no FTP: reenvia o que o job não conseguiu mandar. Não
// faz nada com o arquivamento desligado.
Schedule::command('signature:archive')->hourly()->withoutOverlapping();

// Contratos de freelancer assinados pelas duas partes: gera o PDF e manda a
// cópia ao mesmo servidor de arquivos. É o único caminho desse arquivamento, e
// também não faz nada desligado.
Schedule::command('freelancers:archive')->hourly()->withoutOverlapping();

// Bot do WhatsApp: a Poli não encerra por inatividade as conversas do O Lara.
// Fecha as concluídas (encerramento diferido) e as abandonadas no meio do
// fluxo; a reconciliação pega as que a Lara perdeu de vista.
Schedule::command('poli:bot-expirar')->everyMinute()->withoutOverlapping();
Schedule::command('poli:bot-reconciliar')->everyTenMinutes()->withoutOverlapping();

// Confere no Sicoob o desfecho dos Pix em processamento e dá a baixa dos que
// finalizaram. Só CONSULTA — nunca envia —, por isso pode rodar a cada minuto.
// É o que fecha o ciclo do Job, que de propósito não tem retry.
Schedule::command('sicoob:pix-reconciliar')->everyMinute()->withoutOverlapping();
