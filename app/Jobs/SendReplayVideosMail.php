<?php

namespace App\Jobs;

use App\Models\Member;
use App\Models\Replay\MemberNotification;
use App\Models\Replay\Video;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Avisa o sócio que os vídeos da reserva dele estão prontos.
 *
 * UM e-mail por reserva, com a contagem — nunca um por clipe: uma hora de
 * quadra rende dezenas de apertos no botão, e avisar a cada um faria o sócio
 * criar filtro para o remetente.
 *
 * O e-mail não leva o arquivo nem link direto: leva ao login do portal de
 * locação. Foi a decisão de quem opera — o vídeo de quem alugou se coleta
 * autenticado, no mesmo lugar onde a reserva foi feita.
 *
 * Roda depois do fim da reserva (agendado pelo VideoIntakeService). Se a fila
 * atrasar e o vídeo já tiver expirado quando o Job rodar, nada é enviado:
 * melhor silêncio do que mandar alguém a uma página vazia.
 */
class SendReplayVideosMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $notificationId)
    {
    }

    public function handle(EmailService $emails): void
    {
        $notification = MemberNotification::find($this->notificationId);

        if (! $notification || $notification->sent_at) {
            return;
        }

        $videos = Video::available()
            ->where('schedule_id', $notification->schedule_id)
            ->orderBy('recorded_at')
            ->get();

        if ($videos->isEmpty()) {
            Log::info('Replay: reserva sem vídeos disponíveis na hora do aviso — nada enviado.', [
                'schedule_id' => $notification->schedule_id,
            ]);

            return;
        }

        $member = Member::find($notification->member_id);

        if (! $member) {
            Log::warning('Replay: sócio da reserva não encontrado.', [
                'schedule_id' => $notification->schedule_id,
                'member_id' => $notification->member_id,
            ]);

            return;
        }

        $first = $videos->first();

        // `email`/`name` em minúscula: é como as colunas existem na tabela
        // `members`. O $fillable do model lista 'Email'/'Name' em maiúscula,
        // mas isso é resquício — ler por ali devolve null.
        $sent = $emails->sendScheduleMail([
            'type' => 'replay.available',
            'email' => $member->email,
            'subject' => 'Seus vídeos do Replay estão disponíveis',
            'member_name' => $member->name ?? 'Sócio',
            'place_name' => $first->place?->name ?? 'sua quadra',
            'videos_count' => $videos->count(),
            'recorded_at' => $first->recorded_at?->format('d/m/Y'),
            'expires_at' => $first->expires_at?->format('d/m/Y'),
            'portal_url' => config('services.replay.portal_url'),
            'schedule_ids' => [$notification->schedule_id],
        ]);

        // Marca como enviado mesmo quando o e-mail falhou: o EmailService já
        // registrou o motivo no log, e insistir renderia uma fila de retentativas
        // atrás de um endereço que continua inválido. A ausência do aviso não
        // esconde o vídeo — ele está no portal do mesmo jeito.
        $notification->forceFill([
            'videos_count' => $videos->count(),
            'sent_at' => now(),
        ])->save();

        if (! $sent) {
            Log::warning('Replay: aviso de vídeos não chegou a ser despachado.', [
                'schedule_id' => $notification->schedule_id,
            ]);
        }
    }
}
