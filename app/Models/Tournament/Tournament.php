<?php

namespace App\Models\Tournament;

use App\Models\PlaceGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Torneio de um grupo de espaços, que usa o catálogo de categorias do grupo.
 *
 * "Inscrições abertas" não é situação gravada: sai das datas
 * (`registrationOpen()`). Inscrever-se e pagar só dentro dessa janela.
 *
 * `audience` = `members`: por enquanto só sócios. `public` está reservado
 * para a inscrição de não sócios (ver docs/funcionalidades/torneios.md).
 *
 * `results_code_*`: o código de lançamento de resultados pelo site (F6). O
 * banco guarda só o sha256; quem gera vê o código uma vez. Fora do fillable:
 * só TournamentResultCodeService grava.
 */
class Tournament extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Rascunho',
        self::STATUS_PUBLISHED => 'Publicado',
        self::STATUS_IN_PROGRESS => 'Em andamento',
        self::STATUS_FINISHED => 'Encerrado',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    public const AUDIENCE_MEMBERS = 'members';

    protected $fillable = [
        'place_group_id',
        'title',
        'description',
        'rules',
        'image',
        'registration_starts_at',
        'registration_ends_at',
        'starts_on',
        'ends_on',
        'audience',
        'status',
        'created_by',
    ];

    protected $casts = [
        'registration_starts_at' => 'datetime',
        'registration_ends_at' => 'datetime',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'results_code_expires_at' => 'datetime',
        'results_code_created_at' => 'datetime',
    ];

    protected $hidden = [
        'results_code_hash',
    ];

    protected $attributes = [
        'audience' => self::AUDIENCE_MEMBERS,
        'status' => self::STATUS_DRAFT,
    ];

    public function placeGroup(): BelongsTo
    {
        return $this->belongsTo(PlaceGroup::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(TournamentCategory::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resultsCodeCreator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'results_code_created_by');
    }

    /** Há código de lançamento pelo site, e ele ainda vale. */
    public function resultsCodeActive(?Carbon $at = null): bool
    {
        return $this->results_code_hash !== null
            && $this->results_code_expires_at !== null
            && ($at ?? now())->lessThanOrEqualTo($this->results_code_expires_at);
    }

    public function registrationOpen(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->status === self::STATUS_PUBLISHED
            && $this->registration_starts_at !== null
            && $this->registration_ends_at !== null
            && $at->betweenIncluded($this->registration_starts_at, $this->registration_ends_at);
    }
}
