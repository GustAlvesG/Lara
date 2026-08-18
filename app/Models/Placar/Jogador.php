<?php

namespace App\Models\Placar;

use App\Services\Placar\ImagemService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jogador extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jogadores';

    protected $fillable = [
        'nome',
        'nome_exibicao',
        'foto_path',
        'video_path',
        'data_nascimento',
        'documento',
        'criado_em_campo',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'criado_em_campo' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function elencos()
    {
        return $this->hasMany(Elenco::class);
    }

    public function escalacoes()
    {
        return $this->hasMany(Escalacao::class);
    }

    public function eventos()
    {
        return $this->hasMany(JogoEvento::class);
    }

    public function times()
    {
        return $this->belongsToMany(Time::class, 'elencos')
            ->withPivot(['temporada', 'numero', 'posicao', 'ativo']);
    }

    /** Nome curto exibido no telão — se null, cai para o nome completo. */
    public function nomeExibicaoResolvido(): string
    {
        return $this->nome_exibicao ?: $this->nome;
    }

    public function fotoUrl(): ?string
    {
        return ImagemService::url($this->foto_path);
    }

    /**
     * Vídeo curto de apresentação — o telão usa foto e vídeo em momentos
     * diferentes, então os dois convivem e cada um pode ser null por conta
     * própria.
     */
    public function videoUrl(): ?string
    {
        return ImagemService::url($this->video_path);
    }

    public function scopeAtivos($query)
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
              ->orWhere('nome_exibicao', 'like', "%{$termo}%")
              ->orWhere('documento', 'like', "%{$termo}%");
        });
    }
}
