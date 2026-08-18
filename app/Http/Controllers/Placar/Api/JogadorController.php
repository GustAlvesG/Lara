<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Placar\Api\Concerns\UploadsPlacarImagem;
use App\Http\Requests\Placar\CriarJogadorEmCampoRequest;
use App\Http\Requests\Placar\UploadImagemRequest;
use App\Http\Resources\Placar\JogadorResource;
use App\Models\Placar\Elenco;
use App\Models\Placar\Jogador;
use App\Services\Placar\ImagemService;
use App\Services\Placar\VideoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JogadorController extends Controller
{
    use UploadsPlacarImagem;

    /**
     * POST /placar/jogadores — modo avulso.
     * body: { nome, nome_exibicao?, time_id?, numero?, temporada? }
     * Sem foto, data de nascimento nem documento — nada disso é essencial
     * pra entrar em quadra. Com time_id, já cria o vínculo em elencos na
     * mesma transação.
     */
    public function store(CriarJogadorEmCampoRequest $request)
    {
        $jogador = DB::transaction(function () use ($request) {
            // 'ativo' explícito — ver o comentário equivalente em EquipeController@store.
            $jogador = Jogador::create([
                'nome' => $request->input('nome'),
                'nome_exibicao' => $request->input('nome_exibicao'),
                'criado_em_campo' => true,
                'ativo' => true,
            ]);

            if ($request->filled('time_id')) {
                Elenco::create([
                    'time_id' => $request->input('time_id'),
                    'jogador_id' => $jogador->id,
                    'temporada' => $request->input('temporada', now()->year),
                    'numero' => $request->input('numero'),
                    'ativo' => true,
                ]);
            }

            return $jogador;
        });

        return new JogadorResource($jogador);
    }

    /** POST /placar/jogadores/{jogador}/foto — multipart `arquivo` ou `arquivo_base64`. */
    public function storeFoto(UploadImagemRequest $request, Jogador $jogador, ImagemService $imagens)
    {
        try {
            $caminho = $this->processarImagem(
                $imagens, $request, "placar/jogadores/{$jogador->id}", 'foto', crop: true, caminhoAnterior: $jogador->foto_path,
            );
        } catch (InvalidArgumentException $e) {
            return $this->respostaErroImagem($e);
        }

        $jogador->update(['foto_path' => $caminho]);

        return new JogadorResource($jogador);
    }

    /** DELETE /placar/jogadores/{jogador}/foto */
    public function destroyFoto(Jogador $jogador, ImagemService $imagens)
    {
        $imagens->remover($jogador->foto_path);
        $jogador->update(['foto_path' => null]);

        return new JogadorResource($jogador);
    }

    /**
     * POST /placar/jogadores/{jogador}/video — multipart `video`, mp4/webm.
     * Sem base64 aqui de propósito: um vídeo em base64 inflaria ~33% e
     * estouraria o limite de POST do servidor bem antes do limite útil.
     */
    public function storeVideo(Request $request, Jogador $jogador, VideoService $videos)
    {
        try {
            $caminho = $videos->salvar($request, "placar/jogadores/{$jogador->id}", $jogador->video_path);
        } catch (InvalidArgumentException $e) {
            return $this->respostaErroImagem($e);
        }

        $jogador->update(['video_path' => $caminho]);

        return new JogadorResource($jogador);
    }

    /** DELETE /placar/jogadores/{jogador}/video */
    public function destroyVideo(Jogador $jogador, VideoService $videos)
    {
        $videos->remover($jogador->video_path);
        $jogador->update(['video_path' => null]);

        return new JogadorResource($jogador);
    }
}
