<?php

namespace App\Jobs;

use App\Mail\SignatureCopyMail;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Manda ao signatário a via assinada, por e-mail.
 *
 * Só sai quando três coisas são verdade: a pessoa PEDIU no tablet, há e-mail
 * cadastrado, e o documento já está finalizado (é o PDF final que se envia,
 * com o manifesto).
 *
 * Em fila e com retry: e-mail é a parte mais frágil do caminho, e uma falha de
 * SMTP não pode derrubar a finalização de um documento que já está assinado e
 * guardado. Diferente do e-mail do código de liberação dos freelancers, que é
 * síncrono de propósito porque alguém espera o número na tela — aqui ninguém
 * espera: a pessoa já foi embora com o atendimento concluído.
 *
 * O WhatsApp não entra nesta entrega. O gateway da Poli responde 200 para
 * envios que não entrega, e um "enviado" gravado com base nisso seria uma
 * informação falsa na trilha de auditoria. A flag existe em config/signature.
 */
class SendSignatureCopy implements ShouldQueue
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

        if (!$signer || !$signer->document) {
            return;
        }

        if (!config('signature.delivery.email', true)) {
            return;
        }

        // Reentrega da fila: não manda o mesmo arquivo duas vezes.
        if ($signer->copy_sent_at !== null) {
            return;
        }

        if (!$signer->wants_copy || !$signer->email) {
            return;
        }

        $document = $signer->document;

        if ($document->status !== SignatureDocument::STATUS_FINALIZED || !$document->final_path) {
            Log::warning('Via de assinatura não enviada: documento ainda não finalizado.', [
                'signer_id' => $signer->id,
                'document_id' => $document->id,
            ]);

            return;
        }

        Mail::to($signer->email)->send(new SignatureCopyMail($signer));

        $signer->forceFill(['copy_sent_at' => now()])->save();

        $states->note($document, SignatureAuditEvent::EVENT_COPY_SENT, [
            'signer' => $signer->id,
            'actor_type' => SignatureAuditEvent::ACTOR_SYSTEM,
            'payload' => ['canal' => 'e-mail'],
        ]);
    }

    /**
     * A via não chegou, mas o documento continua assinado e guardado. O
     * atendente reenvia pelo painel — e o motivo da falha fica no log.
     */
    public function failed(Throwable $e): void
    {
        Log::error('Falha ao enviar a via assinada.', [
            'signer_id' => $this->signerId,
            'erro' => $e->getMessage(),
        ]);
    }
}
