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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'cpf' => ['required', 'string', 'max:11', 'unique:freelancers,cpf'],
            'pix_key' => ['nullable', 'string', 'max:255'],
            'rg' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'nacionality' => ['required', 'string'],
            'civil_status' => ['required', 'string'],
            'address' => ['required', 'string'],
            'telephone' => ['required', 'string'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
