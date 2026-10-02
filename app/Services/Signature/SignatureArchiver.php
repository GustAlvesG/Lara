<?php

namespace App\Services\Signature;

use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Arquiva a cópia do documento assinado no servidor de arquivos (FTP).
 *
 * As pastas são feitas para quem PROCURA um documento sem abrir o sistema —
 * primeiro o tipo, depois quando, depois de quem:
 *
 *     Lara/DocumentosAssinados/
 *       Contrato de Locacao de Espaco para Evento/
 *         2026/
 *           10 - Outubro/
 *             2026-10-03 - Maria de Souza e Joao Pereira - 6W5YTTTJGRCU.pdf
 *
 *  - **Modelo primeiro**: é a pergunta que vem antes ("cadê os contratos de
 *    locação?"), e é por modelo que o prazo de guarda é definido.
 *  - **Ano e mês** da assinatura, com o número na frente do mês para a pasta
 *    ordenar na ordem do calendário.
 *  - **No nome do arquivo**, a data, quem assinou e o código de validação. O
 *    código é o que torna o nome único e o que liga o arquivo à página de
 *    validação e ao registro no sistema. O CPF não entra em nome de arquivo.
 *
 * Nomes sem acento e sem os caracteres que Windows e FTP recusam: servidor
 * FTP antigo troca "ç" por lixo, e uma pasta com nome quebrado ninguém acha.
 *
 * O arquivo de verdade continua no disco privado do módulo. Aqui vai a CÓPIA
 * — e, por ser cópia, ela é conferida depois de enviada: um PDF truncado numa
 * pasta chamada "DocumentosAssinados" é pior que nenhum.
 */
class SignatureArchiver
{
    private const MONTHS = [
        1 => 'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
    ];

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

        $signers = $document->signers()->get();
        $nomes = $signers->take(2)->map(fn($s) => $this->clean($s->name, 40))->filter()->join(' e ');

        if ($signers->count() > 2) {
            $nomes .= ' e mais ' . ($signers->count() - 2);
        }

        return implode('/', [
            $this->root(),
            // Documento enviado pronto não tem modelo: uma pasta por título
            // viraria uma pasta por arquivo.
            $document->template?->single_use
                ? 'Documentos avulsos'
                : ($this->clean($document->template?->name ?? $document->title, 80) ?: 'Sem modelo'),
            $data->format('Y'),
            $data->format('m') . ' - ' . self::MONTHS[(int) $data->format('n')],
            implode(' - ', array_filter([
                $data->format('Y-m-d'),
                $nomes,
                $document->validation_code ?: ('doc' . $document->id),
            ])) . '.pdf',
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

        $document->forceFill(['archive_path' => $destino, 'archived_at' => now()])->save();

        $this->states->note($document, SignatureAuditEvent::EVENT_ARCHIVED, [
            'actor_type' => SignatureAuditEvent::ACTOR_SYSTEM,
            'payload' => ['caminho' => $destino, 'bytes' => strlen($bytes)],
        ]);

        return $document;
    }

    /**
     * Um trecho de nome de pasta ou arquivo: sem acento, sem os caracteres que
     * Windows e FTP recusam, sem ponto ou espaço nas pontas.
     */
    private function clean(?string $texto, int $limite): string
    {
        $texto = Str::ascii((string) $texto);
        $texto = (string) preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/', ' ', $texto);
        $texto = (string) preg_replace('/[^A-Za-z0-9 ._()\-]+/', '', $texto);
        $texto = trim((string) preg_replace('/\s+/', ' ', $texto), ' .');

        return trim(mb_substr($texto, 0, $limite), ' .');
    }
}
