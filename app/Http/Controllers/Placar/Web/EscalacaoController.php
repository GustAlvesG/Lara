<?php

namespace App\Http\Controllers\Placar\Web;

use App\Http\Controllers\Controller;
use App\Models\Placar\Jogo;
use App\Models\Placar\Time;
use App\Services\Placar\EscalacaoService;
use Illuminate\Http\Request;

/**
 * Tela de escalação de um jogo: marcar relacionados/titulares/capitão e
 * ajustar o número de cada time antes da partida. A regra de gravação
 * (substitui por completo) mora em EscalacaoService, compartilhada com a API.
 */
class EscalacaoController extends Controller
{
    public function edit(Jogo $jogo)
    {
        $jogo->load(['timeCasa.equipe', 'timeFora.equipe']);

        $temporada = $jogo->data_hora?->year ?? now()->year;

        $times = [
            'casa' => $this->montarPainel($jogo, $jogo->timeCasa, $temporada),
            'fora' => $this->montarPainel($jogo, $jogo->timeFora, $temporada),
        ];

        return view('placar.jogos.escalacao', compact('jogo', 'times'));
    }

    /** Elenco da temporada do time, cada jogador já com os dados da escalação atual (se houver). */
    private function montarPainel(Jogo $jogo, Time $time, int $temporada): array
    {
        $elenco = $time->elencoDaTemporada($temporada)->orderBy('numero')->get();
        $escalados = $jogo->escalacaoDoTime($time->id)->get()->keyBy('jogador_id');

        return [
            'time' => $time,
            'jogadores' => $elenco->map(function ($vinculo) use ($escalados) {
                $escalacao = $escalados->get($vinculo->jogador_id);

                return [
                    'jogador' => $vinculo->jogador,
                    'relacionado' => (bool) $escalacao,
                    'numero' => $escalacao->numero ?? $vinculo->numero,
                    'titular' => (bool) ($escalacao?->titular),
                    'capitao' => (bool) ($escalacao?->capitao),
                ];
            })->values(),
        ];
    }

    public function update(Request $request, Jogo $jogo, EscalacaoService $escalacoes)
    {
        $timeId = (int) $request->input('time_id');
        abort_if(!in_array($timeId, [$jogo->time_casa_id, $jogo->time_fora_id], true), 422, 'Este time não faz parte deste jogo.');

        $relacionados = $request->input('jogadores', []);

        $jogadores = [];
        foreach ($relacionados as $jogadorId => $item) {
            if (!($item['relacionado'] ?? false)) {
                continue;
            }

            $jogadores[] = [
                'jogador_id' => (int) $jogadorId,
                'numero' => $item['numero'] ?? '',
                'titular' => (bool) ($item['titular'] ?? false),
                'capitao' => (bool) ($item['capitao'] ?? false),
            ];
        }

        $escalacoes->substituir($jogo, $timeId, $jogadores);

        return redirect()->route('placar.jogos.escalacao.edit', $jogo)
            ->with('success', 'Escalação salva com sucesso.');
    }
}
