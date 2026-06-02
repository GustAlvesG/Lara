<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyAccessRulesRequest extends FormRequest
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
            'type'        => 'sometimes|required|in:include,exclude',
            'start_date'  => 'sometimes|required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'start_time'  => 'nullable|date_format:H:i',
            'end_time'    => 'nullable|date_format:H:i',
            'days'        => 'nullable|array',
            'days.*'      => 'integer|exists:weekdays,id',
            'description' => 'nullable|string|max:500',
        ];
    }
}
