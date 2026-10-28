<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TicketException;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketLot;
use App\Models\Ticket\TicketMap;
use App\Models\Ticket\TicketOrder;
use App\Services\MultiClubes\MemberProfile;
use App\Services\Ticket\TicketBuyer;
use Illuminate\Support\Facades\Crypt;
use App\Services\Ticket\TicketOrderService;
use App\Services\Ticket\TicketPdf;
use App\Services\Ticket\TicketPresenter;
use App\Support\Cpf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ingressos pelo site de locação (`/api/tickets/*`).
 *
 * Todas pedem o `api_token`. Dois caminhos de compra:
 *   - sócio: rotas no grupo `login_token` (JWT no header `Session`); o
 *     comprador é sempre o dono do JWT, e compra para as pessoas do título
 *     dele (escolhidas pela `ref` de `my-title`);
 *   - não sócio, sem conta: `guest-orders`; o pedido é acessado com o token
 *     devolvido na criação, no header `Ticket-Order-Token` (pedido alheio ou
 *     token errado: 404 `order_not_found`).
 *
 * Recusas de regra saem com 4xx e `reason` estável (TicketException).
 * Rascunho e evento cancelado não aparecem.
 */
class TicketApiController extends Controller
{
    public const TOKEN_HEADER = 'Ticket-Order-Token';

    public function __construct(
        private TicketOrderService $orders,
        private TicketPresenter $presenter,
        private TicketPdf $pdf,
    ) {
    }

    public function index(): JsonResponse
    {
        $events = $this->visible()->orderBy('starts_at')->get();

        return response()->json($events->map(fn (TicketEvent $e) => $this->presenter->event($e))->values());
    }

    public function show(int $id): JsonResponse
    {
        $event = $this->visible()->find($id);

        return $event ? response()->json($this->presenter->event($event, detail: true)) : $this->eventNotFound();
    }

    /**
     * O mapa de lugares (mesas, bistrôs, camarotes) com a situação de cada
     * lugar no dia (`?day_id=`, obrigatório no evento de vários dias). O site
     * recarrega a cada `refresh_after` segundos enquanto a pessoa escolhe; a
     * garantia contra a venda dupla é a do pedido (`seat_taken`).
     *
     * Mesa cativa: aqui (sem login) sai `captive` para todo mundo; o sócio
     * logado usa `mapMember`, em que a cativa da matrícula dele sai livre.
     */
    public function map(Request $request, int $id, int $mapId): JsonResponse
    {
        return $this->seatMap($request, $id, $mapId);
    }

    /** O mesmo mapa, visto pelo sócio do JWT: a mesa cativa da matrícula dele aparece livre (`captive_mine`). */
    public function mapMember(Request $request, int $id, int $mapId): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        return $this->seatMap($request, $id, $mapId, (string) $member->title);
    }

    private function seatMap(Request $request, int $id, int $mapId, ?string $title = null): JsonResponse
    {
        $event = $this->visible()->with('days')->find($id);
        if (! $event) {
            return $this->eventNotFound();
        }

        $map = $event->types->where('active', true)->contains('ticket_map_id', $mapId)
            ? TicketMap::where('ticket_event_id', $event->id)->find($mapId)
            : null;
        if (! $map) {
            return response()->json(['message' => 'Mapa não encontrado.', 'reason' => TicketException::MAP_NOT_FOUND], 404);
        }

        $day = null;
        if ($event->hasDays()) {
            $day = $event->days->firstWhere('id', $request->integer('day_id'));
            if (! $day) {
                return response()->json(['message' => 'Escolha o dia: os lugares são vendidos por dia.', 'reason' => TicketException::DAY_REQUIRED], 422);
            }
        }

        return response()->json($this->presenter->seatMap($map, $day, title: $title));
    }

    /**
     * Confere um cupom antes do checkout: `{code, buyer_kind: member|guest}`.
     * Só informa; o desconto vale de fato no pedido, que confere de novo.
     */
    public function checkCoupon(Request $request, int $id): JsonResponse
    {
        $event = $this->visible()->find($id);
        if (! $event) {
            return $this->eventNotFound();
        }

        $data = Validator::make($request->all(), [
            'code' => 'required|string|max:40',
            'buyer_kind' => 'required|in:' . TicketOrder::BUYER_MEMBER . ',' . TicketOrder::BUYER_GUEST,
        ])->validate();

        $priceKind = $data['buyer_kind'] === TicketOrder::BUYER_MEMBER ? TicketLot::PRICE_MEMBER : TicketLot::PRICE_NON_MEMBER;

        try {
            $coupon = $this->orders->checkCoupon($event, $data['code'], $priceKind);
        } catch (TicketException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json($this->presenter->coupon($coupon));
    }

    /* ---------------------------------------------------------------- sócio */

    /**
     * As pessoas do título do sócio logado (titular e dependentes ativos), para
     * escolher para quem comprar. Sai a `ref` (CPF e matrícula cifrados) em vez
     * do CPF. Com `?event_id=`, diz quem já tem ingresso naquele evento.
     */
    public function myTitle(Request $request): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        try {
            $buyer = $this->orders->buyerFor($member);
        } catch (TicketException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        $family = $this->orders->titleMembers($buyer->title, $buyer);
        $event = $request->filled('event_id') ? TicketEvent::with('days')->find($request->integer('event_id')) : null;
        // cpf => dias do tipo de cada ingresso ativo (null = todos os dias).
        $taken = $event
            ? Ticket::occupying()->people()->with('type')->where('ticket_event_id', $event->id)
                ->whereIn('holder_cpf', array_map(fn ($key) => str_pad((string) $key, 11, '0', STR_PAD_LEFT), array_keys($family)))
                ->get()->groupBy('holder_cpf')->map(fn ($tickets) => $tickets->map(fn (Ticket $t) => $t->type->ticket_event_day_id)->all())->all()
            : [];
        // Os dias já cobertos: o passaporte (ou o ingresso do evento sem dias) cobre todos.
        $daysTaken = fn (array $days) => in_array(null, $days, true)
            ? $event->days->pluck('id')->all()
            : array_values(array_unique($days));

        return response()->json([
            'title' => $buyer->title,
            // A chave do array é o CPF, que o PHP pode ter virado número: volta a texto.
            'people' => collect($family)->map(function (MemberProfile $p, $key) use ($buyer, $taken, $daysTaken) {
                $cpf = str_pad((string) $key, 11, '0', STR_PAD_LEFT);

                return [
                    'ref' => Crypt::encryptString($cpf . '|' . $buyer->title),
                    'name' => $p->name,
                    'cpf' => Cpf::mask($cpf),
                    'titular' => $p->titular,
                    'is_me' => $cpf === $buyer->cpf,
                    'has_ticket' => isset($taken[$cpf]),
                    // Evento de vários dias: os dias em que a pessoa já tem ingresso.
                    'days_taken' => isset($taken[$cpf]) ? $daysTaken($taken[$cpf]) : [],
                ];
            })->sortByDesc('is_me')->values(),
        ]);
    }

    /**
     * O sócio compra para as pessoas do título dele:
     * `{tickets: [{type_id, holder_ref?}], coupon_code?}` — sem `holder_ref`,
     * o ingresso é dele mesmo. `{type_id}` sozinho (um ingresso, para ele)
     * continua aceito. Tipo de lugar marcado: `seat_id` do mapa (a pessoa
     * da `ref` é a responsável pela mesa).
     */
    public function storeMember(Request $request, int $id): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        $event = $this->visible()->find($id);
        if (! $event) {
            return $this->eventNotFound();
        }

        $data = Validator::make($request->except(['user', 'member_id', 'cpf']), [
            'type_id' => 'required_without:tickets|nullable|integer',
            'tickets' => 'required_without:type_id|nullable|array|min:1|max:50',
            'tickets.*.type_id' => 'required|integer',
            'tickets.*.holder_ref' => 'nullable|string|max:1000',
            'tickets.*.seat_id' => 'nullable|integer',
            'coupon_code' => 'nullable|string|max:40',
        ])->validate();

        try {
            $buyer = $this->orders->buyerFor($member);
            $items = [];
            foreach (array_values($data['tickets'] ?? [['type_id' => $data['type_id']]]) as $index => $item) {
                $items[] = [
                    'type_id' => (int) $item['type_id'],
                    'holder_cpf' => $this->cpfFromRef($item['holder_ref'] ?? null, $buyer->title, $index),
                    'seat_id' => isset($item['seat_id']) ? (int) $item['seat_id'] : null,
                ];
            }
            $order = $this->orders->create($event, $buyer, $items, couponCode: $data['coupon_code'] ?? null);
        } catch (TicketException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json($this->presenter->order($order), 201);
    }

    /** O CPF de uma `ref` de `my-title`; tem de ser do título de quem compra. */
    private function cpfFromRef(?string $ref, ?string $title, int $index): ?string
    {
        if (blank($ref)) {
            return null;
        }

        try {
            [$cpf, $refTitle] = explode('|', Crypt::decryptString($ref), 2) + [null, null];
        } catch (\Throwable) {
            $cpf = $refTitle = null;
        }

        if (! $cpf || (string) $refTitle !== (string) $title) {
            throw new TicketException(TicketException::INVALID_HOLDER, 'Pessoa não encontrada no seu título. Atualize a lista e tente de novo.', 422, ['index' => $index, 'field' => 'holder_ref']);
        }

        return $cpf;
    }

    public function mine(Request $request): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        $orders = TicketOrder::with(['event', 'tickets.type', 'tickets.lot', 'payment'])
            ->where('member_id', $member->id)
            ->orderByDesc('id')
            ->get();

        return response()->json($orders->map(fn (TicketOrder $o) => $this->presenter->order($o))->values());
    }

    public function showMember(Request $request, int $id): JsonResponse
    {
        $order = $this->memberOrder($request, $id);

        return $order instanceof TicketOrder ? response()->json($this->presenter->order($order)) : $order;
    }

    public function payMember(Request $request, int $id): JsonResponse
    {
        $order = $this->memberOrder($request, $id);

        return $order instanceof TicketOrder ? $this->pay($request, $order) : $order;
    }

    public function withdrawMember(Request $request, int $id): JsonResponse
    {
        $order = $this->memberOrder($request, $id);

        return $order instanceof TicketOrder ? $this->withdraw($order) : $order;
    }

    public function pdfMember(Request $request, int $id): Response
    {
        $order = $this->memberOrder($request, $id);

        return $order instanceof TicketOrder ? $this->pdfResponse($order) : $order;
    }

    /* ------------------------------------------------------------ não sócio */

    /**
     * Pedido sem conta: `{buyer: {cpf, name, email, phone}, tickets: [{type_id, holder_name, holder_cpf}]}`.
     * Devolve o pedido com `access_token` — a única vez que ele sai pela API.
     */
    public function storeGuest(Request $request, int $id): JsonResponse
    {
        $event = $this->visible()->find($id);
        if (! $event) {
            return $this->eventNotFound();
        }

        $data = Validator::make($request->all(), [
            'buyer' => 'required|array',
            'buyer.cpf' => 'required|string|max:20',
            'buyer.name' => 'required|string|min:3|max:255',
            'buyer.email' => 'required|email|max:255',
            'buyer.phone' => 'nullable|string|max:30',
            'tickets' => 'required|array|min:1',
            'tickets.*.type_id' => 'required|integer',
            'tickets.*.holder_name' => 'required|string|max:255',
            'tickets.*.holder_cpf' => 'required|string|max:20',
            // Tipo de lugar marcado: o lugar do mapa; o titular é o responsável.
            'tickets.*.seat_id' => 'nullable|integer',
            'coupon_code' => 'nullable|string|max:40',
        ])->validate();

        if (! Cpf::isValid(Cpf::digits($data['buyer']['cpf']))) {
            return response()->json(['message' => 'CPF de quem compra é inválido.', 'reason' => TicketException::INVALID_HOLDER, 'context' => ['field' => 'buyer.cpf']], 422);
        }

        $buyer = TicketBuyer::guest($data['buyer']['cpf'], $data['buyer']['name'], $data['buyer']['email'], $data['buyer']['phone'] ?? null);

        try {
            $order = $this->orders->create($event, $buyer, $data['tickets'], couponCode: $data['coupon_code'] ?? null);
        } catch (TicketException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json($this->presenter->order($order, withToken: true), 201);
    }

    public function showGuest(Request $request, int $id): JsonResponse
    {
        $order = $this->guestOrder($request, $id);

        return $order instanceof TicketOrder ? response()->json($this->presenter->order($order)) : $order;
    }

    public function payGuest(Request $request, int $id): JsonResponse
    {
        $order = $this->guestOrder($request, $id);

        return $order instanceof TicketOrder ? $this->pay($request, $order) : $order;
    }

    public function withdrawGuest(Request $request, int $id): JsonResponse
    {
        $order = $this->guestOrder($request, $id);

        return $order instanceof TicketOrder ? $this->withdraw($order) : $order;
    }

    public function pdfGuest(Request $request, int $id): Response
    {
        $order = $this->guestOrder($request, $id);

        return $order instanceof TicketOrder ? $this->pdfResponse($order) : $order;
    }

    /**
     * "Perdi o link": reenvia por e-mail o link dos pedidos ativos daquele CPF
     * e e-mail. A resposta é sempre a mesma, exista pedido ou não — não diz a
     * ninguém quem comprou.
     */
    public function resendGuestLink(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'cpf' => 'required|string|max:20',
            'email' => 'required|email|max:255',
        ])->validate();

        TicketOrder::where('buyer_kind', TicketOrder::BUYER_GUEST)
            ->where('buyer_cpf', Cpf::digits($data['cpf']))
            ->where('buyer_email', trim($data['email']))
            ->whereIn('status', [TicketOrder::STATUS_PENDING_PAYMENT, TicketOrder::STATUS_PAID])
            ->whereHas('event', fn ($q) => $q->where('ends_at', '>=', now()))
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->each(fn (TicketOrder $order) => $this->orders->resendLink($order));

        return response()->json(['message' => 'Se houver pedido com esse CPF e e-mail, o link foi enviado.']);
    }

    /* -------------------------------------------------------------- comuns */

    private function pay(Request $request, TicketOrder $order): JsonResponse
    {
        $data = Validator::make($request->except(['user', 'member_id', 'cpf']), [
            'payment_method' => 'required|string|max:40',
            'paid_amount' => 'required|numeric|min:0',
            'payment_integration_id' => 'required|string|max:255',
            'paid_at' => 'nullable|date',
        ])->validate();

        try {
            $result = $this->orders->pay($order, $data);
        } catch (TicketException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'message' => $result['created'] ? 'Pagamento registrado.' : 'Pagamento já registrado.',
            'order' => $this->presenter->order($order->fresh()),
        ], $result['created'] ? 201 : 200);
    }

    private function withdraw(TicketOrder $order): JsonResponse
    {
        try {
            $this->orders->withdraw($order);
        } catch (TicketException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json($this->presenter->order($order->fresh()));
    }

    private function pdfResponse(TicketOrder $order): Response
    {
        if ($order->status !== TicketOrder::STATUS_PAID) {
            return response()->json(['message' => 'Os ingressos ficam disponíveis depois do pagamento.', 'reason' => TicketException::INVALID_STATUS], 409);
        }

        return $this->pdf->download($order);
    }

    /** O pedido do sócio do JWT, ou a resposta de erro. */
    private function memberOrder(Request $request, int $id): TicketOrder|JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        return TicketOrder::where('member_id', $member->id)->find($id) ?? $this->orderNotFound();
    }

    /** O pedido do não sócio, se o token do header confere. */
    private function guestOrder(Request $request, int $id): TicketOrder|JsonResponse
    {
        $order = TicketOrder::where('buyer_kind', TicketOrder::BUYER_GUEST)->find($id);

        return $order && $order->tokenMatches($request->header(self::TOKEN_HEADER)) ? $order : $this->orderNotFound();
    }

    private function visible()
    {
        return TicketEvent::with(['types.lots'])->whereIn('status', TicketEvent::VISIBLE);
    }

    /** O sócio do JWT, pelo mesmo critério da API dos torneios. */
    private function member(Request $request): ?Member
    {
        $cpf = data_get($request->input('user'), 'username');

        return $cpf ? Member::where('cpf', $cpf)->first() : null;
    }

    private function noSession(): JsonResponse
    {
        return response()->json(['message' => 'Sessão inválida.'], 401);
    }

    private function eventNotFound(): JsonResponse
    {
        return response()->json(['message' => 'Evento não encontrado.', 'reason' => TicketException::EVENT_NOT_FOUND], 404);
    }

    private function orderNotFound(): JsonResponse
    {
        return response()->json(['message' => 'Pedido não encontrado.', 'reason' => TicketException::ORDER_NOT_FOUND], 404);
    }
}
