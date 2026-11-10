<?php

namespace App\Models\Waitlist;

use App\Models\User;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Não sócio na fila de espera. O CPF é obrigatório (inclusive de menor) e
 * único: é ele que diz que a pessoa da fila da natação é a mesma da fila do
 * tênis. Gravado só com dígitos; para exibir, Cpf::format().
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
            set: fn (?string $value) => Cpf::digits($value),
        );
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
