<?php

namespace App\Http\Controllers\Placar\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Placar\Api\Concerns\UploadsPlacarImagem;
use App\Http\Requests\Placar\UploadImagemRequest;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Services\Placar\ImagemService;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Cadastro de times (recorte de uma equipe por modalidade+categoria) — tela
 * web. A ficha do time (show) é onde o elenco é gerenciado — ver
 * ElencoController.
 */
class TimeController extends Controller
{
    use UploadsPlacarImagem;

    public function index(Request $request)
    {
        $times = Time::query()
            ->with(['equipe', 'modalidade'])
            ->daModalidade($request->query('modalidade'))
            ->when($request->filled('equipe_id'), fn ($q) => $q->where('equipe_id', $request->query('equipe_id')))
            ->busca($request->query('busca'))
            ->when($request->boolean('criado_em_campo'), fn ($q) => $q->where('criado_em_campo', true))
            ->orderBy('categoria')
            ->paginate(20)
            ->withQueryString();

        $modalidades = Modalidade::ativas()->orderBy('nome')->get();
        $equipes = Equipe::ativas()->orderBy('nome')->get();

        return view('placar.times.index', compact('times', 'modalidades', 'equipes'));
    }

    public function create()
    {
        $equipes = Equipe::ativas()->orderBy('nome')->get();
        $modalidades = Modalidade::ativas()->orderBy('nome')->get();

        return view('placar.times.create', compact('equipes', 'modalidades'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'equipe_id' => ['required', 'integer', 'exists:equipes,id'],
            'modalidade_id' => ['required', 'integer', 'exists:modalidades,id'],
            'categoria' => ['required', 'string', 'max:255'],
            'nome_exibicao' => ['nullable', 'string', 'max:255'],
        ]);

        $time = Time::create([
            ...$data,
            'criado_em_campo' => false,
            'ativo' => true,
        ]);

        return redirect()->route('placar.times.show', $time)
            ->with('success', 'Time cadastrado com sucesso.');
    }

    public function show(Request $request, Time $time)
    {
        $time->load(['equipe', 'modalidade']);

        $temporada = now()->year;
        $elenco = $time->elencoDaTemporada($temporada)->orderBy('numero')->get();
        $temporadaAnteriorTemElenco = $time->elencos()->where('temporada', $temporada - 1)->exists();

        // Candidatos para adicionar ao elenco: jogadores ativos que ainda não
        // estão nele nesta temporada, filtráveis por nome/documento — a
        // busca fica no mesmo GET da ficha, não numa tela separada.
        $jaNoElenco = $elenco->pluck('jogador_id');
        $jogadoresDisponiveis = Jogador::ativos()
            ->whereNotIn('id', $jaNoElenco)
            ->busca($request->query('jogador_busca'))
            ->orderBy('nome')
            ->limit(50)
            ->get();

        return view('placar.times.show', compact(
            'time', 'elenco', 'temporada', 'temporadaAnteriorTemElenco', 'jogadoresDisponiveis',
        ));
    }

    public function update(Request $request, Time $time)
    {
        $data = $request->validate([
            'categoria' => ['required', 'string', 'max:255'],
            'nome_exibicao' => ['nullable', 'string', 'max:255'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        $time->update([
            'categoria' => $data['categoria'],
            'nome_exibicao' => $data['nome_exibicao'] ?? null,
            'ativo' => $request->boolean('ativo'),
        ]);

        return redirect()->route('placar.times.show', $time)
            ->with('success', 'Time atualizado com sucesso.');
    }

    public function destroy(Time $time)
    {
        if ($time->jogosEmCasa()->exists() || $time->jogosFora()->exists()) {
            return redirect()->route('placar.times.index')
                ->with('error', 'Não é possível excluir este time pois possui jogos vinculados.');
        }

        $time->delete();

        return redirect()->route('placar.times.index')
            ->with('success', 'Time excluído com sucesso.');
    }

    public function storeLogo(UploadImagemRequest $request, Time $time, ImagemService $imagens)
    {
        try {
            $caminho = $this->processarImagem(
                $imagens, $request, "placar/times/{$time->id}", 'logo', crop: false, caminhoAnterior: $time->logo_path,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $time->update(['logo_path' => $caminho]);

        return back()->with('success', 'Logo atualizada com sucesso.');
    }

    public function destroyLogo(Time $time, ImagemService $imagens)
    {
        $imagens->remover($time->logo_path);
        $time->update(['logo_path' => null]);

        return back()->with('success', 'Logo removida com sucesso.');
    }
}
