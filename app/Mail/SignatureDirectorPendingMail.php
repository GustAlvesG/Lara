<?php

namespace App\Mail;

use App\Models\SignatureSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso ao diretor de que há documento para ele assinar no tablet, com
 * matrícula e PIN. Ver NotifySignatureDirector.
 *
 * Sem link de propósito: o Lara não é acessível de fora, e a assinatura do
 * diretor é presencial, no tablet do clube.
 */
class SignatureDirectorPendingMail extends Mailable
{
    use Queueable;

    public function __construct(public SignatureSigner $signer)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documento aguardando a sua assinatura — ' . $this->signer->document->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signature-director-pending',
            with: [
                'signer' => $this->signer,
                'document' => $this->signer->document,
                'expiresAt' => $this->signer->document->expires_at,
            ],
        );
    }
}
