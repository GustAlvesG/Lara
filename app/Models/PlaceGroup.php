<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Place;
use App\Models\ScheduleRules;
use App\Models\Weekday;

class PlaceGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name', 
        'category', 
        'image_vertical', 
        'image_horizontal', 
        'status',
        'vertices',
        'icon',
        'minimum_antecedence',
        'maximum_antecedence',
        'duration',
        'interval',
        'daily_limit',
        'start_time',
        'end_time',
        'start_time_sales',
        'end_time_sales',
        'social_payment_days',
        'social_limit_count',
        'social_limit_days',
        'social_active_limit',
        'social_rules',
    ];

    /** Quadras e afins: reserva por horário, paga na hora. */
    public const CATEGORY_SPORT = 'esportiva';

    /**
     * Churrasqueiras, salões, áreas de festa: reserva do dia inteiro, com
     * pré-reserva, análise da equipe e pagamento depois (SocialBookingService).
     */
    public const CATEGORY_SOCIAL = 'social';

    /** @var array<string, string> valor => rótulo */
    public const CATEGORIES = [
        self::CATEGORY_SPORT => 'Esportiva',
        self::CATEGORY_SOCIAL => 'Social',
    ];

    /** Prazo para pagar, em dias, quando o grupo social não define um. */
    public const DEFAULT_SOCIAL_PAYMENT_DAYS = 7;

    public function places()
    {
        return $this->hasMany(Place::class);
    }

    public function isSocial(): bool
    {
        return $this->category === self::CATEGORY_SOCIAL;
    }

    /** Dias para pagar depois da aprovação (o grupo pode deixar em branco). */
    public function socialPaymentDays(): int
    {
        return (int) ($this->social_payment_days ?: self::DEFAULT_SOCIAL_PAYMENT_DAYS);
    }

    public function scopeSocial($query)
    {
        return $query->where('category', self::CATEGORY_SOCIAL);
    }

    /**
     * Tudo o que não é social. Grupo sem categoria sempre foi quadra — a
     * migration de 14/10/2026 preencheu os vazios, mas o filtro não depende disso.
     */
    public function scopeSport($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('category')->orWhere('category', '!=', self::CATEGORY_SOCIAL);
        });
    }

    public function weekdays()
    {
        return $this->belongsToMany(Weekday::class, 'week_days_place_group', 'place_group_id', 'weekday_id')
            ->withPivot('id')
            ->withTimestamps();
    }
}
