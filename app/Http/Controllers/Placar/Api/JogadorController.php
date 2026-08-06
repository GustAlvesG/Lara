<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Placar\Api\Concerns\UploadsPlacarImagem;
use App\Http\Requests\Placar\UploadImagemRequest;
use App\Http\Resources\Placar\JogadorResource;
use App\Models\Placar\Jogador;
use App\Services\Placar\ImagemService;
use InvalidArgumentException;

/**
 * Cadastro de jogadores em campo (POST /placar/jogadores) entra na etapa de
 * criação em campo — aqui só o upload/remoção da foto, que já é necessário
 * desde já para as telas de cadastro consumirem.
 */
class JogadorController extends Controller
{
    use UploadsPlacarImagem;

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
}
