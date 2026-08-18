<?php

namespace App\Http\Controllers\Placar\Web;

use App\Http\Controllers\Controller;
use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Services\Placar\ScoutService;
use Illuminate\Http\Request;

/**
 * Telas de scout — só leitura, tudo via ScoutService (o mesmo usado pela
 * API). Nenhum controller aqui grava nada.
 */
class ScoutController extends Controller
{
    /**
     * GET /placar/scout/jogos — porta de entrada do scout: as partidas, da
     * mais recente para a mais antiga, de onde se chega à súmula. Fica sob
     * o Gate de scout (a listagem de cadastro exige o Gate de cadastro, que
     * quem só acompanha jogo não tem).
     */
    public function jogos(Request $request)
    {
        $jogos = Jogo::query()
            ->with(['modalidade', 'competicao', 'timeCasa.equipe', 'timeFora.equipe'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->daModalidade($request->query('modalidade'))
            ->orderByDesc('data_hora')
            ->paginate(20)
            ->withQueryString();

        return view('placar.scout.jogos', [
            'jogos' => $jogos,
            'modalidades' => Modalidade::ativas()->orderBy('nome')->get(),
        ]);
    }

    /** GET /placar/scout/jogos/{jogo}/sumula?time_id= */
    public function sumula(Request $request, Jogo $jogo, ScoutService $scout)
    {
        $sumula = $scout->sumula($jogo, $this->recorte($request, $jogo));

        return view('placar.scout.sumula', [
            'jogo' => $jogo,
            'sumula' => $sumula,
            'lados' => $this->ladosExibidos($sumula),
        ]);
    }

    /**
     * GET /placar/scout/jogos/{jogo}/sumula/impressao?time_id=
     * Versão para impressão/PDF pelo navegador — é por aqui que sai a
     * súmula completa ou a de um time só.
     */
    public function sumulaPrint(Request $request, Jogo $jogo, ScoutService $scout)
    {
        $sumula = $scout->sumula($jogo, $this->recorte($request, $jogo));

        return view('placar.scout.sumula-print', [
            'jogo' => $jogo,
            'sumula' => $sumula,
            'lados' => $this->ladosExibidos($sumula),
        ]);
    }

    /**
     * `?time_id=` recorta a súmula em um dos dois times. Id que não é de
     * nenhum deles é 404: devolver a súmula completa em silêncio faria ela
     * passar por recortada na hora de imprimir.
     */
    private function recorte(Request $request, Jogo $jogo): ?int
    {
        if (!$request->filled('time_id')) {
            return null;
        }

        $timeId = (int) $request->query('time_id');
        abort_if($jogo->ladoDoTime($timeId) === null, 404);

        return $timeId;
    }

    /**
     * Quais blocos de totais a tela desenha: os dois na súmula completa, só
     * um na recortada. Vem do controller, e não de cada view, porque as
     * duas (tela e impressão) precisam da mesma decisão.
     *
     * @return array<string, string> chave em `totais_por_jogador` => nome do time
     */
    private function ladosExibidos(array $sumula): array
    {
        $lados = [
            'time_casa' => $sumula['jogo']['time_casa']['nome_exibicao'],
            'time_fora' => $sumula['jogo']['time_fora']['nome_exibicao'],
        ];

        if (!$sumula['recorte']) {
            return $lados;
        }

        $chave = 'time_' . $sumula['recorte']['lado'];

        return [$chave => $lados[$chave]];
    }

    /**
     * GET /placar/scout/jogos/{jogo}/jogadores/{jogador}
     * Ficha de atuação minutada do jogador nesta partida.
     */
    public function atuacao(Jogo $jogo, Jogador $jogador, ScoutService $scout)
    {
        $atuacao = $scout->atuacaoNaPartida($jogo, $jogador);

        return view('placar.scout.atuacao', [
            'jogo' => $jogo,
            'jogador' => $jogador,
            'atuacao' => $atuacao,
        ]);
    }

    /** GET /placar/scout/jogadores/{jogador}?temporada= */
    public function jogador(Request $request, Jogador $jogador, ScoutService $scout)
    {
        $temporada = $request->filled('temporada') ? (int) $request->query('temporada') : null;
        $perfil = $scout->partidasDoJogador($jogador, $temporada);

        return view('placar.scout.jogador', ['jogador' => $jogador, 'perfil' => $perfil, 'temporada' => $temporada]);
    }

    /** GET /placar/scout/times/{time} */
    public function time(Request $request, Time $time, ScoutService $scout)
    {
        $filtros = [
            'competicao_id' => $request->query('competicao_id'),
            'temporada' => $request->query('temporada'),
        ];

        $painel = $scout->painelTime($time, $filtros);

        return view('placar.scout.time', ['time' => $time, 'painel' => $painel]);
    }
}
