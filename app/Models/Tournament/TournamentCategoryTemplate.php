<?php

namespace App\Models\Tournament;

use App\Models\PlaceGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Categoria do catálogo de um grupo de espaços (o "book" do grupo), usada ao
 * montar torneios. O torneio copia nome, tamanho, sexo e idade para a sua
 * TournamentCategory: mudar aqui não mexe em torneio já criado.
 *
 * `team_size` 1 = categoria individual. A idade é conferida na data de
 * início do torneio; `min_age`/`max_age` vazios = sem limite.
 */
class TournamentCategoryTemplate extends Model
{
    use SoftDeletes;

    public const GENDER_MALE = 'male';
    public const GENDER_FEMALE = 'female';
    public const GENDER_ANY = 'any';

    /** @var array<string, string> */
    public const GENDERS = [
        self::GENDER_MALE => 'Masculino',
        self::GENDER_FEMALE => 'Feminino',
        self::GENDER_ANY => 'Livre',
    ];

    protected $fillable = [
        'place_group_id',
        'name',
        'team_size',
        'gender',
        'min_age',
        'max_age',
        'active',
    ];

    protected $casts = [
        'team_size' => 'integer',
        'min_age' => 'integer',
        'max_age' => 'integer',
        'active' => 'boolean',
    ];

    protected $attributes = [
        'team_size' => 1,
        'gender' => self::GENDER_ANY,
        'active' => true,
    ];

    public function placeGroup(): BelongsTo
    {
        return $this->belongsTo(PlaceGroup::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** "Duplas · Feminino · 40+" — o resumo que as listas mostram. */
    public function summary(): string
    {
        return TournamentCategory::describe($this->team_size, $this->gender, $this->min_age, $this->max_age);
    }
}
