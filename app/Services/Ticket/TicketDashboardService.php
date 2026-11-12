<?php

namespace App\Services\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketLot;
use App\Models\Ticket\TicketOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Números dos ingressos para os dashboards do site (/api/dashboard/tickets/*,
 * só sócio administrador). Mesma régua do relatório financeiro
 * (TicketFinanceService): vendido = válido + usado; cortesia conta como
 * ingresso, com valor zero; o recebido sai dos pagamentos, menos estornos.
 *
 * Só números agregados: nada de nome, CPF ou e-mail de comprador sai daqui —
 * o site é público e o painel dele não é o Lara.
 *
 * Rascunho não aparece: ainda não vendeu nada e o site nem o mostra.
 */
class TicketDashboardService
{
    /** Status que entram no painel (rascunho fica de fora). */
    public const STATUSES = [TicketEvent::STATUS_PUBLISHED, TicketEvent::STATUS_FINISHED, TicketEvent::STATUS_CANCELLED];

    /** Teto de dias da série diária: um evento à venda o ano inteiro não vira 365 pontos sem fim. */
    private const MAX_DAYS = 400;

    public function __construct(private TicketFinanceService $finance)
    {
    }

    /** O evento existe e entra no painel? */
    public function find(int $id): ?TicketEvent
    {
        return TicketEvent::whereIn('status', self::STATUSES)->find($id);
    }

    /**
     * Eventos para o seletor, do mais novo para o mais antigo, e qual abre
     * primeiro: o publicado mais próximo de acontecer (o que já começou e
     * ainda não acabou também conta); sem nenhum à frente, o publicado mais
     * recente; sem publicado, o primeiro da lista.
     *
     * @return array{default_event_id: ?int, events: list<array<string, mixed>>}
     */
    public function events(): array
    {
        $events = TicketEvent::whereIn('status', self::STATUSES)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();

        $totals = Ticket::query()
            ->whereIn('ticket_event_id', $events->pluck('id'))
            ->whereIn('status', Ticket::SOLD)
            ->selectRaw('ticket_event_id, count(*) as sold, coalesce(sum(amount), 0) as net')
            ->groupBy('ticket_event_id')
            ->get()
            ->keyBy('ticket_event_id');

        $now = now();
        $published = $events->where('status', TicketEvent::STATUS_PUBLISHED);
        $upcoming = $published
            ->filter(fn (TicketEvent $e) => ($e->ends_at ?? $e->starts_at)?->gte($now))
            ->sortBy('starts_at')
            ->first();
        $default = $upcoming ?? $published->first() ?? $events->first();

        return [
            'default_event_id' => $default?->id,
            'events' => $events->map(fn (TicketEvent $e) => $this->eventPayload($e) + [
                'sold' => (int) ($totals[$e->id]->sold ?? 0),
                'net' => round((float) ($totals[$e->id]->net ?? 0), 2),
            ])->values()->all(),
        ];
    }

    /** Cards do evento. */
    public function summary(TicketEvent $event): array
    {
        $t = $this->finance->totals($event);

        $kpis = [
            $this->kpi('sold', 'Ingressos vendidos', $t['sold'], 'int'),
            $this->kpi('used', 'Entradas (check-in)', $t['used'], 'int'),
            $this->kpi('courtesies', 'Cortesias', $t['courtesies'], 'int'),
            $this->kpi('cancelled', 'Cancelados', $t['cancelled'], 'int'),
            $this->kpi('face', 'Valor de face', $t['face'], 'money'),
            $this->kpi('discount', 'Descontos (cupons)', $t['discount'], 'money'),
            $this->kpi('net', 'Valor líquido', $t['net'], 'money'),
            $this->kpi('received', 'Recebido', $t['received'], 'money'),
            $this->kpi('refunded', 'Estornado', $t['refunded'], 'money'),
        ];

        if ($event->capacity) {
            $kpis[] = $this->kpi('capacity', 'Capacidade', $event->capacity, 'int');
            $kpis[] = $this->kpi('occupancy', 'Ocupação', round($t['sold'] / $event->capacity * 100, 1), 'percent');
        }

        return [
            'event' => $this->eventPayload($event),
            'kpis' => $kpis,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Vendas por dia (pelo pagamento do pedido; cortesia, pela emissão). Os
     * dias sem venda entre o primeiro e o último aparecem com zero, para a
     * linha do gráfico não pular.
     */
    public function salesByDay(TicketEvent $event): array
    {
        $byDay = $this->soldTickets($event)
            ->groupBy(fn (Ticket $t) => ($t->order->paid_at ?? $t->order->created_at ?? $t->created_at)->toDateString())
            ->map(fn (Collection $g) => ['tickets' => $g->count(), 'amount' => round((float) $g->sum('amount'), 2)]);

        $series = [];
        if ($byDay->isNotEmpty()) {
            $day = Carbon::parse($byDay->keys()->min());
            $last = Carbon::parse($byDay->keys()->max());
            if ($day->diffInDays($last) > self::MAX_DAYS) {
                $day = $last->copy()->subDays(self::MAX_DAYS);
            }

            for (; $day->lte($last); $day->addDay()) {
                $key = $day->toDateString();
                $series[] = ['date' => $key] + ($byDay[$key] ?? ['tickets' => 0, 'amount' => 0.0]);
            }
        }

        return ['event' => $this->eventPayload($event), 'series' => $series];
    }

    /**
     * Por tipo e lote: vendidos a sócio, a não sócio, cortesias e a
     * capacidade do lote (null = sem limite). Lote sem venda também aparece.
     */
    public function byLot(TicketEvent $event): array
    {
        $sold = $this->soldTickets($event)->groupBy(fn (Ticket $t) => (int) $t->ticket_lot_id);

        $rows = [];
        foreach ($event->types()->with('lots')->get() as $type) {
            foreach ($type->lots as $lot) {
                $rows[] = $this->lotRow($type->fullName(), $lot->name, $lot->quantity, $sold->get($lot->id, collect()));
                $sold->forget($lot->id);
            }
        }

        // Ingresso sem lote (ou de lote apagado): não some da conta.
        foreach ($sold as $group) {
            $first = $group->first();
            $rows[] = $this->lotRow($first->type?->fullName() ?? 'Ingresso', $first->lot?->name ?? 'Sem lote', null, $group);
        }

        return ['event' => $this->eventPayload($event), 'rows' => $rows];
    }

    /** Por canal (site, secretaria, cortesia) e por público (sócio, não sócio). */
    public function byChannel(TicketEvent $event): array
    {
        $sold = $this->soldTickets($event);

        $channel = fn (Ticket $t) => $t->order->courtesy
            ? 'courtesy'
            : ($t->order->origin === TicketOrder::ORIGIN_COUNTER ? 'counter' : 'site');

        return [
            'event' => $this->eventPayload($event),
            'origin' => $this->slices($sold->groupBy($channel), [
                'site' => 'Site', 'counter' => 'Secretaria', 'courtesy' => 'Cortesia',
            ]),
            'buyer' => $this->slices($sold->groupBy(fn (Ticket $t) => $t->price_kind), [
                TicketLot::PRICE_MEMBER => 'Sócio', TicketLot::PRICE_NON_MEMBER => 'Não sócio',
            ]),
        ];
    }

    /** Recebido por forma de pagamento (eRede e cada baixa manual). */
    public function byMethod(TicketEvent $event): array
    {
        return [
            'event' => $this->eventPayload($event),
            'rows' => $this->finance->byMethod($event)->map(fn (array $row) => $row + [
                'received' => round($row['paid'] - $row['refunded'], 2),
            ])->values()->all(),
        ];
    }

    private function eventPayload(TicketEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'venue' => $event->venue,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'status' => $event->status,
            'status_label' => TicketEvent::STATUS_LABELS[$event->status] ?? $event->status,
        ];
    }

    private function kpi(string $key, string $label, int|float $value, string $format): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value, 'format' => $format];
    }

    private function lotRow(string $type, ?string $lot, ?int $capacity, Collection $tickets): array
    {
        $paid = $tickets->reject(fn (Ticket $t) => $t->order->courtesy);

        return [
            'type' => $type,
            'lot' => $lot,
            'capacity' => $capacity,
            'member' => $paid->where('price_kind', TicketLot::PRICE_MEMBER)->count(),
            'non_member' => $paid->where('price_kind', TicketLot::PRICE_NON_MEMBER)->count(),
            'courtesies' => $tickets->count() - $paid->count(),
            'total' => $tickets->count(),
            'net' => round((float) $tickets->sum('amount'), 2),
        ];
    }

    /**
     * @param  array<string, string>  $labels  na ordem em que aparecem
     * @return list<array{key: string, label: string, count: int, amount: float}>
     */
    private function slices(Collection $groups, array $labels): array
    {
        $slices = [];
        foreach ($labels as $key => $label) {
            $group = $groups->get($key, collect());
            $slices[] = ['key' => $key, 'label' => $label, 'count' => $group->count(), 'amount' => round((float) $group->sum('amount'), 2)];
        }

        return $slices;
    }

    /** @return Collection<int, Ticket> */
    private function soldTickets(TicketEvent $event): Collection
    {
        return Ticket::with(['order', 'type', 'lot'])
            ->where('ticket_event_id', $event->id)
            ->whereIn('status', Ticket::SOLD)
            ->get();
    }
}
