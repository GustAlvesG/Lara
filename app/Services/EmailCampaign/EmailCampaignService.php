<?php

namespace App\Services\EmailCampaign;

use App\Exceptions\EmailCampaignException;
use App\Models\EmailCampaign\EmailCampaign;
use App\Models\EmailCampaign\EmailCampaignRecipient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * As mudanças de situação da campanha que a tela pede. Nenhuma delas envia
 * nada: só marcam, e o CampaignSender obedece na rodada seguinte (no máximo
 * um minuto depois).
 */
final class EmailCampaignService
{
    public function __construct(private readonly CampaignRenderer $renderer)
    {
    }

    /**
     * Começa agora ou agenda. Confere o que faria o envio sair errado para
     * todo mundo de uma vez: sem lista, sem texto, variável que a lista não tem.
     *
     * @throws EmailCampaignException
     */
    public function start(EmailCampaign $campaign, ?Carbon $scheduledAt = null): void
    {
        if (! $campaign->canStart()) {
            throw new EmailCampaignException('Só a campanha em rascunho pode ser enviada.');
        }

        // No editor visual o HTML sempre tem o layout (cabeçalho e rodapé):
        // o que diz se há conteúdo é o texto escrito.
        $content = $campaign->usesVisualEditor() ? $campaign->body : $campaign->html;

        if (trim(strip_tags((string) $content, '<img>')) === '') {
            throw new EmailCampaignException('Escreva o conteúdo do e-mail antes de enviar.');
        }

        if (! $campaign->recipients()->where('status', EmailCampaignRecipient::STATUS_PENDING)->exists()) {
            throw new EmailCampaignException('A campanha não tem destinatário na fila. Importe a lista antes de enviar.');
        }

        if ($unknown = $this->renderer->unknownVariables($campaign)) {
            throw new EmailCampaignException(
                'O texto usa variáveis que a lista não tem: {{' . implode('}}, {{', $unknown) . '}}. '
                . 'Elas sairiam vazias. Corrija o texto ou importe uma lista com essas colunas.'
            );
        }

        if ($scheduledAt && $scheduledAt->isFuture()) {
            $campaign->forceFill([
                'status' => EmailCampaign::STATUS_SCHEDULED,
                'scheduled_at' => $scheduledAt,
                'last_error' => null,
            ])->save();

            return;
        }

        $campaign->forceFill([
            'status' => EmailCampaign::STATUS_SENDING,
            'scheduled_at' => null,
            'started_at' => now(),
            'last_error' => null,
        ])->save();
    }

    public function pause(EmailCampaign $campaign): void
    {
        $this->guard($campaign->canPause(), 'Só a campanha que está enviando pode ser pausada.');

        $campaign->forceFill(['status' => EmailCampaign::STATUS_PAUSED])->save();
    }

    public function resume(EmailCampaign $campaign): void
    {
        $this->guard($campaign->canResume(), 'Só a campanha pausada pode ser retomada.');

        $campaign->forceFill(['status' => EmailCampaign::STATUS_SENDING, 'last_error' => null])->save();
    }

    /** O que já foi, foi; os que estavam na fila não recebem. */
    public function cancel(EmailCampaign $campaign): void
    {
        $this->guard($campaign->canCancel(), 'Esta campanha não está em andamento.');

        $campaign->forceFill(['status' => EmailCampaign::STATUS_CANCELED, 'finished_at' => now()])->save();
    }

    public function unschedule(EmailCampaign $campaign): void
    {
        $this->guard($campaign->canUnschedule(), 'Esta campanha não está agendada.');

        $campaign->forceFill(['status' => EmailCampaign::STATUS_DRAFT, 'scheduled_at' => null])->save();
    }

    /**
     * Devolve à fila quem falhou e volta a enviar. Útil depois de corrigir o
     * motivo (remetente, servidor) — quem tem caixa inexistente falha de novo.
     *
     * @return int quantos voltaram para a fila
     */
    public function retryFailed(EmailCampaign $campaign): int
    {
        $this->guard(
            in_array($campaign->status, [EmailCampaign::STATUS_DONE, EmailCampaign::STATUS_SENDING, EmailCampaign::STATUS_PAUSED], true),
            'Só dá para reenviar as falhas de campanha concluída, enviando ou pausada.'
        );

        $count = $campaign->recipients()
            ->where('status', EmailCampaignRecipient::STATUS_FAILED)
            ->update(['status' => EmailCampaignRecipient::STATUS_PENDING, 'attempts' => 0, 'error' => null, 'updated_at' => now()]);

        $this->guard($count > 0, 'Nenhum envio falhou nesta campanha.');

        if ($campaign->status === EmailCampaign::STATUS_DONE) {
            $campaign->forceFill(['status' => EmailCampaign::STATUS_SENDING, 'finished_at' => null, 'last_error' => null])->save();
        }

        return $count;
    }

    /** Cópia em rascunho, com a mesma lista (toda de volta à fila). */
    public function duplicate(EmailCampaign $campaign, ?int $userId): EmailCampaign
    {
        return DB::transaction(function () use ($campaign, $userId) {
            $copy = $campaign->replicate(['status', 'scheduled_at', 'started_at', 'finished_at', 'last_error', 'created_by']);
            $copy->forceFill([
                'name' => 'Cópia de ' . $campaign->name,
                'status' => EmailCampaign::STATUS_DRAFT,
                'created_by' => $userId,
            ])->save();

            $now = now();

            DB::insert(
                'insert into email_campaign_recipients (email_campaign_id, email, name, data, status, attempts, created_at, updated_at) '
                . 'select ?, email, name, data, ?, 0, ?, ? from email_campaign_recipients where email_campaign_id = ?',
                [$copy->id, EmailCampaignRecipient::STATUS_PENDING, $now, $now, $campaign->id]
            );

            return $copy;
        });
    }

    public function clearRecipients(EmailCampaign $campaign): void
    {
        $this->guard($campaign->status === EmailCampaign::STATUS_DRAFT, 'A lista só pode ser apagada com a campanha em rascunho.');

        $campaign->recipients()->delete();
        $campaign->forceFill(['columns' => null, 'list_name' => null])->save();
    }

    /** @throws EmailCampaignException */
    private function guard(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new EmailCampaignException($message);
        }
    }
}
