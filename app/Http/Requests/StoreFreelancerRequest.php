<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFreelancerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Só `name` e `cpf` são obrigatórios: aceitamos o cadastro com dados
     * pendentes (rg, nacionalidade, estado civil, endereço, telefone). Esses
     * campos precisam estar preenchidos apenas na hora de gerar o contrato —
     * regra centralizada em Freelancer::hasCompleteContractData().
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'cpf' => ['required', 'string', 'max:11', 'unique:freelancers,cpf'],
            'pix_key' => ['nullable', 'string', 'max:255'],
            'rg' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'nacionality' => ['nullable', 'string'],
            'civil_status' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'telephone' => ['nullable', 'string'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
