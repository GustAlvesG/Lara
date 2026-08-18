<?php

namespace App\Models\Placar;

use App\Services\Placar\ImagemService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Time extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'times';

    const CATEGORIA_PADRAO = 'Adulto';

    protected $fillable = [
        'equipe_id',
        'modalidade_id',
        'categoria',
        'nome_exibicao',
        'logo_path',
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

    public function equipe()
    {
        return $this->belongsTo(Equipe::class);
    }

    public function modalidade()
    {
        return $this->belongsTo(Modalidade::class);
    }

    public function elencos()
    {
        return $this->hasMany(Elenco::class);
    }

    public function escalacoes()
    {
        return $this->hasMany(Escalacao::class);
    }

    public function jogosEmCasa()
    {
        return $this->hasMany(Jogo::class, 'time_casa_id');
    }

    public function jogosFora()
    {
        return $this->hasMany(Jogo::class, 'time_fora_id');
    }

    /**
     * Jogadores vinculados na temporada informada (default: ano corrente),
     * já com o elenco carregado (numero, posição, ativo).
     */
    public function elencoDaTemporada(?int $temporada = null)
    {
        return $this->elencos()
            ->where('temporada', $temporada ?? now()->year)
            ->where('ativo', true)
            ->with('jogador');
    }

    /**
     * Se null, monta a partir da equipe + categoria — a mesma regra usada
     * pela API (ver os Resources) e pelas telas.
     */
    public function nomeExibicaoResolvido(): string
    {
        if (filled($this->nome_exibicao)) {
            return $this->nome_exibicao;
        }

        $nomeEquipe = $this->equipe->nome_curto ?: $this->equipe->nome;

        return trim("{$nomeEquipe} {$this->categoria}");
    }

    /**
     * URL absoluta da logo — própria se houver, senão cai para a da equipe.
     * Resolvido aqui (e não no cliente) por pedido explícito da API.
     */
    public function logoUrl(): ?string
    {
        if ($this->logo_path) {
            return ImagemService::url($this->logo_path);
        }

        return $this->equipe->logoUrl();
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeDaModalidade($query, ?string $slugOuId)
    {
        if (blank($slugOuId)) {
            return $query;
        }

        return $query->whereHas('modalidade', function ($q) use ($slugOuId) {
            $q->where('slug', $slugOuId)->orWhere('id', $slugOuId);
        });
    }

    public function scopeBusca($query, ?string $termo)
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $query;
        }

        return $query->where(function ($q) use ($termo) {
            $q->where('nome_exibicao', 'like', "%{$termo}%")
              ->orWhere('categoria', 'like', "%{$termo}%")
              ->orWhereHas('equipe', function ($eq) use ($termo) {
                  $eq->where('nome', 'like', "%{$termo}%")
                     ->orWhere('nome_curto', 'like', "%{$termo}%");
              });
        });
    }
}
