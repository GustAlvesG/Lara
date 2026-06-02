<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyWorkerRequest extends FormRequest
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
            'name'      => 'sometimes|required|string|max:255',
            'email'     => 'sometimes|required|email|max:255',
            'position'  => 'sometimes|required|string|max:255',
            'document'  => 'nullable|string|max:20',
            'telephone' => 'nullable|string|max:20',
            'image'     => 'nullable|string',
        ];
    }
}
