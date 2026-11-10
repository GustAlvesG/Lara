<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Social\PlacePhoto;
use App\Support\ImageSource;
use Illuminate\Http\JsonResponse;

/**
 * Vitrine pública dos espaços sociais (10/11/2026): o site mostra as
 * churrasqueiras e salões, com a galeria de fotos, para quem ainda não
 * entrou. Só o `api_token` da aplicação — sem `login_token`, como a consulta
 * dos Torneios. Não traz preço de convidado extra, regra de reserva nem nada
 * do sócio: para reservar, o site segue usando `/api/social/*` com login.
 *
 * Imagens: o navegador de quem visita não alcança o Lara. Cada imagem vem com
 * o `path` relativo (arquivo estático do Lara, para o servidor do site buscar
 * e servir no domínio dele) e a `url` montada com o host da chamada. Capa que
 * é link externo (imgur…) vem com `path` nulo e `remote: true` — essa o
 * navegador abre direto.
 */
class SocialShowcaseApiController extends Controller
{
    /** Grupos sociais ativos com seus espaços ativos e as fotos visíveis. */
    public function index(): JsonResponse
    {
        $groups = PlaceGroup::social()
            ->with(['places' => fn ($q) => $q->where('status_id', 1)->with(['photos' => fn ($p) => $p->where('active', true)])])
            ->orderBy('name')
            ->get()
            ->filter(fn (PlaceGroup $group) => $group->places->isNotEmpty())
            ->map(fn (PlaceGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'cover' => $this->image($group->image_horizontal ?: $group->image_vertical),
                'places' => $group->places->map(fn (Place $place) => $this->place($place))->values(),
            ])
            ->values();

        return response()->json(['groups' => $groups]);
    }

    /** Um espaço social ativo, com a galeria. */
    public function show(int $id): JsonResponse
    {
        $place = Place::with(['group', 'photos' => fn ($p) => $p->where('active', true)])->where('status_id', 1)->find($id);

        if (! $place || ! $place->isSocial()) {
            return response()->json(['message' => 'Espaço social não encontrado.'], 404);
        }

        return response()->json($this->place($place) + ['group' => ['id' => $place->group->id, 'name' => $place->group->name]]);
    }

    private function place(Place $place): array
    {
        return [
            'id' => $place->id,
            'name' => $place->name,
            'description' => $place->description,
            'amenities' => $place->amenities,
            'capacity' => $place->capacity,
            'price' => (float) $place->price,
            'cover' => $this->image($place->image),
            'photos' => $place->photos->map(fn (PlacePhoto $photo) => $photo->toApi())->values(),
        ];
    }

    /** @return array{path: ?string, url: string, remote: bool}|null */
    private function image(?string $value): ?array
    {
        if (blank($value)) {
            return null;
        }

        $remote = ImageSource::isRemote($value);

        return [
            'path' => $remote ? null : 'images/' . ltrim($value, '/'),
            'url' => ImageSource::url($value),
            'remote' => $remote,
        ];
    }
}
