<?php

namespace App\Services\Placar;

use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\JogoEvento;
use App\Models\Placar\Time;
use Illuminate\Support\Collection;

/**
 * Camada de leitura do scout. Único ponto de leitura de `jogo_eventos`
 * para essas visões, usado tanto pelos endpoints da API quanto pelos
 * controllers das telas web. Nunca lê de campo denormalizado — tudo aqui
 * parte do log de eventos, descontando o que foi `estorno`.
 *
 * O scout mede **atuação numa partida**, não ranking entre partidas: cada
 * ponto e cada falta carrega a minutagem do jogo em que aconteceu (ver
 * JogoEvento::TIPOS_COM_MINUTAGEM). A visão de artilharia — ranking de
 * pontos agregado entre jogos — foi removida de propósito; se algum dia
 * voltar, é como relatório à parte, não como o eixo do scout.
 */
class ScoutService
{
    /**
     * Súmula de um jogo: placar por período/set, timeline cronológica com
     * jogador e minutagem, e totais por jogador de cada time.
     *
     * Com `$timeId`, sai a súmula **daquele time** — é a mesma súmula,
     * recortada, não um relatório diferente: cada equipe costuma querer só
     * a sua para arquivar ou entregar ao técnico. O que muda:
     *
     * - `eventos` traz só os lances do time, mais os marcos que não são de
     *   time nenhum (início/fim de jogo, virada de período/set) — sem eles
     *   a linha do tempo perde a referência de quando cada coisa aconteceu.
     * - `totais_por_jogador` vem vazio do lado de fora do recorte.
     * - `placar_por_periodo` e o cabeçalho continuam **completos**: uma
     *   súmula que não diz contra quem se jogou e como ficou o placar não
     *   serve para nada.
     *
     * `recorte` é null na súmula completa, e identifica o time quando há.
     * Quem chama é responsável por recusar id de time que não é do jogo
     * (ver Jogo::ladoDoTime()).
     */
    public function sumula(Jogo $jogo, ?int $timeId = null): array
    {
        $jogo->loadMissing(['modalidade', 'competicao', 'timeCasa.equipe', 'timeFora.equipe']);

        $lado = $jogo->ladoDoTime($timeId);
        $eventos = $jogo->eventos()->with(['jogador', 'time'])->get();

        // Os estornos são apurados sobre TODOS os eventos, antes de
        // qualquer recorte: o evento de estorno não pertence a time nenhum,
        // e filtrar primeiro faria um lance estornado voltar a valer na
        // súmula individual.
        $estornados = JogoEvento::uuidsEstornados($eventos);

        // Número que cada jogador usa NESTE jogo — a escalação, se já foi
        // feita; senão o elenco da temporada corrente (mesma prioridade de
        // Jogo::elencoOperacionalDoTime()). Uma consulta para os dois times,
        // não uma por evento.
        $numeros = $this->numerosDoJogo($jogo);

        $daLinhaDoTempo = $lado === null
            ? $eventos
            : $eventos->filter(fn (JogoEvento $evento) => $evento->time_id === $timeId || $evento->time_id === null);

        return [
            'jogo' => $this->cabecalhoDoJogo($jogo),
            'recorte' => $lado === null ? null : [
                'time_id' => $timeId,
                'lado' => $lado,
                'nome_exibicao' => ($lado === 'casa' ? $jogo->timeCasa : $jogo->timeFora)->nomeExibicaoResolvido(),
            ],
            'placar_por_periodo' => $this->placarPorPeriodo($jogo, $eventos, $estornados),
            'eventos' => $daLinhaDoTempo->map(fn (JogoEvento $evento) => $this->linhaDoTempo($evento, $estornados, $numeros))->values()->all(),
            'totais_por_jogador' => [
                'time_casa' => $lado === 'fora' ? [] : $this->totaisPorJogador($jogo->time_casa_id, $eventos, $estornados, $numeros),
                'time_fora' => $lado === 'casa' ? [] : $this->totaisPorJogador($jogo->time_fora_id, $eventos, $estornados, $numeros),
            ],
        ];
    }

    /**
     * Ficha de atuação de um jogador NUMA partida — o coração do scout.
     * Traz cada lance dele com a minutagem em que aconteceu, além dos
     * totais da partida. Lance estornado continua na lista, marcado, mas
     * não conta nos totais.
     */
    public function atuacaoNaPartida(Jogo $jogo, Jogador $jogador): array
    {
        $jogo->loadMissing(['modalidade', 'competicao', 'timeCasa.equipe', 'timeFora.equipe']);

        $todosOsEventos = $jogo->eventos()->get();
        $estornados = JogoEvento::uuidsEstornados($todosOsEventos);
        $numeros = $this->numerosDoJogo($jogo);

        $doJogador = $todosOsEventos->where('jogador_id', $jogador->id)->values();

        $pontos = 0;
        $faltas = 0;
        $lances = [];

        foreach ($doJogador as $evento) {
            $foiEstornado = isset($estornados[$evento->uuid]);

            if (!$foiEstornado) {
                if ($evento->tipo === JogoEvento::TIPO_PONTO) {
                    $pontos += $evento->valor ?? 1;
                } elseif ($evento->tipo === JogoEvento::TIPO_FALTA) {
                    $faltas++;
                }
            }

            $lances[] = [
                'sequencia' => $evento->sequencia,
                'tipo' => $evento->tipo,
                'valor' => $evento->valor,
                'periodo' => $evento->periodo,
                'cronometro_ms' => $evento->cronometro_ms,
                'minuto' => $evento->minuto(),
                'ocorrido_em' => $evento->ocorrido_em?->toIso8601String(),
                'estornado' => $foiEstornado,
            ];
        }

        return [
            'jogo' => $this->cabecalhoDoJogo($jogo),
            'jogador' => [
                'id' => $jogador->id,
                'numero' => $numeros[$jogador->id] ?? null,
                'nome_exibicao' => $jogador->nomeExibicaoResolvido(),
                'foto_url' => $jogador->fotoUrl(),
                'time_id' => $this->timeDoJogadorNoJogo($jogo, $jogador, $doJogador),
            ],
            'totais' => [
                'pontos' => $pontos,
                'faltas' => $faltas,
                'lances' => count($lances),
            ],
            'lances' => $lances,
        ];
    }

    /**
     * Partidas em que o jogador atuou, da mais recente para a mais antiga,
     * com os totais dele em cada uma. Substitui o antigo perfil agregado
     * (pontos/média entre jogos, que era artilharia individual): aqui a
     * unidade é sempre a partida — para o detalhe minutado de uma delas,
     * ver atuacaoNaPartida().
     */
    public function partidasDoJogador(Jogador $jogador, ?int $temporada = null): array
    {
        $jogoIds = JogoEvento::where('jogador_id', $jogador->id)
            ->when($temporada, fn ($q) => $q->whereHas('jogo', fn ($jq) => $jq->whereYear('data_hora', $temporada)))
            ->pluck('jogo_id')
            ->unique();

        $base = [
            'jogador' => [
                'id' => $jogador->id,
                'nome_exibicao' => $jogador->nomeExibicaoResolvido(),
                'foto_url' => $jogador->fotoUrl(),
            ],
            'partidas' => [],
        ];

        if ($jogoIds->isEmpty()) {
            return $base;
        }

        $estornados = $this->uuidsEstornadosEm($jogoIds)->flip();

        $eventosPorJogo = JogoEvento::whereIn('jogo_id', $jogoIds)
            ->where('jogador_id', $jogador->id)
            ->get()
            ->groupBy('jogo_id');

        $jogos = Jogo::whereIn('id', $jogoIds)
            ->with(['modalidade', 'competicao', 'timeCasa.equipe', 'timeFora.equipe'])
            ->orderByDesc('data_hora')
            ->get();

        $partidas = $jogos->map(function (Jogo $jogo) use ($eventosPorJogo, $estornados) {
            $eventos = $eventosPorJogo->get($jogo->id, collect());

            $pontos = 0;
            $faltas = 0;

            foreach ($eventos as $evento) {
                if (isset($estornados[$evento->uuid])) {
                    continue;
                }
                if ($evento->tipo === JogoEvento::TIPO_PONTO) {
                    $pontos += $evento->valor ?? 1;
                } elseif ($evento->tipo === JogoEvento::TIPO_FALTA) {
                    $faltas++;
                }
            }

            return [
                'jogo_id' => $jogo->id,
                'esporte' => $jogo->modalidade->slug,
                'data_hora' => $jogo->data_hora?->toIso8601String(),
                'status' => $jogo->status,
                'competicao' => $jogo->competicao?->nome,
                'confronto' => $jogo->timeCasa->nomeExibicaoResolvido() . ' x ' . $jogo->timeFora->nomeExibicaoResolvido(),
                'placar' => $jogo->placar_casa . ' x ' . $jogo->placar_fora,
                'pontos' => $pontos,
                'faltas' => $faltas,
            ];
        })->values()->all();

        return [...$base, 'partidas' => $partidas];
    }

    /**
     * Painel do time: retrospecto (V/D/E) a partir dos jogos encerrados e o
     * histórico completo de confrontos. Sem ranking de artilheiros — o
     * destaque individual se vê na ficha de atuação de cada partida.
     */
    public function painelTime(Time $time, array $filtros = []): array
    {
        $jogos = Jogo::where(function ($q) use ($time) {
            $q->where('time_casa_id', $time->id)->orWhere('time_fora_id', $time->id);
        })
            ->when(filled($filtros['competicao_id'] ?? null), fn ($q) => $q->where('competicao_id', $filtros['competicao_id']))
            ->when(filled($filtros['temporada'] ?? null), fn ($q) => $q->whereYear('data_hora', $filtros['temporada']))
            ->with(['modalidade', 'competicao', 'timeCasa', 'timeFora'])
            ->orderByDesc('data_hora')
            ->get()
            ->map(fn (Jogo $jogo) => $this->linhaDoConfronto($jogo, $time))
            ->values();

        $retrospecto = ['vitorias' => 0, 'derrotas' => 0, 'empates' => 0];
        foreach ($jogos as $linha) {
            match ($linha['resultado']) {
                'V' => $retrospecto['vitorias']++,
                'D' => $retrospecto['derrotas']++,
                'E' => $retrospecto['empates']++,
                default => null,
            };
        }

        return [
            'time' => [
                'id' => $time->id,
                'nome_exibicao' => $time->nomeExibicaoResolvido(),
                'logo_url' => $time->logoUrl(),
            ],
            'retrospecto' => $retrospecto,
            'jogos' => $jogos->all(),
        ];
    }

    /** Cabeçalho comum à súmula e à ficha de atuação. */
    private function cabecalhoDoJogo(Jogo $jogo): array
    {
        return [
            'id' => $jogo->id,
            'esporte' => $jogo->modalidade->slug,
            'status' => $jogo->status,
            'data_hora' => $jogo->data_hora?->toIso8601String(),
            'local' => $jogo->local,
            'competicao' => $jogo->competicao ? [
                'id' => $jogo->competicao->id,
                'nome' => $jogo->competicao->nome,
            ] : null,
            'time_casa' => [
                'id' => $jogo->timeCasa->id,
                'nome_exibicao' => $jogo->timeCasa->nomeExibicaoResolvido(),
                'logo_url' => $jogo->timeCasa->logoUrl(),
            ],
            'time_fora' => [
                'id' => $jogo->timeFora->id,
                'nome_exibicao' => $jogo->timeFora->nomeExibicaoResolvido(),
                'logo_url' => $jogo->timeFora->logoUrl(),
            ],
            'placar_casa' => $jogo->placar_casa,
            'placar_fora' => $jogo->placar_fora,
            'sets_casa' => $jogo->sets_casa,
            'sets_fora' => $jogo->sets_fora,
        ];
    }

    /**
     * De qual time o jogador atuou neste jogo: o time dos próprios lances
     * dele; se não marcou nada, a escalação registrada.
     */
    private function timeDoJogadorNoJogo(Jogo $jogo, Jogador $jogador, Collection $eventosDoJogador): ?int
    {
        $peloEvento = $eventosDoJogador->firstWhere(fn (JogoEvento $e) => $e->time_id !== null);

        if ($peloEvento) {
            return $peloEvento->time_id;
        }

        return $jogo->escalacoes()->where('jogador_id', $jogador->id)->value('time_id');
    }

    /**
     * @return array<int, ?string> jogador_id => número, para os dois times
     * deste jogo.
     */
    private function numerosDoJogo(Jogo $jogo): array
    {
        $numeros = [];

        foreach ([$jogo->timeCasa, $jogo->timeFora] as $time) {
            foreach ($jogo->elencoOperacionalDoTime($time) as $item) {
                $numeros[$item['jogador']->id] = $item['numero'];
            }
        }

        return $numeros;
    }

    /** @return array<int, array{periodo: int, placar_casa: int, placar_fora: int}> */
    private function placarPorPeriodo(Jogo $jogo, Collection $eventos, array $estornados): array
    {
        $porPeriodo = [];

        foreach ($eventos as $evento) {
            if ($evento->tipo !== JogoEvento::TIPO_PONTO || isset($estornados[$evento->uuid])) {
                continue;
            }

            $periodo = $evento->periodo ?? 0;
            $porPeriodo[$periodo] ??= ['periodo' => $periodo, 'placar_casa' => 0, 'placar_fora' => 0];

            if ($evento->time_id === $jogo->time_casa_id) {
                $porPeriodo[$periodo]['placar_casa'] += $evento->valor ?? 1;
            } elseif ($evento->time_id === $jogo->time_fora_id) {
                $porPeriodo[$periodo]['placar_fora'] += $evento->valor ?? 1;
            }
        }

        ksort($porPeriodo);

        return array_values($porPeriodo);
    }

    private function linhaDoTempo(JogoEvento $evento, array $estornados, array $numeros): array
    {
        return [
            'sequencia' => $evento->sequencia,
            'tipo' => $evento->tipo,
            'time_id' => $evento->time_id,
            'jogador' => $evento->jogador ? [
                'id' => $evento->jogador->id,
                'numero' => $numeros[$evento->jogador->id] ?? null,
                'nome_exibicao' => $evento->jogador->nomeExibicaoResolvido(),
                'foto_url' => $evento->jogador->fotoUrl(),
            ] : null,
            'valor' => $evento->valor,
            'periodo' => $evento->periodo,
            'cronometro_ms' => $evento->cronometro_ms,
            'minuto' => $evento->minuto(),
            'ocorrido_em' => $evento->ocorrido_em?->toIso8601String(),
            // A estornada continua na timeline (é histórico), só marcada —
            // some do placar e dos totais, nunca da linha do tempo.
            'estornado' => isset($estornados[$evento->uuid]),
        ];
    }

    /** @return array<int, array{jogador_id: int, numero: ?string, nome_exibicao: string, pontos: int, faltas: int}> */
    private function totaisPorJogador(?int $timeId, Collection $eventos, array $estornados, array $numeros): array
    {
        $totais = [];

        foreach ($eventos as $evento) {
            if ($evento->time_id !== $timeId || !$evento->jogador_id || isset($estornados[$evento->uuid])) {
                continue;
            }
            if (!in_array($evento->tipo, [JogoEvento::TIPO_PONTO, JogoEvento::TIPO_FALTA], true)) {
                continue;
            }

            $totais[$evento->jogador_id] ??= [
                'jogador_id' => $evento->jogador_id,
                'numero' => $numeros[$evento->jogador_id] ?? null,
                'nome_exibicao' => $evento->jogador?->nomeExibicaoResolvido(),
                'pontos' => 0,
                'faltas' => 0,
            ];

            if ($evento->tipo === JogoEvento::TIPO_PONTO) {
                $totais[$evento->jogador_id]['pontos'] += $evento->valor ?? 1;
            } else {
                $totais[$evento->jogador_id]['faltas']++;
            }
        }

        return array_values($totais);
    }

    private function linhaDoConfronto(Jogo $jogo, Time $time): array
    {
        $emCasa = $jogo->time_casa_id === $time->id;
        $adversario = $emCasa ? $jogo->timeFora : $jogo->timeCasa;
        $placarTime = $emCasa ? $jogo->placar_casa : $jogo->placar_fora;
        $placarAdversario = $emCasa ? $jogo->placar_fora : $jogo->placar_casa;

        $resultado = null;
        if ($jogo->status === Jogo::STATUS_ENCERRADO && $placarTime !== null && $placarAdversario !== null) {
            $resultado = match (true) {
                $placarTime > $placarAdversario => 'V',
                $placarTime < $placarAdversario => 'D',
                default => 'E',
            };
        }

        return [
            'jogo_id' => $jogo->id,
            'data_hora' => $jogo->data_hora?->toIso8601String(),
            'status' => $jogo->status,
            'mandante' => $emCasa,
            'adversario' => [
                'id' => $adversario->id,
                'nome_exibicao' => $adversario->nomeExibicaoResolvido(),
            ],
            'placar_time' => $placarTime,
            'placar_adversario' => $placarAdversario,
            'resultado' => $resultado,
        ];
    }

    /**
     * Uuids dos eventos originais estornados dentro de um conjunto de jogos
     * — uma query para o conjunto inteiro, nunca uma por jogo.
     */
    private function uuidsEstornadosEm($jogoIds): Collection
    {
        return JogoEvento::whereIn('jogo_id', $jogoIds)
            ->where('tipo', JogoEvento::TIPO_ESTORNO)
            ->pluck('payload')
            ->map(fn (?array $payload) => $payload['evento_uuid'] ?? null)
            ->filter()
            ->values();
    }
}
