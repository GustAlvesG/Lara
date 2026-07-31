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
