<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesServiceSchedule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFreelancerServiceRequest extends FormRequest
{
    use ValidatesServiceSchedule;

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
     * `total_hours`, `end_date` e `price` não são aceitos como entrada: são
     * derivados de start_time/end_time e do valor da função.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge($this->scheduleRules(), [
            'freelancer_id' => ['required', 'integer', 'exists:freelancers,id'],
            'function_freelancer_id' => ['required', 'integer', 'exists:function_freelancers,id'],
            'location' => ['required', 'string', 'max:255'],
            'status_id' => ['nullable', 'integer', 'exists:status,id'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    public function attributes(): array
    {
        return [
            'location' => 'evento/local',
            'freelancer_id' => 'freelancer',
            'function_freelancer_id' => 'função',
            'start_date' => 'data de início',
            'start_time' => 'horário de início',
            'end_time' => 'horário de término',
        ];
    }
}
