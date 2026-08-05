<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Análise de uma solicitação de cachê pela gerência: uma decisão por linha,
 * `decisions[{id}][decision]` = approve|reject, e o motivo na recusa.
 */
class ReviewEmployeeCacheBatchRequest extends FormRequest
{
    /** Só o coordenador do setor Gerência aprova cachê. */
    public function authorize(): bool
    {
        return $this->user()?->isManagementCoordinator() ?? false;
    }

    public function rules(): array
    {
        return [
            'decisions' => ['required', 'array'],
            'decisions.*.decision' => ['required', 'in:approve,reject'],
            'decisions.*.reason' => ['nullable', 'string', 'max:255', 'required_if:decisions.*.decision,reject'],
        ];
    }

    /**
     * Decisões indexadas pelo id do cachê.
     *
     * @return array<int, array{decision: string, reason: string|null}>
     */
    public function decisions(): array
    {
        $decisions = [];

        foreach ($this->input('decisions', []) as $cacheId => $entry) {
            $decisions[(int) $cacheId] = [
                'decision' => $entry['decision'] ?? 'approve',
                'reason' => isset($entry['reason']) ? trim((string) $entry['reason']) ?: null : null,
            ];
        }

        return $decisions;
    }

    public function messages(): array
    {
        return [
            'decisions.*.reason.required_if' => 'Informe o motivo da recusa.',
            'decisions.required' => 'Nenhuma decisão foi enviada.',
        ];
    }
}
