<?php

namespace App\Http\Controllers\Replay\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Replay\VideoResource;
use App\Models\Member;
use App\Models\Place;
use App\Models\Replay\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O que o site de locação de espaços consome.
 *
 * São dois públicos, com regras diferentes:
 *
 *  - A GALERIA DA QUADRA é aberta: qualquer visitante vê e baixa os vídeos
 *    de uma quadra, inclusive os que foram gravados durante uma reserva. Foi
 *    a decisão de quem opera — o replay é do jogo, e o jogo aconteceu em
 *    espaço coletivo. Nenhum dado do sócio sai nessa listagem.
 *  - MEUS VÍDEOS exige o login do sócio (JWT `login_token`) e devolve só o
 *    que está amarrado a ele. É o destino do link do e-mail.
 */
class PortalController extends Controller
{
    /** Quantos vídeos por página — a galeria carrega em rolagem infinita. */
    const PER_PAGE = 24;

    /**
     * Quadras que têm vídeo disponível agora.
     *
     * Só as que têm: uma lista com dezenas de quadras vazias faria o visitante
     * procurar vídeo onde não há.
     */
    public function places(): JsonResponse
    {
        $counts = Video::available()
            ->selectRaw('place_id, COUNT(*) as videos_count, MAX(recorded_at) as last_recorded_at')
            ->groupBy('place_id')
            ->get()
            ->keyBy('place_id');

        $places = Place::with('group')->whereIn('id', $counts->keys())->get();

        $payload = $places->map(fn (Place $place) => [
            'id' => $place->id,
            'name' => $place->name,
            'place_group' => [
                'id' => $place->place_group_id,
                'name' => $place->group?->name,
            ],
            'videos_count' => (int) $counts[$place->id]->videos_count,
            'last_recorded_at' => optional($counts[$place->id]->last_recorded_at)
                ? \Carbon\Carbon::parse($counts[$place->id]->last_recorded_at)->toIso8601String()
                : null,
        ])->sortBy('name')->values();

        return response()->json(['places' => $payload]);
    }

    /**
     * Vídeos de uma quadra, do mais recente para o mais antigo.
     *
     * `date` (Y-m-d) filtra o dia, que é como as pessoas procuram: "o jogo de
     * terça".
     */
    public function placeVideos(Request $request, Place $place): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $videos = Video::available()
            ->with(['place', 'group'])
            ->where('place_id', $place->id)
            ->when($request->filled('date'), fn ($query) => $query->whereDate('recorded_at', $request->input('date')))
            ->orderByDesc('recorded_at')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'place' => [
                'id' => $place->id,
                'name' => $place->name,
                'place_group' => [
                    'id' => $place->place_group_id,
                    'name' => $place->group?->name,
                ],
            ],
            'videos' => VideoResource::collection($videos->items())->resolve(),
            'meta' => [
                'current_page' => $videos->currentPage(),
                'last_page' => $videos->lastPage(),
                'total' => $videos->total(),
            ],
        ]);
    }

    /**
     * Os vídeos do sócio logado.
     *
     * O sócio sai do próprio token (o `username` do JWT é o CPF), nunca de um
     * id vindo na URL: com id na URL, trocar o número na barra de endereços
     * daria acesso aos vídeos de outro sócio. Mesmo cuidado do
     * ScheduleController::indexByMember.
     */
    public function myVideos(Request $request): JsonResponse
    {
        $session = $request->input('user');
        $cpf = is_array($session) ? ($session['username'] ?? null) : null;

        if (! $cpf) {
            return response()->json(['message' => 'Sessão inválida.'], 401);
        }

        $member = Member::where('cpf', $cpf)->first();

        if (! $member) {
            return response()->json(['message' => 'Sócio não encontrado.'], 404);
        }

        $videos = Video::available()
            ->with(['place', 'group'])
            ->where('member_id', $member->id)
            ->orderByDesc('recorded_at')
            ->get();

        return response()->json([
            'videos' => VideoResource::collection($videos)->resolve(),
        ]);
    }
}
