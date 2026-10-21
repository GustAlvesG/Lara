<?php

namespace App\Jobs;

use App\Mail\SignatureDirectorPendingMail;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureStateMachine;
use App\Support\EmailMask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa o diretor, por e-mail, de que há documento para ele assinar no tablet
 * (Assinatura Diretoria). Despachado no congelamento, um por signatário-diretor.
 *
 * Em fila e com retry, como a via: ninguém espera por este e-mail, e uma falha
 * de SMTP não pode atrapalhar o congelamento. Não reenvia: `director_notified_at`
 * preenchido encerra. Documento que já saiu de "aguardando" (assinado,
 * cancelado) ou foi para o gov.br não gera aviso — o tablet não o mostraria.
 */
class NotifySignatureDirector implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(public int $signerId)
    {
    }

    public function handle(SignatureStateMachine $states): void
    {
        $signer = SignatureSigner::with('document')->find($this->signerId);

        if (!$signer || !$signer->isDirector() || $signer->director_notified_at !== null) {
            return;
        }

        $document = $signer->document;

        if ($signer->status !== SignatureSigner::STATUS_PENDING
            || $document?->status !== SignatureDocument::STATUS_AWAITING_SIGNATURE
            || $document->isGovbr()) {
            return;
        }

        if (blank($signer->email)) {
            Log::warning('Assinatura Diretoria: diretor sem e-mail, aviso não enviado.', [
                'signer_id' => $signer->id,
                'document_id' => $document->id,
            ]);

            return;
        }

        Mail::to($signer->email)->send(new SignatureDirectorPendingMail($signer));

        $signer->forceFill(['director_notified_at' => now()])->save();

        $states->note($document, SignatureAuditEvent::EVENT_DIRECTOR_NOTIFIED, [
            'signer' => $signer->id,
            'actor_type' => SignatureAuditEvent::ACTOR_SYSTEM,
            'payload' => ['email' => EmailMask::of($signer->email)],
        ]);
    }
}
