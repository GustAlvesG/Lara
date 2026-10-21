<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um mandato: um usuário do setor Diretoria respondendo por uma área, como
 * titular ou substituto.
 *
 * Não se edita nem se apaga. Trocar o titular encerra o mandato anterior
 * (`ended_at`) e abre outro — é o histórico que diz quem respondia pela área
 * quando um documento antigo foi assinado. Vigente = `ended_at` nulo.
 *
 * O nome do usuário é RETRATO (`user_name`): `users` mora na conexão `mysql`,
 * e a tela de histórico não depende dela.
 *
 * @property int $id
 * @property int $signature_director_area_id
 * @property int $user_id
 * @property string $role
 */
class SignatureDirectorAreaHolder extends Model
{
    public const ROLE_PRIMARY = 'primary';
    public const ROLE_SUBSTITUTE = 'substitute';

    public const ROLE_LABELS = [
        self::ROLE_PRIMARY => 'Titular',
        self::ROLE_SUBSTITUTE => 'Substituto',
    ];

    protected $fillable = [
        'signature_director_area_id',
        'user_id',
        'user_name',
        'role',
        'starts_on',
        'ended_at',
        'set_by',
        'set_by_name',
        'ended_by',
        'ended_by_name',
    ];

    protected $casts = [
        'signature_director_area_id' => 'integer',
        'user_id' => 'integer',
        'starts_on' => 'date',
        'ended_at' => 'datetime',
        'set_by' => 'integer',
        'ended_by' => 'integer',
    ];

    /**
     * @return BelongsTo<SignatureDirectorArea, SignatureDirectorAreaHolder>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(SignatureDirectorArea::class, 'signature_director_area_id');
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }
}
