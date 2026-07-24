<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchedulePaymentRequest extends FormRequest
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
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['integer', 'exists:schedules,id'],
            'status_id' => ['required', 'integer'],
            'payment_method' => ['required', 'string'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_integration_id' => ['required', 'string'],
            'paid_at' => ['nullable'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
