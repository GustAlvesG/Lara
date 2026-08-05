<?php

namespace App\Models;

use App\Models\Concerns\CalculatesShiftPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * O cachê de um funcionário num turno.
 *
 * Trâmite:
 *
 *   coordenador solicita (em lote, com o horário PREVISTO)
 *     → gerência aprova item a item
 *       → funcionário assina informando o horário REAL
 *         → mudou o início ou o término?
 *              não  → financeiro
 *              sim  → coordenador reconfere → gerência reconfere → financeiro
 *
 * O estado é lido dos carimbos, nunca de uma coluna `status`: assim a tela e a
 * regra contam a mesma história, e não há um enum a manter sincronizado com as
 * datas.
 */
class EmployeeCache extends Model
{
    use HasFactory;

    /** Virada de meia-noite e duração do turno, comuns ao contrato de freelancer. */
    use CalculatesShiftPeriod;

    protected $table = 'employee_caches';

    protected $fillable = [
        'batch_id',
        'employee_id',
        'function_freelancer_id',
        'location',
        'description',
        'event_date',
        'expected_start_time',
        'expected_end_time',
        'expected_end_date',
        'expected_hours',
        'expected_price',
        'actual_start_time',
        'actual_end_time',
        'actual_end_date',
        'hours',
        'price',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'event_date' => 'date',
        'expected_end_date' => 'date',
        'actual_end_date' => 'date',
        'expected_hours' => 'integer',
        'hours' => 'integer',
        'expected_price' => 'decimal:2',
        'price' => 'decimal:2',
        'manager_approved_at' => 'datetime',
        'manager_rejected_at' => 'datetime',
        'employee_signed_at' => 'datetime',
        'recheck_coordinator_at' => 'datetime',
        'recheck_manager_at' => 'datetime',
        'recheck_rejected_at' => 'datetime',
        'paid' => 'boolean',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /* ---------------------------------------------------------------------
     | Relações
     |---------------------------------------------------------------------*/

    public function batch()
    {
        return $this->belongsTo(EmployeeCacheBatch::class, 'batch_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function functionFreelancer()
    {
        return $this->belongsTo(FunctionFreelancer::class);
    }

    public function managerApprovedBy()
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function managerRejectedBy()
    {
        return $this->belongsTo(User::class, 'manager_rejected_by');
    }

    public function recheckCoordinatorBy()
    {
        return $this->belongsTo(User::class, 'recheck_coordinator_by');
    }

    public function recheckManagerBy()
    {
        return $this->belongsTo(User::class, 'recheck_manager_by');
    }

    public function recheckRejectedBy()
    {
        return $this->belongsTo(User::class, 'recheck_rejected_by');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ---------------------------------------------------------------------
     | Período
     |---------------------------------------------------------------------*/

    /** Data em que o turno termina: o dia do evento, ou o seguinte na virada. */
    public static function endDateFor($eventDate, string $startTime, string $endTime): Carbon
    {
        $date = Carbon::parse($eventDate)->startOfDay();

        return self::crossesMidnight($startTime, $endTime) ? $date->addDay() : $date;
    }

    public function expectedMinutes(): int
    {
        return self::minutesBetween($this->expected_start_time, $this->expected_end_time);
    }

    public function actualMinutes(): ?int
    {
        if (blank($this->actual_start_time) || blank($this->actual_end_time)) {
            return null;
        }

        return self::minutesBetween($this->actual_start_time, $this->actual_end_time);
    }

    /** "22:00 → 02:00 (+1)" — o período como a tela mostra. */
    public function formattedExpectedPeriod(): string
    {
        return self::formatPeriod($this->expected_start_time, $this->expected_end_time);
    }

    public function formattedActualPeriod(): ?string
    {
        if (blank($this->actual_start_time) || blank($this->actual_end_time)) {
            return null;
        }

        return self::formatPeriod($this->actual_start_time, $this->actual_end_time);
    }

    private static function formatPeriod(string $start, string $end): string
    {
        return substr(self::normalizeTime($start), 0, 5)
            . ' → ' . substr(self::normalizeTime($end), 0, 5)
            . (self::crossesMidnight($start, $end) ? ' (+1)' : '');
    }

    /** Ex.: "4h30" — a duração corrida, não a faixa cobrada. */
    public static function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? "{$hours}h" : sprintf('%dh%02d', $hours, $rest);
    }

    /* ---------------------------------------------------------------------
     | Divergência
     |
     | Divergência é QUALQUER alteração do início ou do término previstos —
     | não a mudança de faixa. O que a gerência aprovou foi um turno; se o
     | turno mudou, quem aprovou precisa ver de novo, mesmo que o valor tenha
     | ficado igual.
     |---------------------------------------------------------------------*/

    public function hasDivergence(): bool
    {
        if (!$this->isSigned() || blank($this->actual_start_time) || blank($this->actual_end_time)) {
            return false;
        }

        return self::normalizeTime($this->actual_start_time) !== self::normalizeTime($this->expected_start_time)
            || self::normalizeTime($this->actual_end_time) !== self::normalizeTime($this->expected_end_time);
    }

    /** Ex.: "previsto 18:00 → 22:00 · real 18:00 → 23:30". */
    public function divergenceLabel(): ?string
    {
        if (!$this->hasDivergence()) {
            return null;
        }

        return 'previsto ' . $this->formattedExpectedPeriod() . ' · real ' . $this->formattedActualPeriod();
    }

    /** Quanto o valor mudou por causa da divergência. Zero quando só o horário mudou de faixa igual. */
    public function priceDifference(): float
    {
        return (float) ($this->price ?? 0) - (float) $this->expected_price;
    }

    /* ---------------------------------------------------------------------
     | Estado
     |---------------------------------------------------------------------*/

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isManagerApproved(): bool
    {
        return $this->manager_approved_at !== null;
    }

    public function isManagerRejected(): bool
    {
        return $this->manager_rejected_at !== null && !$this->isManagerApproved();
    }

    public function isSigned(): bool
    {
        return $this->employee_signed_at !== null;
    }

    public function isRecheckRejected(): bool
    {
        return $this->recheck_rejected_at !== null;
    }

    public function isPaid(): bool
    {
        return (bool) $this->paid;
    }

    /** Enquanto o lote é rascunho, o coordenador ainda mexe na solicitação. */
    public function canBeEdited(): bool
    {
        return !$this->isCancelled() && ($this->batch?->isDraft() ?? false);
    }

    /**
     * O funcionário assina o que a gerência já aprovou — e uma vez só. Antes da
     * aprovação não há o que assinar: o turno pode nem acontecer.
     */
    public function canBeSignedByEmployee(): bool
    {
        return !$this->isCancelled()
            && $this->isManagerApproved()
            && !$this->isSigned();
    }

    /** Divergiu e o coordenador ainda não reconferiu. */
    public function awaitsCoordinatorRecheck(): bool
    {
        return $this->hasDivergence()
            && !$this->isCancelled()
            && !$this->isRecheckRejected()
            && $this->recheck_coordinator_at === null;
    }

    /** O coordenador reconferiu; falta a gerência. */
    public function awaitsManagerRecheck(): bool
    {
        return $this->hasDivergence()
            && !$this->isCancelled()
            && !$this->isRecheckRejected()
            && $this->recheck_coordinator_at !== null
            && $this->recheck_manager_at === null;
    }

    /**
     * Pronto para o financeiro: aprovado pela gerência, assinado e — só quando
     * o horário mudou — reconferido pelo coordenador e pela gerência.
     */
    public function isPayable(): bool
    {
        if ($this->isCancelled() || $this->isRecheckRejected()) {
            return false;
        }

        if (!$this->isManagerApproved() || !$this->isSigned()) {
            return false;
        }

        if (!$this->hasDivergence()) {
            return true;
        }

        return $this->recheck_coordinator_at !== null && $this->recheck_manager_at !== null;
    }

    public function canBePaid(): bool
    {
        return $this->isPayable() && !$this->isPaid();
    }

    /** Rótulo do trâmite, para exibição. */
    public function statusLabel(): string
    {
        return match (true) {
            $this->isCancelled() => 'Cancelado',
            $this->isPaid() => 'Pago',
            $this->isRecheckRejected() => 'Recusado na reconferência',
            $this->isManagerRejected() => 'Recusado pela gerência',
            $this->isPayable() => 'Liberado para o financeiro',
            $this->awaitsManagerRecheck() => 'Aguardando reconferência da gerência',
            $this->awaitsCoordinatorRecheck() => 'Aguardando reconferência do coordenador',
            $this->isSigned() => 'Assinado',
            $this->isManagerApproved() => 'Aguardando assinatura do funcionário',
            $this->batch?->isDraft() => 'Em solicitação (rascunho)',
            default => 'Aguardando aprovação da gerência',
        };
    }

    /* ---------------------------------------------------------------------
     | Escopos
     |---------------------------------------------------------------------*/

    /** O que o funcionário vê na tela de assinatura. */
    public function scopeAwaitingSignature($query)
    {
        return $query->whereNotNull('manager_approved_at')
            ->whereNull('employee_signed_at')
            ->whereNull('cancelled_at');
    }

    /**
     * Peneira grossa das filas de reconferência: assinados, sem recusa e com
     * alguma etapa pendente. A divergência em si é conferida em PHP — data e
     * hora moram em colunas separadas, e comparar horário no SQL muda de MySQL
     * para SQLite.
     */
    public function scopeSignedPendingRecheck($query)
    {
        return $query->whereNotNull('employee_signed_at')
            ->whereNull('cancelled_at')
            ->whereNull('recheck_rejected_at')
            ->where(fn($q) => $q->whereNull('recheck_coordinator_at')->orWhereNull('recheck_manager_at'));
    }

    /**
     * Candidatos do financeiro. O `isPayable()` decide de verdade — aqui só se
     * evita trazer o que nem chegou perto.
     */
    public function scopeAwaitingFinance($query)
    {
        return $query->whereNotNull('manager_approved_at')
            ->whereNotNull('employee_signed_at')
            ->whereNull('cancelled_at')
            ->whereNull('recheck_rejected_at');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($term)) . '%';
        $digits = preg_replace('/\D/', '', $term);

        return $query->where(function ($q) use ($like, $digits) {
            $q->where('location', 'like', $like)
                ->orWhereHas('employee', function ($e) use ($like, $digits) {
                    $e->where('name', 'like', $like)
                        ->orWhere('employee_code', 'like', $like);

                    if ($digits !== '') {
                        $e->orWhere('cpf', 'like', '%' . $digits . '%');
                    }
                });
        });
    }
}
