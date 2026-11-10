<?php

namespace App\Http\Requests;

use App\Models\Waitlist\WaitlistPerson;
use App\Support\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro e edição do não sócio da fila de espera. CPF obrigatório e único
 * (inclusive de menor): é ele que liga a mesma pessoa a várias filas.
 */
class WaitlistPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf' => Cpf::digits($this->input('cpf')),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    public function rules(): array
    {
        /** @var WaitlistPerson|null $person */
        $person = $this->route('person');

        return [
            'cpf' => [
                'required', 'string', EmployeeRequest::validCpf(),
                Rule::unique('waitlist_people', 'cpf')->ignore($person?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['required', 'string', 'max:20'],
            'phone_alt' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Só no cadastro: as turmas em que a pessoa já entra.
            'classes' => ['nullable', 'array'],
            'classes.*' => ['integer', 'exists:waitlist_classes,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cpf' => 'CPF',
            'name' => 'nome',
            'birth_date' => 'data de nascimento',
            'phone' => 'telefone',
            'phone_alt' => 'outro telefone',
            'email' => 'e-mail',
            'guardian_name' => 'nome do responsável',
            'guardian_phone' => 'telefone do responsável',
            'notes' => 'observação',
            'classes' => 'turmas',
        ];
    }

    public function messages(): array
    {
        return [
            'cpf.unique' => 'Já existe uma pessoa na fila de espera com este CPF.',
            'birth_date.before_or_equal' => 'A data de nascimento não pode ser no futuro.',
        ];
    }

    /** Os campos da pessoa, sem as turmas. */
    public function personData(): array
    {
        return collect($this->validated())->except('classes')->all();
    }
}
