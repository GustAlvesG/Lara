<?php

namespace App\Jobs;

use App\Models\CompTimeImport;
use App\Services\CompTimeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $import->update(['status' => 'processing']);

        try {
            $service->importWithDecisions($this->tempPath, $this->acceptedIds);
            @unlink($this->tempPath);
            $import->update(['status' => 'completed']);
        } catch (\Exception $e) {
            @unlink($this->tempPath);
            $import->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }
}
