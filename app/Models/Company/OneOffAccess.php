<?php

namespace App\Models\Company;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Liberação pontual de acesso: uma entrada, no dia em que foi criada, para
 * quem não tem vínculo com empresa parceira, Uber ou contrato de freelancer.
 *
 * Não há estado gravado além dos carimbos: disponível é "de hoje, sem uso e
 * sem cancelamento". O dia virou, ela vence sozinha — sem job de expiração.
 */
class OneOffAccess extends Model
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_USED = 'used';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LABELS = [
        self::STATUS_AVAILABLE => 'Disponível',
        self::STATUS_USED => 'Utilizada',
        self::STATUS_CANCELED => 'Cancelada',
        self::STATUS_EXPIRED => 'Vencida',
    ];

    protected $fillable = [
        'cpf',
        'name',
        'reason',
        'image',
        'access_date',
        'used_at',
        'canceled_at',
        'canceled_by_user',
        'created_by_user',
    ];

    protected $casts = [
        'access_date' => 'date',
        'used_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user');
    }

    public function canceler()
    {
        return $this->belongsTo(User::class, 'canceled_by_user');
    }

    public function accessLogs()
    {
        return $this->hasMany(CompanyAccessLog::class, 'one_off_access_id');
    }

    public function scopeForCpf(Builder $query, string $cpf): Builder
    {
        return $query->where('cpf', preg_replace('/\D/', '', $cpf));
    }

    public function scopeOnDate(Builder $query, $date): Builder
    {
        return $query->whereDate('access_date', Carbon::parse($date)->toDateString());
    }

    /** Ainda pode liberar uma entrada — desde que seja do dia. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('used_at')->whereNull('canceled_at');
    }

    public function status(?Carbon $now = null): string
    {
        $now ??= now();

        return match (true) {
            $this->canceled_at !== null => self::STATUS_CANCELED,
            $this->used_at !== null => self::STATUS_USED,
            !$this->access_date->isSameDay($now) => self::STATUS_EXPIRED,
            default => self::STATUS_AVAILABLE,
        };
    }

    public function statusLabel(?Carbon $now = null): string
    {
        return self::STATUS_LABELS[$this->status($now)];
    }

    public function isAvailable(?Carbon $now = null): bool
    {
        return $this->status($now) === self::STATUS_AVAILABLE;
    }

    public function formattedCpf(): string
    {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $this->cpf);
    }

    /**
     * A foto mora em `public/images`, junto das de terceirizados e
     * freelancers — não em `Storage::disk('public')`, que neste projeto grava
     * onde nenhuma URL alcança.
     */
    public function imageUrl(): ?string
    {
        return filled($this->image) ? asset('images/' . $this->image) : null;
    }
}
