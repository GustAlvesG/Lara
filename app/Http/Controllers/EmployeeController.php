<?php

namespace App\Http\Controllers;

use App\Exceptions\SpreadsheetImportException;
use App\Http\Requests\EmployeeRequest;
use App\Http\Requests\ImportSpreadsheetRequest;
use App\Imports\EmployeeImport;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\Sector;
use App\Services\Employees\EmployeeSectorSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Módulo Funcionários — o cadastro do RH (permissão `funcionarios.gerenciar`,
 * no corte da rota).
 *
 * O funcionário é quem se identifica no pedido de carro de aplicativo e quem
 * dirige a frota; para os dois só vale o apto (Employee::blockReason()). Não é
 * usuário do painel: só se liga a um quando as matrículas casam, e então o
 * setor do funcionário coloca o usuário no setor (EmployeeSectorSync).
 *
 * Excluir é soft delete e tem volta (restaurar): o funcionário carrega o
 * histórico do Banco de Horas, das viagens da frota e dos pedidos de carro.
 */
class EmployeeController extends Controller
{
    /** Filtros de situação da lista: valor => rótulo. */
    public const SITUATIONS = [
        '' => 'Todos (sem os excluídos)',
        'eligible' => 'Aptos',
        'absent' => 'De férias ou afastados',
        'terminated' => 'Desligados',
        'trashed' => 'Excluídos',
    ];

    public function __construct(private readonly EmployeeSectorSync $sectorSync)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'sector_id', 'situation']);

        $query = Employee::with(['sector', 'user', 'absences'])->orderBy('name');

        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $digits = preg_replace('/\D/', '', $term);

            $query->where(function (Builder $q) use ($term, $digits) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('employee_code', 'like', "%{$term}%");

                if ($digits !== '') {
                    $q->orWhere('cpf', 'like', "%{$digits}%");
                }
            });
        }

        if (filled($filters['sector_id'] ?? null)) {
            $query->where('sector_id', $filters['sector_id']);
        }

        match ($filters['situation'] ?? '') {
            'eligible' => $query->eligible(),
            'absent' => $query->active()->whereHas('absences', fn (Builder $q) => $q->current()),
            'terminated' => $query->terminated(),
            'trashed' => $query->onlyTrashed(),
            default => null,
        };

        return view('employees.index', [
            'employees' => $query->paginate(30)->withQueryString(),
            'sectors' => Sector::orderBy('name')->pluck('name', 'id'),
            'filters' => $filters,
            'situations' => self::SITUATIONS,
        ]);
    }

    public function create(EmployeeImport $import)
    {
        return view('employees.create', [
            'sectors' => Sector::orderBy('name')->pluck('name', 'id'),
            'importColumns' => $import->columns(),
        ]);
    }

    public function store(EmployeeRequest $request)
    {
        $employee = Employee::create($request->safe()->except(['termination_date', 'termination_reason'])
            + ['source' => Employee::SOURCE_MANUAL]);

        $joined = $this->sectorSync->syncEmployee($employee);

        return redirect()->route('employees.show', $employee)
            ->with('success', trim("Funcionário {$employee->name} cadastrado. {$joined}"));
    }

    public function show(Employee $employee)
    {
        $employee->load(['sector', 'user', 'absences']);

        return view('employees.show', [
            'employee' => $employee,
            'sectors' => Sector::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        $data = $request->validated();

        // Motivo sem data não descreve nada — some junto.
        if (empty($data['termination_date'])) {
            $data['termination_date'] = null;
            $data['termination_reason'] = null;
        }

        $employee->update($data);

        // Setor ou matrícula novos podem levar um usuário a um setor.
        $joined = $employee->wasChanged(['sector_id', 'employee_code', 'termination_date'])
            ? $this->sectorSync->syncEmployee($employee)
            : null;

        return redirect()->route('employees.show', $employee)
            ->with('success', trim("Cadastro de {$employee->name} atualizado. {$joined}"));
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', "Funcionário {$employee->name} excluído. Ele deixa de valer para carro de aplicativo e frota; dá para restaurar pelo filtro \"Excluídos\".");
    }

    public function restore(Employee $employee)
    {
        $employee->restore();

        $joined = $this->sectorSync->syncEmployee($employee);

        return redirect()->route('employees.show', $employee)
            ->with('success', trim("Funcionário {$employee->name} restaurado. {$joined}"));
    }

    /** Arquivo .xlsx em branco, no formato aceito pela importação. */
    public function importTemplate(EmployeeImport $import)
    {
        return $import->downloadTemplate();
    }

    /**
     * Carga em massa. Matrícula nova cria, matrícula existente atualiza; com
     * qualquer erro, nada é gravado e a lista volta para a tela.
     */
    public function import(ImportSpreadsheetRequest $request, EmployeeImport $import)
    {
        try {
            $result = $import->import($request->file('spreadsheet')->getRealPath());
        } catch (SpreadsheetImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['errors']) {
            return back()
                ->with('error', 'Nenhum funcionário foi importado. Corrija a planilha e envie novamente.')
                ->with('import_errors', $result['errors']);
        }

        $created = $result['records']->filter(fn (Employee $e) => $e->wasRecentlyCreated)->count();
        $updated = $result['imported'] - $created;

        $joined = $result['records']
            ->map(fn (Employee $e) => $this->sectorSync->syncEmployee($e))
            ->filter()
            ->count();

        $message = "Planilha importada: {$created} funcionário(s) novo(s) e {$updated} atualizado(s).";

        if ($joined) {
            $message .= " {$joined} usuário(s) entraram no setor do funcionário de mesma matrícula.";
        }

        return redirect()->route('employees.index')->with('success', $message);
    }

    public function storeAbsence(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'type'       => 'required|in:' . implode(',', array_keys(EmployeeAbsence::TYPES)),
            'start_date' => 'required|date',
            // `after_or_equal` e não `after`: férias e afastamento de um dia só
            // começam e terminam na mesma data.
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'notes'      => 'nullable|string|max:255',
        ], [], [
            'type'       => 'tipo',
            'start_date' => 'data de início',
            'end_date'   => 'data de fim',
            'notes'      => 'observação',
        ]);

        $employee->absences()->create($data + ['created_by' => Auth::id()]);

        return redirect()->route('employees.show', $employee)
            ->with('success', EmployeeAbsence::TYPES[$data['type']] . ' registrado(a). Enquanto valer, o funcionário não pede carro de aplicativo nem retira veículo da frota.');
    }

    public function destroyAbsence(Employee $employee, EmployeeAbsence $absence)
    {
        // Rota aninhada: o registro tem de ser deste funcionário.
        abort_unless($absence->employee_id === $employee->id, 404);

        $absence->delete();

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Registro removido.');
    }
}
