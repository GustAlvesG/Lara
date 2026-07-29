<?php

namespace App\Http\Requests;

use App\Services\ParkingAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

class StoreParkingAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plate'           => ['required', 'string', 'max:20', $this->uniquePlateRule()],
            'name'            => ['required', 'string', 'max:255'],
            'expiration_date' => ['required', 'date'],
        ];
    }

    /**
     * Compara na forma normalizada (sem hífen/espaço/ponto e em maiúsculas),
     * que é como a placa é gravada.
     */
    protected function uniquePlateRule(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            if (app(ParkingAuthorizationService::class)->plateExists((string) $value)) {
                $fail('Esta placa já está cadastrada.');
            }
        };
    }
}
