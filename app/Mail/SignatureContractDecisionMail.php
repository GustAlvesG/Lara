<?php

namespace App\Mail;

use App\Models\SignatureContract;
use App\Models\SignatureContractVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Retorno da Gerência a quem redigiu o contrato: devolvido com observações
 * ou aprovado (e o documento de assinatura gerado, em rascunho).
 */
class SignatureContractDecisionMail extends Mailable
{
    use Queueable;

    public function __construct(
        public SignatureContract $contract,
        public SignatureContractVersion $version,
    ) {
    }

    public function envelope(): Envelope
    {
        $aprovado = $this->version->decision === SignatureContractVersion::DECISION_APPROVED;

        return new Envelope(
            subject: ($aprovado ? 'Contrato aprovado — ' : 'Contrato devolvido para ajustes — ')
                . ($this->contract->number ? $this->contract->number . ' — ' : '') . $this->contract->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signature-contract-decision',
            with: [
                'contract' => $this->contract,
                'version' => $this->version,
                'approved' => $this->version->decision === SignatureContractVersion::DECISION_APPROVED,
                'comments' => $this->version->comments()->count(),
                'url' => route('signature-contracts.show', $this->contract),
            ],
        );
    }
}
