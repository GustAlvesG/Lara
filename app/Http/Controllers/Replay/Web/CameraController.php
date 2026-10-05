<?php

namespace App\Http\Controllers\Replay\Web;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\Replay\Camera;
use App\Services\Replay\ReplayResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro das câmeras — a ponte entre o equipamento instalado na quadra e o
 * que o Lara sabe.
 *
 * O `external_id` é o identificador do equipamento no sistema de captura, e é
 * por ele que toda a API do Replay endereça a câmera. Quem cadastra precisa
 * pegar esse valor com quem instalou; inventar um aqui significa uma câmera
 * que grava e nunca consegue enviar.
 */
class CameraController extends Controller
{
    public function __construct(private ReplayResolver $resolver)
    {
    }

    public function index()
    {
        $cameras = Camera::with('place.group')->orderBy('name')->get();

        // A configuração efetiva aparece aqui também: é a tela que o pessoal
        // abre quando a câmera "está gravando errado", e ter a resposta ao
        // lado do equipamento poupa a ida à outra aba.
        $resolved = $cameras->mapWithKeys(function (Camera $camera) {
            return [$camera->id => $camera->place
                ? $this->resolver->resolveFor($camera->place)
                : null];
        });

        return view('replay.cameras.index', [
            'cameras' => $cameras,
            'resolved' => $resolved,
            'places' => Place::with('group')->get(),
            'maxPerPlace' => Camera::MAX_PER_PLACE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $this->guardCameraLimit($data['place_id']);

        Camera::create($data + ['active' => $request->boolean('active', true)]);

        return back()->with('success', 'Câmera cadastrada.');
    }

    public function update(Request $request, Camera $camera): RedirectResponse
    {
        $data = $this->validated($request, $camera);

        if ((int) $data['place_id'] !== (int) $camera->place_id) {
            $this->guardCameraLimit($data['place_id']);
        }

        $camera->update($data + ['active' => $request->boolean('active')]);

        return back()->with('success', 'Câmera atualizada.');
    }

    public function destroy(Camera $camera): RedirectResponse
    {
        // Os vídeos já gravados continuam: a FK é nullOnDelete de propósito,
        // porque o clipe pertence à quadra, não ao equipamento que o fez.
        $camera->delete();

        return back()->with('success', 'Câmera removida. Os vídeos já gravados continuam disponíveis.');
    }

    private function validated(Request $request, ?Camera $camera = null): array
    {
        return $request->validate([
            'place_id' => ['required', 'integer', 'exists:places,id'],
            'external_id' => [
                'required', 'string', 'max:255',
                Rule::unique('replay_cameras', 'external_id')->ignore($camera?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
        ], [
            'external_id.unique' => 'Já existe uma câmera com este identificador — ele é único em todo o clube.',
        ], [
            'place_id' => 'quadra',
            'external_id' => 'identificador',
            'name' => 'nome',
        ]);
    }

    /**
     * Duas câmeras por quadra é o teto: campo de futebol tem uma por metade,
     * e nenhum outro espaço do clube precisa de mais. O limite é de negócio,
     * por isso mora aqui e não no banco.
     */
    private function guardCameraLimit(int $placeId): void
    {
        if (Camera::where('place_id', $placeId)->count() >= Camera::MAX_PER_PLACE) {
            throw ValidationException::withMessages([
                'place_id' => 'Esta quadra já tem ' . Camera::MAX_PER_PLACE . ' câmeras — o limite do cadastro.',
            ]);
        }
    }
}
