<?php

namespace App\Http\Requests;

use App\Models\Freelancer;
use App\Models\FreelancerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

/**
 * Registro em massa de serviços pelo painel — várias linhas de uma vez, sem
 * planilha. Mesmas regras do registro individual, aplicadas linha a linha, e o
 * erro aponta o número da linha para quem preencheu achar onde corrigir.
 *
 * `total_hours`, `end_date` e `price` continuam sendo derivados no servidor.
 */
class StoreFreelancerServicesBulkRequest extends FormRequest
{
    /** Teto de linhas por envio — segura tanto a tela quanto o POST. */
    public const MAX_ROWS = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'services' => ['required', 'array', 'min:1', 'max:' . self::MAX_ROWS],
            'services.*.freelancer_id' => ['required', 'integer', 'exists:freelancers,id'],
            'services.*.function_freelancer_id' => ['required', 'integer', 'exists:function_freelancers,id'],
            'services.*.location' => ['required', 'string', 'max:255'],
            'services.*.start_date' => ['required', 'date'],
            'services.*.start_time' => ['required', 'date_format:H:i,H:i:s'],
            'services.*.end_time' => ['required', 'date_format:H:i,H:i:s'],
        ];
    }

    public function attributes(): array
    {
        $labels = [
            'freelancer_id' => 'freelancer',
            'function_freelancer_id' => 'função',
            'location' => 'evento/local',
            'start_date' => 'data',
            'start_time' => 'horário de início',
            'end_time' => 'horário de término',
        ];

        // "linha 3: horário de término" lê melhor que "services.2.end_time".
        $attributes = [];

        foreach (array_keys((array) $this->input('services', [])) as $index) {
            foreach ($labels as $field => $label) {
                $attributes["services.{$index}.{$field}"] = 'linha ' . ((int) $index + 1) . ': ' . $label;
            }
        }

        return $attributes;
    }

    public function messages(): array
    {
        return [
            'services.required' => 'Adicione ao menos uma linha para registrar.',
            'services.max' => 'São no máximo ' . self::MAX_ROWS . ' linhas por envio.',
        ];
    }

    /**
     * O que só dá para conferir com a linha inteira em mãos: a duração do turno
     * e a completude do cadastro do freelancer. Ambas já barram o registro
     * individual e a importação por planilha.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $rows = (array) $this->input('services', []);
            $freelancers = $this->freelancersById($rows);

            foreach ($rows as $index => $row) {
                $line = (int) $index + 1;

                $this->checkSchedule($validator, $index, $line, $row);
                $this->checkFreelancer($validator, $index, $line, $row, $freelancers);
            }
        });
    }

    private function checkSchedule($validator, $index, int $line, array $row): void
    {
        $start = $row['start_time'] ?? null;
        $end = $row['end_time'] ?? null;

        // Sem os dois horários válidos, as regras de formato já reclamaram.
        if (blank($start) || blank($end)
            || $validator->errors()->hasAny(["services.{$index}.start_time", "services.{$index}.end_time"])) {
            return;
        }

        if ($error = FreelancerService::scheduleError($start, $end)) {
            $validator->errors()->add("services.{$index}.end_time", 'Linha ' . $line . ': ' . $error);
        }
    }

    private function checkFreelancer($validator, $index, int $line, array $row, Collection $freelancers): void
    {
        $freelancer = $freelancers->get((int) ($row['freelancer_id'] ?? 0));

        if (!$freelancer || $freelancer->hasCompleteContractData()) {
            return;
        }

        $validator->errors()->add(
            "services.{$index}.freelancer_id",
            'Linha ' . $line . ': cadastro de ' . $freelancer->name . ' incompleto: faltam '
                . implode(', ', $freelancer->missingContractFieldLabels())
                . '. Complete o cadastro antes de gerar o contrato.'
        );
    }

    /** Uma consulta só para todas as linhas, em vez de uma por linha. */
    private function freelancersById(array $rows): Collection
    {
        $ids = collect($rows)->pluck('freelancer_id')->filter()->unique()->values();

        return $ids->isEmpty() ? collect() : Freelancer::whereIn('id', $ids)->get()->keyBy('id');
    }
}
