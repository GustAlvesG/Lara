<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Placar\Api\Concerns\UploadsPlacarImagem;
use App\Http\Requests\Placar\CriarEquipeEmCampoRequest;
use App\Http\Requests\Placar\UploadImagemRequest;
use App\Http\Resources\Placar\EquipeResource;
use App\Models\Placar\Equipe;
use App\Services\Placar\ImagemService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class EquipeController extends Controller
{
    use UploadsPlacarImagem;

    /** GET /placar/equipes?busca=&modalidade=futsal&page= */
    public function index(Request $request)
    {
        $equipes = Equipe::query()
            ->ativas()
            ->withCount('times')
            ->busca($request->query('busca'))
            ->when($request->filled('modalidade'), function ($query) use ($request) {
                $query->whereHas('times', function ($times) use ($request) {
                    $times->daModalidade($request->query('modalidade'));
                });
            })
            ->orderBy('nome')
            ->paginate(20);

        return EquipeResource::collection($equipes);
    }

    /**
     * POST /placar/equipes — modo avulso. body: { nome, nome_curto?, cidade? }
     * Sempre criado_em_campo = true — alguém revisa depois pela tela web.
     */
    public function store(CriarEquipeEmCampoRequest $request)
    {
        // 'ativo' explícito: o default só existe no banco, e o model recém
        // criado não o reflete sem um refresh() — melhor não depender disso.
        $equipe = Equipe::create([
            'nome' => $request->input('nome'),
            'nome_curto' => $request->input('nome_curto'),
            'cidade' => $request->input('cidade'),
            'criado_em_campo' => true,
            'ativo' => true,
        ]);

        return new EquipeResource($equipe);
    }

    /** POST /placar/equipes/{equipe}/logo — multipart `arquivo` ou `arquivo_base64`. */
    public function storeLogo(UploadImagemRequest $request, Equipe $equipe, ImagemService $imagens)
    {
        try {
            $caminho = $this->processarImagem(
                $imagens, $request, "placar/equipes/{$equipe->id}", 'logo', crop: false, caminhoAnterior: $equipe->logo_path,
            );
        } catch (InvalidArgumentException $e) {
            return $this->respostaErroImagem($e);
        }

        $equipe->update(['logo_path' => $caminho]);

        return new EquipeResource($equipe);
    }

    /** DELETE /placar/equipes/{equipe}/logo */
    public function destroyLogo(Equipe $equipe, ImagemService $imagens)
    {
        $imagens->remover($equipe->logo_path);
        $equipe->update(['logo_path' => null]);

        return new EquipeResource($equipe);
    }
}
