<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\TimeAdjustment;
use App\Models\CompTimeImport;
use App\Jobs\DetectCompTimeDuplicatesJob;
use App\Jobs\ConfirmCompTimeImportJob;
use App\Services\CompTimeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CompTimeController extends Controller
{

    protected $compTimeService;

    public function __construct(CompTimeService $compTimeService)
    {
        $this->compTimeService = $compTimeService;
    }

    private function getAccessRestriction(): array
    {
        $user = Auth::user();

        // Coordenadores de RH ou TI têm acesso total a todos os registros
        $fullAccessSectors = ['RH', 'TI'];
        $hasFullAccess = $user->coordinatorSectors()
            ->whereIn('name', $fullAccessSectors)
            ->exists();
        if ($hasFullAccess) {
            return ['type' => 'all'];
        }

        // Coordenadores de outros setores veem apenas os departamentos dos seus setores
        $coordinatorDepartments = $user->coordinatorSectors()->pluck('name')->toArray();
        if (!empty($coordinatorDepartments)) {
            return ['type' => 'departments', 'values' => $coordinatorDepartments];
        }

        // Qualquer usuário com matrícula vê apenas seus próprios registros
        if ($user->matricula) {
            return ['type' => 'employee_code', 'value' => $user->matricula];
        }

        return ['type' => 'none'];
    }

    public function index()
    {
        $access = $this->getAccessRestriction();

        if ($access['type'] === 'none') {
            return view('compTime.upload', ['structures' => [], 'accessDenied' => true, 'isCoordinator' => false]);
        }

        $isCoordinator = in_array($access['type'], ['all', 'departments']);
        $structures = $this->compTimeService->getStructures($access);

        // Para acesso individual (por matrícula), carrega os dados automaticamente ao abrir a página
        if ($access['type'] === 'employee_code') {
            $employees = $this->compTimeService->filterEmployees([], $access);
            $reportData = $this->compTimeService->getTimeEntriesForEmployees($employees, []);
            return view('compTime.upload', compact('structures', 'reportData', 'isCoordinator'));
        }

        return view('compTime.upload', compact('structures', 'isCoordinator'));
    }

    public function indexFilter(Request $request)
    {
        $access = $this->getAccessRestriction();

        if ($access['type'] === 'none') {
            return view('compTime.upload', ['structures' => [], 'reportData' => [], 'filters' => [], 'accessDenied' => true, 'isCoordinator' => false]);
        }

        $isCoordinator = in_array($access['type'], ['all', 'departments']);
        $filters = $request->only(['structure', 'employee_name', 'employee_code', 'period_start', 'period_end', 'status']);

        $employees = $this->compTimeService->filterEmployees($filters, $access);
        $reportData = $this->compTimeService->getTimeEntriesForEmployees($employees, $filters);
        $structures = $this->compTimeService->getStructures($access);

        return view('compTime.upload', compact('structures', 'filters', 'reportData', 'isCoordinator'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:html,xls,txt',
        ]);

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

        // Limpar arquivos e registros abandonados com mais de 24h
        foreach (glob($tempDir . '/*.html') as $f) {
            if (filemtime($f) < time() - 86400) @unlink($f);
        }
        CompTimeImport::where('created_at', '<', now()->subDay())->delete();

        $uuid     = (string) Str::uuid();
        $tempPath = $tempDir . '/' . $uuid . '.html';
        $request->file('arquivo')->move($tempDir, $uuid . '.html');

        CompTimeImport::create([
            'uuid'           => $uuid,
            'status'         => 'pending',
            'phase'          => 'detecting',
            'temp_file_path' => $tempPath,
        ]);

        DetectCompTimeDuplicatesJob::dispatch($uuid, $tempPath);

        return redirect()->route('comp-time.import-status', $uuid);
    }

    public function importStatus(string $uuid)
    {
        $import = CompTimeImport::where('uuid', $uuid)->firstOrFail();

        // Se já terminou (ex: usuário F5 na página), redireciona direto
        if ($import->status === 'completed' && in_array($import->phase, ['importing', 'confirming'])) {
            return redirect()->route('comp-time.index')->with('success', 'Importação concluída com sucesso.');
        }
        if ($import->status === 'failed') {
            return redirect()->route('comp-time.index')->with('error', 'Erro na importação: ' . $import->error_message);
        }

        return view('compTime.import-status', compact('import'));
    }

    public function importComplete(string $uuid)
    {
        return redirect()->route('comp-time.index')->with('success', 'Importação concluída com sucesso.');
    }

    public function importStatusApi(string $uuid)
    {
        $import = CompTimeImport::where('uuid', $uuid)->firstOrFail();

        $payload = [
            'status'       => $import->status,
            'phase'        => $import->phase,
            'error'        => $import->error_message,
            'redirect_url' => route('comp-time.import-complete', $uuid),
        ];

        if ($import->status === 'completed' && $import->phase === 'detecting') {
            $duplicates = $import->result_data['duplicate_entries'] ?? [];
            $payload['has_duplicates']    = !empty($duplicates);
            $payload['new_entries_count'] = $import->result_data['new_entries_count'] ?? 0;
            $payload['preview_url']       = route('comp-time.import-preview', $uuid);
        }

        return response()->json($payload);
    }

    public function showImportPreview(string $uuid)
    {
        $import = CompTimeImport::where('uuid', $uuid)
            ->where('status', 'completed')
            ->where('phase', 'detecting')
            ->firstOrFail();

        $duplicates = $import->result_data['duplicate_entries'] ?? [];
        $newEntries = array_fill(0, $import->result_data['new_entries_count'] ?? 0, []);

        $access        = $this->getAccessRestriction();
        $isCoordinator = in_array($access['type'], ['all', 'departments']);

        return view('compTime.import-preview', compact('duplicates', 'newEntries', 'isCoordinator', 'uuid'));
    }

    public function confirmImport(Request $request, string $uuid)
    {
        $import = CompTimeImport::where('uuid', $uuid)
            ->where('status', 'completed')
            ->where('phase', 'detecting')
            ->firstOrFail();

        $acceptedIds = $request->input('accepted_ids', []);

        $import->update(['status' => 'pending', 'phase' => 'confirming']);

        ConfirmCompTimeImportJob::dispatch($uuid, $import->temp_file_path, $acceptedIds);

        return redirect()->route('comp-time.import-status', $uuid);
    }

    public function showDetails(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
        ]);

        try {
            $employeeId = $request->input('employee_id');
            $periodStart = $request->input('period_start') ?: null;
            $periodEnd = $request->input('period_end') ?: null;

            $details = $this->compTimeService->getTimeEntryDetails($employeeId, $periodStart, $periodEnd);
            $employee = $details['employee'] ?? null;
            unset($details['employee']);
            $dashboard = $details['dashboard'] ?? null;
            unset($details['dashboard']);

            $access = $this->getAccessRestriction();
            $isCoordinator = in_array($access['type'], ['all', 'departments']);

            return view('compTime.details', compact('details', 'employee', 'dashboard', 'isCoordinator'));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao obter detalhes: ' . $e->getMessage()], 500);
        }
    }

    public function showDayDetails(Request $request)
    {
        $request->validate([
            'day' => 'required|date',
        ]);

        try {
            $day = $request->input('day');

            $dayDetails = $this->compTimeService->getDayDetails($day);

            return view('compTime.dayDetails', compact('dayDetails', 'day'));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao obter detalhes do dia: ' . $e->getMessage()], 500);
        }
    }

    public function recalculateBalances()
    {
        $this->compTimeService->recalculateAllBalances();

        return redirect()->route('comp-time.index')->with('success', 'Saldos recalculados com sucesso.');
    }

    public function writeOff(Request $request)
    {
        $access = $this->getAccessRestriction();
        if (!in_array($access['type'], ['all', 'departments'])) {
            abort(403);
        }
        $request->validate(['entry_id' => 'required|integer|exists:time_entries,id']);
        TimeEntry::where('id', $request->entry_id)->update(['written_off' => true]);
        return back()->with('success', 'Baixa registrada com sucesso.');
    }

    public function undoWriteOff(Request $request)
    {
        $access = $this->getAccessRestriction();
        if (!in_array($access['type'], ['all', 'departments'])) {
            abort(403);
        }
        $request->validate(['entry_id' => 'required|integer|exists:time_entries,id']);
        TimeEntry::where('id', $request->entry_id)->update(['written_off' => false]);
        return back()->with('success', 'Baixa desfeita com sucesso.');
    }
}
