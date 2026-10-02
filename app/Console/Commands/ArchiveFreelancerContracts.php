<?php

namespace App\Console\Commands;

use App\Models\FreelancerService;
use App\Services\FreelancerContractArchiver;
use Illuminate\Console\Command;
use Throwable;

/**
 * Arquiva no servidor de arquivos (FTP) os contratos de freelancer assinados
 * pelas duas partes que ainda não têm cópia lá.
 *
 * É o ÚNICO caminho do arquivamento dos contratos — não há envio no ato da
 * assinatura (ver FreelancerContractArchiver). Roda de hora em hora: a cópia
 * não tem pressa, e o contrato continua inteiro no sistema enquanto isso.
 *
 * Na primeira vez em que o arquivamento é ligado, a fila é o histórico inteiro
 * de contratos assinados; ela é consumida aos poucos, `--limite` por execução,
 * dos mais antigos para os mais novos.
 */
class ArchiveFreelancerContracts extends Command
{
    protected $signature = 'freelancers:archive
        {--limite=100 : Máximo de contratos por execução}
        {--forcar : Envia mesmo com o arquivamento desligado (teste manual)}';

    protected $description = 'Envia ao servidor de arquivos (FTP) os contratos de freelancer assinados ainda não arquivados';

    public function handle(FreelancerContractArchiver $archiver): int
    {
        if (!$archiver->enabled() && !$this->option('forcar')) {
            return self::SUCCESS;
        }

        $pendentes = FreelancerService::with(['freelancer', 'functionFreelancer', 'director', 'baseService'])
            ->awaitingArchive()
            ->orderBy('id')
            ->limit((int) $this->option('limite'))
            ->get();

        $enviados = 0;
        $falhas = 0;

        foreach ($pendentes as $contrato) {
            try {
                $archiver->archive($contrato);
                $enviados++;
            } catch (Throwable $e) {
                $falhas++;
                $this->error("Contrato {$contrato->id}: {$e->getMessage()}");

                // Servidor fora do ar falha igual para todos: insistir nos
                // outros só encheria o log.
                if ($enviados === 0 && $falhas >= 3) {
                    break;
                }
            }
        }

        if ($enviados > 0 || $falhas > 0) {
            $this->info("Freelancers: {$enviados} contrato(s) arquivado(s), {$falhas} falha(s).");
        }

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }
}
