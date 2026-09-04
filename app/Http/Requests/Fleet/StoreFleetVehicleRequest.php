<?php

namespace App\Http\Requests\Fleet;

use App\Models\Fleet\FleetVehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFleetVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255', Rule::unique('fleet_vehicles', 'name')->ignore($this->vehicleId())],
            'plate'            => ['nullable', 'string', 'max:20', $this->uniquePlateRule()],
            'description'      => ['nullable', 'string', 'max:255'],
            'current_odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'active'           => ['nullable', 'boolean'],
        ];
    }

    protected function vehicleId(): ?int
    {
        return $this->route('vehicle')?->id;
    }

    /**
     * A placa é comparada normalizada (sem hífen e em maiúsculas), que é como
     * ela é gravada — senão "ABC-1234" entraria ao lado de "ABC1234".
     */
    protected function uniquePlateRule(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $plate = FleetVehicle::normalizePlate($value);

            if ($plate === '') {
                return;
            }

            $exists = FleetVehicle::where('plate', $plate)
                ->when($this->vehicleId(), fn ($query, $id) => $query->where('id', '!=', $id))
                ->exists();

            if ($exists) {
                $fail('Esta placa já está cadastrada em outro veículo.');
            }
        };
    }
}
