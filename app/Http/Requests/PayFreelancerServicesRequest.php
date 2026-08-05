<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Baixa de pagamento pelo financeiro. A mesma tela envia a baixa individual
 * (botão da linha, campo `only`) e a baixa em lote (checkboxes, `services[]`),
 * evitando formulários aninhados dentro da tabela.
 */
class PayFreelancerServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-freelancer-payments') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'only' => ['nullable', 'integer', 'exists:freelancer_services,id'],
            'services' => ['array'],
            'services.*' => ['integer', 'exists:freelancer_services,id'],
            // De qual lote a baixa partiu, para voltar para ele.
            'batch' => ['nullable', 'integer', 'exists:freelancer_service_batches,id'],
        ];
    }

    /**
     * Para onde voltar depois da baixa: o lote de onde ela partiu, ou a lista
     * de lotes. Vai e volta um **id**, nunca uma URL — aceitar o destino pronto
     * do formulário abriria redirect aberto.
     */
    public function returnUrl(): string
    {
        return $this->filled('batch')
            ? route('freelancer-services.finance.batch', (int) $this->input('batch'))
            : route('freelancer-services.finance');
    }

    /**
     * Ids a receber baixa. O botão da linha tem precedência: quando ele é
     * usado, as caixas marcadas não entram junto.
     */
    public function serviceIds(): array
    {
        if ($this->filled('only')) {
            return [(int) $this->input('only')];
        }

        return array_map('intval', $this->input('services', []));
    }

    public function attributes(): array
    {
        return [
            'only' => 'contrato',
            'services' => 'contratos',
        ];
    }
}
