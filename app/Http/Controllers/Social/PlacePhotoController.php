<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\Social\PlacePhoto;
use App\Services\Social\PlacePhotoService;
use Illuminate\Http\JsonResponse;
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

    /**
     * A tela manda **uma foto por requisição**, já reduzida no navegador
     * (`Accept: application/json`): várias fotos de celular num POST só
     * estouravam o `post_max_size` do PHP, e uma foto sozinha o
     * `upload_max_filesize`. Sem JavaScript, o formulário comum ainda funciona,
     * dentro desses limites.
     */
    public function store(Request $request, int $placeId): RedirectResponse|JsonResponse
    {
        $place = Place::findOrFail($placeId);
        $limitMb = PlacePhotoService::uploadLimitMb();

        $request->validate([
            'photos' => 'required|array|max:' . PlacePhoto::MAX_PER_PLACE,
            'photos.*' => 'file|max:' . PlacePhotoService::MAX_UPLOAD_KB,
        ], [
            'photos.required' => 'Escolha ao menos uma foto.',
            'photos.max' => 'Envie no máximo ' . PlacePhoto::MAX_PER_PLACE . ' fotos de uma vez.',
            'photos.*.max' => "Cada foto pode ter até {$limitMb} MB.",
            'photos.*.file' => 'Uma das fotos não chegou inteira. Tente de novo.',
            // O PHP recusou o arquivo antes do Lara (upload_max_filesize).
            'photos.*.uploaded' => "A foto passa do limite de envio do servidor ({$limitMb} MB por foto). Reduza a foto ou envie pela tela com o navegador atualizado, que reduz sozinho.",
        ]);

        $count = $this->photos->add($place, $request->file('photos', []), $request->user()?->id);
        $message = $count === 1 ? 'Foto adicionada à galeria.' : "{$count} fotos adicionadas à galeria.";

        if ($request->expectsJson()) {
            return response()->json(['count' => $count, 'message' => $message]);
        }

        return $this->back($place->id, $message);
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
