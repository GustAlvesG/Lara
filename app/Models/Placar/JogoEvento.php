<?php

namespace App\Models\Placar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JogoEvento extends Model
{
    use HasFactory;

    protected $table = 'jogo_eventos';

    /**
     * Append-only: nunca é atualizado depois de criado. Ver a migration e
     * docs/placar-clube-api.md — correção se faz com um evento novo do tipo
     * `estorno`, nunca com UPDATE.
     */
    const UPDATED_AT = null;

    const TIPO_INICIO_JOGO = 'inicio_jogo';
    const TIPO_FIM_JOGO = 'fim_jogo';
    const TIPO_PONTO = 'ponto';
    const TIPO_FALTA = 'falta';
    const TIPO_SET = 'set';
    const TIPO_PERIODO = 'periodo';
    const TIPO_CRONO_PLAY = 'crono_play';
    const TIPO_CRONO_PAUSE = 'crono_pause';
    const TIPO_CRONO_SET = 'crono_set';
    const TIPO_SUBSTITUICAO = 'substituicao';
    const TIPO_TIMEOUT = 'timeout';
    const TIPO_CARTAO = 'cartao';
    const TIPO_ESTORNO = 'estorno';

    const TIPOS = [
        self::TIPO_INICIO_JOGO,
        self::TIPO_FIM_JOGO,
        self::TIPO_PONTO,
        self::TIPO_FALTA,
        self::TIPO_SET,
        self::TIPO_PERIODO,
        self::TIPO_CRONO_PLAY,
        self::TIPO_CRONO_PAUSE,
        self::TIPO_CRONO_SET,
        self::TIPO_SUBSTITUICAO,
        self::TIPO_TIMEOUT,
        self::TIPO_CARTAO,
        self::TIPO_ESTORNO,
    ];

    /**
     * Tipos que compõem a ficha de atuação do jogador e por isso exigem
     * `cronometro_ms`: sem o instante da partida, o lance não serve ao
     * scout. Validado em JogoEventoLoteService::validar().
     */
    const TIPOS_COM_MINUTAGEM = [
        self::TIPO_PONTO,
        self::TIPO_FALTA,
    ];

    protected $fillable = [
        'uuid',
        'jogo_id',
        'sequencia',
        'tipo',
        'time_id',
        'jogador_id',
        'valor',
        'periodo',
        'cronometro_ms',
        'ocorrido_em',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'ocorrido_em' => 'datetime:Y-m-d H:i:s.v',
            'payload' => 'array',
        ];
    }

    public function jogo()
    {
        return $this->belongsTo(Jogo::class);
    }

    public function time()
    {
        return $this->belongsTo(Time::class);
    }

    public function jogador()
    {
        return $this->belongsTo(Jogador::class);
    }

    public function scopeDoTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Minutagem do lance na partida, "MM:SS", a partir do cronômetro do
     * jogo — não do relógio de parede. Null quando o tipo de evento não
     * carrega cronômetro (ver TIPOS_COM_MINUTAGEM).
     *
     * Formatado aqui, e não em cada view/consumidor, para que a súmula, a
     * ficha de atuação e o Node mostrem o mesmo minuto do mesmo jeito.
     */
    public static function formatarMinuto(?int $cronometroMs): ?string
    {
        if ($cronometroMs === null) {
            return null;
        }

        $segundos = intdiv(max(0, $cronometroMs), 1000);

        return sprintf('%02d:%02d', intdiv($segundos, 60), $segundos % 60);
    }

    public function minuto(): ?string
    {
        return self::formatarMinuto($this->cronometro_ms);
    }

    /**
     * Uuids dos eventos que uma coleção de eventos estorna — lidos do
     * `payload.evento_uuid` de cada `estorno` presente na coleção. Único
     * ponto de leitura dessa regra: usado por Jogo::calcularPlacar() e por
     * toda a camada de scout (súmula, artilharia, perfil do jogador) para
     * excluir pontos/sets/faltas revertidos do que é contado.
     *
     * @param  \Illuminate\Support\Collection<int, self>  $eventos
     * @return array<string, true> mapa uuid => true, pronto para isset()
     */
    public static function uuidsEstornados($eventos): array
    {
        return $eventos->where('tipo', self::TIPO_ESTORNO)
            ->pluck('payload')
            ->map(fn (?array $payload) => $payload['evento_uuid'] ?? null)
            ->filter()
            ->flip()
            ->all();
    }
}
