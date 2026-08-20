<?php

namespace App\Http\Controllers\Placar\Web;

use App\Http\Controllers\Controller;
use App\Models\Placar\Competicao;
use App\Models\Placar\Modalidade;
use Illuminate\Http\Request;

class CompeticaoController extends Controller
{
    public function index()
    {
        $competicoes = Competicao::query()
            ->with('modalidade')
            ->withCount('jogos')
            ->orderByDesc('temporada')
            ->orderBy('nome')
            ->paginate(20);

        return view('placar.competicoes.index', compact('competicoes'));
    }

    public function create()
    {
        $modalidades = Modalidade::ativas()->orderBy('nome')->get();

        return view('placar.competicoes.create', compact('modalidades'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'modalidade_id' => ['required', 'integer', 'exists:modalidades,id'],
            'temporada' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $competicao = Competicao::create([...$data, 'ativo' => true]);

        return redirect()->route('placar.competicoes.show', $competicao)
            ->with('success', "Competição \"{$competicao->nome}\" cadastrada com sucesso.");
    }

    public function show(Competicao $competicao)
    {
        $competicao->load('modalidade');
        $jogos = $competicao->jogos()->with(['timeCasa', 'timeFora'])->orderByDesc('data_hora')->paginate(20);

        return view('placar.competicoes.show', compact('competicao', 'jogos'));
    }

    public function update(Request $request, Competicao $competicao)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'temporada' => ['required', 'integer', 'min:2000', 'max:2100'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        $competicao->update([
            'nome' => $data['nome'],
            'temporada' => $data['temporada'],
            'ativo' => $request->boolean('ativo'),
        ]);

        return redirect()->route('placar.competicoes.show', $competicao)
            ->with('success', 'Competição atualizada com sucesso.');
    }

    public function destroy(Competicao $competicao)
    {
        if ($competicao->jogos()->exists()) {
            return redirect()->route('placar.competicoes.index')
                ->with('error', "Não é possível excluir \"{$competicao->nome}\" pois possui jogos vinculados.");
        }

        $nome = $competicao->nome;
        $competicao->delete();

        return redirect()->route('placar.competicoes.index')
            ->with('success', "Competição \"{$nome}\" excluída com sucesso.");
    }
}
