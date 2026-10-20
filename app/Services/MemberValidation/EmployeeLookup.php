<?php

namespace App\Services\MemberValidation;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consulta de funcionários do clube (cadastro do módulo Funcionários).
 *
 * O funcionário pode se identificar pela matrícula (employee_code) ou pelo
 * CPF. Funcionário excluído (soft delete) não aparece — o escopo padrão do
 * model já o exclui. Desligado, de férias ou afastado aparece, mas com o
 * motivo do bloqueio: quem decide o que fazer com ele é o MemberValidator.
 */
class EmployeeLookup
{
    /**
     * O CPF é gravado só com dígitos (ver Employee::cpf()), então a
     * comparação é direta.
     *
     * @return list<array{id: int, name: string, blocked: ?string}>
     */
    public function forCpf(string $cpfDigits): array
    {
        return $this->matches(Employee::where('cpf', $cpfDigits));
    }

    /** @return list<array{id: int, name: string, blocked: ?string}> */
    public function forCode(string $code): array
    {
        $digits = preg_replace('/\D/', '', $code);

        return $this->matches(
            Employee::whereIn('employee_code', array_values(array_unique(array_filter([$code, $digits]))))
        );
    }

    /** @return list<array{id: int, name: string, blocked: ?string}> */
    private function matches(Builder $query): array
    {
        return $query->with('absences')->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => trim((string) $employee->name),
                'blocked' => $employee->blockReason(),
            ])
            ->filter(fn (array $employee) => $employee['name'] !== '')
            ->values()
            ->all();
    }
}
