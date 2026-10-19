<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Schedule;
use App\Models\Status;
use App\Models\User;

class SchedulePayment extends Model
{
    /** @use HasFactory<\Database\Factories\SchedulePaymentFactory> */
    use HasFactory;

    /**
     * Formas da baixa manual (pago fora da plataforma). O prefixo `manual_`
     * não colide com o que o site manda pela Rede (`credit`, `debit`).
     */
    public const MANUAL_METHODS = [
        'manual_cash' => 'Dinheiro',
        'manual_pix' => 'Pix direto ao clube',
        'manual_card' => 'Cartão na secretaria (maquininha)',
        'manual_transfer' => 'Transferência ou depósito',
        'manual_other' => 'Outro',
    ];

    /** Rótulos de tela de todas as formas, as da Rede e as manuais. */
    public const METHOD_LABELS = [
        'credit' => 'Cartão de crédito',
        'debit' => 'Cartão de débito',
        'credit_card' => 'Cartão de crédito',
        'debit_card' => 'Cartão de débito',
        'pix' => 'Pix',
    ] + self::MANUAL_METHODS;

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

    public function methodLabel(): string
    {
        return self::METHOD_LABELS[$this->payment_method] ?? (string) $this->payment_method;
    }

    /** Pagamento da Rede: só esses se consultam e se estornam por lá. */
    public function isOnline(): bool
    {
        return ! $this->is_manual && ! empty($this->payment_integration_id);
    }
}
