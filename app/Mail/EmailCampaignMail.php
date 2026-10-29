<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

/**
 * Um e-mail de campanha do Disparo de E-mails, já personalizado para um
 * destinatário (CampaignRenderer). Sai fora da fila, mandado pelo
 * `email-campaigns:send`: a fila do sistema é uma só e serve o bot do
 * WhatsApp — milhares de e-mails ali atrasariam o atendimento.
 *
 * As imagens enviadas pelo editor vão embutidas (`cid:`), não por link: O Lara
 * não é alcançável da internet, e o destinatário veria a imagem quebrada.
 *
 * `List-Unsubscribe` aponta para o e-mail de resposta: é o que os provedores
 * (Gmail, Outlook) mostram como "cancelar inscrição", e o pedido chega à
 * caixa de quem cuida das campanhas — que o lança em "Não enviar para".
 */
class EmailCampaignMail extends Mailable
{
    /**
     * @param  array<string, string>  $images  nome usado no `cid:` => caminho do arquivo
     */
    public function __construct(
        public string $subjectLine,
        public string $htmlBody,
        public string $textBody,
        public string $fromAddress,
        public ?string $fromName = null,
        public ?string $replyToAddress = null,
        public array $images = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName ?: null),
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress)] : [],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
            text: 'emails.campaign-text',
            with: ['text' => $this->textBody],
        );
    }

    public function headers(): Headers
    {
        $unsubscribe = $this->replyToAddress ?: $this->fromAddress;

        return new Headers(text: [
            'List-Unsubscribe' => '<mailto:' . $unsubscribe . '?subject=' . rawurlencode('Descadastrar') . '>',
        ]);
    }

    public function build(): static
    {
        if ($this->images === []) {
            return $this;
        }

        // O Symfony troca `cid:nome` no HTML pelo Content-ID da parte de mesmo nome.
        return $this->withSymfonyMessage(function (Email $message) {
            foreach ($this->images as $name => $path) {
                $message->addPart((new DataPart(new File($path), $name))->asInline());
            }
        });
    }
}
