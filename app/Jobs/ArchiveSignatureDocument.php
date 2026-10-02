<?php

namespace App\Jobs;

use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Services\Signature\SignatureArchiver;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envia a cópia do documento assinado para o servidor de arquivos (FTP).
 *
 * Job próprio, depois da finalização, pela mesma razão do envio da via por
 * e-mail: o FTP é um servidor de fora, e a queda dele não pode desfazer nem
 * atrasar a finalização de um documento que já está assinado e guardado.
 *
 * Uma falha aqui não perde nada — o PDF continua no disco do módulo. O
 * documento fica com `archived_at` nulo, e o `signature:archive` agendado o
 * reenvia.
 */
class ArchiveSignatureDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(public int $documentId)
    {
    }

    public function handle(SignatureArchiver $archiver): void
    {
        if (!$archiver->enabled()) {
            return;
        }

        $document = SignatureDocument::with('template')->find($this->documentId);

        // Já arquivado: o job foi despachado duas vezes, ou o comando agendado
        // chegou antes. Sair em silêncio, como a finalização faz.
        if (!$document || $document->archived_at !== null) {
            return;
        }

        $archiver->archive($document);
    }

    public function failed(Throwable $e): void
    {
        $document = SignatureDocument::find($this->documentId);

        if (!$document) {
            return;
        }

        app(SignatureStateMachine::class)->note($document, SignatureAuditEvent::EVENT_ARCHIVE_FAILED, [
            'actor_type' => SignatureAuditEvent::ACTOR_SYSTEM,
            'payload' => ['erro' => mb_substr($e->getMessage(), 0, 300)],
        ]);

        Log::error('Falha ao arquivar documento de assinatura no servidor de arquivos.', [
            'document_id' => $this->documentId,
            'erro' => $e->getMessage(),
        ]);
    }
}
