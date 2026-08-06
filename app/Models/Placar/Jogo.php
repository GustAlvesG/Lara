<?php

namespace App\Models\Placar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jogo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jogos';

    const STATUS_AGENDADO = 'agendado';
    const STATUS_AO_VIVO = 'ao_vivo';
    const STATUS_ENCERRADO = 'encerrado';
    const STATUS_CANCELADO = 'cancelado';

    const STATUSES = [
        self::STATUS_AGENDADO,
        self::STATUS_AO_VIVO,
        self::STATUS_ENCERRADO,
        self::STATUS_CANCELADO,
    ];

    protected $fillable = [
        'competicao_id',
        'modalidade_id',
        'time_casa_id',
        'time_fora_id',
        'data_hora',
        'local',
        'status',
        'placar_casa',
        'placar_fora',
        'sets_casa',
        'sets_fora',
        'periodos_jogados',
        'criado_em_campo',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
            'criado_em_campo' => 'boolean',
        ];
    }

    public function competicao()
    {
        return $this->belongsTo(Competicao::class);
    }

    public function modalidade()
    {
        return $this->belongsTo(Modalidade::class);
    }

    public function timeCasa()
    {
        return $this->belongsTo(Time::class, 'time_casa_id');
    }

    public function timeFora()
    {
        return $this->belongsTo(Time::class, 'time_fora_id');
    }

    public function escalacoes()
    {
        return $this->hasMany(Escalacao::class);
    }

    public function escalacaoDoTime(int $timeId)
    {
        return $this->escalacoes()->where('time_id', $timeId);
    }

    /** Log append-only, sempre em ordem de sequência. */
    public function eventos()
    {
        return $this->hasMany(JogoEvento::class)->orderBy('sequencia');
    }

    /**
     * Elenco "operacional" de um time NESTE jogo: a escalação, se já foi
     * feita; senão o elenco da temporada corrente do time, sem titular nem
     * capitão marcados (a escalação é quem decide isso). Usado pelo payload
     * completo de GET /jogos/{id} — ver JogoDetalheResource.
     *
     * @return \Illuminate\Support\Collection<int, array{jogador: Jogador, numero: ?string, titular: bool, capitao: bool}>
     */
    public function elencoOperacionalDoTime(Time $time)
    {
        $escalacao = $this->escalacoes()->where('time_id', $time->id)->with('jogador')->get();

        if ($escalacao->isNotEmpty()) {
            return $escalacao->map(fn (Escalacao $item) => [
                'jogador' => $item->jogador,
                'numero' => $item->numero,
                'titular' => $item->titular,
                'capitao' => $item->capitao,
            ]);
        }

        return $time->elencoDaTemporada()->get()->map(fn (Elenco $elenco) => [
            'jogador' => $elenco->jogador,
            'numero' => $elenco->numero,
            'titular' => false,
            'capitao' => false,
        ]);
    }

    /**
     * Placar e sets recalculados a partir do log de eventos — a verdade é
     * sempre aqui, nunca em placar_casa/placar_fora/sets_casa/sets_fora
     * (esses são cache). Eventos `estorno` cancelam o `ponto`/`set` original
     * que citam em `payload.evento_uuid`, e nunca são eles mesmos pontuados.
     *
     * Usado pelo endpoint de eventos (atualiza o cache a cada lote aceito),
     * por `encerrar()` (detecta divergência com o snapshot enviado pelo
     * Node) e, depois, pela súmula do scout.
     *
     * @return array{placar_casa: int, placar_fora: int, sets_casa: int, sets_fora: int}
     */
    public function calcularPlacar(): array
    {
        $eventos = $this->eventos()
            ->whereIn('tipo', [JogoEvento::TIPO_PONTO, JogoEvento::TIPO_SET, JogoEvento::TIPO_ESTORNO])
            ->get();

        $estornados = JogoEvento::uuidsEstornados($eventos);

        $placarCasa = $placarFora = $setsCasa = $setsFora = 0;

        foreach ($eventos as $evento) {
            if ($evento->tipo === JogoEvento::TIPO_ESTORNO || isset($estornados[$evento->uuid])) {
                continue;
            }

            $doTimeCasa = $evento->time_id === $this->time_casa_id;
            $doTimeFora = $evento->time_id === $this->time_fora_id;

            if ($evento->tipo === JogoEvento::TIPO_PONTO) {
                if ($doTimeCasa) {
                    $placarCasa += $evento->valor ?? 1;
                } elseif ($doTimeFora) {
                    $placarFora += $evento->valor ?? 1;
                }
            } elseif ($evento->tipo === JogoEvento::TIPO_SET) {
                if ($doTimeCasa) {
                    $setsCasa++;
                } elseif ($doTimeFora) {
                    $setsFora++;
                }
            }
        }

        return [
            'placar_casa' => $placarCasa,
            'placar_fora' => $placarFora,
            'sets_casa' => $setsCasa,
            'sets_fora' => $setsFora,
        ];
    }

    public function estaAoVivo(): bool
    {
        return $this->status === self::STATUS_AO_VIVO;
    }

    public function estaEncerrado(): bool
    {
        return $this->status === self::STATUS_ENCERRADO;
    }

    public function scopeStatusEntre($query, array $statuses)
    {
        return $query->whereIn('status', $statuses);
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
}
