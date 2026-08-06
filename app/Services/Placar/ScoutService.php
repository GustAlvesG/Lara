<?php

namespace App\Services\Placar;

use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\JogoEvento;
use Illuminate\Support\Collection;

/**
 * Camada de agregação do scout — súmula, artilharia, perfil do jogador.
 * Único ponto de leitura de `jogo_eventos` para essas três visões, usado
 * tanto pelos endpoints da API (Etapa 8) quanto pelos controllers das
 * telas web (Etapa 11). Nunca lê de campo denormalizado — tudo aqui parte
 * do log de eventos, descontando o que foi `estorno`.
 */
class ScoutService
{
    /**
     * Súmula de um jogo: placar por período/set, timeline cronológica com
     * jogador, e totais por jogador de cada time.
     */
    public function sumula(Jogo $jogo): array
    {
        $jogo->loadMissing(['modalidade', 'competicao', 'timeCasa.equipe', 'timeFora.equipe']);

        $eventos = $jogo->eventos()->with(['jogador', 'time'])->get();
        $estornados = JogoEvento::uuidsEstornados($eventos);

        return [
            'jogo' => [
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
            ],
            'placar_por_periodo' => $this->placarPorPeriodo($jogo, $eventos, $estornados),
            'eventos' => $eventos->map(fn (JogoEvento $evento) => $this->linhaDoTempo($evento, $estornados))->values()->all(),
            'totais_por_jogador' => [
                'time_casa' => $this->totaisPorJogador($jogo->time_casa_id, $eventos, $estornados),
                'time_fora' => $this->totaisPorJogador($jogo->time_fora_id, $eventos, $estornados),
            ],
        ];
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

    private function linhaDoTempo(JogoEvento $evento, array $estornados): array
    {
        return [
            'sequencia' => $evento->sequencia,
            'tipo' => $evento->tipo,
            'time_id' => $evento->time_id,
            'jogador' => $evento->jogador ? [
                'id' => $evento->jogador->id,
                'nome_exibicao' => $evento->jogador->nomeExibicaoResolvido(),
                'foto_url' => $evento->jogador->fotoUrl(),
            ] : null,
            'valor' => $evento->valor,
            'periodo' => $evento->periodo,
            'cronometro_ms' => $evento->cronometro_ms,
            'ocorrido_em' => $evento->ocorrido_em?->toIso8601String(),
            // A estornada continua na timeline (é histórico), só marcada —
            // some do placar e dos totais, nunca da linha do tempo.
            'estornado' => isset($estornados[$evento->uuid]),
        ];
    }

    /** @return array<int, array{jogador_id: int, nome_exibicao: string, pontos: int, faltas: int}> */
    private function totaisPorJogador(?int $timeId, Collection $eventos, array $estornados): array
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

    /**
     * Ranking de pontos por jogador. Filtros aceitos: modalidade (slug ou
     * id), competicao_id, temporada (ano de `data_hora`), time_id.
     *
     * @return array<int, array{jogador_id: int, nome_exibicao: ?string, foto_url: ?string, pontos: int, jogos: int, media: float}>
     */
    public function artilharia(array $filtros): array
    {
        $jogoIds = $this->jogosFiltrados($filtros)->pluck('id');

        if ($jogoIds->isEmpty()) {
            return [];
        }

        $estornoUuids = $this->uuidsEstornadosEm($jogoIds);

        $linhas = JogoEvento::whereIn('jogo_id', $jogoIds)
            ->where('tipo', JogoEvento::TIPO_PONTO)
            ->whereNotNull('jogador_id')
            // time_id filtra pelo time do PONTO, não pelos jogos em que o
            // time apareceu — senão o artilheiro do adversário entraria no
            // ranking "do time" também.
            ->when(filled($filtros['time_id'] ?? null), fn ($q) => $q->where('time_id', $filtros['time_id']))
            ->when($estornoUuids->isNotEmpty(), fn ($q) => $q->whereNotIn('uuid', $estornoUuids))
            ->selectRaw('jogador_id, SUM(valor) as pontos, COUNT(DISTINCT jogo_id) as jogos')
            ->groupBy('jogador_id')
            ->orderByDesc('pontos')
            ->get();

        $jogadores = Jogador::whereIn('id', $linhas->pluck('jogador_id'))->get()->keyBy('id');

        return $linhas->map(function ($linha) use ($jogadores) {
            $jogador = $jogadores->get($linha->jogador_id);
            $pontos = (int) $linha->pontos;
            $jogos = (int) $linha->jogos;

            return [
                'jogador_id' => $linha->jogador_id,
                'nome_exibicao' => $jogador?->nomeExibicaoResolvido(),
                'foto_url' => $jogador?->fotoUrl(),
                'pontos' => $pontos,
                'jogos' => $jogos,
                'media' => $jogos > 0 ? round($pontos / $jogos, 2) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * Totais de um jogador: jogos disputados (qualquer evento seu num jogo,
     * não só ponto — escalação é opcional, nem todo jogo tem uma registrada),
     * pontos, média, faltas e distribuição de pontos por período.
     */
    public function perfilJogador(Jogador $jogador, ?int $temporada = null): array
    {
        $jogoIds = JogoEvento::where('jogador_id', $jogador->id)
            ->when($temporada, fn ($q) => $q->whereHas('jogo', fn ($jq) => $jq->whereYear('data_hora', $temporada)))
            ->pluck('jogo_id')
            ->unique();

        $base = [
            'jogador_id' => $jogador->id,
            'nome_exibicao' => $jogador->nomeExibicaoResolvido(),
            'foto_url' => $jogador->fotoUrl(),
            'jogos' => 0,
            'pontos' => 0,
            'media' => 0.0,
            'faltas' => 0,
            'distribuicao_por_periodo' => [],
        ];

        if ($jogoIds->isEmpty()) {
            return $base;
        }

        $estornoUuids = $this->uuidsEstornadosEm($jogoIds);

        $eventos = JogoEvento::whereIn('jogo_id', $jogoIds)
            ->where('jogador_id', $jogador->id)
            ->whereIn('tipo', [JogoEvento::TIPO_PONTO, JogoEvento::TIPO_FALTA])
            ->when($estornoUuids->isNotEmpty(), fn ($q) => $q->whereNotIn('uuid', $estornoUuids))
            ->get(['tipo', 'valor', 'periodo']);

        $pontos = 0;
        $faltas = 0;
        $distribuicao = [];

        foreach ($eventos as $evento) {
            if ($evento->tipo === JogoEvento::TIPO_PONTO) {
                $valor = $evento->valor ?? 1;
                $pontos += $valor;
                $periodo = $evento->periodo ?? 0;
                $distribuicao[$periodo] = ($distribuicao[$periodo] ?? 0) + $valor;
            } else {
                $faltas++;
            }
        }

        ksort($distribuicao);
        $jogos = $jogoIds->count();

        return [
            ...$base,
            'jogos' => $jogos,
            'pontos' => $pontos,
            'media' => $jogos > 0 ? round($pontos / $jogos, 2) : 0.0,
            'faltas' => $faltas,
            'distribuicao_por_periodo' => collect($distribuicao)
                ->map(fn ($pontosNoPeriodo, $periodo) => ['periodo' => $periodo, 'pontos' => $pontosNoPeriodo])
                ->values()
                ->all(),
        ];
    }

    /**
     * Jogos que casam com modalidade/competição/temporada — o filtro por
     * time_id NÃO entra aqui de propósito: ele restringe o artilheiro ao
     * time (no evento), não ao jogo (que tem os dois times).
     */
    private function jogosFiltrados(array $filtros)
    {
        return Jogo::query()
            ->select('id')
            ->daModalidade($filtros['modalidade'] ?? null)
            ->when(filled($filtros['competicao_id'] ?? null), fn ($q) => $q->where('competicao_id', $filtros['competicao_id']))
            ->when(filled($filtros['temporada'] ?? null), fn ($q) => $q->whereYear('data_hora', $filtros['temporada']))
            ->get();
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
