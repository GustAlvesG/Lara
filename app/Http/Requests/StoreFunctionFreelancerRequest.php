<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFunctionFreelancerRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Preço cobrado por bloco de 15 minutos.
            'price' => ['required', 'numeric', 'min:0'],
            // Habilita o aditivo de comissão de venda para esta função.
            'allows_sales_commission' => ['nullable', 'boolean'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
