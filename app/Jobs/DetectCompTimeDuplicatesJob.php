<?php

namespace App\Jobs;

use App\Models\CompTimeImport;
use App\Services\CompTimeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $import->update(['status' => 'processing']);

        try {
            $result = $service->detectDuplicatesFast($this->tempPath);

            if (empty($result['duplicate_entries'])) {
                $service->importFile($this->tempPath);
                @unlink($this->tempPath);

                $import->update([
                    'status'      => 'completed',
                    'phase'       => 'importing',
                    'result_data' => ['new_entries_count' => count($result['new_entries'])],
                ]);
            } else {
                // Serializa Carbon → string antes de salvar no JSON
                $serializedDups = array_map(function ($dup) {
                    return array_merge($dup, [
                        'entry_date' => $dup['entry_date']->format('Y-m-d'),
                        'due_date'   => isset($dup['due_date']) ? $dup['due_date']->format('Y-m-d') : null,
                    ]);
                }, $result['duplicate_entries']);

                $import->update([
                    'status'      => 'completed',
                    'phase'       => 'detecting',
                    'result_data' => [
                        'duplicate_entries' => $serializedDups,
                        'new_entries_count' => count($result['new_entries']),
                    ],
                ]);
            }
        } catch (\Exception $e) {
            @unlink($this->tempPath);
            $import->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }
}
