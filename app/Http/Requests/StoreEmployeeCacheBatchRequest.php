<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\EmployeeCache;
use App\Models\FunctionFreelancer;
use App\Support\EmployeeScope;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitação de cachês em lote pelo coordenador — várias linhas de uma vez,
 * cada uma com um funcionário, a função e o horário **previsto**.
 *
 * O horário real não é pedido aqui: quem o informa é o funcionário, ao assinar.
 * Pedir os dois na solicitação transformaria a assinatura em formalidade.
 */
class StoreEmployeeCacheBatchRequest extends FormRequest
{
    /** Teto de linhas por envio — segura a tela e o POST. */
    public const MAX_ROWS = 100;

    /** Solicitar cachê é atribuição de coordenador de setor. */
    public function authorize(): bool
    {
        return $this->user()?->isCoordinator() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'caches' => ['required', 'array', 'min:1', 'max:' . self::MAX_ROWS],
            'caches.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'caches.*.function_freelancer_id' => ['required', 'integer', 'exists:function_freelancers,id'],
            'caches.*.location' => ['required', 'string', 'max:255'],
            'caches.*.description' => ['nullable', 'string', 'max:2000'],
            'caches.*.event_date' => ['required', 'date'],
            'caches.*.start_time' => ['required', 'date_format:H:i,H:i:s'],
            'caches.*.end_time' => ['required', 'date_format:H:i,H:i:s'],
        ];
    }

    public function attributes(): array
    {
        $labels = [
            'employee_id' => 'funcionário',
            'function_freelancer_id' => 'função',
            'location' => 'evento/local',
            'description' => 'observação',
            'event_date' => 'data',
            'start_time' => 'horário previsto de início',
            'end_time' => 'horário previsto de término',
        ];

        $attributes = [];

        foreach (array_keys((array) $this->input('caches', [])) as $index) {
            foreach ($labels as $field => $label) {
                $attributes["caches.{$index}.{$field}"] = 'linha ' . ((int) $index + 1) . ': ' . $label;
            }
        }

        return $attributes;
    }

    public function messages(): array
    {
        return [
            'caches.required' => 'Adicione ao menos uma linha para solicitar.',
            'caches.max' => 'São no máximo ' . self::MAX_ROWS . ' linhas por solicitação.',
        ];
    }

    /**
     * O que só se confere com a linha inteira em mãos: o turno precisa ter
     * duração, a função precisa ser de cachê e com as faixas cadastradas, e o
     * funcionário precisa estar entre os que este coordenador responde.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $rows = (array) $this->input('caches', []);
            $access = EmployeeScope::for($this->user());
            $allowed = EmployeeScope::apply(Employee::query(), $access)->pluck('id')->all();
            $functions = FunctionFreelancer::with('cacheRates')
                ->whereIn('id', array_filter(array_column($rows, 'function_freelancer_id')))
                ->get()
                ->keyBy('id');

            foreach ($rows as $index => $row) {
                $line = (int) $index + 1;

                if (filled($row['start_time'] ?? null) && filled($row['end_time'] ?? null)
                    && EmployeeCache::minutesBetween($row['start_time'], $row['end_time']) === 0) {
                    $validator->errors()->add(
                        "caches.{$index}.end_time",
                        "Linha {$line}: o horário de término deve ser diferente do de início."
                    );
                }

                $function = $functions->get((int) ($row['function_freelancer_id'] ?? 0));

                if ($function && !$function->isCache()) {
                    $validator->errors()->add(
                        "caches.{$index}.function_freelancer_id",
                        "Linha {$line}: \"{$function->name}\" é uma função de freelancer, não de cachê."
                    );
                } elseif ($function && !$function->hasCompleteCacheRates()) {
                    $validator->errors()->add(
                        "caches.{$index}.function_freelancer_id",
                        "Linha {$line}: a função \"{$function->name}\" está com faixas de valor incompletas."
                    );
                }

                if (isset($row['employee_id']) && !in_array((int) $row['employee_id'], $allowed, true)) {
                    $validator->errors()->add(
                        "caches.{$index}.employee_id",
                        "Linha {$line}: este funcionário não pertence a um setor que você coordena."
                    );
                }
            }
        });
    }

    /** @return array<int, array> */
    public function rows(): array
    {
        return array_map(fn($row) => [
            'employee_id' => (int) $row['employee_id'],
            'function_freelancer_id' => (int) $row['function_freelancer_id'],
            'location' => $row['location'],
            'description' => $row['description'] ?? null,
            'event_date' => $row['event_date'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
        ], array_values((array) $this->validated()['caches']));
    }
}
