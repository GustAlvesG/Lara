<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\TimeAdjustment;
use App\Services\CompTimeService;

class CompTimeController extends Controller
{

    protected $compTimeService;

    public function __construct(CompTimeService $compTimeService)
    {
        $this->compTimeService = $compTimeService;
    }

    public function index()
    {
        $structures = $this->compTimeService->getStructures();

        return view('compTime.upload', compact('structures'));
    }

    public function indexFilter(Request $request)
    {
        $filters = $request->only(['structure', 'employee_name', 'employee_code', 'period_start', 'period_end', 'status']);

        $employees = $this->compTimeService->filterEmployees($filters);
        $reportData = $this->compTimeService->getTimeEntriesForEmployees($employees, $filters);
        $structures = $this->compTimeService->getStructures();

        return view('compTime.upload', compact('structures', 'filters', 'reportData'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:html,xls,txt',
        ]);

        try {
            $path = $request->file('arquivo')->getRealPath();
            
            $this->compTimeService->importFile($path);

            return redirect()->route('comp-time.index')->with('success', 'Arquivo importado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->route('comp-time.index')->with('error', 'Erro ao processar: ' . $e->getMessage());
        }
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
            return view('compTime.details', compact('details', 'employee', 'dashboard'));
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
}
