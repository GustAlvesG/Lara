<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportSpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'spreadsheet' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'spreadsheet' => 'planilha',
        ];
    }

    public function messages(): array
    {
        return [
            'spreadsheet.mimes' => 'A planilha precisa estar no formato .xlsx.',
            'spreadsheet.max' => 'A planilha pode ter no máximo 5 MB.',
        ];
    }
}
