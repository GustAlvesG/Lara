<?php

namespace App\Mail;

use App\Models\SignatureContract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * O presidente ou a contratada recusou a assinatura (05/11/2026): o contrato
 * voltou para quem redigiu, com o motivo como observação. O documento de
 * assinatura recusado não volta; um novo nasce na próxima aprovação.
 */
class SignatureContractRefusedMail extends Mailable
{
    use Queueable;

    public function __construct(
        public SignatureContract $contract,
        public string $refusedBy,
        public ?string $reason,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contrato recusado na assinatura — '
                . ($this->contract->number ? $this->contract->number . ' — ' : '') . $this->contract->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signature-contract-refused',
            with: [
                'contract' => $this->contract,
                'refusedBy' => $this->refusedBy,
                'reason' => $this->reason,
                'url' => route('contracts.edit', $this->contract),
            ],
        );
    }
}
