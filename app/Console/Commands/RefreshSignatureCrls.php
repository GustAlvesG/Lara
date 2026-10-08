<?php

namespace App\Console\Commands;

use App\Services\Signature\Pki\PkiRepository;
use Illuminate\Console\Command;

/**
 * Renova as Listas de Certificados Revogados (LCR) que a conferência de
 * assinaturas usa: as do gov.br (`signature.pki.crl_urls`) e todas as que já
 * foram consultadas alguma vez.
 *
 * Só baixa a que venceu (`nextUpdate`). Existe para a conferência na aba não
 * esperar o download da lista do gov.br (~3 MB, renovada a cada 2 h). Roda de
 * hora em hora; falha de rede não é erro do comando — a conferência mostra
 * "não conferido" quando não tem lista em vigor.
 *
 * `--forcar` baixa todas de novo. Com `SIGNATURE_PKI_NETWORK=false`, não faz nada.
 */
class RefreshSignatureCrls extends Command
{
    protected $signature = 'signature:crl {--forcar : Baixa de novo mesmo as que ainda valem}';

    protected $description = 'Renova as listas de certificados revogados (gov.br e ICP-Brasil) usadas na conferência de assinaturas';

    public function handle(PkiRepository $repository): int
    {
        if (!config('signature.pki.network', true) || !config('signature.pki.revocation', true)) {
            $this->line('Consulta de revogação ou acesso à rede desligado: nada a fazer.');

            return self::SUCCESS;
        }

        $urls = array_values(array_unique(array_merge(
            (array) config('signature.pki.crl_urls', []),
            $repository->knownCrlUrls(),
        )));

        $falhas = 0;

        foreach ($urls as $url) {
            $lista = $repository->crl($url, (bool) $this->option('forcar'));

            if ($lista === null || !$lista->isCurrent(time())) {
                $falhas++;
                $this->warn("Sem lista em vigor: {$url}");

                continue;
            }

            $this->line(sprintf(
                '%s — %d revogados, vale até %s',
                $url,
                $lista->count(),
                $lista->nextUpdate ? date('d/m/Y H:i', $lista->nextUpdate) : 'sem data',
            ));
        }

        $this->info(count($urls) - $falhas . ' de ' . count($urls) . ' listas em vigor.');

        return self::SUCCESS;
    }
}
