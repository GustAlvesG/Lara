<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * O termo de menores de UM evento: o modelo do documento e o período em que o
 * tablet de autoatendimento aceita termos novos.
 *
 * Um vigente por vez — o cadastro recusa períodos que se sobrepõem, e por isso
 * o tablet nunca pergunta "qual evento". Ver MinorTermService.
 *
 * @property int $id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property bool $active
 */
class SignatureMinorTerm extends Model
{
    protected $fillable = [
        'name',
        'signature_template_id',
        'starts_on',
        'ends_on',
        'active',
        'created_by',
        'created_by_name',
    ];

    protected $attributes = [
        'active' => true,
    ];

    protected $casts = [
        'signature_template_id' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'active' => 'boolean',
        'created_by' => 'integer',
    ];

    /**
     * O modelo escolhido no cadastro (a raiz das versões). O documento sai da
     * versão vigente no momento — ver `currentTemplate()`.
     *
     * @return BelongsTo<SignatureTemplate, SignatureMinorTerm>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SignatureTemplate::class, 'signature_template_id');
    }

    /** A versão do modelo que o próximo documento usa. */
    public function currentTemplate(): ?SignatureTemplate
    {
        return $this->template?->currentVersion();
    }

    /**
     * @return HasMany<SignatureMinorAuthorization>
     */
    public function authorizations(): HasMany
    {
        return $this->hasMany(SignatureMinorAuthorization::class);
    }

    /** O termo que o tablet usa HOJE, se houver. */
    public static function current(?Carbon $day = null): ?self
    {
        $dia = ($day ?? now())->toDateString();

        return static::query()
            ->where('active', true)
            ->whereDate('starts_on', '<=', $dia)
            ->whereDate('ends_on', '>=', $dia)
            ->orderBy('starts_on')
            ->first();
    }

    /**
     * Termos ativos cujo período cruza o informado — o que o cadastro recusa.
     *
     * @return Builder<SignatureMinorTerm>
     */
    public static function overlapping(string $startsOn, string $endsOn, ?int $exceptId = null): Builder
    {
        return static::query()
            ->where('active', true)
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId));
    }

    public function isCurrent(?Carbon $day = null): bool
    {
        $dia = ($day ?? now())->copy()->startOfDay();

        return $this->active
            && $this->starts_on->lte($dia)
            && $this->ends_on->gte($dia);
    }

    public function situationLabel(): string
    {
        if (!$this->active) {
            return 'Desativado';
        }

        if ($this->isCurrent()) {
            return 'Vigente';
        }

        return $this->starts_on->isFuture() ? 'Agendado' : 'Encerrado';
    }

    public function periodLabel(): string
    {
        return $this->starts_on->equalTo($this->ends_on)
            ? $this->starts_on->format('d/m/Y')
            : $this->starts_on->format('d/m/Y') . ' a ' . $this->ends_on->format('d/m/Y');
    }
}
