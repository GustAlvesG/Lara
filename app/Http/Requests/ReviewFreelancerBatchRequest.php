<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Análise de um lote pela gerência. A tela manda uma decisão por contrato:
 * `decisions[{id}][decision]` = approve|reject e, na recusa, um motivo.
 */
class ReviewFreelancerBatchRequest extends FormRequest
{
    /** Só o coordenador do setor Gerência aprova lote (ver BatchController). */
    public function authorize(): bool
    {
        return $this->user()?->isManagementCoordinator() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decisions' => ['required', 'array'],
            'decisions.*.decision' => ['required', 'in:approve,reject'],
            // O motivo só é exigido de quem recusa — é o que volta para o coordenador.
            'decisions.*.reason' => ['nullable', 'string', 'max:255', 'required_if:decisions.*.decision,reject'],
        ];
    }

    /**
     * Decisões indexadas pelo id do contrato.
     *
     * @return array<int, array{decision: string, reason: string|null}>
     */
    public function decisions(): array
    {
        $decisions = [];

        foreach ($this->input('decisions', []) as $serviceId => $entry) {
            $decisions[(int) $serviceId] = [
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
