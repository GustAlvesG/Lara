<?php

namespace App\Console\Commands;

use App\Services\Signature\Pki\Certificates;
use App\Services\Signature\Pki\TimestampClient;
use App\Services\Signature\SignaturePdfSealer;
use Illuminate\Console\Command;
use Throwable;

/**
 * Confere a configuração do lacre (e-CNPJ do clube + carimbo de tempo) sem
 * finalizar documento nenhum: abre o certificado, mostra titular, emissor e
 * validade, e pede um carimbo de teste à ACT.
 *
 * É o primeiro passo depois de instalar o certificado no servidor — o lacre
 * ligado e mal configurado faz a finalização falhar (de propósito).
 */
class CheckSignatureSeal extends Command
{
    protected $signature = 'signature:seal-check';

    protected $description = 'Confere o certificado do lacre de documentos e a Autoridade de Carimbo do Tempo';

    public function handle(SignaturePdfSealer $sealer, TimestampClient $timestamps): int
    {
        $this->line('Lacre ligado (SIGNATURE_PADES_ENABLED): ' . ($sealer->isEnabled() ? 'sim' : 'NÃO'));

        try {
            $certificado = $sealer->certificate();
        } catch (Throwable $e) {
            $this->error('Certificado: ' . $e->getMessage());

            return self::FAILURE;
        }

        $info = openssl_x509_parse($certificado['cert']) ?: [];
        $this->line('Titular: ' . Certificates::commonName($certificado['cert']));
        $this->line('Emitido por: ' . Certificates::commonName($certificado['cert'], 'issuer'));
        $this->line('Válido até: ' . date('d/m/Y H:i', (int) ($info['validTo_time_t'] ?? 0)));
        $this->line('Cadeia no arquivo: ' . count($certificado['chain']) . ' certificado(s)');

        if (count($certificado['chain']) === 0) {
            $this->warn('O .pfx não traz a cadeia da AC: o validador terá de buscá-la. Prefira exportar com a cadeia completa.');
        }

        if ((string) config('signature.pades.tsa_url') === '') {
            $this->warn('Sem ACT (SIGNATURE_TSA_URL): o lacre sai sem carimbo de tempo.');

            return self::SUCCESS;
        }

        try {
            $token = $timestamps->stamp(hash('sha256', 'teste do Lara ' . microtime(true), true));
            $quando = $timestamps->tstInfo($token)['time'];
            $this->info('Carimbo de tempo OK: ' . ($quando ? date('d/m/Y H:i:s', $quando) : 'sem hora legível'));
        } catch (Throwable $e) {
            $this->error('Carimbo de tempo: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
