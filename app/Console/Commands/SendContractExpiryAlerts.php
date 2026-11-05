<?php

namespace App\Console\Commands;

use App\Services\Signature\Contracts\ContractService;
use Illuminate\Console\Command;

/**
 * Avisos de término da vigência dos contratos (05/11/2026): a 60, 30 e 15
 * dias do fim, para quem redigiu e o setor do contrato, no sino e no
 * navegador. Cada marco vai uma vez (`signature_contracts.expiry_alerts`),
 * então rodar de novo no mesmo dia não repete. Agendado uma vez por dia em
 * routes/console.php.
 */
class SendContractExpiryAlerts extends Command
{
    protected $signature = 'contratos:avisar-vencimento';

    protected $description = 'Avisa quem redigiu e o setor quando a vigência de um contrato está perto do fim (60, 30 e 15 dias)';

    public function handle(ContractService $contracts): int
    {
        $avisados = $contracts->sendExpiryAlerts();

        $this->info($avisados . ' contrato(s) avisado(s).');

        return self::SUCCESS;
    }
}
