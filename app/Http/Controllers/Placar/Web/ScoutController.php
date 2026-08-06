<?php

namespace App\Http\Controllers\Placar\Web;

use App\Http\Controllers\Controller;
use App\Models\Placar\Competicao;
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
    /** GET /placar/scout/jogos/{jogo}/sumula */
    public function sumula(Jogo $jogo, ScoutService $scout)
    {
        $sumula = $scout->sumula($jogo);

        return view('placar.scout.sumula', ['jogo' => $jogo, 'sumula' => $sumula]);
    }

    /** GET /placar/scout/jogos/{jogo}/sumula/impressao — versão para impressão/PDF pelo navegador. */
    public function sumulaPrint(Jogo $jogo, ScoutService $scout)
    {
        $sumula = $scout->sumula($jogo);

        return view('placar.scout.sumula-print', ['jogo' => $jogo, 'sumula' => $sumula]);
    }

    /** GET /placar/scout/artilharia?modalidade=&competicao_id=&temporada=&time_id= */
    public function artilharia(Request $request, ScoutService $scout)
    {
        $filtros = [
            'modalidade' => $request->query('modalidade'),
            'competicao_id' => $request->query('competicao_id'),
            'temporada' => $request->query('temporada'),
            'time_id' => $request->query('time_id'),
        ];

        $artilharia = $scout->artilharia($filtros);

        // Ordenação da tabela (a agregação já vem por pontos desc; aqui é só
        // reordenar a lista pronta — não vale outra query por coluna).
        $ordenar = in_array($request->query('ordenar'), ['pontos', 'jogos', 'media'], true)
            ? $request->query('ordenar')
            : 'pontos';
        usort($artilharia, fn ($a, $b) => $b[$ordenar] <=> $a[$ordenar]);

        return view('placar.scout.artilharia', [
            'artilharia' => $artilharia,
            'filtros' => $filtros,
            'ordenar' => $ordenar,
            'modalidades' => Modalidade::ativas()->orderBy('nome')->get(),
            'competicoes' => Competicao::ativas()->orderBy('nome')->get(),
            'times' => Time::ativos()->with('equipe')->orderBy('categoria')->get(),
        ]);
    }

    /** GET /placar/scout/jogadores/{jogador}?temporada= */
    public function jogador(Request $request, Jogador $jogador, ScoutService $scout)
    {
        $temporada = $request->filled('temporada') ? (int) $request->query('temporada') : null;
        $perfil = $scout->perfilJogador($jogador, $temporada);

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
