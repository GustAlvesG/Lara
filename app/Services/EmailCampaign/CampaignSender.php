<?php

namespace App\Services\EmailCampaign;

use App\Mail\EmailCampaignMail;
use App\Models\EmailCampaign\EmailCampaign;
use App\Models\EmailCampaign\EmailCampaignRecipient;
use App\Models\EmailCampaign\EmailSuppression;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

/**
 * Quem de fato manda os e-mails das campanhas. Chamado pelo
 * `email-campaigns:send` a cada minuto (scheduler), nunca pela tela: o envio
 * não depende de ninguém com a página aberta.
 *
 * Cada rodada manda até `email_campaigns.per_minute` e-mails no total, por
 * ordem de início das campanhas, e para antes do minuto acabar. Entre um lote
 * e outro relê a campanha: pausar ou cancelar na tela vale em segundos.
 *
 * Três tipos de falha, tratados diferente:
 *  - recusa definitiva do servidor (5xx: caixa inexistente, domínio que não
 *    existe) — o destinatário falha na hora;
 *  - recusa temporária (4xx) ou erro do e-mail — conta tentativa; na
 *    `max_attempts`ª, falha;
 *  - servidor SMTP fora do ar (sem resposta) — não é culpa do destinatário:
 *    não conta tentativa, encerra a rodada e a seguinte tenta de novo.
 * Recusas definitivas seguidas (`max_consecutive_failures`) costumam ser do
 * remetente, não da lista: a campanha é pausada com o motivo, para não
 * queimar a lista inteira.
 */
final class CampaignSender
{
    private const BATCH = 50;

    public function __construct(private readonly CampaignRenderer $renderer)
    {
    }

    /**
     * @return array{sent: int, failed: int, skipped: int, retry: int, finished: int, stopped: ?string}
     */
    public function run(?int $budget = null, int $maxSeconds = 50): array
    {
        $budget ??= max(1, (int) config('email_campaigns.per_minute'));
        $deadline = microtime(true) + $maxSeconds;
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'retry' => 0, 'finished' => 0, 'stopped' => null];

        $this->promoteScheduled();

        $campaigns = EmailCampaign::where('status', EmailCampaign::STATUS_SENDING)
            ->orderBy('started_at')->orderBy('id')->get();

        foreach ($campaigns as $campaign) {
            $tried = [];
            $consecutive = 0;
            $sentHere = 0;

            while ($budget > 0 && microtime(true) < $deadline) {
                $campaign->refresh();

                if ($campaign->status !== EmailCampaign::STATUS_SENDING) {
                    break;
                }

                $batch = $campaign->recipients()
                    ->where('status', EmailCampaignRecipient::STATUS_PENDING)
                    ->when($tried, fn ($q) => $q->whereNotIn('id', $tried))
                    ->orderBy('id')
                    ->limit(min($budget, self::BATCH))
                    ->get();

                if ($batch->isEmpty()) {
                    $stats['finished'] += (int) $this->finishIfDone($campaign);
                    break;
                }

                $suppressed = EmailSuppression::whereIn('email', $batch->pluck('email'))->pluck('email')->flip();

                foreach ($batch as $recipient) {
                    if (microtime(true) >= $deadline) {
                        break 2;
                    }

                    $tried[] = $recipient->id;

                    if (isset($suppressed[$recipient->email])) {
                        $recipient->forceFill(['status' => EmailCampaignRecipient::STATUS_SKIPPED, 'error' => null])->save();
                        $stats['skipped']++;

                        continue;
                    }

                    $budget--;
                    $outcome = $this->deliver($campaign, $recipient);
                    $stats[$outcome]++;

                    if ($outcome === 'sent') {
                        $consecutive = 0;
                        $sentHere++;
                    } elseif ($outcome === 'retry' && $recipient->error === null) {
                        // Servidor fora: nada que a próxima linha faça vai dar certo agora.
                        $stats['stopped'] = $campaign->last_error;

                        return $stats;
                    } else {
                        $consecutive++;
                    }

                    if ($consecutive >= (int) config('email_campaigns.max_consecutive_failures')) {
                        $this->autoPause($campaign, (string) $recipient->error);
                        $stats['stopped'] = $campaign->last_error;
                        break 2;
                    }

                    if ($budget <= 0) {
                        break;
                    }
                }
            }

            // Voltou a enviar: o aviso de servidor fora sai. O da pausa automática fica.
            if ($sentHere > 0 && $campaign->last_error !== null && $campaign->status !== EmailCampaign::STATUS_PAUSED) {
                $campaign->forceFill(['last_error' => null])->save();
            }

            if ($budget <= 0 || microtime(true) >= $deadline) {
                break;
            }
        }

        return $stats;
    }

    /**
     * E-mail de teste, na hora, com os dados do primeiro destinatário. A
     * falha sobe para a tela mostrar o motivo.
     *
     * @throws Throwable
     */
    public function sendTest(EmailCampaign $campaign, string $address): void
    {
        $rendered = $this->renderer->render($campaign, $this->renderer->sampleVariables($campaign));
        $rendered['subject'] = '[TESTE] ' . $rendered['subject'];

        $this->mailer()->to($address)->send($this->mailable($campaign, $rendered));
    }

    /** Agendada cuja hora chegou passa a enviar. */
    private function promoteScheduled(): void
    {
        EmailCampaign::where('status', EmailCampaign::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->get()
            ->each(fn (EmailCampaign $c) => $c->forceFill([
                'status' => EmailCampaign::STATUS_SENDING,
                'started_at' => $c->started_at ?? now(),
            ])->save());
    }

    /** @return 'sent'|'failed'|'retry' */
    private function deliver(EmailCampaign $campaign, EmailCampaignRecipient $recipient): string
    {
        try {
            $rendered = $this->renderer->render($campaign, CampaignRenderer::variablesFor($recipient));

            $this->mailer()->to($recipient->email, $recipient->name ?: null)->send($this->mailable($campaign, $rendered));
        } catch (Throwable $e) {
            return $this->failure($campaign, $recipient, $e);
        }

        $recipient->forceFill([
            'status' => EmailCampaignRecipient::STATUS_SENT,
            'attempts' => $recipient->attempts + 1,
            'error' => null,
            'sent_at' => now(),
        ])->save();

        return 'sent';
    }

    /** @return 'failed'|'retry' */
    private function failure(EmailCampaign $campaign, EmailCampaignRecipient $recipient, Throwable $e): string
    {
        $message = Str::limit(trim(preg_replace('/\s+/', ' ', $e->getMessage())), 480);
        $code = $e instanceof UnexpectedResponseException ? (int) $e->getCode() : 0;

        // Sem resposta do servidor: a culpa não é do destinatário.
        if ($e instanceof TransportExceptionInterface && ! $e instanceof UnexpectedResponseException) {
            $campaign->forceFill(['last_error' => 'Servidor de e-mail indisponível; o envio tenta de novo no próximo minuto. ' . $message])->save();
            $recipient->forceFill(['error' => null])->save();

            Log::warning('Disparo de E-mails: servidor SMTP indisponível.', ['campaign_id' => $campaign->id, 'error' => $message]);

            return 'retry';
        }

        $attempts = $recipient->attempts + 1;
        $permanent = $code >= 500 && $code < 600;
        $failed = $permanent || $attempts >= (int) config('email_campaigns.max_attempts');

        $recipient->forceFill([
            'status' => $failed ? EmailCampaignRecipient::STATUS_FAILED : EmailCampaignRecipient::STATUS_PENDING,
            'attempts' => $attempts,
            'error' => $message ?: 'Falha sem mensagem do servidor.',
        ])->save();

        return $failed ? 'failed' : 'retry';
    }

    private function autoPause(EmailCampaign $campaign, string $error): void
    {
        $campaign->forceFill([
            'status' => EmailCampaign::STATUS_PAUSED,
            'last_error' => 'Pausada automaticamente: ' . config('email_campaigns.max_consecutive_failures')
                . ' envios seguidos recusados pelo servidor. Confira o remetente e a mensagem abaixo, depois retome. Último erro: ' . $error,
        ])->save();

        Log::warning('Disparo de E-mails: campanha pausada por recusas seguidas.', ['campaign_id' => $campaign->id, 'error' => $error]);
    }

    private function finishIfDone(EmailCampaign $campaign): bool
    {
        $pending = $campaign->recipients()->where('status', EmailCampaignRecipient::STATUS_PENDING)->exists();

        if ($pending) {
            return false;
        }

        $campaign->forceFill(['status' => EmailCampaign::STATUS_DONE, 'finished_at' => now()])->save();

        return true;
    }

    /** @param  array{subject: string, html: string, text: string, images: array<string, string>}  $rendered */
    private function mailable(EmailCampaign $campaign, array $rendered): EmailCampaignMail
    {
        return new EmailCampaignMail(
            subjectLine: $rendered['subject'],
            htmlBody: $rendered['html'],
            textBody: $rendered['text'],
            fromAddress: $campaign->from_address,
            fromName: $campaign->from_name,
            replyToAddress: $campaign->reply_to,
            images: $rendered['images'],
        );
    }

    private function mailer(): \Illuminate\Contracts\Mail\Mailer
    {
        return Mail::mailer(config('email_campaigns.mailer') ?: null);
    }
}
