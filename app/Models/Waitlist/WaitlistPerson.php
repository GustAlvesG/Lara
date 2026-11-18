<?php

namespace App\Models\Waitlist;

use App\Models\User;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Não sócio na fila de espera. O CPF é único (inclusive de menor): é ele que
 * diz que a pessoa da fila da natação é a mesma da fila do tênis. Gravado só
 * com dígitos; para exibir, Cpf::format().
 *
 * Desde 18/11/2026 o cadastro pode nascer SEM CPF (as listas antigas vieram de
 * planilhas sem ele), mas qualquer ação posterior exige o CPF antes: ver
 * `hasCpf()` e `WaitlistService::ensureHasCpf()`.
 */
class WaitlistPerson extends Model
{
    protected $table = 'waitlist_people';

    protected $fillable = [
        'cpf', 'name', 'birth_date', 'phone', 'phone_alt', 'email',
        'guardian_name', 'guardian_phone', 'notes', 'created_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    protected function cpf(): Attribute
    {
        return Attribute::make(
            // Vazio vira NULL: o UNIQUE aceita vários NULL, não vários ''.
            set: fn (?string $value) => Cpf::digits($value) !== '' ? Cpf::digits($value) : null,
        );
    }

    /** Sem CPF a pessoa só fica na fila: ligação, saída, reativação e nova turma pedem o CPF. */
    public function hasCpf(): bool
    {
        return filled($this->cpf);
    }

    public function scopeWithoutCpf($query)
    {
        return $query->where(fn ($q) => $q->whereNull('cpf')->orWhere('cpf', ''));
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Idade em anos completos, ou null sem data de nascimento. */
    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    public function isMinor(): bool
    {
        $age = $this->age();

        return $age !== null && $age < 18;
    }
}
