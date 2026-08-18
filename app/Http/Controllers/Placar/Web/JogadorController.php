<?php

namespace App\Http\Controllers\Placar\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Placar\Api\Concerns\UploadsPlacarImagem;
use App\Http\Requests\Placar\UploadImagemRequest;
use App\Models\Placar\Jogador;
use App\Services\Placar\ImagemService;
use App\Services\Placar\VideoService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class JogadorController extends Controller
{
    use UploadsPlacarImagem;

    public function index(Request $request)
    {
        $jogadores = Jogador::query()
            ->busca($request->query('busca'))
            ->when($request->boolean('criado_em_campo'), fn ($q) => $q->where('criado_em_campo', true))
            ->orderBy('nome')
            ->paginate(20)
            ->withQueryString();

        return view('placar.jogadores.index', compact('jogadores'));
    }

    public function create()
    {
        return view('placar.jogadores.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'nome_exibicao' => ['nullable', 'string', 'max:255'],
            'data_nascimento' => ['nullable', 'date'],
            'documento' => ['nullable', 'string', 'max:255'],
        ]);

        $jogador = Jogador::create([
            ...$data,
            'criado_em_campo' => false,
            'ativo' => true,
        ]);

        return redirect()->route('placar.jogadores.show', $jogador)
            ->with('success', "Jogador \"{$jogador->nome}\" cadastrado com sucesso.");
    }

    public function show(Jogador $jogador)
    {
        $jogador->load(['elencos.time.equipe']);

        return view('placar.jogadores.show', compact('jogador'));
    }

    public function update(Request $request, Jogador $jogador)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'nome_exibicao' => ['nullable', 'string', 'max:255'],
            'data_nascimento' => ['nullable', 'date'],
            'documento' => ['nullable', 'string', 'max:255'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        $jogador->update([
            ...$data,
            'ativo' => $request->boolean('ativo'),
        ]);

        return redirect()->route('placar.jogadores.show', $jogador)
            ->with('success', 'Jogador atualizado com sucesso.');
    }

    public function destroy(Jogador $jogador)
    {
        if ($jogador->elencos()->exists() || $jogador->escalacoes()->exists() || $jogador->eventos()->exists()) {
            return redirect()->route('placar.jogadores.index')
                ->with('error', "Não é possível excluir \"{$jogador->nome}\" pois possui histórico vinculado (elenco, escalação ou eventos).");
        }

        $nome = $jogador->nome;
        $jogador->delete();

        return redirect()->route('placar.jogadores.index')
            ->with('success', "Jogador \"{$nome}\" excluído com sucesso.");
    }

    public function storeFoto(UploadImagemRequest $request, Jogador $jogador, ImagemService $imagens)
    {
        try {
            $caminho = $this->processarImagem(
                $imagens, $request, "placar/jogadores/{$jogador->id}", 'foto', crop: true, caminhoAnterior: $jogador->foto_path,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $jogador->update(['foto_path' => $caminho]);

        return back()->with('success', 'Foto atualizada com sucesso.');
    }

    public function destroyFoto(Jogador $jogador, ImagemService $imagens)
    {
        $imagens->remover($jogador->foto_path);
        $jogador->update(['foto_path' => null]);

        return back()->with('success', 'Foto removida com sucesso.');
    }

    public function storeVideo(Request $request, Jogador $jogador, VideoService $videos)
    {
        try {
            $caminho = $videos->salvar($request, "placar/jogadores/{$jogador->id}", $jogador->video_path);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $jogador->update(['video_path' => $caminho]);

        return back()->with('success', 'Vídeo atualizado com sucesso.');
    }

    public function destroyVideo(Jogador $jogador, VideoService $videos)
    {
        $videos->remover($jogador->video_path);
        $jogador->update(['video_path' => null]);

        return back()->with('success', 'Vídeo removido com sucesso.');
    }
}
