<?php

namespace App\Http\Controllers\Placar\Web;

use App\Http\Controllers\Controller;
use App\Models\Placar\Competicao;
use App\Models\Placar\Jogo;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cadastro de jogos (modo planejado) — tela web. O ciclo de vida (iniciar,
 * eventos, encerrar) é operado pelo Node via API; aqui só o que precisa ser
 * decidido com antecedência: os times, a data, a competição e a escalação
 * (ver EscalacaoController).
 */
class JogoController extends Controller
{
    public function index(Request $request)
    {
        $jogos = Jogo::query()
            ->with(['modalidade', 'competicao', 'timeCasa', 'timeFora'])
            ->when($request->filled('status'), fn ($q) => $q->statusEntre(explode(',', $request->query('status'))))
            ->daModalidade($request->query('modalidade'))
            ->when($request->filled('competicao_id'), fn ($q) => $q->where('competicao_id', $request->query('competicao_id')))
            ->when($request->boolean('criado_em_campo'), fn ($q) => $q->where('criado_em_campo', true))
            ->orderByDesc('data_hora')
            ->paginate(20)
            ->withQueryString();

        $modalidades = Modalidade::ativas()->orderBy('nome')->get();
        $competicoes = Competicao::ativas()->orderBy('nome')->get();

        return view('placar.jogos.index', compact('jogos', 'modalidades', 'competicoes'));
    }

    public function create()
    {
        $modalidades = Modalidade::ativas()->orderBy('nome')->get();
        $times = Time::ativos()->with('equipe')->orderBy('categoria')->get();
        $competicoes = Competicao::ativas()->orderBy('nome')->get();

        return view('placar.jogos.create', compact('modalidades', 'times', 'competicoes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'modalidade_id' => ['required', 'integer', 'exists:modalidades,id'],
            'time_casa_id' => ['required', 'integer', 'exists:times,id', 'different:time_fora_id'],
            'time_fora_id' => ['required', 'integer', 'exists:times,id'],
            'competicao_id' => ['nullable', 'integer', 'exists:competicoes,id'],
            'data_hora' => ['required', 'date'],
            'local' => ['nullable', 'string', 'max:255'],
        ]);

        foreach (['time_casa_id', 'time_fora_id'] as $campo) {
            $time = Time::find($data[$campo]);
            if ($time && $time->modalidade_id !== (int) $data['modalidade_id']) {
                return back()->withInput()->withErrors([$campo => 'Este time não é da modalidade informada.']);
            }
        }

        $jogo = Jogo::create([
            ...$data,
            'status' => Jogo::STATUS_AGENDADO,
            'criado_em_campo' => false,
        ]);

        return redirect()->route('placar.jogos.show', $jogo)
            ->with('success', 'Jogo cadastrado com sucesso.');
    }

    public function show(Jogo $jogo)
    {
        $jogo->load(['modalidade', 'competicao', 'timeCasa.equipe', 'timeFora.equipe', 'escalacoes']);

        return view('placar.jogos.show', compact('jogo'));
    }

    public function update(Request $request, Jogo $jogo)
    {
        $data = $request->validate([
            'competicao_id' => ['nullable', 'integer', 'exists:competicoes,id'],
            'data_hora' => ['required', 'date'],
            'local' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Jogo::STATUSES)],
        ]);

        $jogo->update($data);

        return redirect()->route('placar.jogos.show', $jogo)
            ->with('success', 'Jogo atualizado com sucesso.');
    }

    public function destroy(Jogo $jogo)
    {
        if ($jogo->eventos()->exists()) {
            return redirect()->route('placar.jogos.index')
                ->with('error', 'Não é possível excluir um jogo que já tem eventos registrados.');
        }

        $jogo->delete();

        return redirect()->route('placar.jogos.index')
            ->with('success', 'Jogo excluído com sucesso.');
    }
}
