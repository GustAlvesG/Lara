<?php

namespace App\Http\Controllers\Placar\Api\Concerns;

use App\Http\Requests\Placar\UploadImagemRequest;
use App\Services\Placar\ImagemService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Boilerplate comum aos três endpoints de upload (equipe, time, jogador):
 * extrai, redimensiona/corta, salva por cima da imagem anterior — e devolve
 * 422 com a mensagem certa se algo no meio do caminho for inválido.
 */
trait UploadsPlacarImagem
{
    private function processarImagem(
        ImagemService $imagens,
        UploadImagemRequest $request,
        string $diretorio,
        string $nomeArquivo,
        bool $crop,
        ?string $caminhoAnterior,
    ): string {
        $extraido = $imagens->extrair($request);

        $processado = $crop
            ? $imagens->redimensionarFoto($extraido['binario'], $extraido['extensao'])
            : $imagens->redimensionarLogo($extraido['binario'], $extraido['extensao']);

        $caminho = "{$diretorio}/{$nomeArquivo}.{$extraido['extensao']}";

        return $imagens->salvar($processado, $caminho, $caminhoAnterior);
    }

    private function respostaErroImagem(InvalidArgumentException $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage()], 422);
    }
}
