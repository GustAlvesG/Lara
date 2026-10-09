<?php

namespace App\Mail;

use App\Models\SignatureSigner;
use App\Services\Signature\SignatureValidationLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * A via do documento assinado, enviada ao próprio signatário.
 *
 * Vai o PDF FINAL — o que tem o traço e a página de manifesto. O manifesto
 * carrega o código e o QR de validação, então a pessoa consegue conferir a
 * autenticidade do arquivo mesmo meses depois, sem precisar do clube.
 *
 * Assinado pelo gov.br, o final é o arquivo do gov.br, e o relatório de
 * validação vai como um segundo anexo — ver FinalizeSignatureDocument.
 *
 * O corpo do e-mail NÃO repete dados pessoais: o documento já os tem, e um
 * e-mail é reencaminhado, impresso e esquecido em caixa de entrada alheia com
 * uma facilidade que o papel não tem.
 */
class SignatureCopyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public SignatureSigner $signer)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sua via assinada — ' . $this->signer->document->title,
        );
    }

    public function content(): Content
    {
        $links = app(SignatureValidationLink::class);

        return new Content(
            view: 'emails.signature-copy',
            with: [
                'signer' => $this->signer,
                'document' => $this->signer->document,
                // Quem recebe está fora da rede do clube: com o PDF lacrado (ou
                // assinado pelo gov.br), o botão leva ao validador oficial.
                'validationUrl' => $links->forFinal($this->signer->document),
                'validationIti' => $links->finalUsesIti($this->signer->document),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $document = $this->signer->document;

        $disk = Storage::disk(config('signature.disk'));
        $bytes = $disk->get($document->final_path);

        $anexos = [
            Attachment::fromData(fn() => $bytes, 'documento-assinado-' . $document->validation_code . '.pdf')
                ->withMime('application/pdf'),
        ];

        if ($document->report_path && $disk->exists($document->report_path)) {
            $relatorio = $disk->get($document->report_path);

            $anexos[] = Attachment::fromData(fn() => $relatorio, 'relatorio-govbr-' . $document->validation_code . '.pdf')
                ->withMime('application/pdf');
        }

        return $anexos;
    }
}
