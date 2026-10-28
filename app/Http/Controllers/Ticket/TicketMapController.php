<?php

namespace App\Http\Controllers\Ticket;

use App\Exceptions\TicketException;
use App\Http\Controllers\Controller;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketMap;
use App\Models\Ticket\TicketSeat;
use App\Services\Ticket\TicketMapService;
use App\Services\Ticket\TicketPresenter;
use App\Services\Ticket\TicketSeatService;
use App\Support\ImageSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Mapas de lugar marcado (mesas, bistrôs, camarotes) no painel.
 *
 *   - lista, criar (em branco ou copiando outro mapa), editar o desenho e
 *     apagar: `ingressos.gerenciar`;
 *   - ocupação (quem está em cada lugar), o JSON do mapa para o seletor da
 *     emissão e a troca de lugar de um ingresso: `ingressos.vendas`.
 */
class TicketMapController extends Controller
{
    public function __construct(
        private TicketMapService $maps,
        private TicketSeatService $seats,
        private TicketPresenter $presenter,
    ) {
    }

    public function index(TicketEvent $event): View
    {
        $event->load(['maps' => fn ($q) => $q->withCount('seats')->with('types.day'), 'days']);

        return view('tickets.maps.index', [
            'event' => $event,
            // Para copiar: os mapas de qualquer evento, do mais novo ao mais antigo.
            'sources' => TicketMap::with('event')->withCount('seats')
                ->whereHas('event')
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
        ]);
    }

    public function store(Request $request, TicketEvent $event): RedirectResponse
    {
        $data = $this->validated($request);
        $copyFrom = $request->filled('copy_from') ? TicketMap::with('seats')->findOrFail($request->integer('copy_from')) : null;

        $map = $this->maps->saveMap($event, $data, copyFrom: $copyFrom);

        // As mesas cativas vêm junto na cópia: o aviso leva para a tela delas.
        $captives = $copyFrom ? $map->seats()->whereNotNull('captive_title')->count() : 0;

        return redirect()->route('tickets.maps.edit', [$event, $map])
            ->with('success', $copyFrom
                ? "Mapa {$map->name} criado com os lugares e áreas de {$copyFrom->name}."
                : "Mapa {$map->name} criado. Desenhe as áreas e os lugares.")
            ->with('warning', $captives > 0
                ? "{$captives} mesa(s) cativa(s) vieram junto do mapa copiado. Confira em Mesas cativas (tire as que não valem para este evento) e o prazo de liberação."
                : null);
    }

    public function edit(TicketEvent $event, TicketMap $map): View
    {
        $map->load(['seats' => fn ($q) => $q->withCount('tickets'), 'types.day']);

        return view('tickets.maps.edit', [
            'event' => $event,
            'map' => $map,
            'layout' => [
                'width' => $map->width,
                'height' => $map->height,
                'background_url' => $map->backgroundUrl(),
                'areas' => array_values($map->areas ?? []),
                'seats' => $map->seats->map(fn (TicketSeat $s) => $s->only(['id', 'label', 'category', 'shape', 'x', 'y', 'w', 'h', 'places', 'active', 'captive_title']) + [
                    // Já teve ingresso: rótulo e categoria travados, não apaga.
                    'locked' => $s->tickets_count > 0,
                ])->values(),
            ],
        ]);
    }

    /** Nome, tamanho e imagem de fundo. */
    public function update(Request $request, TicketEvent $event, TicketMap $map): RedirectResponse
    {
        $this->maps->saveMap($event, $this->validated($request, $map), $map);

        return redirect()->route('tickets.maps.edit', [$event, $map])->with('success', 'Mapa atualizado.');
    }

    /** O desenho inteiro, em JSON (`layout`), como o editor manda. */
    public function layout(Request $request, TicketEvent $event, TicketMap $map): RedirectResponse
    {
        $request->validate(['layout' => 'required|string|max:2000000']);
        $layout = json_decode($request->input('layout'), true);

        if (! is_array($layout) || ! is_array($layout['seats'] ?? null) || ! is_array($layout['areas'] ?? [])) {
            throw ValidationException::withMessages(['layout' => 'O desenho não chegou inteiro. Tente salvar de novo.']);
        }

        $result = $this->maps->saveLayout($map, $layout['seats'], $layout['areas'] ?? []);

        return redirect()->route('tickets.maps.edit', [$event, $map])->with('success', "Desenho salvo: {$result['created']} lugar(es) novo(s), {$result['updated']} mantido(s), {$result['deleted']} apagado(s).");
    }

    public function destroy(TicketEvent $event, TicketMap $map): RedirectResponse
    {
        $this->maps->deleteMap($map);

        return redirect()->route('tickets.maps.index', $event)->with('success', 'Mapa apagado.');
    }

    /** Quem está em cada lugar, num dia (evento de vários dias: `?dia=`). */
    public function occupancy(Request $request, TicketEvent $event, TicketMap $map): View
    {
        $event->load('days');

        return view('tickets.maps.occupancy', [
            'event' => $event,
            'map' => $map,
            'day' => $this->day($request, $event),
        ]);
    }

    /** O mapa com a situação dos lugares, para o seletor da emissão e a ocupação. */
    public function state(Request $request, TicketEvent $event, TicketMap $map): JsonResponse
    {
        $event->load('days');

        return response()->json($this->presenter->seatMap($map, $this->day($request, $event), panel: true));
    }

    /** Troca o lugar de um ingresso de lugar marcado (mesmo tipo, lugar livre). */
    public function changeSeat(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['seat_id' => 'required|integer'], [], ['seat_id' => 'lugar']);
        $seat = TicketSeat::findOrFail($data['seat_id']);

        try {
            $ticket = $this->seats->changeSeat($ticket, $seat, $request->user());
        } catch (TicketException $e) {
            throw ValidationException::withMessages(['seat_id' => $e->getMessage()]);
        }

        return redirect()->route('tickets.orders.show', $ticket->ticket_order_id)->with('success', "Lugar trocado para {$seat->label}.");
    }

    private function day(Request $request, TicketEvent $event)
    {
        if (! $event->hasDays()) {
            return null;
        }

        return $event->days->firstWhere('id', $request->integer('dia')) ?? $event->days->first();
    }

    private function validated(Request $request, ?TicketMap $map = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'width' => 'nullable|integer|min:' . TicketMap::MIN_SIZE . '|max:' . TicketMap::MAX_SIZE,
            'height' => 'nullable|integer|min:' . TicketMap::MIN_SIZE . '|max:' . TicketMap::MAX_SIZE,
            'sort_order' => 'nullable|integer|min:0|max:999',
            'background' => 'nullable|image|max:4096',
            'background_url' => 'nullable|string|max:255',
            'remove_background' => 'nullable|boolean',
            'copy_from' => 'nullable|integer',
        ], [], ['name' => 'nome', 'width' => 'largura', 'height' => 'altura', 'background' => 'imagem de fundo']);

        $out = ['name' => $data['name']];
        foreach (['width', 'height', 'sort_order'] as $field) {
            if (isset($data[$field])) {
                $out[$field] = (int) $data[$field];
            }
        }

        $background = $request->boolean('remove_background') ? null : ImageSource::resolve($request, 'background', $map?->background);
        if ($map || $background !== null) {
            $out['background'] = $background;
        }

        return $out;
    }
}
