<?php

namespace App\Console\Commands;

use App\Services\Signature\SignatureDeadlineService;
use Illuminate\Console\Command;

/**
 * Reabre os documentos de assinatura que expiraram nos últimos dias.
 *
 * Criado em 13/11/2026, quando o prazo do balcão passou de 24 horas para 7
 * dias: os documentos (e contratos) que venceram na regra antiga voltam a
 * aguardar assinatura, com o prazo novo. Sem `--force` só lista — é a
 * simulação que se roda antes.
 *
 * Os signatários que expiraram voltam a pendentes; quem já tinha assinado
 * continua assinado. QR Codes e links remotos antigos continuam vencidos: o
 * atendente gera outro QR ou envia um novo link.
 */
class ReactivateExpiredSignatureDocuments extends Command
{
    protected $signature = 'signature:reactivate-expired
        {--days=4 : Janela, em dias, contada para trás a partir de agora}
        {--contracts-only : Só os documentos do módulo Contratos}
        {--force : Reabre de verdade (sem isso, só lista)}';

    protected $description = 'Reabre para assinatura os documentos que expiraram nos últimos dias (simulação sem --force)';

    public function handle(SignatureDeadlineService $deadlines): int
    {
        $dias = max(1, (int) $this->option('days'));
        $documentos = $deadlines->recentlyExpired($dias, (bool) $this->option('contracts-only'));

        if ($documentos->isEmpty()) {
            $this->info("Nenhum documento expirou nos últimos {$dias} dia(s).");

            return self::SUCCESS;
        }

        $this->table(
            ['Id', 'Título', 'Contrato', 'Expirou em', 'Assinaram', 'Voltam a pendente'],
            $documentos->map(fn($d) => [
                $d->id,
                \Illuminate\Support\Str::limit($d->title, 60),
                $d->contract()->exists() ? 'sim' : 'não',
                $d->expires_at?->format('d/m/Y H:i'),
                $d->signers->where('status', \App\Models\SignatureSigner::STATUS_SIGNED)->count() . '/' . $d->signers->count(),
                $d->signers->where('status', \App\Models\SignatureSigner::STATUS_EXPIRED)->count(),
            ])->all(),
        );

        if (!$this->option('force')) {
            $this->warn('Simulação: nada foi alterado. Rode de novo com --force para reabrir estes ' . $documentos->count() . ' documento(s).');

            return self::SUCCESS;
        }

        $reabertos = 0;

        foreach ($documentos as $documento) {
            try {
                $novo = $deadlines->reactivate($documento, "Reativação em lote: expirou nos últimos {$dias} dia(s) (prazo mudou para 7 dias em 13/11/2026).");
                $reabertos++;

                $this->line("#{$novo->id} reaberto — prazo: " . ($novo->expires_at?->format('d/m/Y H:i') ?? 'sem prazo (só falta a diretoria)'));
            } catch (\Throwable $e) {
                $this->error("#{$documento->id} não reaberto: " . $e->getMessage());
            }
        }

        $this->info("{$reabertos} documento(s) reaberto(s).");

        return self::SUCCESS;
    }
}
