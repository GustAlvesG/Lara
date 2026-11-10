<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\Social\PlacePhoto;
use App\Services\Social\PlacePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Galeria de fotos do espaço social, na tela de edição do espaço
 * (Reservas › Locais e Grupos). Permissão `reservas.configurar`, a mesma do
 * cadastro do espaço (o grupo de rotas já exige).
 */
class PlacePhotoController extends Controller
{
    public function __construct(private PlacePhotoService $photos)
    {
    }

    public function store(Request $request, int $placeId): RedirectResponse
    {
        $place = Place::findOrFail($placeId);

        $request->validate([
            'photos' => 'required|array|max:' . PlacePhoto::MAX_PER_PLACE,
            'photos.*' => 'file|max:' . PlacePhotoService::MAX_UPLOAD_KB,
        ], [
            'photos.required' => 'Escolha ao menos uma foto.',
            'photos.max' => 'Envie no máximo ' . PlacePhoto::MAX_PER_PLACE . ' fotos de uma vez.',
            'photos.*.max' => 'Cada foto pode ter até ' . (PlacePhotoService::MAX_UPLOAD_KB / 1024) . ' MB.',
            'photos.*.file' => 'Uma das fotos não chegou inteira. Tente de novo.',
        ]);

        $count = $this->photos->add($place, $request->file('photos', []), $request->user()?->id);

        return $this->back($place->id, $count === 1 ? 'Foto adicionada à galeria.' : "{$count} fotos adicionadas à galeria.");
    }

    public function update(Request $request, PlacePhoto $photo): RedirectResponse
    {
        $request->validate(['caption' => 'nullable|string|max:120']);

        $this->photos->update($photo, $request->input('caption'), $request->boolean('active'));

        return $this->back($photo->place_id, 'Foto atualizada.');
    }

    public function move(Request $request, PlacePhoto $photo): RedirectResponse
    {
        $request->validate(['direction' => 'required|in:up,down']);

        $this->photos->move($photo, $request->input('direction'));

        return $this->back($photo->place_id);
    }

    public function cover(PlacePhoto $photo): RedirectResponse
    {
        $this->photos->makeCover($photo);

        return $this->back($photo->place_id, 'Foto definida como capa do espaço.');
    }

    public function destroy(PlacePhoto $photo): RedirectResponse
    {
        $placeId = $photo->place_id;
        $this->photos->delete($photo);

        return $this->back($placeId, 'Foto excluída.');
    }

    private function back(int $placeId, ?string $message = null): RedirectResponse
    {
        $redirect = redirect()->to(route('place-group.editPlace', $placeId) . '#galeria');

        return $message ? $redirect->with('success', $message) : $redirect;
    }
}
