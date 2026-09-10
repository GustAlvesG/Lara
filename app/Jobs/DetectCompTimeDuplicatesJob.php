<?php

namespace App\Jobs;

use App\Models\CompTimeImport;
use App\Services\CompTimeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Primeira fase da importação: confere o arquivo contra o banco.
 *
 * Sem colisão nenhuma, grava na hora e encerra. Com colisão, para em
 * `awaiting_review` e devolve a decisão para a tela — ver
 * ConfirmCompTimeImportJob para a segunda metade.
 *
 * O arquivo temporário só é apagado quando não há mais o que fazer com ele.
 * Na parada para revisão ele **fica**: é dele que a confirmação lê as linhas.
 */
class DetectCompTimeDuplicatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        private string $importUuid,
        private string $tempPath,
    ) {}

    public function handle(CompTimeService $service): void
    {
        $import = CompTimeImport::where('uuid', $this->importUuid)->firstOrFail();
        $import->update(['status' => CompTimeImport::STATUS_PROCESSING]);

        try {
            $result = $service->detectDuplicates($this->tempPath);

            if (empty($result['duplicate_entries'])) {
                // Mesmo `$service` da linha acima: o parse do arquivo está
                // memorizado na instância, então gravar não relê o arquivo.
                $summary = $service->importFile($this->tempPath);
                @unlink($this->tempPath);

                $import->update([
                    'status'      => CompTimeImport::STATUS_COMPLETED,
                    'phase'       => CompTimeImport::PHASE_IMPORTING,
                    'result_data' => [
                        'new_entries_count' => count($result['new_entries']),
                        'summary'           => $summary,
                    ],
                ]);

                return;
            }

            $import->update([
                'status'      => CompTimeImport::STATUS_AWAITING_REVIEW,
                'phase'       => CompTimeImport::PHASE_DETECTING,
                'result_data' => [
                    'duplicate_entries' => $this->serializeDuplicates($result['duplicate_entries']),
                    'new_entries_count' => count($result['new_entries']),
                ],
            ]);
        } catch (\Throwable $e) {
            @unlink($this->tempPath);
            $import->update([
                'status'        => CompTimeImport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /** Carbon não sobrevive ao `json` da coluna `result_data` — vira string aqui. */
    private function serializeDuplicates(array $duplicates): array
    {
        return array_map(fn (array $duplicate) => array_merge($duplicate, [
            'entry_date' => $duplicate['entry_date']->format('Y-m-d'),
            'due_date'   => isset($duplicate['due_date']) ? $duplicate['due_date']->format('Y-m-d') : null,
        ]), $duplicates);
    }

    /**
     * A fila é `database` e o job pode morrer por timeout ou por erro fatal
     * fora do try. Sem isto o registro ficaria em `processing` para sempre e a
     * tela giraria sem fim.
     */
    public function failed(\Throwable $e): void
    {
        @unlink($this->tempPath);

        CompTimeImport::where('uuid', $this->importUuid)->update([
            'status'        => CompTimeImport::STATUS_FAILED,
            'error_message' => $e->getMessage(),
        ]);
    }
}
