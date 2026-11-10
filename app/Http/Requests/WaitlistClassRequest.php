<?php

namespace App\Http\Requests;

use App\Models\Waitlist\WaitlistClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cadastro e edição de turma da fila de espera. */
class WaitlistClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'modality' => trim((string) $this->input('modality')),
            'name' => trim((string) $this->input('name')),
            'active' => $this->boolean('active'),
        ]);
    }

    public function rules(): array
    {
        /** @var WaitlistClass|null $class */
        $class = $this->route('waitlistClass');

        return [
            'modality' => ['required', 'string', 'max:100'],
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('waitlist_classes', 'name')
                    ->where('modality', $this->input('modality'))
                    ->ignore($class?->id),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'modality' => 'modalidade',
            'name' => 'turma',
            'notes' => 'observação',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Já existe uma turma com este nome nesta modalidade.',
        ];
    }
}
