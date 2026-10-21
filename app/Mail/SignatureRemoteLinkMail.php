<?php

namespace App\Mail;

use App\Models\SignatureRemoteLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * O link de assinatura remota. Abre a tela de quem assina no site externo,
 * sem login — quem tem o e-mail tem o link, e o CPF completo e a foto são
 * conferidos lá.
 *
 * Sai NA HORA, fora da fila (ver RemoteSignatureService::send): o token em
 * claro não pode ficar gravado na tabela de jobs.
 *
 * O corpo não traz CPF nem o conteúdo do documento: a pessoa lê na tela.
 */
class SignatureRemoteLinkMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SignatureRemoteLink $link,
        private string $url,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documento para assinar — ' . $this->link->signer->document->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signature-remote-link',
            with: [
                'signer' => $this->link->signer,
                'document' => $this->link->signer->document,
                'link' => $this->link,
                'url' => $this->url,
            ],
        );
    }
}
