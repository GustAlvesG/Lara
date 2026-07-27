<?php

namespace App\Mail;

use App\Models\FreelancerServiceBatch;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail que leva um lote à diretoria.
 *
 * Vai com os DOIS PINs do lote (um aprova, outro recusa) e o PDF dos contratos
 * em anexo, para o diretor conferir sem precisar da plataforma. Ele responde
 * ditando o PIN escolhido para a gerência.
 *
 * NÃO implementa ShouldQueue de propósito: o envio acontece dentro da ação de
 * aprovação para que a gerência veja na hora se o e-mail saiu ou não. Com fila,
 * uma falha de SMTP ficaria invisível e o lote travaria em silêncio.
 */
class DirectorBatchApprovalMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public FreelancerServiceBatch $batch,
        public string $approvePin,
        public string $rejectPin,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Aprovação de contratos de freelancer — Lote #{$this->batch->id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.freelancer.director-approval',
            with: [
                'batch' => $this->batch,
                'services' => $this->batch->services,
                'total' => $this->batch->services->sum('price'),
                'approvePin' => $this->approvePin,
                'rejectPin' => $this->rejectPin,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('freelancer.batches.pdf', [
            'batch' => $this->batch,
            'services' => $this->batch->services,
            'total' => $this->batch->services->sum('price'),
        ])->setPaper('a4');

        return [
            Attachment::fromData(fn() => $pdf->output(), "lote-{$this->batch->id}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
