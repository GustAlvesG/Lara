<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cadastro de funcionários do Banco de Horas — a aba do RH.
 *
 * O funcionário continua nascendo da importação do espelho de ponto; esta tela
 * cuida do que o espelho não traz: em que setor ele responde, se está de
 * férias ou afastado, e se foi desligado.
 *
 * Férias, afastamento e rescisão são **informativos**. Não alteram a
 * importação nem o cálculo de saldo — a ficha de quem saiu continua inteira.
 * Existem para o cadastro e para o módulo de consulta de funcionários ativos
 * que vem depois (ver Employee::scopeActive()).
 */
class CompTimeEmployeeController extends Controller
{
    // A aba inteira é do RH. O corte está na rota
    // (`middleware('can:manage-comp-time')`, ver routes/web.php) e não aqui:
    // o Controller base do Laravel 11 não tem mais `$this->middleware()`, e o
    // resto do app já usa `can:` na definição da rota.

    public function index(Request $request)
    {
        $filters = $request->only(['sector_id', 'search', 'situation']);

        $query = Employee::with('sector')->orderBy('name');

        if (!empty($filters['sector_id'])) {
            $query->where('sector_id', $filters['sector_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%")
                  ->orWhere('cpf', 'like', "%{$search}%");
            });
        }

        match ($filters['situation'] ?? 'active') {
            'terminated' => $query->terminated(),
            'all'        => null,
            default      => $query->active(),
        };

        $employees = $query->paginate(30)->withQueryString();

        // Ausência vigente de cada um da página, numa consulta só — a coluna
        // "situação" da listagem precisa disso por linha.
        $currentAbsences = EmployeeAbsence::whereIn('employee_id', $employees->pluck('id'))
            ->current()
            ->get()
            ->keyBy('employee_id');

        $sectors = Sector::orderBy('name')->pluck('name', 'id');

        return view('compTime.employees.index', compact('employees', 'sectors', 'filters', 'currentAbsences'));
    }

    public function show(Employee $employee)
    {
        $employee->load(['sector', 'absences', 'user']);
        $sectors = Sector::orderBy('name')->pluck('name', 'id');

        return view('compTime.employees.show', compact('employee', 'sectors'));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'sector_id'          => 'nullable|integer|exists:sectors,id',
            'termination_date'   => 'nullable|date',
            'termination_reason' => 'nullable|string|max:255',
        ], [], [
            'sector_id'          => 'setor',
            'termination_date'   => 'data de rescisão',
            'termination_reason' => 'motivo da rescisão',
        ]);

        // Motivo sem data não descreve nada — some junto.
        if (empty($data['termination_date'])) {
            $data['termination_date']   = null;
            $data['termination_reason'] = null;
        }

        $employee->update($data);

        return redirect()->route('comp-time.employees.show', $employee)
            ->with('success', 'Cadastro de ' . $employee->name . ' atualizado.');
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

        return redirect()->route('comp-time.employees.show', $employee)
            ->with('success', EmployeeAbsence::TYPES[$data['type']] . ' registrado(a) com sucesso.');
    }

    public function destroyAbsence(Employee $employee, EmployeeAbsence $absence)
    {
        // Rota aninhada: o registro tem de ser deste funcionário.
        abort_unless($absence->employee_id === $employee->id, 404);

        $absence->delete();

        return redirect()->route('comp-time.employees.show', $employee)
            ->with('success', 'Registro removido.');
    }
}
