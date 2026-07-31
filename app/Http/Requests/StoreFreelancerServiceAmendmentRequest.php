<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesServiceSchedule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada do contrato aditivo: só o que ele pode alterar.
 *
 * Freelancer, função e data vêm do contrato base e não são aceitos aqui — se
 * viessem, o "aditivo" poderia virar outro contrato disfarçado. Preço, horas e
 * data de término continuam derivados no servidor, como no contrato comum.
 */
class StoreFreelancerServiceAmendmentRequest extends FormRequest
{
    use ValidatesServiceSchedule;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge($this->timeRules(), [
            'location' => ['required', 'string', 'max:255'],
        ]);
    }

    public function attributes(): array
    {
        return [
            'location' => 'evento/local',
            'start_time' => 'novo horário de início',
            'end_time' => 'novo horário de término',
        ];
    }
}
