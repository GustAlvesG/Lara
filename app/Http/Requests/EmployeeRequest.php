<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Support\Cpf;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro e edição de funcionário (módulo Funcionários). As mesmas regras
 * valem para a planilha — ver EmployeeImport::rules().
 */
class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'employee_code' => trim((string) $this->input('employee_code')),
            'cpf' => Cpf::digits($this->input('cpf')),
        ]);
    }

    public function rules(): array
    {
        /** @var Employee|null $employee */
        $employee = $this->route('employee');

        return self::rulesFor($employee?->id);
    }

    /**
     * Regras de um funcionário, para a tela e para a planilha.
     *
     * Matrícula única contando os excluídos (é o índice do banco: quem foi
     * excluído é restaurado, não recadastrado). CPF único só entre os que não
     * foram excluídos — o mesmo CPF volta numa recontratação com outra
     * matrícula.
     */
    public static function rulesFor(?int $ignoreId = null): array
    {
        return [
            'employee_code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Za-z0-9.\-]+$/',
                Rule::unique('employees', 'employee_code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                'required', 'string', self::validCpf(),
                Rule::unique('employees', 'cpf')->ignore($ignoreId)->whereNull('deleted_at'),
            ],
            'sector_id' => ['required', 'integer', 'exists:sectors,id'],
            'position' => ['nullable', 'string', 'max:255'],
            'admission_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date'],
            'termination_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Onze dígitos com os verificadores certos (o valor já chega só com dígitos). */
    public static function validCpf(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (!Cpf::isValid((string) $value)) {
                $fail('O CPF informado não é válido.');
            }
        };
    }

    public static function labels(): array
    {
        return [
            'employee_code' => 'matrícula',
            'name' => 'nome',
            'cpf' => 'CPF',
            'sector_id' => 'setor',
            'position' => 'cargo',
            'admission_date' => 'data de admissão',
            'termination_date' => 'data de rescisão',
            'termination_reason' => 'motivo da rescisão',
        ];
    }

    public function attributes(): array
    {
        return self::labels();
    }

    public function messages(): array
    {
        return [
            'employee_code.unique' => 'Já existe um funcionário com esta matrícula (confira também os excluídos).',
            'employee_code.regex' => 'A matrícula aceita só letras, números, ponto e hífen.',
            'cpf.unique' => 'Já existe um funcionário ativo com este CPF.',
        ];
    }
}
