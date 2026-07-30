<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInformationRequest extends FormRequest
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
            'description' => ['required', 'string'],
            // svg fora da lista: pode carregar <script> embutido.
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'information_id' => ['nullable', 'integer', 'exists:information,id'],

            // Tags substituíram o antigo campo único `category`.
            'tags' => ['required', 'array', 'min:3'],
            'tags.*' => ['required', 'string', 'max:50', 'distinct:ignore_case'],

            'fee' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'slots' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],

            'name_price' => ['nullable', 'array'],
            'name_price.*' => ['nullable', 'string', 'max:255'],
            'price_associated' => ['nullable', 'array'],
            'price_associated.*' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'price_not_associated' => ['nullable', 'array'],
            'price_not_associated.*' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],

            'responsible' => ['nullable', 'array'],
            'responsible.*' => ['nullable', 'string', 'max:255'],
            'responsible_contact' => ['nullable', 'array'],
            'responsible_contact.*' => ['nullable', 'string', 'max:50'],

            'day' => ['nullable', 'array'],
            'day.*' => ['nullable', 'string', 'max:20'],
            'start_hour' => ['nullable', 'array'],
            'start_hour.*' => ['nullable', 'string'],
            'end_hour' => ['nullable', 'array'],
            'end_hour.*' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'tags.required' => 'Cadastre pelo menos 3 tags para esta informação.',
            'tags.min' => 'Cadastre pelo menos 3 tags para esta informação.',
            'tags.*.distinct' => 'Há tags repetidas — cada tag precisa ser diferente das outras.',
            'tags.*.max' => 'Cada tag pode ter no máximo 50 caracteres.',
            'name.required' => 'O nome é obrigatório.',
            'description.required' => 'A descrição é obrigatória.',
        ];
    }
}
