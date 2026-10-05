<?php

namespace App\Http\Requests\Concerns;

use App\Models\FreelancerService;

/**
 * Validações do período trabalhado, compartilhadas entre criação e edição
 * de serviço.
 */
trait ValidatesServiceSchedule
{
    /** Regras da data + dos horários. */
    protected function scheduleRules(): array
    {
        return ['start_date' => ['required', 'date']] + $this->timeRules();
    }

    /**
     * Só os horários. O aditivo herda a data do contrato base e por isso não
     * recebe `start_date`, mas as travas do período continuam valendo.
     */
    protected function timeRules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['required', 'date_format:H:i,H:i:s'],
        ];
    }

    /**
     * Forma de cálculo do valor. Sem `pricing_mode` o contrato é por horas, o
     * padrão; `fixed` exige o `fixed_price`, que é o valor digitado. `price`
     * continua não sendo aceito como entrada.
     *
     * `$prefix` serve ao registro em massa (`services.*.`).
     */
    protected function pricingRules(string $prefix = ''): array
    {
        return [
            $prefix . 'pricing_mode' => ['nullable', 'string', 'in:' . implode(',', array_keys(FreelancerService::PRICING_MODES))],
            $prefix . 'fixed_price' => [
                'nullable',
                'required_if:' . $prefix . 'pricing_mode,' . FreelancerService::PRICING_FIXED,
                'numeric',
                'min:0.01',
                'max:' . FreelancerService::MAX_FIXED_PRICE,
            ],
        ];
    }

    /** O `required_if` padrão citaria "fixed", que não diz nada a quem preenche. */
    protected function pricingMessages(string $prefix = ''): array
    {
        return [
            $prefix . 'fixed_price.required_if' => 'Informe o valor fixo do contrato.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            // Sem os dois horários válidos, as regras de formato já reclamaram.
            if (blank($start) || blank($end) || $validator->errors()->hasAny(['start_time', 'end_time'])) {
                return;
            }

            if ($error = FreelancerService::scheduleError($start, $end)) {
                $validator->errors()->add('end_time', $error);
            }
        });
    }
}
