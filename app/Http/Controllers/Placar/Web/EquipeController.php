<?php

namespace App\Http\Controllers\Placar\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Placar\Api\Concerns\UploadsPlacarImagem;
use App\Http\Controllers\Placar\Web\Concerns\RedirecionaAposCadastro;
use App\Http\Requests\Placar\UploadImagemRequest;
use App\Models\Placar\Equipe;
use App\Services\Placar\ImagemService;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Cadastro de equipes (agremiações) — tela web. A API tem seu próprio
 * EquipeController (modo avulso, criação em campo); este é o cadastro
 * completo, feito com calma pela mesa.
 */
class EquipeController extends Controller
{
    use RedirecionaAposCadastro, UploadsPlacarImagem;

    public function index(Request $request)
    {
        $equipes = Equipe::query()
            ->withCount('times')
            ->busca($request->query('busca'))
            ->when($request->boolean('criado_em_campo'), fn ($q) => $q->where('criado_em_campo', true))
            ->orderBy('nome')
            ->paginate(20)
            ->withQueryString();

        return view('placar.equipes.index', compact('equipes'));
    }

    public function create()
    {
        return view('placar.equipes.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'nome_curto' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        $equipe = Equipe::create([
            'nome' => $data['nome'],
            'nome_curto' => $data['nome_curto'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'criado_em_campo' => false,
            'ativo' => $request->boolean('ativo', true),
        ]);

        // Nada a manter no "criar outro": cada equipe é um clube diferente.
        // O próximo passo natural é criar os times dela.
        return $this->aposCadastrar(
            $request, 'equipes',
            "Equipe \"{$equipe->nome}\" cadastrada com sucesso.",
            link: ['url' => route('placar.times.create', ['equipe_id' => $equipe->id]), 'label' => 'Criar um time desta equipe'],
        );
    }

    public function show(Equipe $equipe)
    {
        $equipe->load(['times.modalidade']);

        return view('placar.equipes.show', compact('equipe'));
    }

    public function update(Request $request, Equipe $equipe)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'nome_curto' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        $equipe->update([
            'nome' => $data['nome'],
            'nome_curto' => $data['nome_curto'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'ativo' => $request->boolean('ativo'),
        ]);

        return redirect()->route('placar.equipes.show', $equipe)
            ->with('success', 'Equipe atualizada com sucesso.');
    }

    public function destroy(Equipe $equipe)
    {
        if ($equipe->times()->exists()) {
            return redirect()->route('placar.equipes.index')
                ->with('error', "Não é possível excluir \"{$equipe->nome}\" pois possui times vinculados.");
        }

        $nome = $equipe->nome;
        $equipe->delete();

        return redirect()->route('placar.equipes.index')
            ->with('success', "Equipe \"{$nome}\" excluída com sucesso.");
    }

    public function storeLogo(UploadImagemRequest $request, Equipe $equipe, ImagemService $imagens)
    {
        try {
            $caminho = $this->processarImagem(
                $imagens, $request, "placar/equipes/{$equipe->id}", 'logo', crop: false, caminhoAnterior: $equipe->logo_path,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $equipe->update(['logo_path' => $caminho]);

        return back()->with('success', 'Logo atualizada com sucesso.');
    }

    public function destroyLogo(Equipe $equipe, ImagemService $imagens)
    {
        $imagens->remover($equipe->logo_path);
        $equipe->update(['logo_path' => null]);

        return back()->with('success', 'Logo removida com sucesso.');
    }
}
