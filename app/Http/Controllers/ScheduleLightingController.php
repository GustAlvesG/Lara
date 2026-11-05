<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Services\HomeAssistant\ManualCommandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * "Acender a luz" de uma quadra pela agenda de reservas (/schedule).
 *
 * Não inventa regra nova: vira o mesmo comando manual do painel do Home
 * Assistant, só que com prazo em minutos. Como um comando manual substitui o
 * anterior, acender uma quadra já acesa renova o prazo para a nova minutagem
 * a partir de agora — inclusive para menos, se for o que o atendente pediu.
 *
 * O Lara continua sem acionar o relé: o Home Assistant aplica na próxima
 * consulta ao endpoint de automação.
 */
class ScheduleLightingController extends Controller
{
    /** Origem gravada no comando, para o painel saber de onde veio. */
    public const ORIGIN = 'Agenda de reservas';

    public function __construct(private ManualCommandService $commands)
    {
    }

    public function store(Request $request, int $place): RedirectResponse
    {
        $max = (int) config('home_assistant.manual_max_minutes', 720);

        // A agenda não exibe $errors, só os avisos de sessão: a recusa volta
        // como aviso, com o texto da regra.
        $validator = Validator::make($request->all(), [
            'minutes' => ['required', 'integer', 'min:1', 'max:' . $max],
        ], [
            'minutes.required' => 'Informe por quantos minutos a luz deve ficar acesa.',
            'minutes.integer'  => 'Informe os minutos em número inteiro.',
            'minutes.min'      => 'Informe ao menos 1 minuto.',
            'minutes.max'      => "A luz pode ser acesa por no máximo {$max} minutos de uma vez.",
        ]);

        if ($validator->fails()) {
            return back()->with('warning', $validator->errors()->first());
        }

        $data = $validator->validated();

        $place = Place::with('contactor')->findOrFail($place);

        if (! $place->contactor) {
            return back()->with('warning', "{$place->name} não tem luz ligada ao sistema.");
        }

        $override = $this->commands->apply(
            $place->contactor,
            'on',
            (int) $data['minutes'],
            self::ORIGIN,
            $request->user()?->id,
        );

        $until = $override->expires_at->format('H:i');

        return back()->with('success', "Luz de {$place->name} acesa até {$until}.");
    }
}
