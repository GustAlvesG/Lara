<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CheckParkingPlateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Só a placa é validada. camera, time_entry e confidence são de auditoria e
     * passam sem regra de tipo de propósito: um 422 é tratado pelo LPR como
     * negação definitiva, então o portão pararia de abrir para todo mundo por
     * causa de um campo que nem entra na decisão de acesso.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    /**
     * Aceita a placa vinda como número ou bool sem virar 422 na regra "string".
     */
    protected function prepareForValidation(): void
    {
        $plate = $this->input('plate');

        if (is_scalar($plate) && !is_string($plate)) {
            $this->merge(['plate' => (string) $plate]);
        }
    }

    public function rules(): array
    {
        return [
            'plate'      => ['required', 'string', 'max:20'],
            'camera'     => ['nullable'],
            'time_entry' => ['nullable'],
            'confidence' => ['nullable'],
        ];
    }

    /**
     * O LPR nem sempre manda "Accept: application/json"; sem isso o Laravel
     * responderia com redirect em vez de 422.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
