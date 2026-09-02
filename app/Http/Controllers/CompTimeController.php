<?php

namespace App\Http\Controllers;

use App\Jobs\ConfirmCompTimeImportJob;
use App\Jobs\DetectCompTimeDuplicatesJob;
use App\Models\CompTimeImport;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Services\CompTimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CompTimeController extends Controller
{
    public function __construct(private CompTimeService $compTimeService) {}

    /**
     * O que o usuário da requisição enxerga. Toda ação passa por aqui — ver
     * CompTimeService::accessFor() para a regra.
     */
    private function access(): array
    {
        return $this->compTimeService->accessFor(Auth::user());
    }

    /**
     * Importar, recalcular e administrar o cadastro é do RH. Um `abort` e não
     * um `@if` na view: esconder o botão não fecha a rota.
     */
    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()->canManageCompTime(), 403);
    }

    /** Dados que toda tela do módulo precisa para decidir o que mostrar. */
    private function viewFlags(array $access): array
    {
        return [
            'canImport'     => Auth::user()->canManageCompTime(),
            'isCoordinator' => in_array($access['type'], ['all', 'sectors'], true),
        ];
    }

    public function index()
    {
        $access = $this->access();

        if ($access['type'] === 'none') {
            return view('compTime.upload', [
                'sectors'       => [],
                'accessDenied'  => true,
                'canImport'     => false,
                'isCoordinator' => false,
            ]);
        }

        $sectors = $this->compTimeService->getSectors($access);

        // Quem só enxerga a própria ficha não tem o que filtrar — a tela já
        // abre com os dados dele.
        if ($access['type'] === 'employee_code') {
            $employees  = $this->compTimeService->filterEmployees([], $access);
            $reportData = $this->compTimeService->getTimeEntriesForEmployees($employees, []);

            return view('compTime.upload', array_merge(
                compact('sectors', 'reportData'),
                $this->viewFlags($access)
            ));
        }

        return view('compTime.upload', array_merge(
            compact('sectors'),
            $this->viewFlags($access)
        ));
    }

    public function indexFilter(Request $request)
    {
        $access = $this->access();

        if ($access['type'] === 'none') {
            return view('compTime.upload', [
                'sectors'       => [],
                'reportData'    => [],
                'filters'       => [],
                'accessDenied'  => true,
                'canImport'     => false,
                'isCoordinator' => false,
            ]);
        }

        $filters = $request->only([
            'sector_id', 'employee_name', 'employee_code', 'period_start', 'period_end', 'status',
        ]);

        $employees  = $this->compTimeService->filterEmployees($filters, $access);
        $reportData = $this->compTimeService->getTimeEntriesForEmployees($employees, $filters);
        $sectors    = $this->compTimeService->getSectors($access);

        return view('compTime.upload', array_merge(
            compact('sectors', 'filters', 'reportData'),
            $this->viewFlags($access)
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeManagement();

        $request->validate([
            'arquivo' => 'required|file|mimes:html,xls,txt|max:20480',
        ]);

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Faxina do que ficou para trás: arquivo abandonado por importação que
        // nunca foi revisada, e o registro correspondente.
        foreach (glob($tempDir . '/*.html') as $file) {
            if (filemtime($file) < time() - 86400) {
                @unlink($file);
            }
        }
        CompTimeImport::where('created_at', '<', now()->subDay())->delete();

        $uuid     = (string) Str::uuid();
        $tempPath = $tempDir . '/' . $uuid . '.html';
        $request->file('arquivo')->move($tempDir, $uuid . '.html');

        CompTimeImport::create([
            'uuid'           => $uuid,
            'user_id'        => Auth::id(),
            'status'         => CompTimeImport::STATUS_PENDING,
            'phase'          => CompTimeImport::PHASE_DETECTING,
            'temp_file_path' => $tempPath,
            'dispatched_at'  => now(),
        ]);

        DetectCompTimeDuplicatesJob::dispatch($uuid, $tempPath);

        return redirect()->route('comp-time.import-status', $uuid);
    }

    /**
     * Importação só é acessível a quem a iniciou. Antes bastava ter o UUID:
     * qualquer usuário autenticado abria a revisão de um arquivo alheio e
     * confirmava a gravação dele.
     */
    private function findOwnImport(string $uuid): CompTimeImport
    {
        $this->authorizeManagement();

        $import = CompTimeImport::where('uuid', $uuid)->firstOrFail();

        abort_unless($import->user_id === null || $import->user_id === Auth::id(), 403);

        return $import;
    }

    public function importStatus(string $uuid)
    {
        $import = $this->findOwnImport($uuid);

        // Recarregou a página depois de terminar: manda direto para o resultado.
        if ($import->status === CompTimeImport::STATUS_COMPLETED) {
            return redirect()->route('comp-time.index')->with('success', $this->successMessage($import));
        }

        if ($import->status === CompTimeImport::STATUS_FAILED) {
            return redirect()->route('comp-time.index')
                ->with('error', 'Erro na importação: ' . $import->error_message);
        }

        if ($import->isAwaitingReview()) {
            return redirect()->route('comp-time.import-preview', $uuid);
        }

        return view('compTime.import-status', compact('import'));
    }

    public function importComplete(string $uuid)
    {
        $import = $this->findOwnImport($uuid);

        return redirect()->route('comp-time.index')->with('success', $this->successMessage($import));
    }

    public function importStatusApi(string $uuid)
    {
        $import = $this->findOwnImport($uuid);

        $payload = [
            'status'       => $import->status,
            'phase'        => $import->phase,
            'error'        => $import->error_message,
            'stalled'      => $import->looksStalled(),
            'redirect_url' => route('comp-time.import-complete', $uuid),
        ];

        if ($import->isAwaitingReview()) {
            $payload['has_duplicates']    = true;
            $payload['new_entries_count'] = $import->newEntriesCount();
            $payload['preview_url']       = route('comp-time.import-preview', $uuid);
        }

        return response()->json($payload);
    }

    public function showImportPreview(string $uuid)
    {
        $import = $this->findOwnImport($uuid);

        abort_unless($import->isAwaitingReview(), 404);

        $duplicates = $import->duplicateEntries();
        $newCount   = $import->newEntriesCount();

        return view('compTime.import-preview', compact('duplicates', 'newCount', 'uuid'));
    }

    public function confirmImport(Request $request, string $uuid)
    {
        $import = $this->findOwnImport($uuid);

        abort_unless($import->isAwaitingReview(), 404);

        // Só ids que estavam de fato na lista de duplicatas desta importação —
        // o formulário chega do navegador e pode vir com qualquer coisa.
        $offered  = array_column($import->duplicateEntries(), 'existing_entry_id');
        $accepted = array_values(array_intersect(
            array_map('intval', (array) $request->input('accepted_ids', [])),
            array_map('intval', $offered)
        ));

        $import->update([
            'status' => CompTimeImport::STATUS_PENDING,
            'phase'  => CompTimeImport::PHASE_CONFIRMING,
            'dispatched_at' => now(),
        ]);

        ConfirmCompTimeImportJob::dispatch($uuid, $import->temp_file_path, $accepted);

        return redirect()->route('comp-time.import-status', $uuid);
    }

    private function successMessage(CompTimeImport $import): string
    {
        $summary = $import->result_data['summary'] ?? null;

        if (!$summary) {
            return 'Importação concluída com sucesso.';
        }

        return sprintf(
            'Importação concluída: %d lançamento(s) novo(s), %d atualizado(s), %d ignorado(s), %d funcionário(s) recalculado(s).',
            $summary['created'] ?? 0,
            $summary['updated'] ?? 0,
            $summary['skipped'] ?? 0,
            $summary['employees'] ?? 0,
        );
    }

    public function showDetails(Request $request)
    {
        $request->validate([
            'employee_id'  => 'required|integer|exists:employees,id',
            'period_start' => 'nullable|date',
            'period_end'   => 'nullable|date',
        ]);

        $access   = $this->access();
        $employee = Employee::findOrFail($request->integer('employee_id'));

        // A rota conferia apenas que o funcionário existia. Qualquer usuário
        // autenticado abria a ficha de qualquer um só trocando o id do formulário.
        abort_unless($this->compTimeService->canViewEmployee($access, $employee), 403);

        $details = $this->compTimeService->getTimeEntryDetails(
            $employee->id,
            $request->input('period_start') ?: null,
            $request->input('period_end') ?: null,
        );

        $employee  = $details['employee'];
        $dashboard = $details['dashboard'];
        unset($details['employee'], $details['dashboard']);

        return view('compTime.details', array_merge(
            compact('details', 'employee', 'dashboard'),
            $this->viewFlags($access)
        ));
    }

    public function showDayDetails(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'day'         => 'required|date',
        ]);

        $access   = $this->access();
        $employee = Employee::findOrFail($request->integer('employee_id'));

        abort_unless($this->compTimeService->canViewEmployee($access, $employee), 403);

        $day        = $request->input('day');
        $dayDetails = $this->compTimeService->getDayDetails($employee->id, $day);

        return view('compTime.dayDetails', compact('dayDetails', 'day', 'employee'));
    }

    public function recalculateBalances()
    {
        $this->authorizeManagement();

        $this->compTimeService->recalculateAllBalances();

        return redirect()->route('comp-time.index')->with('success', 'Saldos recalculados com sucesso.');
    }

    public function writeOff(Request $request)
    {
        $this->updateWriteOff($request, true);

        return back()->with('success', 'Baixa registrada com sucesso.');
    }

    public function undoWriteOff(Request $request)
    {
        $this->updateWriteOff($request, false);

        return back()->with('success', 'Baixa desfeita com sucesso.');
    }

    /**
     * Dar e desfazer baixa é ação de coordenador — e só sobre lançamento de
     * gente que ele enxerga. A checagem antiga parava no papel do usuário e
     * não olhava de quem era o lançamento.
     */
    private function updateWriteOff(Request $request, bool $writtenOff): void
    {
        $request->validate(['entry_id' => 'required|integer|exists:time_entries,id']);

        $access = $this->access();
        abort_unless(in_array($access['type'], ['all', 'sectors'], true), 403);

        $entry = TimeEntry::with('employee')->findOrFail($request->integer('entry_id'));
        abort_unless(
            $entry->employee && $this->compTimeService->canViewEmployee($access, $entry->employee),
            403
        );

        $entry->update(['written_off' => $writtenOff]);

        // A baixa tira (ou devolve) o lançamento da compensação — o saldo do
        // funcionário precisa ser refeito, senão a ficha mostra um total que
        // não corresponde mais aos lançamentos ativos.
        $this->compTimeService->recalculateAllBalances($entry->employee_id);
    }
}
