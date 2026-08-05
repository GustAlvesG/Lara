<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesFunctionType;
use Illuminate\Foundation\Http\FormRequest;

class StoreFunctionFreelancerRequest extends FormRequest
{
    /** Regras dos dois tipos de função (freelancer por bloco, cachê por faixa). */
    use ValidatesFunctionType;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->functionTypeRules() + [
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
