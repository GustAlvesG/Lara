<?php

namespace App\Console\Commands;

use App\Models\SignatureDocument;
use App\Services\Signature\SignatureArchiver;
use Illuminate\Console\Command;
use Throwable;

/**
 * Arquiva no servidor de arquivos (FTP) os documentos assinados que ainda não
 * têm cópia lá.
 *
 * É a rede do job `ArchiveSignatureDocument`: pega o que ele não conseguiu
 * mandar (FTP fora do ar) e o que foi finalizado antes de o arquivamento
 * existir. Roda de hora em hora — a cópia não tem pressa, e o PDF está seguro
 * no disco do módulo enquanto isso.
 *
 * `--testar` só confere a conexão e cria a pasta-raiz, sem enviar documento.
 */
class ArchiveSignatureDocuments extends Command
{
    protected $signature = 'signature:archive
        {--testar : Só testa a conexão e cria a pasta-raiz}
        {--limite=200 : Máximo de documentos por execução}';

    protected $description = 'Envia ao servidor de arquivos (FTP) os documentos assinados ainda não arquivados';

    public function handle(SignatureArchiver $archiver): int
    {
        if ($this->option('testar')) {
            return $this->testar($archiver);
        }

        if (!$archiver->enabled()) {
            return self::SUCCESS;
        }

        $pendentes = SignatureDocument::with('template')
            ->where('status', SignatureDocument::STATUS_FINALIZED)
            ->whereNull('archived_at')
            ->orderBy('id')
            ->limit((int) $this->option('limite'))
            ->get();

        $enviados = 0;
        $falhas = 0;

        foreach ($pendentes as $documento) {
            try {
                $archiver->archive($documento);
                $enviados++;
            } catch (Throwable $e) {
                $falhas++;
                $this->error("Documento {$documento->id}: {$e->getMessage()}");

                // Servidor fora do ar falha igual para todos: insistir nos
                // outros duzentos só encheria o log.
                if ($enviados === 0 && $falhas >= 3) {
                    break;
                }
            }
        }

        if ($enviados > 0 || $falhas > 0) {
            $this->info("Assinatura: {$enviados} documento(s) arquivado(s), {$falhas} falha(s).");
        }

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function testar(SignatureArchiver $archiver): int
    {
        try {
            $raiz = $archiver->ensureRoot();
        } catch (Throwable $e) {
            $this->error('Não foi possível usar o servidor de arquivos: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Conexão ok. Pasta do arquivo: {$raiz}");

        if (!$archiver->enabled()) {
            $this->warn('O arquivamento está DESLIGADO (SIGNATURE_ARCHIVE_ENABLED=false): nada é enviado.');
        }

        return self::SUCCESS;
    }
}
