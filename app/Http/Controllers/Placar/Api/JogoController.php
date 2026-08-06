<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Placar\CriarJogoEmCampoRequest;
use App\Http\Requests\Placar\EncerrarJogoRequest;
use App\Http\Requests\Placar\IniciarJogoRequest;
use App\Http\Resources\Placar\JogoDetalheResource;
use App\Http\Resources\Placar\JogoResource;
use App\Models\Placar\Jogo;
use App\Models\Placar\Modalidade;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class JogoController extends Controller
{
    /**
     * GET /placar/jogos?status=agendado&data=2026-08-06&modalidade=&competicao_id=
     * Default (sem `status`/`data`): jogos de hoje + amanhã, agendado ou ao_vivo.
     */
    public function index(Request $request)
    {
        $query = Jogo::query()->with(['modalidade', 'competicao', 'timeCasa', 'timeFora']);

        if ($request->filled('status')) {
            $query->statusEntre(explode(',', $request->query('status')));
        } else {
            $query->statusEntre([Jogo::STATUS_AGENDADO, Jogo::STATUS_AO_VIVO]);
        }

        if ($request->filled('data')) {
            $query->whereDate('data_hora', $request->query('data'));
        } else {
            $query->whereBetween('data_hora', [now()->startOfDay(), now()->addDay()->endOfDay()]);
        }

        $query->daModalidade($request->query('modalidade'));

        if ($request->filled('competicao_id')) {
            $query->where('competicao_id', $request->query('competicao_id'));
        }

        $jogos = $query->orderBy('data_hora')->paginate(20);

        return JogoResource::collection($jogos);
    }

    /** GET /placar/jogos/{jogo} — payload completo para o Node montar o gameState. */
    public function show(Jogo $jogo)
    {
        $jogo->load(['modalidade', 'competicao', 'timeCasa.equipe', 'timeFora.equipe']);

        return new JogoDetalheResource($jogo);
    }

    /**
     * POST /placar/jogos — modo avulso.
     * body: { modalidade, time_casa_id, time_fora_id, data_hora?, local?, competicao_id? }
     * `data_hora` default = agora. Devolve o mesmo payload de GET /jogos/{id}
     * — o Node segue direto para o jogo, sem uma segunda chamada.
     */
    public function store(CriarJogoEmCampoRequest $request)
    {
        $modalidade = Modalidade::resolver($request->input('modalidade'));

        // 'status' explícito — ver o comentário equivalente em EquipeController@store.
        $jogo = Jogo::create([
            'modalidade_id' => $modalidade->id,
            'time_casa_id' => $request->input('time_casa_id'),
            'time_fora_id' => $request->input('time_fora_id'),
            'data_hora' => $request->filled('data_hora') ? Carbon::parse($request->input('data_hora')) : now(),
            'local' => $request->input('local'),
            'competicao_id' => $request->input('competicao_id'),
            'status' => Jogo::STATUS_AGENDADO,
            'criado_em_campo' => true,
        ]);

        $jogo->load(['modalidade', 'competicao', 'timeCasa.equipe', 'timeFora.equipe']);

        return new JogoDetalheResource($jogo);
    }

    /**
     * POST /placar/jogos/{jogo}/iniciar — body: { operador?: string }
     * Idempotente: se já está ao_vivo, só devolve o estado atual. Devolve a
     * última sequência registrada para o Node retomar depois de uma queda.
     */
    public function iniciar(IniciarJogoRequest $request, Jogo $jogo)
    {
        if ($jogo->status !== Jogo::STATUS_AO_VIVO) {
            $operador = $request->input('operador');

            $jogo->status = Jogo::STATUS_AO_VIVO;
            if (filled($operador)) {
                $linha = "Iniciado por {$operador} em " . now()->toDateTimeString() . '.';
                $jogo->observacoes = trim(($jogo->observacoes ? $jogo->observacoes . "\n" : '') . $linha);
            }
            $jogo->save();
        }

        return response()->json([
            'status' => $jogo->status,
            'ultima_sequencia' => (int) $jogo->eventos()->max('sequencia'),
        ]);
    }

    /**
     * POST /placar/jogos/{jogo}/encerrar
     * body: { placar_casa, placar_fora, sets_casa?, sets_fora?, periodos_jogados? }
     *
     * Idempotente: chamada repetida com o jogo já encerrado só devolve o
     * estado atual, sem reprocessar nada. Na primeira vez, recalcula o
     * placar a partir do log de eventos e, se divergir do snapshot enviado,
     * registra em `observacoes` — o log manda, mas a divergência não pode
     * passar em branco.
     */
    public function encerrar(EncerrarJogoRequest $request, Jogo $jogo)
    {
        if ($jogo->status === Jogo::STATUS_ENCERRADO) {
            return response()->json([
                'status' => $jogo->status,
                'placar_casa' => $jogo->placar_casa,
                'placar_fora' => $jogo->placar_fora,
            ]);
        }

        $enviado = [
            'placar_casa' => (int) $request->input('placar_casa'),
            'placar_fora' => (int) $request->input('placar_fora'),
        ];
        $calculado = $jogo->calcularPlacar();

        $observacoes = $jogo->observacoes;
        if ($calculado['placar_casa'] !== $enviado['placar_casa'] || $calculado['placar_fora'] !== $enviado['placar_fora']) {
            $divergencia = sprintf(
                'Divergência ao encerrar em %s: placar enviado %d x %d, recalculado do log %d x %d.',
                now()->toDateTimeString(),
                $enviado['placar_casa'], $enviado['placar_fora'],
                $calculado['placar_casa'], $calculado['placar_fora'],
            );
            $observacoes = trim(($observacoes ? $observacoes . "\n" : '') . $divergencia);
        }

        $jogo->update([
            'status' => Jogo::STATUS_ENCERRADO,
            'placar_casa' => $enviado['placar_casa'],
            'placar_fora' => $enviado['placar_fora'],
            'sets_casa' => $request->input('sets_casa', $jogo->sets_casa),
            'sets_fora' => $request->input('sets_fora', $jogo->sets_fora),
            'periodos_jogados' => $request->input('periodos_jogados', $jogo->periodos_jogados),
            'observacoes' => $observacoes,
        ]);

        return response()->json([
            'status' => $jogo->status,
            'placar_casa' => $jogo->placar_casa,
            'placar_fora' => $jogo->placar_fora,
        ]);
    }
}
