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
        'equipe_id',
        'modalidade_id',
        'nome',
        'nome_exibicao',
        'foto_path',
        'video_path',
        'data_nascimento',
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

    /**
     * O jogador pertence a UMA equipe e UMA modalidade. Ele pode estar em
     * vários times daquela equipe (Sub-15 e Adulto, por exemplo), mas
     * nunca em time de outra equipe nem de outra modalidade.
     */
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

    /**
     * Idade em anos completos, ou null quando a data de nascimento não foi
     * informada — o cadastro não a exige.
     *
     * É o que aparece ao montar o elenco: quem escala precisa saber se o
     * jogador cabe na categoria do time (Sub-15, Sub-17…), e conferir isso
     * de cabeça a partir da data de nascimento é onde o erro acontece.
     */
    public function idade(): ?int
    {
        return $this->data_nascimento?->age;
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

    /**
     * Pode entrar no elenco deste time? Só se o time for da mesma equipe e
     * da mesma modalidade do jogador.
     *
     * Ponto único da regra: a checam o cadastro web, a importação por
     * planilha e a criação em campo da API. Jogador sem equipe/modalidade
     * definidas (legado anterior à regra) não é bloqueado aqui — a
     * listagem o marca como pendente de revisão.
     */
    public function podeJogarPor(Time $time): bool
    {
        if ($this->equipe_id === null || $this->modalidade_id === null) {
            return true;
        }

        return $this->equipe_id === $time->equipe_id
            && $this->modalidade_id === $time->modalidade_id;
    }

    /** Cadastro incompleto pela regra atual — aparece sinalizado na listagem. */
    public function precisaDeRevisao(): bool
    {
        return $this->equipe_id === null || $this->modalidade_id === null;
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeDaEquipe($query, $equipeId)
    {
        return filled($equipeId) ? $query->where('equipe_id', $equipeId) : $query;
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

    /** Elegíveis para o elenco de um time: mesma equipe e mesma modalidade. */
    public function scopeElegiveisPara($query, Time $time)
    {
        return $query->where('equipe_id', $time->equipe_id)
            ->where('modalidade_id', $time->modalidade_id);
    }

    public function scopeBusca($query, ?string $termo)
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $query;
        }

        return $query->where(function ($q) use ($termo) {
            $q->where('nome', 'like', "%{$termo}%")
              ->orWhere('nome_exibicao', 'like', "%{$termo}%");
        });
    }
}
