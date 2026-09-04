<?php

namespace App\Models\Fleet;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class FleetTrip extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELED = 'canceled';

    public const STATUS_LABELS = [
        self::STATUS_OPEN     => 'Em rota',
        self::STATUS_CLOSED   => 'Concluída',
        self::STATUS_CANCELED => 'Cancelada',
    ];

    protected $fillable = [
        'fleet_vehicle_id',
        'employee_id',
        'driver_name',
        'driver_document',
        'destination',
        'departure_odometer',
        'departure_at',
        'departure_operator',
        'departure_registered_by',
        'departure_obs',
        'return_odometer',
        'return_at',
        'return_operator',
        'return_registered_by',
        'return_obs',
        'distance_km',
        'status',
        'canceled_at',
        'cancel_reason',
        'odometer_alert',
    ];

    protected $casts = [
        'departure_at'       => 'datetime',
        'return_at'          => 'datetime',
        'canceled_at'        => 'datetime',
        'departure_odometer' => 'integer',
        'return_odometer'    => 'integer',
        'distance_km'        => 'integer',
        'odometer_alert'     => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    /** Preenchido só quando o motorista digitado bateu com um funcionário. */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** Minutos entre a saída e o retorno — ou até agora, se ainda está fora. */
    public function durationMinutes(): ?int
    {
        if (!$this->departure_at) {
            return null;
        }

        return (int) $this->departure_at->diffInMinutes($this->return_at ?? now());
    }

    /**
     * Formato único de viagem para a API e para as telas. A portaria consome
     * este payload tanto na resposta do registro quanto na listagem, então ele
     * precisa ser o mesmo nos dois lugares.
     */
    public function toApiArray(): array
    {
        $vehicle = $this->relationLoaded('vehicle') ? $this->vehicle : $this->vehicle()->first();

        return [
            'id'           => $this->id,
            'status'       => $this->status,
            'status_label' => $this->statusLabel(),
            'vehicle'      => $vehicle ? [
                'id'    => $vehicle->id,
                'name'  => $vehicle->name,
                'plate' => $vehicle->plate,
            ] : null,
            'driver' => [
                'name'          => $this->driver_name,
                'document'      => $this->driver_document,
                'employee_id'   => $this->employee_id,
                'employee_code' => $this->employee?->employee_code,
            ],
            'destination' => $this->destination,
            'departure'   => [
                'odometer' => $this->departure_odometer,
                'at'       => optional($this->departure_at)->toIso8601String(),
                'at_human' => optional($this->departure_at)->format('d/m/Y H:i'),
                'operator' => $this->departure_operator,
                'obs'      => $this->departure_obs,
            ],
            'return' => $this->return_at ? [
                'odometer' => $this->return_odometer,
                'at'       => $this->return_at->toIso8601String(),
                'at_human' => $this->return_at->format('d/m/Y H:i'),
                'operator' => $this->return_operator,
                'obs'      => $this->return_obs,
            ] : null,
            'distance_km'      => $this->distance_km,
            'duration_minutes' => $this->durationMinutes(),
            'odometer_alert'   => $this->odometer_alert,
            'canceled_at'      => optional($this->canceled_at)->toIso8601String(),
            'cancel_reason'    => $this->cancel_reason,
        ];
    }
}
