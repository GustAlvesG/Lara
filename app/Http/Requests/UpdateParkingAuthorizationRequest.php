<?php

namespace App\Http\Requests;

use App\Models\ParkingAuthorization;
use App\Services\ParkingAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateParkingAuthorizationRequest extends FormRequest
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
     * Mesma checagem do cadastro, ignorando o próprio registro em edição.
     */
    protected function uniquePlateRule(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $current = $this->route('parking_authorization');
            $currentId = $current instanceof ParkingAuthorization ? $current->getKey() : (int) $current;

            $exists = app(ParkingAuthorizationService::class)
                ->plateExists((string) $value, $currentId ?: null);

            if ($exists) {
                $fail('Esta placa já está cadastrada em outro registro.');
            }
        };
    }
}
