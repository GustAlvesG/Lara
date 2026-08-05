<?php

namespace App\Http\Requests;

use App\Models\EmployeeCache;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Assinatura do funcionário: o horário que ele de fato cumpriu e o traço
 * desenhado na tela.
 *
 * Não há senha nem PIN — o funcionário não é usuário do sistema. Quem ele é já
 * foi resolvido na entrada da tela (matrícula ou CPF), e a autorização de quem
 * assina o quê é conferida no controller contra a sessão da assinatura.
 */
class SignEmployeeCacheRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['required', 'date_format:H:i,H:i:s'],
            'signature' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'start_time' => 'horário de início',
            'end_time' => 'horário de término',
            'signature' => 'assinatura',
        ];
    }

    public function messages(): array
    {
        return [
            'signature.required' => 'Desenhe a assinatura antes de confirmar.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            if (blank($start) || blank($end)) {
                return;
            }

            if (EmployeeCache::minutesBetween($start, $end) === 0) {
                $validator->errors()->add('end_time', 'O horário de término deve ser diferente do de início.');
            }
        });
    }
}
