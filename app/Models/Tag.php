<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = ['name'];

    public function avisos()
    {
        return $this->belongsToMany(Aviso::class, 'aviso_tag');
    }

    /**
     * Normaliza o nome da tag: minúsculas e sem espaços nas pontas.
     * Garante que "Urgente", "urgente" e " URGENTE " sejam a mesma tag.
     */
    public static function normalize(string $name): string
    {
        return trim(mb_strtolower($name));
    }
}
