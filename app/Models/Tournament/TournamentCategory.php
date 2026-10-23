<?php

namespace App\Models\Tournament;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Categoria ligada a um torneio. Nome, tamanho de equipe, sexo e idade são
 * cópia do catálogo (TournamentCategoryTemplate) no momento do vínculo; preço,
 * vagas, montagem dos times, formato e sets são definidos aqui.
 *
 * `max_registrations` vazio = sem limite de vagas.
 */
class TournamentCategory extends Model
{
    public const FORMATION_ORGANIZER = 'organizer';
    public const FORMATION_DRAW = 'draw';
    public const FORMATION_CAPTAIN = 'captain';

    /** @var array<string, string> */
    public const FORMATIONS = [
        self::FORMATION_ORGANIZER => 'Organizador monta',
        self::FORMATION_DRAW => 'Sorteio',
        self::FORMATION_CAPTAIN => 'Capitão convida',
    ];

    public const FORMAT_SINGLE_ELIMINATION = 'single_elimination';
    public const FORMAT_GROUPS_KNOCKOUT = 'groups_knockout';

    /** @var array<string, string> */
    public const FORMATS = [
        self::FORMAT_SINGLE_ELIMINATION => 'Eliminatória simples',
        self::FORMAT_GROUPS_KNOCKOUT => 'Grupos + mata-mata',
    ];

    /** Motivos estáveis de recusa por elegibilidade (a API devolve em `reason`). */
    public const INELIGIBLE_GENDER = 'gender';
    public const INELIGIBLE_GENDER_UNKNOWN = 'gender_unknown';
    public const INELIGIBLE_BIRTH_DATE_UNKNOWN = 'birth_date_unknown';
    public const INELIGIBLE_TOO_YOUNG = 'too_young';
    public const INELIGIBLE_TOO_OLD = 'too_old';

    protected $fillable = [
        'tournament_id',
        'category_template_id',
        'name',
        'team_size',
        'gender',
        'min_age',
        'max_age',
        'price',
        'max_registrations',
        'team_formation',
        'format',
        'groups_count',
        'qualifiers_per_group',
        'third_place_match',
        'sets_to_win',
        'sort_order',
        'bracket_published_at',
    ];

    protected $casts = [
        'team_size' => 'integer',
        'min_age' => 'integer',
        'max_age' => 'integer',
        'price' => 'decimal:2',
        'max_registrations' => 'integer',
        'groups_count' => 'integer',
        'qualifiers_per_group' => 'integer',
        'third_place_match' => 'boolean',
        'sets_to_win' => 'integer',
        'sort_order' => 'integer',
        'bracket_published_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TournamentCategoryTemplate::class, 'category_template_id')->withTrashed();
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(TournamentTeam::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(TournamentStage::class)->orderBy('order');
    }

    /** Todos os jogos da categoria (das fases dela). */
    public function matches(): HasManyThrough
    {
        return $this->hasManyThrough(TournamentMatch::class, TournamentStage::class, 'tournament_category_id', 'tournament_stage_id');
    }

    public function isIndividual(): bool
    {
        return $this->team_size === 1;
    }

    /**
     * Por que a pessoa não pode jogar esta categoria, ou null se pode.
     * A idade é a completada na data de início do torneio (`$onDate`).
     */
    public function ineligibilityReason(?string $gender, ?CarbonInterface $birthDate, CarbonInterface $onDate): ?string
    {
        if ($this->gender !== TournamentCategoryTemplate::GENDER_ANY) {
            if ($gender === null) {
                return self::INELIGIBLE_GENDER_UNKNOWN;
            }
            if ($gender !== $this->gender) {
                return self::INELIGIBLE_GENDER;
            }
        }

        if ($this->min_age === null && $this->max_age === null) {
            return null;
        }

        if ($birthDate === null) {
            return self::INELIGIBLE_BIRTH_DATE_UNKNOWN;
        }

        $age = self::ageOn($birthDate, $onDate);

        if ($this->min_age !== null && $age < $this->min_age) {
            return self::INELIGIBLE_TOO_YOUNG;
        }
        if ($this->max_age !== null && $age > $this->max_age) {
            return self::INELIGIBLE_TOO_OLD;
        }

        return null;
    }

    /** Anos completos em `$onDate` (aniversário no próprio dia já conta). */
    public static function ageOn(CarbonInterface $birthDate, CarbonInterface $onDate): int
    {
        $age = $onDate->year - $birthDate->year;

        if ($onDate->format('md') < $birthDate->format('md')) {
            $age--;
        }

        return $age;
    }

    /** "Duplas · Feminino · 40 a 59 anos". */
    public static function describe(int $teamSize, string $gender, ?int $minAge, ?int $maxAge): string
    {
        $parts = [match ($teamSize) {
            1 => 'Individual',
            2 => 'Duplas',
            default => "Equipes de {$teamSize}",
        }];

        $parts[] = TournamentCategoryTemplate::GENDERS[$gender] ?? $gender;

        if ($minAge !== null && $maxAge !== null) {
            $parts[] = "{$minAge} a {$maxAge} anos";
        } elseif ($minAge !== null) {
            $parts[] = "{$minAge}+ anos";
        } elseif ($maxAge !== null) {
            $parts[] = "até {$maxAge} anos";
        }

        return implode(' · ', $parts);
    }
}
