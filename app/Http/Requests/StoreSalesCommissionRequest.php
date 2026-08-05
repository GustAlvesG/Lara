<?php

namespace App\Http\Requests;

use App\Models\FreelancerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entrada da comissão de venda: o critério e o valor vendido no turno.
 *
 * Nada mais é aceito — freelancer, função, dia, período e local vêm do contrato
 * base, e a comissão em si é calculada no servidor a partir destes dois campos.
 */
class StoreSalesCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(array_keys(FreelancerService::COMMISSION_METHODS))],
            // O teto é trava contra o zero a mais digitado no tablet, não uma
            // regra de negócio: um turno não vende R$ 1 milhão.
            'sales_amount' => ['required', 'numeric', 'min:0.01', 'max:' . FreelancerService::MAX_SALES_AMOUNT],
            // Parâmetros da apuração no MultiVendas. Opcionais: sem eles a
            // comissão sai com o valor informado e sem relatório anexo — é o
            // caminho de quando o MultiVendas está fora do ar.
            'login' => ['nullable', 'string', 'max:100', 'required_with:from,to'],
            'from' => ['nullable', 'date', 'required_with:login'],
            'to' => ['nullable', 'date', 'required_with:login', 'after:from'],
        ];
    }

    public function attributes(): array
    {
        return [
            'method' => 'critério da comissão',
            'sales_amount' => 'valor vendido',
            'login' => 'login do vendedor',
            'from' => 'início do período apurado',
            'to' => 'fim do período apurado',
        ];
    }

    public function messages(): array
    {
        return [
            'sales_amount.max' => 'O valor vendido parece alto demais. Confira antes de gerar a comissão.',
            'sales_amount.min' => 'Informe o valor vendido no turno.',
        ];
    }
}
