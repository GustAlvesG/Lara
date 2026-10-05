<?php

namespace App\Http\Controllers\Replay\Web;

use App\Http\Controllers\Controller;
use App\Models\PlaceGroup;
use App\Models\Replay\Camera;
use App\Models\Replay\Setting;
use App\Services\Replay\ReplayResolver;
use App\Support\Replay\Orientation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Configuração de vídeo do Replay — orientação e duração do clipe, por
 * esporte ou por quadra.
 *
 * A tela é uma só, com os esportes e suas quadras aninhadas, porque a
 * pergunta que o Marketing faz é sempre comparativa ("a quadra 3 está
 * diferente das outras?"). Separar em telas de cadastro esconderia
 * exatamente isso.
 *
 * Cada linha mostra a configuração EFETIVA e de onde ela veio. Quem resolve
 * é o ReplayResolver, o mesmo que a API usa — a tela nunca reimplementa a
 * herança, senão um dia mostraria uma coisa e a câmera receberia outra.
 */
class SettingController extends Controller
{
    public function __construct(private ReplayResolver $resolver)
    {
    }

    public function index()
    {
        $groups = PlaceGroup::with('places')->orderBy('name')->get();

        $settings = Setting::all();
        $settingsByGroup = $settings->whereNotNull('place_group_id')->keyBy('place_group_id');
        $settingsByPlace = $settings->whereNotNull('place_id')->keyBy('place_id');

        $camerasByPlace = Camera::orderBy('name')->get()->groupBy('place_id');

        $rows = $groups->map(function (PlaceGroup $group) use ($settingsByGroup, $settingsByPlace, $camerasByPlace) {
            return [
                'group' => $group,
                'setting' => $settingsByGroup->get($group->id),
                'places' => $group->places->map(fn ($place) => [
                    'place' => $place,
                    'own' => $settingsByPlace->get($place->id),
                    'effective' => $this->resolver->settingFor($place),
                    'cameras' => $camerasByPlace->get($place->id, collect()),
                ]),
            ];
        });

        return view('replay.settings.index', [
            'rows' => $rows,
            'orientations' => Orientation::LABELS,
            'minSeconds' => Orientation::MIN_CLIP_SECONDS,
            'maxSeconds' => Orientation::MAX_CLIP_SECONDS,
        ]);
    }

    /**
     * Grava a configuração do esporte ou da quadra.
     *
     * `updateOrCreate` pela chave do dono: a tabela tem unique em
     * place_group_id e em place_id justamente para não existirem duas linhas
     * disputando a mesma quadra.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'owner_type' => ['required', 'in:group,place'],
            'owner_id' => ['required', 'integer'],
            'orientation' => ['required', 'in:' . implode(',', Orientation::ALL)],
            'clip_seconds' => [
                'required',
                'integer',
                'min:' . Orientation::MIN_CLIP_SECONDS,
                'max:' . Orientation::MAX_CLIP_SECONDS,
            ],
        ], [], [
            'clip_seconds' => 'duração do clipe',
            'orientation' => 'orientação',
        ]);

        $key = $data['owner_type'] === 'group'
            ? ['place_group_id' => $data['owner_id'], 'place_id' => null]
            : ['place_id' => $data['owner_id'], 'place_group_id' => null];

        Setting::updateOrCreate($key, [
            'orientation' => $data['orientation'],
            'clip_seconds' => $data['clip_seconds'],
            'active' => true,
        ]);

        return back()->with('success', 'Configuração salva. As câmeras recebem a mudança na próxima consulta.');
    }

    /**
     * Remove a configuração PRÓPRIA da quadra — ela volta a herdar do
     * esporte. Não é "desligar o Replay": é desfazer a exceção.
     */
    public function destroy(Setting $setting): RedirectResponse
    {
        $setting->delete();

        return back()->with('success', 'Configuração própria removida — a quadra voltou a herdar do esporte.');
    }
}
