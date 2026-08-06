<?php

namespace App\Http\Controllers\Placar\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Placar\Api\Concerns\UploadsPlacarImagem;
use App\Http\Requests\Placar\UploadImagemRequest;
use App\Http\Resources\Placar\TimeElencoJogadorResource;
use App\Http\Resources\Placar\TimeResource;
use App\Models\Placar\Time;
use App\Services\Placar\ImagemService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TimeController extends Controller
{
    use UploadsPlacarImagem;

    /**
     * GET /placar/times?modalidade=basquete&equipe_id=&categoria=&busca=
     * Para o operador escolher no modo avulso — sem paginação, a lista já
     * sai filtrada o suficiente para caber numa tela.
     */
    public function index(Request $request)
    {
        $times = Time::query()
            ->ativos()
            ->with(['equipe', 'modalidade'])
            ->daModalidade($request->query('modalidade'))
            ->when($request->filled('equipe_id'), fn ($query) => $query->where('equipe_id', $request->query('equipe_id')))
            ->when($request->filled('categoria'), fn ($query) => $query->where('categoria', $request->query('categoria')))
            ->busca($request->query('busca'))
            ->orderBy('categoria')
            ->get();

        return TimeResource::collection($times);
    }

    /** GET /placar/times/{time}/elenco?temporada=2026 */
    public function elenco(Request $request, Time $time)
    {
        $elencos = $time->elencos()
            ->daTemporada($request->query('temporada'))
            ->where('ativo', true)
            ->with('jogador')
            ->get();

        return TimeElencoJogadorResource::collection($elencos);
    }

    /** POST /placar/times/{time}/logo — multipart `arquivo` ou `arquivo_base64`. */
    public function storeLogo(UploadImagemRequest $request, Time $time, ImagemService $imagens)
    {
        try {
            $caminho = $this->processarImagem(
                $imagens, $request, "placar/times/{$time->id}", 'logo', crop: false, caminhoAnterior: $time->logo_path,
            );
        } catch (InvalidArgumentException $e) {
            return $this->respostaErroImagem($e);
        }

        $time->update(['logo_path' => $caminho]);

        return new TimeResource($time->load(['equipe', 'modalidade']));
    }

    /** DELETE /placar/times/{time}/logo */
    public function destroyLogo(Time $time, ImagemService $imagens)
    {
        $imagens->remover($time->logo_path);
        $time->update(['logo_path' => null]);

        return new TimeResource($time->load(['equipe', 'modalidade']));
    }
}
