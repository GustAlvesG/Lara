<?php

namespace App\Services\Placar;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Upload do vídeo de apresentação do jogador.
 *
 * Sem transcodificação: seria preciso ffmpeg, dependência externa que este
 * projeto não tem — o arquivo é guardado como veio, depois de validado.
 * Por isso o formato aceito é restrito ao que todo navegador moderno toca
 * nativamente (mp4/webm), senão o telão receberia um arquivo que não
 * consegue reproduzir.
 *
 * O tipo é conferido pelos bytes reais (assinatura do container), nunca
 * pelo Content-Type nem pela extensão do nome — os dois são declarados
 * pelo cliente e não provam nada. Mesma postura do ImagemService.
 */
class VideoService
{
    const EXTENSOES = ['mp4', 'webm'];

    /**
     * 28MB. O teto real é o `post_max_size`/`upload_max_filesize` do PHP
     * (30M neste servidor): a margem existe para que o arquivo no limite
     * ainda caiba junto com o resto do corpo do POST, e para que o erro
     * seja uma mensagem nossa em vez de um request truncado pelo PHP antes
     * de chegar na aplicação.
     */
    const TAMANHO_MAXIMO_BYTES = 28 * 1024 * 1024;

    /**
     * Valida e grava; devolve o caminho relativo gravado.
     * Substitui o vídeo anterior, inclusive quando muda de extensão.
     */
    public function salvar(Request $request, string $diretorio, ?string $caminhoAnterior, string $campo = 'video'): string
    {
        $arquivo = $request->file($campo);

        if (!$arquivo instanceof UploadedFile) {
            throw new InvalidArgumentException("Envie o vídeo no campo `{$campo}`.");
        }

        if (!$arquivo->isValid()) {
            // Caso clássico: o arquivo estourou o limite do PHP e chegou
            // truncado. A mensagem genérica do framework não ajuda quem
            // está enviando, então nomeamos o limite.
            throw new InvalidArgumentException(
                'Upload falhou — provavelmente o arquivo passou do limite do servidor (' . ini_get('upload_max_filesize') . ').'
            );
        }

        if ($arquivo->getSize() > self::TAMANHO_MAXIMO_BYTES) {
            $limiteMb = intdiv(self::TAMANHO_MAXIMO_BYTES, 1024 * 1024);
            throw new InvalidArgumentException("Vídeo maior que {$limiteMb}MB.");
        }

        $extensao = $this->detectarExtensao($arquivo->getRealPath());

        if ($caminhoAnterior) {
            Storage::disk(ImagemService::DISCO)->delete($caminhoAnterior);
        }

        $caminho = "{$diretorio}/video.{$extensao}";

        Storage::disk(ImagemService::DISCO)->put($caminho, file_get_contents($arquivo->getRealPath()));

        return $caminho;
    }

    public function remover(?string $caminho): void
    {
        if ($caminho) {
            Storage::disk(ImagemService::DISCO)->delete($caminho);
        }
    }

    /**
     * Assinatura do container, lida do início do arquivo:
     *  - MP4/MOV (ISO BMFF): bytes 4..7 são "ftyp".
     *  - WebM (Matroska): começa com o EBML magic 1A 45 DF A3.
     */
    private function detectarExtensao(string $caminhoFisico): string
    {
        $inicio = (string) file_get_contents($caminhoFisico, false, null, 0, 16);

        if (strlen($inicio) >= 12 && substr($inicio, 4, 4) === 'ftyp') {
            return 'mp4';
        }

        if (str_starts_with($inicio, "\x1A\x45\xDF\xA3")) {
            return 'webm';
        }

        throw new InvalidArgumentException('Formato não aceito — envie um vídeo mp4 ou webm.');
    }
}
