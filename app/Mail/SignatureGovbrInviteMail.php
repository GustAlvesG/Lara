<?php

namespace App\Mail;

use App\Models\SignatureGovbrInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Convite para assinar pelo gov.br: o PDF a assinar e o passo a passo.
 *
 * Sem link: o Lara não é acessível de fora. A pessoa entrega o arquivo
 * assinado no atendimento do clube; o retorno NÃO é por e-mail (26/10/2026).
 * O `replyTo` (atendente que enviou) fica só para dúvidas.
 *
 * O corpo não repete dados pessoais (CPF, conteúdo do documento): o PDF anexo
 * já os tem.
 */
class SignatureGovbrInviteMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SignatureGovbrInvite $invite,
        private string $pdf,
        private string $fileName,
        private bool $alreadySigned,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Assinatura pelo gov.br — ' . $this->invite->signer->document->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signature-govbr-invite',
            with: [
                'signer' => $this->invite->signer,
                'document' => $this->invite->signer->document,
                'alreadySigned' => $this->alreadySigned,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = $this->pdf;

        return [
            Attachment::fromData(fn() => $pdf, $this->fileName)->withMime('application/pdf'),
        ];
    }
}
