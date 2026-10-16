<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * Código de liberação do limite de 7 dias para um registro em massa: um
 * código só, listando todos os contratos do lote que passam do limite. O
 * coordenador confere a lista e dita o número uma vez.
 *
 * Síncrono, como WeeklyLimitCodeMail: quem pediu precisa ver se saiu.
 */
class WeeklyLimitBatchCodeMail extends Mailable
{
    /**
     * @param  array<int, array{freelancer: string, date: Carbon}>  $contracts
     */
    public function __construct(
        public User $coordinator,
        public array $contracts,
        public string $code,
        public Carbon $expiresAt,
        public ?User $requestedBy = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código de liberação — registro em massa (' . count($this->contracts) . ' contratos)',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.freelancer.weekly-limit-batch-code');
    }
}
