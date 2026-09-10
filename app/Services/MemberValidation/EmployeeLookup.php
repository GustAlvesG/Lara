<?php

namespace App\Services\MemberValidation;

use App\Models\Employee;

/**
 * Consulta de funcionários do clube (tabela local `employees`, alimentada pela
 * importação do banco de horas).
 *
 * O funcionário pode se identificar pela matrícula (employee_code) ou pelo
 * CPF. Funcionário desligado com soft delete não aparece — o escopo padrão do
 * model já o exclui.
 */
class EmployeeLookup
{
    /**
     * O CPF chega gravado dos dois jeitos (com e sem máscara), então a
     * comparação remove a pontuação dos dois lados.
     *
     * @return string[]
     */
    public function namesForCpf(string $cpfDigits): array
    {
        return $this->names(
            Employee::whereRaw("REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?", [$cpfDigits])
        );
    }

    /** @return string[] */
    public function namesForCode(string $code): array
    {
        $digits = preg_replace('/\D/', '', $code);

        return $this->names(
            Employee::whereIn('employee_code', array_values(array_unique(array_filter([$code, $digits]))))
        );
    }

    /** @return string[] */
    private function names($query): array
    {
        return $query->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();
    }
}
