<?php

namespace App\Models\Placar;

use App\Services\Placar\ImagemService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipe extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'equipes';

    protected $fillable = [
        'nome',
        'nome_curto',
        'logo_path',
        'cidade',
        'criado_em_campo',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'criado_em_campo' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function times()
    {
        return $this->hasMany(Time::class);
    }

    /**
     * URL absoluta da logo própria, ou null se não houver. A queda para uma
     * logo padrão (não há, aqui é sempre null) fica a cargo de quem chama —
     * em Time::logoUrl() é esta equipe quem serve de fallback.
     */
    public function logoUrl(): ?string
    {
        return ImagemService::url($this->logo_path);
    }

    public function scopeAtivas($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeBusca($query, ?string $termo)
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $query;
        }

        return $query->where(function ($q) use ($termo) {
            $q->where('nome', 'like', "%{$termo}%")
              ->orWhere('nome_curto', 'like', "%{$termo}%");
        });
    }
}
