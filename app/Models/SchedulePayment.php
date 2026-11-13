<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Schedule;
use App\Models\Status;
use App\Models\User;
use App\Models\Concerns\FinancePayment;
use App\Services\Finance\PaymentMethods;

class SchedulePayment extends Model
{
    /** @use HasFactory<\Database\Factories\SchedulePaymentFactory> */
    use HasFactory;
    use FinancePayment;

    /**
     * Apelidos de App\Services\Finance\PaymentMethods (a lista mora lá desde
     * o módulo Financeiro, 13/11/2026). Código novo: use PaymentMethods.
     */
    public const MANUAL_METHODS = PaymentMethods::MANUAL;

    public const METHOD_LABELS = PaymentMethods::LABELS;

    // `is_manual`, `recorded_by` e `manual_notes` ficam fora do fillable de
    // propósito: o POST /api/schedule/payment do site cria pagamento com o
    // que validou, e baixa manual não pode nascer por ali.
    protected $fillable = [
        'payment_method',
        'paid_amount',
        'paid_at',
        'payment_integration_id',
        'status_id',
        'refunded_amount',
        'refunded_by',
        'refunded_at',
    ];

    protected $casts = [
        'paid_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'is_manual' => 'boolean',
    ];

    //One Payment can be for many Schedules
    public function schedule()
    {
        return $this->hasMany(Schedule::class, 'schedule_payment_id', 'id');
    }

    //Alias mais explícito, preferir em código novo (schedule() é hasMany apesar do nome singular)
    public function schedules()
    {
        return $this->schedule();
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }

    public function refunder()
    {
        return $this->belongsTo(User::class, 'refunded_by', 'id');
    }

    /** Quem deu a baixa manual. */
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by', 'id');
    }

    /** Estorno parcial mantém o status; o total marca cancelado (0). */
    protected function markRefundStatus(bool $fully): void
    {
        if ($fully) {
            $this->status_id = 0;
        }
    }
}
