<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma área da diretoria do clube — "Diretoria Financeira", "Diretoria Social".
 *
 * Quem responde por ela são os mandatos (`SignatureDirectorAreaHolder`): um
 * titular e quantos substitutos houver. O atendente escolhe, ao criar o
 * documento, QUAL deles assina; o documento guarda o retrato do nome da área.
 *
 * Área não se apaga (os documentos a citam): desativa-se.
 *
 * @property int $id
 * @property string $name
 * @property bool $active
 */
class SignatureDirectorArea extends Model
{
    protected $fillable = ['name', 'description', 'active', 'created_by'];

    protected $attributes = [
        'active' => true,
    ];

    protected $casts = [
        'active' => 'boolean',
        'created_by' => 'integer',
    ];

    /**
     * Todos os mandatos, os vigentes primeiro e o titular à frente.
     *
     * @return HasMany<SignatureDirectorAreaHolder>
     */
    public function holders(): HasMany
    {
        return $this->hasMany(SignatureDirectorAreaHolder::class)
            ->orderByRaw('ended_at IS NOT NULL')
            ->orderByRaw("CASE role WHEN '" . SignatureDirectorAreaHolder::ROLE_PRIMARY . "' THEN 0 ELSE 1 END")
            ->orderByDesc('id');
    }

    /**
     * Os mandatos vigentes: quem pode ser escolhido para assinar pela área.
     *
     * @return HasMany<SignatureDirectorAreaHolder>
     */
    public function currentHolders(): HasMany
    {
        return $this->holders()->whereNull('ended_at');
    }

    /** O titular vigente, ou null se a área está sem titular. */
    public function primaryHolder(): ?SignatureDirectorAreaHolder
    {
        return $this->currentHolders()->where('role', SignatureDirectorAreaHolder::ROLE_PRIMARY)->first();
    }

    /**
     * Os signatários desta área — o que a tela da área lista como pendente.
     *
     * @return HasMany<SignatureSigner>
     */
    public function signers(): HasMany
    {
        return $this->hasMany(SignatureSigner::class, 'director_area_id');
    }
}
