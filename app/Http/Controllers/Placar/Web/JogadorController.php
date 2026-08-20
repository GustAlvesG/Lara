<?php

namespace App\Http\Controllers\Placar\Web;

use App\Exceptions\SpreadsheetImportException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Placar\Api\Concerns\UploadsPlacarImagem;
use App\Http\Requests\ImportSpreadsheetRequest;
use App\Http\Requests\Placar\UploadImagemRequest;
use App\Imports\JogadorImport;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Modalidade;
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
            ->with(['equipe', 'modalidade'])
            ->busca($request->query('busca'))
            ->daEquipe($request->query('equipe_id'))
            ->daModalidade($request->query('modalidade'))
            ->when($request->boolean('criado_em_campo'), fn ($q) => $q->where('criado_em_campo', true))
            ->when($request->boolean('pendentes'), fn ($q) => $q->whereNull('equipe_id')->orWhereNull('modalidade_id'))
            ->orderBy('nome')
            ->paginate(20)
            ->withQueryString();

        return view('placar.jogadores.index', [
            'jogadores' => $jogadores,
            'equipes' => Equipe::ativas()->orderBy('nome')->get(),
            'modalidades' => Modalidade::ativas()->orderBy('nome')->get(),
        ]);
    }

    public function create(JogadorImport $import)
    {
        return view('placar.jogadores.create', [
            ...$this->opcoesDeVinculo(),
            'importColumns' => $import->columns(),
        ]);
    }

    /** Arquivo .xlsx em branco, no formato aceito pela importação. */
    public function importTemplate(JogadorImport $import)
    {
        return $import->downloadTemplate();
    }

    /**
     * Importação em massa. Tudo-ou-nada (ver SpreadsheetImport): havendo
     * erro em qualquer linha, nada é gravado e a lista de erros volta para
     * a tela — assim ninguém importa metade do elenco duas vezes.
     */
    public function import(ImportSpreadsheetRequest $request, JogadorImport $import)
    {
        try {
            $resultado = $import->import($request->file('spreadsheet')->getRealPath());
        } catch (SpreadsheetImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($resultado['errors']) {
            return back()
                ->with('error', 'Nenhum jogador foi importado. Corrija a planilha e envie novamente.')
                ->with('import_errors', $resultado['errors']);
        }

        return redirect()->route('placar.jogadores.index')
            ->with('success', $resultado['imported'] . ' jogador(es) importado(s) com sucesso.');
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->regras());

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
        $jogador->load(['elencos.time.equipe', 'equipe', 'modalidade']);

        return view('placar.jogadores.show', [
            'jogador' => $jogador,
            ...$this->opcoesDeVinculo(),
        ]);
    }

    public function update(Request $request, Jogador $jogador)
    {
        $data = $request->validate([
            ...$this->regras(),
            'ativo' => ['nullable', 'boolean'],
        ]);

        // Trocar a equipe ou a modalidade invalidaria os vínculos de elenco
        // já existentes, que são de times da equipe/modalidade antiga.
        $mudouVinculo = (int) $data['equipe_id'] !== (int) $jogador->equipe_id
            || (int) $data['modalidade_id'] !== (int) $jogador->modalidade_id;

        if ($mudouVinculo && $jogador->elencos()->exists()) {
            return back()->withInput()->withErrors([
                'equipe_id' => 'Este jogador já está no elenco de algum time. Remova-o dos elencos antes de mudar a equipe ou a modalidade.',
            ]);
        }

        $jogador->update([
            ...$data,
            'ativo' => $request->boolean('ativo'),
        ]);

        return redirect()->route('placar.jogadores.show', $jogador)
            ->with('success', 'Jogador atualizado com sucesso.');
    }

    /**
     * Equipe e modalidade são obrigatórias: o jogador pertence a uma só de
     * cada, e é isso que decide em quais times ele pode entrar.
     */
    private function regras(): array
    {
        return [
            'equipe_id' => ['required', 'integer', 'exists:equipes,id'],
            'modalidade_id' => ['required', 'integer', 'exists:modalidades,id'],
            'nome' => ['required', 'string', 'max:255'],
            'nome_exibicao' => ['nullable', 'string', 'max:255'],
            'data_nascimento' => ['nullable', 'date'],
        ];
    }

    private function opcoesDeVinculo(): array
    {
        return [
            'equipes' => Equipe::ativas()->orderBy('nome')->get(),
            'modalidades' => Modalidade::ativas()->orderBy('nome')->get(),
        ];
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
