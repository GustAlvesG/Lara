<?php

namespace App\Http\Controllers\Ticket;

use App\Authorization\Permissions as P;
use App\Exceptions\TicketException;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\SchedulePayment;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketOrder;
use App\Models\Ticket\TicketPayment;
use App\Models\User;
use App\Services\MultiClubes\MemberProfileLookup;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketOrderService;
use App\Support\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Pedidos de ingresso no painel (permissão `ingressos.vendas`): lista e
 * busca, detalhe, reenvio do e-mail, troca de titular, cancelamento (o
 * estorno pede `ingressos.pagamentos.estornar`), baixa manual de pendente
 * (`ingressos.pagamentos.baixa-manual`) e a emissão pelo Lara — pagamento
 * por fora (baixa manual) ou cortesia (`ingressos.cortesias`).
 *
 * Recusas do serviço (TicketException, InvalidArgumentException) voltam
 * como erro de formulário; avisos, como "Confirmar mesmo assim".
 *
 * Lugar marcado: na emissão, o item do tipo com mapa leva o `seat_id`
 * escolhido no mapa; a troca de lugar fica no TicketMapController.
 *
 * Cortesia para não sócio: CPF opcional (de quem recebe e de cada ingresso)
 * e o formato "em quantidade" (`courtesy_format=batch`): N ingressos de um
 * tipo de entrada, sem nome nem CPF, de uma vez. Impressão térmica: `print`.
 */
class TicketOrderController extends Controller
{
    public function __construct(private TicketOrderService $orders, private MemberProfileLookup $profiles)
    {
    }

    public function index(Request $request, TicketEvent $event): View
    {
        $q = trim((string) $request->input('q'));
        $digits = Cpf::digits($q);

        $orders = $event->orders()
            ->with(['tickets.type', 'payment.recordedBy', 'creator'])
            ->withCount('tickets')
            ->when($q !== '', function ($query) use ($q, $digits) {
                $code = Ticket::normalizeCode($q);
                $query->where(function ($w) use ($q, $digits, $code) {
                    $w->where('buyer_name', 'like', "%{$q}%")
                        ->orWhereHas('tickets', fn ($t) => $t->where('holder_name', 'like', "%{$q}%")->orWhere('code', $code));
                    if (strlen($digits) >= 3) {
                        $w->orWhere('buyer_cpf', 'like', "%{$digits}%")
                            ->orWhereHas('tickets', fn ($t) => $t->where('holder_cpf', 'like', "%{$digits}%"));
                    }
                    if (ctype_digit($q)) {
                        $w->orWhere('id', (int) $q);
                    }
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            // Vendido por: quem emitiu no balcão ou recebeu a baixa manual; "site" = criado pelo site.
            ->when($request->input('vendedor') === 'site', fn ($query) => $query->where('origin', TicketOrder::ORIGIN_SITE))
            ->when(ctype_digit((string) $request->input('vendedor')), fn ($query) => $query->where(fn ($w) => $w
                ->where('created_by', (int) $request->input('vendedor'))
                ->orWhereHas('payment', fn ($p) => $p->where('recorded_by', (int) $request->input('vendedor')))))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $sellerIds = $event->orders()->whereNotNull('created_by')->distinct()->pluck('created_by')
            ->merge(TicketPayment::whereNotNull('recorded_by')->whereHas('order', fn ($q) => $q->where('ticket_event_id', $event->id))->distinct()->pluck('recorded_by'))
            ->unique();

        return view('tickets.orders.index', [
            'event' => $event,
            'orders' => $orders,
            'sellers' => User::whereIn('id', $sellerIds)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(TicketOrder $order): View
    {
        $order->load(['event.days', 'tickets.type.day', 'tickets.type.map', 'tickets.seat', 'tickets.lot', 'tickets.checker', 'tickets.checkins.checker', 'payment.recordedBy', 'payment.refundedBy', 'coupon', 'creator', 'canceller', 'logs.user', 'logs.ticket']);

        return view('tickets.orders.show', ['order' => $order, 'manualMethods' => SchedulePayment::MANUAL_METHODS]);
    }

    public function resend(Request $request, TicketOrder $order): RedirectResponse
    {
        $sent = $this->orders->resendLink($order, $request->user());

        return back()->with($sent ? 'success' : 'error', $sent ? "E-mail reenviado para {$order->buyer_email}." : 'O e-mail não saiu (endereço inválido ou falha no envio).');
    }

    public function changeHolder(Request $request, Ticket $ticket): RedirectResponse
    {
        // Cortesia de não sócio pode ficar sem nome e CPF ("Cortesia"); o resto, não.
        $anonymous = $ticket->order->courtesy && $ticket->order->isGuest();
        $data = $request->validate([
            'holder_name' => [$anonymous ? 'nullable' : 'required', 'string', 'max:255'],
            'holder_cpf' => [$anonymous ? 'nullable' : 'required', 'string', 'max:20'],
        ], [], ['holder_name' => 'nome', 'holder_cpf' => 'CPF']);
        $data += ['holder_name' => null, 'holder_cpf' => null];

        $this->guard(fn () => $this->orders->changeHolder($ticket, (string) $data['holder_name'], (string) $data['holder_cpf'], $request->user(), $request->boolean('force')));

        return redirect()->route('tickets.orders.show', $ticket->ticket_order_id)->with('success', 'Titular trocado.');
    }

    /** Cancela os ingressos marcados (ou todos os válidos), com ou sem estorno. */
    public function cancel(Request $request, TicketOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'ticket_ids' => 'nullable|array',
            'ticket_ids.*' => 'integer',
            'reason' => 'required|string|max:500',
            'refund' => 'nullable|boolean',
        ], [], ['reason' => 'motivo']);

        $refund = $request->boolean('refund');
        if ($refund && ! $request->user()->can(P::INGRESSOS_PAGAMENTOS_ESTORNAR)) {
            abort(403);
        }

        $tickets = ! empty($data['ticket_ids'])
            ? $order->tickets()->whereIn('id', $data['ticket_ids'])->get()
            : null;

        $refunded = $this->guard(fn () => $this->orders->cancelTickets($order, $tickets, $request->user(), $data['reason'], $refund));

        return redirect()->route('tickets.orders.show', $order)->with('success', $refunded > 0
            ? 'Cancelado, com estorno de R$ ' . number_format($refunded, 2, ',', '.') . '.'
            : 'Cancelado, sem estorno.');
    }

    /** Baixa manual de um pedido pendente (pago fora da eRede). */
    public function manual(Request $request, TicketOrder $order): RedirectResponse
    {
        $data = $this->paymentData($request);

        $this->guard(fn () => $this->orders->recordManual($order, $data, $request->user(), $request->boolean('force')));

        return redirect()->route('tickets.orders.show', $order)->with('success', 'Baixa registrada; os ingressos foram enviados por e-mail.');
    }

    public function create(TicketEvent $event): View
    {
        $event->load(['types' => fn ($q) => $q->active()->with('lots', 'day', 'map'), 'coupons' => fn ($q) => $q->where('active', true)]);

        return view('tickets.issue', [
            'event' => $event,
            'summary' => collect(app(\App\Services\Ticket\TicketInventory::class)->summary($event))->filter(fn ($row) => $row['type']->active)->values(),
            'manualMethods' => SchedulePayment::MANUAL_METHODS,
            'modes' => array_filter(TicketOrderService::ISSUE_LABELS, fn ($label, $mode) => $this->canIssue(request(), $mode), ARRAY_FILTER_USE_BOTH),
        ]);
    }

    /**
     * Emissão pelo Lara. Sócio: CPF (e matrícula, se o CPF estiver em mais de
     * um título) e os ingressos para as pessoas do título (CPF de cada uma;
     * vazio = o próprio sócio). Não sócio: dados de quem compra e um titular
     * por ingresso.
     */
    public function store(Request $request, TicketEvent $event): RedirectResponse
    {
        $courtesy = $request->input('mode') === TicketOrderService::ISSUE_COURTESY;
        $guest = $request->input('buyer_kind') === TicketOrder::BUYER_GUEST;
        $batch = $courtesy && $request->input('courtesy_format') === 'batch';
        // Cortesia de não sócio: CPF opcional (de quem recebe e de cada ingresso).
        $cpfRequired = $guest && ! $courtesy;

        $data = $request->validate([
            'mode' => ['required', Rule::in(array_keys(TicketOrderService::ISSUE_LABELS))],
            'buyer_kind' => ['required', Rule::in([TicketOrder::BUYER_MEMBER, TicketOrder::BUYER_GUEST])],
            'member_cpf' => 'required_if:buyer_kind,member|nullable|string|max:20',
            'member_title' => 'nullable|string|max:20',
            'buyer_cpf' => [$cpfRequired ? 'required' : 'nullable', 'string', 'max:20'],
            'buyer_name' => 'required_if:buyer_kind,guest|nullable|string|max:255',
            'buyer_email' => 'nullable|email|max:255',
            'buyer_phone' => 'nullable|string|max:30',
            // Sócio: CPF de alguém do título (vazio = o próprio sócio), nome do cadastro do clube.
            'holders' => [$batch ? 'nullable' : 'required', 'array', 'min:1', 'max:50'],
            'holders.*.type_id' => 'required|integer',
            'holders.*.holder_name' => [$cpfRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'holders.*.holder_cpf' => [$cpfRequired ? 'required' : 'nullable', 'string', 'max:20'],
            // Tipo de lugar marcado: o lugar escolhido no mapa.
            'holders.*.seat_id' => 'nullable|integer',
            'coupon_code' => 'nullable|string|max:40',
            // Cortesias em quantidade: N ingressos de um tipo, sem nome nem CPF.
            'courtesy_format' => 'nullable|in:nominal,batch',
            'batch_type_id' => [$batch ? 'required' : 'nullable', 'integer'],
            'batch_quantity' => [$batch ? 'required' : 'nullable', 'integer', 'min:1', 'max:' . TicketOrderService::MAX_COURTESY_BATCH],
            'batch_name' => 'nullable|string|max:255',
        ], [], [
            'member_cpf' => 'CPF do sócio',
            'buyer_cpf' => 'CPF de quem compra',
            'buyer_name' => 'nome de quem compra',
            'holders' => 'ingressos',
            'batch_type_id' => 'tipo das cortesias',
            'batch_quantity' => 'quantidade de cortesias',
        ]);

        if (! $this->canIssue($request, $data['mode'])) {
            abort(403);
        }

        $payment = $data['mode'] === TicketOrderService::ISSUE_EXTERNAL ? $this->paymentData($request) : [];

        if ($batch && ! $guest) {
            throw ValidationException::withMessages(['buyer_kind' => 'Cortesias em quantidade são para não sócio (um patrocinador, uma ação): o sócio recebe cortesia para as pessoas do título dele.']);
        }

        if ($data['buyer_kind'] === TicketOrder::BUYER_MEMBER) {
            $buyer = $this->memberBuyer($data['member_cpf'], $data['member_title'] ?? null);
        } else {
            if (filled($data['buyer_cpf'] ?? null) && ! Cpf::isValid(Cpf::digits($data['buyer_cpf']))) {
                throw ValidationException::withMessages(['buyer_cpf' => 'CPF de quem compra é inválido.']);
            }
            $buyer = TicketBuyer::guest($data['buyer_cpf'] ?? null, $data['buyer_name'], $data['buyer_email'] ?? null, $data['buyer_phone'] ?? null);
        }
        $items = $batch
            ? $this->guard(fn () => $this->orders->batchItems($event, (int) $data['batch_type_id'], (int) $data['batch_quantity'], $data['batch_name'] ?? null))
            : array_values($data['holders']);

        $order = $this->guard(fn () => $this->orders->issueAtCounter(
            $event, $buyer, $items, $request->user(), $data['mode'], $payment,
            $data['mode'] === TicketOrderService::ISSUE_EXTERNAL ? ($data['coupon_code'] ?? null) : null,
            $request->boolean('force'),
        ));

        return redirect()->route('tickets.orders.show', $order)->with('success', $order->tickets()->count() . ' ingresso(s) emitido(s)' . ($order->buyer_email ? " e enviados para {$order->buyer_email}." : '. Sem e-mail: baixe o PDF ou imprima na térmica.'));
    }

    public function pdf(TicketOrder $order, \App\Services\Ticket\TicketPdf $pdf)
    {
        abort_unless($order->status === TicketOrder::STATUS_PAID, 404);

        return $pdf->download($order);
    }

    /**
     * Ingresso físico na impressora térmica: a página de 80 mm, que abre a
     * impressão sozinha. Conta a via e grava no histórico (POST, para não
     * contar uma via a cada recarga sem querer). `ticket_ids` vazio = todos.
     */
    public function print(Request $request, TicketOrder $order, \App\Services\Ticket\TicketPrintService $printer): View
    {
        $data = $request->validate(['ticket_ids' => 'nullable|array', 'ticket_ids.*' => 'integer']);

        $tickets = $this->guard(fn () => $printer->prepare($order->loadMissing('event'), array_map('intval', $data['ticket_ids'] ?? []), $request->user()));

        return view('tickets.print.thermal', ['order' => $order, 'tickets' => $tickets, 'by' => $request->user()]);
    }

    private function canIssue(Request $request, string $mode): bool
    {
        return $mode === TicketOrderService::ISSUE_COURTESY
            ? $request->user()->can(P::INGRESSOS_CORTESIAS)
            : $request->user()->can(P::INGRESSOS_PAGAMENTOS_BAIXA_MANUAL);
    }

    /** @return array{payment_method: string, paid_at: ?string, manual_notes: ?string} */
    private function paymentData(Request $request): array
    {
        return $request->validate([
            'payment_method' => ['required', Rule::in(array_keys(SchedulePayment::MANUAL_METHODS))],
            'paid_at' => 'nullable|date',
            'manual_notes' => 'nullable|string|max:500',
        ], [], ['payment_method' => 'forma de pagamento', 'manual_notes' => 'observação']);
    }

    /** O sócio do balcão, pelo MultiClubes; com o CPF em mais de um título, pede a matrícula. */
    private function memberBuyer(string $cpf, ?string $title): TicketBuyer
    {
        $cpf = Cpf::digits($cpf);
        $profiles = Cpf::isValid($cpf) ? $this->profiles->byCpf($cpf) : [];

        if ($title !== null && trim($title) !== '') {
            $profiles = array_values(array_filter($profiles, fn ($p) => $p->title === trim($title)));
        }

        if ($profiles === []) {
            throw ValidationException::withMessages(['member_cpf' => 'Não há sócio de título ativo com este CPF' . ($title ? ' nesta matrícula' : '') . '. Emita como não sócio.']);
        }

        $titles = array_values(array_unique(array_map(fn ($p) => $p->title, $profiles)));
        if (count($titles) > 1) {
            throw ValidationException::withMessages(['member_title' => 'Este CPF está em mais de um título (' . implode(', ', $titles) . '): informe a matrícula.']);
        }

        return TicketBuyer::member($profiles[0], Member::where('cpf', $cpf)->first());
    }

    /** Recusa do serviço vira erro de formulário. */
    private function guard(callable $call)
    {
        try {
            return $call();
        } catch (TicketException|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['order' => $e->getMessage()]);
        }
    }
}
