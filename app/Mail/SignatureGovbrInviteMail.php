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
 * Sem link: o Lara não é acessível de fora. A pessoa devolve o arquivo
 * assinado respondendo ao e-mail (o `replyTo` é o atendente que enviou) ou
 * entregando-o no clube.
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
        private bool $replyGoesToAttendant,
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
                'replyGoesToAttendant' => $this->replyGoesToAttendant,
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
