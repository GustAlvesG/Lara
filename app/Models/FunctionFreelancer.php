<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Catálogo de funções, em duas modalidades que **não se misturam**:
 *
 * | Tipo         | Quem exerce         | Como o valor é calculado                    |
 * |--------------|---------------------|---------------------------------------------|
 * | `freelancer` | pessoa externa      | preço por bloco de 15 min, proporcional     |
 * | `cache`      | funcionário da casa | valor fixo da faixa de horas, sem proporção |
 *
 * O tipo é exclusivo de propósito: "Garçom Freelancer" e "Garçom Cachê" são
 * dois cadastros. Um único registro servindo aos dois teria de carregar as
 * duas tabelas de preço, e um erro de seleção na tela pagaria um pelo outro.
 */
class FunctionFreelancer extends Model
{
    /** @use HasFactory<\Database\Factories\FunctionFreelancerFactory> */
    use HasFactory;

    const TYPE_FREELANCER = 'freelancer';
    const TYPE_CACHE = 'cache';

    const TYPES = [
        self::TYPE_FREELANCER => 'Freelancer (por bloco de 15 min)',
        self::TYPE_CACHE => 'Cachê (valor fixo por faixa de horas)',
    ];

    /**
     * Faixas de cachê possíveis: de 2 a 11 horas.
     *
     * Abaixo de 2h paga-se a faixa de 2h (é o mínimo acordado); de 11h em
     * diante, a faixa de 11h — o turno pode esticar, o valor não.
     */
    const CACHE_MIN_HOURS = 2;
    const CACHE_MAX_HOURS = 11;

    /**
     * Tolerância do arredondamento da faixa, em minutos: somam-se 15 minutos à
     * duração e toma-se a hora cheia do resultado. Assim 3h45 já paga a faixa
     * de 4h, e 3h44 continua na de 3h.
     */
    const CACHE_ROUNDING_MINUTES = 15;

    protected $table = 'function_freelancers';

    protected $fillable = [
        'name',
        'description',
        'type',
        'price',
        'created_by',
        'updated_by',
    ];

    /** Espelha o default da coluna: cadastro antigo e novo nascem freelancer. */
    protected $attributes = [
        'type' => self::TYPE_FREELANCER,
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function freelancerServices()
    {
        return $this->hasMany(FreelancerService::class);
    }

    /** Cachês lançados com esta função. */
    public function employeeCaches()
    {
        return $this->hasMany(EmployeeCache::class);
    }

    /** Faixas de valor do cachê (2h a 11h). Vazia nas funções de freelancer. */
    public function cacheRates()
    {
        return $this->hasMany(FunctionCacheRate::class)->orderBy('hours');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /* ---------------------------------------------------------------------
     | Tipo
     |---------------------------------------------------------------------*/

    public function isCache(): bool
    {
        return $this->type === self::TYPE_CACHE;
    }

    public function isFreelancer(): bool
    {
        return !$this->isCache();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? (string) $this->type;
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Trocar o tipo de uma função já usada mudaria a conta de lançamentos que
     * já existem — o caminho é cadastrar outra função.
     */
    public function canChangeType(): bool
    {
        return !$this->freelancerServices()->exists() && !$this->employeeCaches()->exists();
    }

    /* ---------------------------------------------------------------------
     | Valor do cachê
     |
     | Não há proporção: a duração do turno diz apenas em qual FAIXA o cachê
     | cai, e a faixa tem seu próprio valor de tabela. Um turno de 5h pode
     | valer mais (ou menos) que 2,5 × a faixa de 2h — quem decide é a tabela.
     |---------------------------------------------------------------------*/

    /**
     * Faixa de horas que um turno de $minutes minutos paga.
     *
     * Arredondamento: somam-se 15 minutos e toma-se a hora cheia do resultado
     * (3h45 → 4h; 3h44 → 3h). Depois vêm o piso de 2h e o teto de 11h.
     */
    public static function cacheBilledHours(int $minutes): int
    {
        $hours = intdiv(max(0, $minutes) + self::CACHE_ROUNDING_MINUTES, 60);

        return max(self::CACHE_MIN_HOURS, min(self::CACHE_MAX_HOURS, $hours));
    }

    /** As 10 faixas que uma função de cachê precisa ter preenchidas. */
    public static function cacheHourRange(): array
    {
        return range(self::CACHE_MIN_HOURS, self::CACHE_MAX_HOURS);
    }

    /**
     * Valor da faixa de $hours horas, ou null quando a faixa não foi cadastrada
     * — cachê com faixa faltando não é lançado, e a tela avisa antes.
     */
    public function priceForHours(int $hours): ?string
    {
        $rate = $this->relationLoaded('cacheRates')
            ? $this->cacheRates->firstWhere('hours', $hours)
            : $this->cacheRates()->where('hours', $hours)->first();

        return $rate?->price;
    }

    /** Valor do cachê para um turno de $minutes minutos. */
    public function priceForMinutes(int $minutes): ?string
    {
        return $this->priceForHours(self::cacheBilledHours($minutes));
    }

    /** Mapa faixa => valor, para preencher o formulário. */
    public function ratesByHour(): Collection
    {
        return $this->cacheRates->pluck('price', 'hours');
    }

    /** Toda faixa de 2h a 11h está cadastrada? */
    public function hasCompleteCacheRates(): bool
    {
        if (!$this->isCache()) {
            return true;
        }

        $hours = $this->relationLoaded('cacheRates')
            ? $this->cacheRates->pluck('hours')->all()
            : $this->cacheRates()->pluck('hours')->all();

        return empty(array_diff(self::cacheHourRange(), $hours));
    }
}
