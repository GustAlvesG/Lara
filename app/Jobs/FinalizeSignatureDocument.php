<?php

namespace App\Jobs;

use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignaturePdfSealer;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Monta o PDF final: documento assinado + página de manifesto.
 *
 * Exceção: documento assinado pelo gov.br (`govbr_check_id`). Ali o final é o
 * arquivo que voltou do gov.br, sem tocar — ver govbrFile() —, e o manifesto
 * vira um PDF À PARTE, o relatório de validação (`report_path`): acrescentar
 * uma página ao arquivo assinado desfaria as assinaturas.
 *
 * Roda em fila porque gerar PDF leva segundos e a pessoa está no balcão — o
 * tablet mostra "assinatura concluída" assim que a gravação entra, e o arquivo
 * fica pronto logo depois.
 *
 * **O final é RE-RENDERIZADO**, e não carimbado sobre o original: o dompdf não
 * edita PDF pronto. As duas coisas saem do MESMO conteúdo congelado
 * (`body_snapshot`), então o texto é o mesmo; o que prova qual arquivo a
 * pessoa leu é o `original_sha256`, guardado no congelamento e impresso no
 * manifesto. O ponto de troca por um carimbo cirúrgico (FPDI) é o
 * SignaturePdfSealer.
 *
 * Retry com espera crescente. Uma falha aqui não perde evidência nenhuma: a
 * assinatura, o traço, a foto e a trilha já estão gravados — o que falta é só
 * o arquivo de entrega, e ele pode ser refeito quantas vezes for preciso.
 */
class FinalizeSignatureDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * Espera crescente entre as tentativas: a falha típica é disco cheio ou
     * indisponível, e insistir de imediato não resolve nenhuma das duas.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300, 900];
    }

    public function __construct(public int $documentId)
    {
    }

    public function handle(
        SignatureDocumentRenderer $renderer,
        SignatureStateMachine $states,
        SignaturePdfSealer $sealer,
    ): void {
        $document = SignatureDocument::with(['template', 'signers.evidence'])->find($this->documentId);

        if (!$document) {
            return;
        }

        /*
         | Já finalizado: o job foi despachado duas vezes, ou a fila reentregou
         | depois de um timeout. Sair em silêncio é o comportamento certo —
         | refazer geraria um segundo arquivo e um segundo hash para o mesmo
         | documento.
         */
        if ($document->status === SignatureDocument::STATUS_FINALIZED) {
            return;
        }

        if ($document->status !== SignatureDocument::STATUS_SIGNED) {
            Log::warning('Finalização de assinatura ignorada: documento não está assinado.', [
                'document_id' => $document->id,
                'status' => $document->status,
            ]);

            return;
        }

        /*
         | Anexo obrigatório sem arquivo: o documento fica "Assinado", à espera.
         | Não é falha — o atendente envia o anexo na tela do documento, e o
         | envio que completa a lista despacha este job de novo
         | (SignatureAttachmentService).
         */
        if ($document->missingAttachments() !== []) {
            return;
        }

        $disk = Storage::disk(config('signature.disk'));
        $pasta = config('signature.paths.documents') . '/' . $document->id;
        $relatorio = [];

        if ($document->govbr_check_id !== null) {
            /*
             | Assinado pelo gov.br: o final é o ARQUIVO QUE VOLTOU, byte a byte —
             | ele carrega as assinaturas. Re-renderizar, carimbar, acrescentar
             | manifesto ou lacrar quebraria todas elas. O que seria o manifesto
             | sai como relatório, num PDF separado, com o hash dele.
             */
            $bytes = $this->govbrFile($document);
            $origem = 'gov.br';

            $relatorioBytes = $renderer->govbrReport($document, hash('sha256', $bytes));
            $relatorio = [
                'report_path' => $pasta . '/relatorio-govbr.pdf',
                'report_sha256' => hash('sha256', $relatorioBytes),
            ];

            $disk->put($relatorio['report_path'], $relatorioBytes);
        } else {
            $bytes = $renderer->pdf($document, SignatureDocumentRenderer::MODE_FINAL);

            // Lacre com certificado (PAdES). Desligado nesta entrega; devolve os
            // mesmos bytes. Ver config/signature.php.
            $bytes = $sealer->seal($bytes, $document);
            $origem = 'tablet';
        }

        $path = $pasta . '/final.pdf';

        $disk->put($path, $bytes);

        $states->documentTo(
            $document,
            SignatureDocument::STATUS_FINALIZED,
            SignatureAuditEvent::EVENT_FINALIZED,
            [
                'final_path' => $path,
                'final_sha256' => hash('sha256', $bytes),
                'finalized_at' => now(),
            ] + $relatorio,
            [
                'actor_type' => SignatureAuditEvent::ACTOR_SYSTEM,
                'payload' => [
                    'bytes' => strlen($bytes),
                    'lacrado' => $origem === 'tablet' && $sealer->isEnabled(),
                    'origem' => $origem,
                ] + ($relatorio ? ['relatorio_sha256' => $relatorio['report_sha256']] : []),
            ],
        );

        /*
         | A via só é enviada a quem PEDIU no tablet e tem e-mail cadastrado.
         | Vai em job próprio: e-mail é a parte mais frágil do caminho, e uma
         | falha de SMTP não pode desfazer a finalização de um documento que já
         | está assinado e guardado.
         */
        foreach ($document->signers()->where('wants_copy', true)->whereNotNull('email')->get() as $signer) {
            SendSignatureCopy::dispatch($signer->id);
        }

        // A cópia no servidor de arquivos (FTP), também em job próprio: é um
        // servidor de fora, e a queda dele não desfaz a finalização.
        if (config('signature.archive.enabled')) {
            ArchiveSignatureDocument::dispatch($document->id);
        }
    }

    /**
     * O arquivo da conferência do gov.br que fechou o documento, conferido
     * contra o hash gravado na conferência: o final não pode ser um arquivo
     * diferente do que foi aprovado.
     *
     * @throws RuntimeException  arquivo ausente ou diferente do conferido
     */
    private function govbrFile(SignatureDocument $document): string
    {
        $conferencia = $document->govbrFinalCheck;
        $disk = Storage::disk(config('signature.disk'));

        if (!$conferencia || !$disk->exists($conferencia->file_path)) {
            throw new RuntimeException("Documento {$document->id}: o arquivo do gov.br que fechou o documento não foi encontrado.");
        }

        $bytes = (string) $disk->get($conferencia->file_path);

        if (!hash_equals($conferencia->file_sha256, hash('sha256', $bytes))) {
            throw new RuntimeException("Documento {$document->id}: o arquivo do gov.br não confere com o hash da conferência.");
        }

        return $bytes;
    }

    /**
     * Esgotadas as tentativas, o fato vira evento — e não só linha de log.
     *
     * O documento fica em `signed`: assinado, com todas as evidências, à
     * espera do arquivo. É o estado honesto, e é dele que uma nova tentativa
     * parte.
     */
    public function failed(Throwable $e): void
    {
        $document = SignatureDocument::find($this->documentId);

        if (!$document) {
            return;
        }

        app(SignatureStateMachine::class)->note(
            $document,
            SignatureAuditEvent::EVENT_FINALIZATION_FAILED,
            [
                'actor_type' => SignatureAuditEvent::ACTOR_SYSTEM,
                'payload' => ['erro' => mb_substr($e->getMessage(), 0, 300)],
            ],
        );

        Log::error('Falha ao finalizar documento de assinatura.', [
            'document_id' => $this->documentId,
            'erro' => $e->getMessage(),
        ]);
    }
}
