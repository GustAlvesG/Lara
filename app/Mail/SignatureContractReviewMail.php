<?php

namespace App\Mail;

use App\Models\SignatureContract;
use App\Models\SignatureContractVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso ao coordenador da Gerência: há contrato para validar. Leva o link da
 * tela do contrato no Lara (quem recebe é usuário interno).
 */
class SignatureContractReviewMail extends Mailable
{
    use Queueable;

    public function __construct(
        public SignatureContract $contract,
        public SignatureContractVersion $version,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contrato para validar — ' . $this->contract->title
                . ($this->contract->counterpartyName() ? ' — ' . $this->contract->counterpartyName() : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signature-contract-review',
            with: [
                'contract' => $this->contract,
                'version' => $this->version,
                'url' => route('signature-contracts.show', $this->contract),
            ],
        );
    }
}
