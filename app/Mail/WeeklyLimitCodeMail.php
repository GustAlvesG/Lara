<?php

namespace App\Mail;

use App\Models\Freelancer;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * Código de liberação do limite de 7 dias, endereçado ao coordenador do
 * Comercial que não está no local. Ele confere os dados do contrato e dita o
 * código para quem está registrando.
 *
 * NÃO implementa ShouldQueue, pelo mesmo motivo do e-mail da diretoria: quem
 * pediu o código precisa ver na hora se ele saiu.
 */
class WeeklyLimitCodeMail extends Mailable
{
    public function __construct(
        public User $coordinator,
        public Freelancer $freelancer,
        public Carbon $startDate,
        public int $servicesAfterSave,
        public string $code,
        public Carbon $expiresAt,
        public ?User $requestedBy = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código de liberação — ' . $this->freelancer->name
                . ' em ' . $this->startDate->format('d/m/Y'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.freelancer.weekly-limit-code');
    }
}
