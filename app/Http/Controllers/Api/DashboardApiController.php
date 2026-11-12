<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Ticket\TicketEvent;
use App\Services\Ticket\TicketDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboards do site (Next.js, área /painel): só o sócio com perfil
 * `administrador` (middlewares `api_token` + `login_token` + `member_admin`).
 * O perfil é dado em Reservas › Contas do site.
 *
 * Por enquanto um painel só, o dos ingressos. Painel novo (MultiVendas,
 * MultiClubes) entra em PANELS e ganha o prefixo próprio em routes/api.php.
 *
 * Ver docs/funcionalidades/dashboards-site.md.
 */
class DashboardApiController extends Controller
{
    /** Painéis que o administrador vê, na ordem do menu do site. */
    public const PANELS = [
        ['key' => 'ingressos', 'label' => 'Ingressos', 'path' => 'tickets'],
    ];

    public function __construct(private TicketDashboardService $tickets)
    {
    }

    /** GET /api/dashboard/access — quem é e o que o menu mostra. */
    public function access(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->attributes->get('member');

        return response()->json([
            'admin' => true,
            'member' => ['name' => $member->name, 'title' => $member->title],
            'panels' => self::PANELS,
        ]);
    }

    /** GET /api/dashboard/tickets/events */
    public function ticketEvents(): JsonResponse
    {
        return response()->json($this->tickets->events());
    }

    /** GET /api/dashboard/tickets/events/{id}/summary */
    public function ticketSummary(int $id): JsonResponse
    {
        return response()->json($this->tickets->summary($this->event($id)));
    }

    /** GET /api/dashboard/tickets/events/{id}/sales-by-day */
    public function ticketSalesByDay(int $id): JsonResponse
    {
        return response()->json($this->tickets->salesByDay($this->event($id)));
    }

    /** GET /api/dashboard/tickets/events/{id}/by-lot */
    public function ticketByLot(int $id): JsonResponse
    {
        return response()->json($this->tickets->byLot($this->event($id)));
    }

    /** GET /api/dashboard/tickets/events/{id}/by-channel */
    public function ticketByChannel(int $id): JsonResponse
    {
        return response()->json($this->tickets->byChannel($this->event($id)));
    }

    /** GET /api/dashboard/tickets/events/{id}/by-method */
    public function ticketByMethod(int $id): JsonResponse
    {
        return response()->json($this->tickets->byMethod($this->event($id)));
    }

    /** Rascunho e apagado respondem 404, como evento inexistente. */
    private function event(int $id): TicketEvent
    {
        $event = $this->tickets->find($id);

        abort_unless($event, 404, 'Evento não encontrado.');

        return $event;
    }
}
