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
 * Segunda fase da importação: grava depois da revisão humana.
 *
 * `$acceptedIds` são ids de `time_entries` **já existentes** que o usuário
 * marcou para serem sobrescritos. Lançamento novo entra de qualquer jeito;
 * duplicata desmarcada é ignorada.
 */
class ConfirmCompTimeImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        private string $importUuid,
        private string $tempPath,
        private array  $acceptedIds,
    ) {}

    public function handle(CompTimeService $service): void
    {
        $import = CompTimeImport::where('uuid', $this->importUuid)->firstOrFail();
        $import->update(['status' => CompTimeImport::STATUS_PROCESSING]);

        try {
            $summary = $service->importWithDecisions($this->tempPath, $this->acceptedIds);
            @unlink($this->tempPath);

            $import->update([
                'status'      => CompTimeImport::STATUS_COMPLETED,
                'result_data' => array_merge($import->result_data ?? [], ['summary' => $summary]),
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

    /** Ver DetectCompTimeDuplicatesJob::failed() — mesmo motivo. */
    public function failed(\Throwable $e): void
    {
        @unlink($this->tempPath);

        CompTimeImport::where('uuid', $this->importUuid)->update([
            'status'        => CompTimeImport::STATUS_FAILED,
            'error_message' => $e->getMessage(),
        ]);
    }
}
