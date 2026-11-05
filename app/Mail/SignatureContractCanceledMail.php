<?php

namespace App\Mail;

use App\Models\SignatureContract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * O contrato foi cancelado (05/11/2026): aviso a quem redigiu, com o motivo.
 * Se estava em assinatura, o documento de assinatura foi cancelado junto.
 */
class SignatureContractCanceledMail extends Mailable
{
    use Queueable;

    public function __construct(
        public SignatureContract $contract,
        public string $canceledBy,
        public string $reason,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contrato cancelado — '
                . ($this->contract->number ? $this->contract->number . ' — ' : '') . $this->contract->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signature-contract-canceled',
            with: [
                'contract' => $this->contract,
                'canceledBy' => $this->canceledBy,
                'reason' => $this->reason,
                'url' => route('contracts.show', $this->contract),
            ],
        );
    }
}
