<?php

namespace App\Services\Placar;

use App\Models\Placar\Jogo;
use App\Models\Placar\JogoEvento;
use App\Models\Placar\Time;
use Illuminate\Support\Collection;

/**
 * O estado de quadra da partida AGORA: quem está jogando, quem está no
 * banco, quantos tempos técnicos e quantas substituições cada time ainda
 * tem no período.
 *
 * Existe para o placar não ter de derivar isso sozinho. A conta é a mesma
 * para todo mundo — titulares da escalação, mais quem entrou, menos quem
 * saiu, na ordem do log — e refazê-la em cada cliente é como duas telas
 * passam a discordar sobre quem está em quadra.
 *
 * Tudo sai de `jogo_eventos`, descontando o que foi estornado: uma
 * substituição estornada devolve o jogador que tinha saído, e um tempo
 * técnico estornado volta para a conta de quem o pediu.
 *
 * Os limites de tempo/substituição são os usuais de cada modalidade (ver
 * ModalidadeRegras) e servem para EXIBIR quantos restam — a API não recusa
 * o evento que passar do limite. Regulamento de torneio muda esses números,
 * e travar aqui pararia um jogo real por causa de uma tabela nossa.
 */
class SituacaoDaPartidaService
{
    public function situacao(Jogo $jogo): array
    {
        $jogo->loadMissing(['modalidade', 'timeCasa.equipe', 'timeFora.equipe']);

        $eventos = $jogo->eventos()->get();
        $estornados = JogoEvento::uuidsEstornados($eventos);

        // Estornado não existe para efeito de estado: nem a troca aconteceu,
        // nem o tempo foi gasto.
        $validos = $eventos->reject(fn (JogoEvento $evento) => isset($estornados[$evento->uuid]))->values();

        $slug = $jogo->modalidade->slug;
        $periodo = $this->periodoAtual($validos);

        return [
            'jogo_id' => $jogo->id,
            'esporte' => $slug,
            'status' => $jogo->status,
            'periodo_atual' => $periodo,
            'nome_do_periodo' => ModalidadeRegras::nomeDoPeriodo($slug),
            'time_casa' => $this->doTime($jogo, $jogo->timeCasa, $validos, $periodo, $slug),
            'time_fora' => $this->doTime($jogo, $jogo->timeFora, $validos, $periodo, $slug),
        ];
    }

    /**
     * O período em curso: o maior já visto no log. Antes do primeiro evento
     * com período, é 1 — a partida ainda não saiu do começo.
     */
    private function periodoAtual(Collection $eventos): int
    {
        return max(1, (int) $eventos->max('periodo'));
    }

    private function doTime(Jogo $jogo, Time $time, Collection $eventos, int $periodo, string $slug): array
    {
        $elenco = $jogo->elencoOperacionalDoTime($time)->keyBy(fn (array $item) => $item['jogador']->id);
        $escalado = $jogo->escalacaoDoTime($time->id)->exists();

        $emQuadra = $this->emQuadra($eventos, $time, $elenco, $escalado);

        $doTime = $eventos->where('time_id', $time->id);

        return [
            'id' => $time->id,
            'nome_exibicao' => $time->nomeExibicaoResolvido(),
            'categoria' => $time->categoria,
            // Sem escalação não há titular definido, e portanto não há como
            // dizer quem está em quadra: o Node manda a escalação primeiro
            // (POST /jogos/{jogo}/escalacao) e esta chave vira true.
            'escalacao_definida' => $escalado,
            'em_quadra' => $elenco->only($emQuadra)->map($this->jogadorResumo(...))->values()->all(),
            'no_banco' => $elenco->except($emQuadra)->map($this->jogadorResumo(...))->values()->all(),
            'timeouts' => $this->contador(
                $doTime->where('tipo', JogoEvento::TIPO_TIMEOUT),
                $periodo,
                ModalidadeRegras::timeoutsPorPeriodo($slug),
            ),
            'substituicoes' => $this->contador(
                $doTime->where('tipo', JogoEvento::TIPO_SUBSTITUICAO),
                $periodo,
                ModalidadeRegras::substituicoesPorPeriodo($slug),
            ),
        ];
    }

    /**
     * Quem está em quadra: os titulares da escalação, aplicando cada
     * substituição na ordem do log — sai um, entra outro. Se um jogador
     * voltar depois (revezamento de futsal), ele volta para a lista.
     *
     * @return list<int> ids dos jogadores em quadra
     */
    private function emQuadra(Collection $eventos, Time $time, Collection $elenco, bool $escalado): array
    {
        if (!$escalado) {
            return [];
        }

        $quadra = $elenco->filter(fn (array $item) => $item['titular'])
            ->keys()
            ->flip()
            ->map(fn () => true);

        $trocas = $eventos->where('tipo', JogoEvento::TIPO_SUBSTITUICAO)
            ->where('time_id', $time->id)
            ->sortBy('sequencia');

        foreach ($trocas as $troca) {
            $sai = (int) data_get($troca->payload, 'sai_jogador_id');
            $entra = (int) data_get($troca->payload, 'entra_jogador_id');

            $quadra->forget($sai);

            if ($entra) {
                $quadra->put($entra, true);
            }
        }

        return $quadra->keys()->all();
    }

    /**
     * @param  ?int  $limite  null = a modalidade não limita (futsal e
     *                        basquete substituem à vontade)
     */
    private function contador(Collection $eventos, int $periodo, ?int $limite): array
    {
        $noPeriodo = $eventos->where('periodo', $periodo)->count();

        return [
            'limite_por_periodo' => $limite,
            'usados_no_periodo' => $noPeriodo,
            'restantes_no_periodo' => $limite === null ? null : max(0, $limite - $noPeriodo),
            'usados_no_jogo' => $eventos->count(),
        ];
    }

    /** @param array{jogador: \App\Models\Placar\Jogador, numero: ?string, titular: bool, capitao: bool} $item */
    private function jogadorResumo(array $item): array
    {
        return [
            'jogador_id' => $item['jogador']->id,
            'numero' => $item['numero'],
            'nome_exibicao' => $item['jogador']->nomeExibicaoResolvido(),
            'foto_url' => $item['jogador']->fotoUrl(),
            'capitao' => (bool) $item['capitao'],
        ];
    }
}
