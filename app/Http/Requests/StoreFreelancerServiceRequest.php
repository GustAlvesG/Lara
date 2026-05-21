<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFreelancerServiceRequest extends FormRequest
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
            'freelancer_id' => ['required', 'integer', 'exists:freelancers,id'],
            'function_freelancer_id' => ['required', 'integer', 'exists:function_freelancers,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'price' => ['required', 'decimal'],
            'total_hours' => ['required', 'integer'],

        ];
    }
}
