<?php

namespace App\Services\Signature;

use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Support\ArchivePath;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Arquiva a cópia do documento assinado no servidor de arquivos (FTP).
 *
 * As pastas são feitas para quem PROCURA um documento sem abrir o sistema —
 * primeiro o tipo, depois a pessoa (ver App\Support\ArchivePath, que é a
 * mesma organização dos contratos de freelancer):
 *
 *     Lara/DocumentosAssinados/
 *       Contrato de Locacao de Espaco para Evento/      ← tipo: o modelo
 *         Maria de Souza/                               ← pessoa: o primeiro signatário
 *           2026-10-03 - Maria de Souza e Joao Pereira - 6W5YTTTJGRCU.pdf
 *       Documentos avulsos/                             ← os enviados prontos, em PDF
 *         Empresa X/
 *           2026-10-03 - Contrato de patrocinio - Empresa X - 8KQ2M4.pdf
 *
 *  - **Tipo primeiro**: é a pergunta que vem antes ("cadê os contratos de
 *    locação?"). Documento enviado pronto não tem modelo — uma pasta por
 *    título viraria uma pasta por arquivo —, então todos ficam em
 *    "Documentos avulsos", e o título vai no nome do arquivo.
 *  - **Pessoa**: o primeiro signatário, que é de quem o documento trata. Os
 *    demais aparecem no nome do arquivo.
 *  - **No nome do arquivo**, a data da assinatura, quem assinou e o código de
 *    validação. O código é o que torna o nome único e o que liga o arquivo à
 *    página de validação e ao registro no sistema.
 *
 * O arquivo de verdade continua no disco privado do módulo. Aqui vai a CÓPIA
 * — e, por ser cópia, ela é conferida depois de enviada: um PDF truncado numa
 * pasta chamada "DocumentosAssinados" é pior que nenhum.
 */
class SignatureArchiver
{
    public function __construct(private SignatureStateMachine $states)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('signature.archive.enabled', false);
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('signature.archive.disk'));
    }

    /** A pasta-raiz do arquivo, a partir da pasta inicial da conta FTP. */
    public function root(): string
    {
        return trim((string) config('signature.archive.root', 'Lara/DocumentosAssinados'), '/');
    }

    /**
     * Garante que a pasta-raiz existe e devolve o caminho dela. É o teste de
     * conexão: se isto funciona, host, usuário, senha e permissão de escrita
     * estão certos.
     */
    public function ensureRoot(): string
    {
        $disk = $this->disk();

        if (!$disk->directoryExists($this->root()) && !$disk->makeDirectory($this->root())) {
            throw new RuntimeException('Não foi possível criar a pasta ' . $this->root() . ' no servidor de arquivos.');
        }

        return $this->root();
    }

    /**
     * Onde este documento fica no arquivo.
     *
     * Depende só do documento — a mesma chamada, meses depois, dá o mesmo
     * caminho. É o que deixa o reenvio sobrescrever a própria cópia em vez de
     * criar uma segunda.
     */
    public function pathFor(SignatureDocument $document): string
    {
        $data = $document->finalized_at ?? now();
        $nomes = $document->signers()->orderBy('position')->pluck('name');
        $avulso = (bool) $document->template?->single_use;

        return implode('/', [
            $this->root(),
            $avulso
                ? 'Documentos avulsos'
                : (ArchivePath::clean($document->template?->name ?? $document->title, 80) ?: 'Sem modelo'),
            ArchivePath::person($nomes->first()),
            ArchivePath::file([
                $data->format('Y-m-d'),
                // Na pasta dos avulsos o tipo não diz o que o documento é: o título diz.
                $avulso ? ArchivePath::clean($document->title, 60) : null,
                ArchivePath::signers($nomes),
                $document->validation_code ?: ('doc' . $document->id),
            ]),
        ]);
    }

    /**
     * Envia a cópia, confere e registra.
     *
     * @throws RuntimeException quando o documento não tem PDF final ou a cópia não confere
     */
    public function archive(SignatureDocument $document): SignatureDocument
    {
        if ($document->status !== SignatureDocument::STATUS_FINALIZED || !$document->final_path) {
            throw new RuntimeException("Documento {$document->id} ainda não tem PDF assinado para arquivar.");
        }

        $origem = Storage::disk(config('signature.disk'));

        if (!$origem->exists($document->final_path)) {
            throw new RuntimeException("Documento {$document->id}: o PDF assinado não está no disco do módulo.");
        }

        $bytes = $origem->get($document->final_path);

        // O que sai daqui tem de ser o arquivo do hash gravado — nunca uma
        // versão que mudou no disco depois da finalização.
        if (hash('sha256', $bytes) !== $document->final_sha256) {
            throw new RuntimeException("Documento {$document->id}: o PDF assinado não confere com o hash gravado.");
        }

        $destino = $this->pathFor($document);
        $disk = $this->disk();

        if (!$disk->put($destino, $bytes)) {
            throw new RuntimeException("Documento {$document->id}: o servidor de arquivos recusou a gravação.");
        }

        if ($disk->size($destino) !== strlen($bytes)) {
            throw new RuntimeException("Documento {$document->id}: a cópia no servidor de arquivos ficou incompleta.");
        }

        // Assinado pelo gov.br: o relatório de validação vai junto, ao lado.
        $relatorio = $this->archiveReport($document, $origem, $disk, $destino);

        // Os anexos (identidade, comprovante) também, ao lado.
        $anexos = $this->archiveAttachments($document, $origem, $disk, $destino);

        $document->forceFill(['archive_path' => $destino, 'archived_at' => now()])->save();

        $this->states->note($document, SignatureAuditEvent::EVENT_ARCHIVED, [
            'actor_type' => SignatureAuditEvent::ACTOR_SYSTEM,
            'payload' => ['caminho' => $destino, 'bytes' => strlen($bytes)]
                + ($relatorio ? ['relatorio' => $relatorio] : [])
                + ($anexos ? ['anexos' => count($anexos)] : []),
        ]);

        return $document;
    }

    /**
     * O relatório de validação do gov.br, ao lado do PDF assinado, com a mesma
     * conferência de hash e tamanho. Devolve o caminho, ou null quando o
     * documento não tem relatório (assinado no tablet).
     *
     * @throws RuntimeException
     */
    private function archiveReport(SignatureDocument $document, Filesystem $origem, Filesystem $disk, string $destino): ?string
    {
        if (!$document->report_path) {
            return null;
        }

        $bytes = $origem->get($document->report_path);

        if ($bytes === null || hash('sha256', $bytes) !== $document->report_sha256) {
            throw new RuntimeException("Documento {$document->id}: o relatório do gov.br não confere com o hash gravado.");
        }

        $caminho = preg_replace('/\.pdf$/', '', $destino) . ' - relatorio gov.br.pdf';

        if (!$disk->put($caminho, $bytes) || $disk->size($caminho) !== strlen($bytes)) {
            throw new RuntimeException("Documento {$document->id}: o relatório do gov.br não foi gravado inteiro no servidor de arquivos.");
        }

        return $caminho;
    }

    /**
     * Os anexos do documento, ao lado do PDF assinado:
     * "… - anexo 1 - Documento de identidade.jpg". Cada um conferido contra o
     * hash gravado no envio, como o próprio PDF. Devolve os caminhos.
     *
     * @return array<int, string>
     *
     * @throws RuntimeException
     */
    private function archiveAttachments(SignatureDocument $document, Filesystem $origem, Filesystem $disk, string $destino): array
    {
        $base = preg_replace('/\.pdf$/', '', $destino);
        $caminhos = [];

        foreach ($document->attachments()->get() as $n => $anexo) {
            $bytes = $origem->get($anexo->path);

            if ($bytes === null || hash('sha256', $bytes) !== $anexo->sha256) {
                throw new RuntimeException("Documento {$document->id}: o anexo {$anexo->id} não confere com o hash gravado.");
            }

            $caminho = $base . ' - anexo ' . ($n + 1) . ' - '
                . (ArchivePath::clean($anexo->label, 60) ?: 'anexo') . '.' . $anexo->extension();

            if (!$disk->put($caminho, $bytes) || $disk->size($caminho) !== strlen($bytes)) {
                throw new RuntimeException("Documento {$document->id}: o anexo {$anexo->id} não foi gravado inteiro no servidor de arquivos.");
            }

            $caminhos[] = $caminho;
        }

        return $caminhos;
    }
}
