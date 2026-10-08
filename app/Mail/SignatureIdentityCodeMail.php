<?php

namespace App\Mail;

use App\Models\SignatureSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * O código de 6 números da conferência de identidade, para a pessoa digitar
 * no tablet. Ver SignatureCaptureService::sendIdentityCode().
 *
 * Sai na hora e fora da fila: a pessoa está no balcão esperando, e o código em
 * claro não pode ficar gravado na tabela de jobs.
 */
class SignatureIdentityCodeMail extends Mailable
{
    use Queueable;

    public function __construct(
        public SignatureSigner $signer,
        private string $code,
        private int $minutes,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Seu código para assinar — ' . $this->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signature-identity-code',
            with: [
                'signer' => $this->signer,
                'document' => $this->signer->document,
                'code' => $this->code,
                'minutes' => $this->minutes,
            ],
        );
    }
}
