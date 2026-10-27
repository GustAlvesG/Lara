<?php

namespace App\Services\Ticket;

use App\Exceptions\ConfirmationRequired;
use App\Exceptions\TicketException;
use App\Models\Member;
use App\Models\SchedulePayment;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketCoupon;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketLot;
use App\Models\Ticket\TicketOrder;
use App\Models\Ticket\TicketPayment;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Services\MultiClubes\MemberProfile;
use App\Services\MultiClubes\MemberProfileLookup;
use App\Services\RedeItauService;
use App\Support\Cpf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Pedido de ingressos e o pagamento dele (docs/funcionalidades/ingressos.md).
 *
 *   criar → `pending_payment` (segura o estoque por `tickets.hold_minutes`)
 *         → (eRede pelo site | baixa manual) → `paid`, ingressos `valid`
 *   saídas: `expired` (prazo vencido, `ingressos:expirar-pedidos`),
 *   `withdrawn` (desistência antes de pagar), `cancelled` (a equipe cancelou
 *   todos os ingressos do pedido pago)
 *
 * Regras:
 *   - o sócio compra para as pessoas do título dele (ele, o titular e os
 *     dependentes; preço de sócio); o não sócio compra sem conta, para uma ou
 *     mais pessoas, com preço de não sócio — e CPF de sócio ativo não entra
 *     nesse pedido;
 *   - um ingresso ativo por CPF por dia: no evento sem dias, um no evento; no
 *     de vários dias, uma diária por dia ou o passaporte (que cruza com
 *     todas). A mesma pessoa pode ter diárias de dias diferentes, até no
 *     mesmo pedido;
 *   - o preço sai do lote vigente, no servidor, e fica congelado no ingresso;
 *     um pedido de vários ingressos do mesmo tipo pode atravessar a virada
 *     de lote (cada ingresso leva o preço do seu lote);
 *   - pagamento que chega depois do prazo ainda vale se houver estoque; se
 *     não houver, `hold_expired` e o site estorna.
 *
 * No balcão do Lara (`$counter`), venda fora do período, acima do limite por
 * pedido, além do estoque ou da lotação e CPF de sócio em pedido de não sócio
 * viram aviso (ConfirmationRequired). O CPF repetido no evento continua
 * recusado.
 *
 * A trava da linha do evento (`lockForUpdate`) serializa pedidos e
 * pagamentos concorrentes do mesmo evento, para estoque, lotação e "um por
 * CPF" não escaparem por corrida. E-mail sai fora da transação.
 */
class TicketOrderService
{
    /** Emissão pelo Lara: pagamento recebido por fora, com baixa manual. */
    public const ISSUE_EXTERNAL = 'external';

    /** Emissão pelo Lara: cortesia, valor zerado. */
    public const ISSUE_COURTESY = 'courtesy';

    /** @var array<string, string> */
    public const ISSUE_LABELS = [
        self::ISSUE_EXTERNAL => 'Pagamento recebido por fora',
        self::ISSUE_COURTESY => 'Cortesia (sem cobrança)',
    ];

    public function __construct(
        private TicketInventory $inventory,
        private MemberProfileLookup $profiles,
        private TicketNotifier $notifier,
        private RedeItauService $rede,
    ) {
    }

    /** O sócio do login, conferido no MultiClubes. */
    public function buyerFor(Member $member): TicketBuyer
    {
        $profile = $this->profiles->find(Cpf::digits($member->cpf), (string) $member->title);

        if (! $profile) {
            throw new TicketException(TicketException::MEMBER_NOT_FOUND, 'Não encontramos seu cadastro de sócio ativo. Procure a secretaria.');
        }

        return TicketBuyer::member($profile, $member);
    }

    /**
     * Cria o pedido, um item por pessoa. Para o sócio, cada item é alguém do
     * título dele (`holder_cpf`; vazio = ele mesmo) e o nome vem do cadastro
     * do clube. Para o não sócio, nome e CPF digitados.
     *
     * `$counter` (só pelo balcão): quem vende; regras viram aviso e, sem
     * `$force`, o pedido para em ConfirmationRequired. `$courtesy` (balcão,
     * permissão própria): ingressos a preço zero, já pagos (sem cupom).
     * `$couponCode`: cupom do evento, abatido de cada ingresso a que se aplica.
     * `$notify` = false deixa o e-mail com quem chamou (emissão no balcão).
     *
     * @param  list<array{type_id: int, holder_name?: ?string, holder_cpf?: ?string}>  $items
     */
    public function create(TicketEvent $event, TicketBuyer $buyer, array $items, ?User $counter = null, bool $force = false, bool $courtesy = false, ?string $couponCode = null, bool $notify = true): TicketOrder
    {
        $warnings = [];
        $site = $counter === null;

        if ($items === []) {
            throw new TicketException(TicketException::TYPE_NOT_FOUND, 'Escolha ao menos um ingresso.');
        }

        if ($event->status === TicketEvent::STATUS_DRAFT || $event->status === TicketEvent::STATUS_CANCELLED) {
            if ($site) {
                throw new TicketException(TicketException::EVENT_NOT_FOUND, 'Evento não encontrado.', 404);
            }
            $warnings[] = 'O evento está ' . mb_strtolower(TicketEvent::STATUS_LABELS[$event->status]) . ': o site não vende.';
        } elseif (! $event->salesOpen()) {
            if ($site) {
                throw new TicketException(TicketException::SALES_CLOSED, 'As vendas deste evento não estão abertas.');
            }
            $warnings[] = 'As vendas pelo site estão fechadas para este evento.';
        }

        if ($buyer->isMember()) {
            // O sócio compra para quem é do título dele (ele, o titular e os
            // dependentes), cada um com o nome do cadastro do clube.
            $items = $this->titleHolders($buyer, $items, $site, $warnings);
        } elseif (count($items) > $event->max_per_order) {
            if ($site) {
                throw new TicketException(TicketException::LIMIT_PER_ORDER, "Este evento aceita até {$event->max_per_order} ingresso(s) por pedido.", 422, ['max' => $event->max_per_order]);
            }
            $warnings[] = "O pedido tem " . count($items) . " ingressos; o site aceita até {$event->max_per_order} por pedido.";
        }

        $event->loadMissing('types');
        $items = $this->normalizeHolders($items, $event->types->pluck('ticket_event_day_id', 'id')->all());

        if (! $buyer->isMember()) {
            foreach ($items as $index => $item) {
                if ($this->isActiveMember($item['holder_cpf'])) {
                    if ($site) {
                        throw new TicketException(
                            TicketException::HOLDER_IS_MEMBER,
                            "{$item['holder_name']} é sócio: o ingresso de sócio é comprado por ele mesmo, entrando no site com o login de sócio.",
                            422,
                            ['index' => $index],
                        );
                    }
                    $warnings[] = "{$item['holder_name']} é sócio e está num pedido de não sócio (preço de não sócio).";
                }
            }
        }

        $priceKind = $buyer->isMember() ? TicketLot::PRICE_MEMBER : TicketLot::PRICE_NON_MEMBER;

        $couponCode = $courtesy ? null : (filled($couponCode) ? TicketCoupon::normalizeCode($couponCode) : null);

        $order = DB::transaction(function () use ($event, $buyer, $items, $counter, $force, $courtesy, $site, $warnings, $priceKind, $couponCode) {
            $event = TicketEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $event->load(['days', 'types' => fn ($q) => $q->with('lots', 'day')]);

            $this->assertNoActiveTicket($event, array_map(fn (array $item) => [
                $item['holder_cpf'],
                $event->types->firstWhere('id', $item['type_id'])?->ticket_event_day_id,
            ], $items));

            // Um ingresso de cada vez: o pedido pode atravessar a virada de lote.
            $occupied = $this->inventory->occupiedByLot($event->types->flatMap->lots->pluck('id'));
            $allocations = [];

            foreach ($items as $index => $item) {
                /** @var TicketType|null $type */
                $type = $event->types->firstWhere('id', (int) $item['type_id']);

                if (! $type || (! $type->active && $site)) {
                    throw new TicketException(TicketException::TYPE_NOT_FOUND, 'Tipo de ingresso não encontrado neste evento.', 404, ['index' => $index]);
                }

                $lot = $this->inventory->currentLot($type, $occupied);

                if (! $lot) {
                    if ($site) {
                        throw new TicketException(TicketException::SOLD_OUT, "Os ingressos {$type->name} estão esgotados no momento.", 409, ['type_id' => $type->id]);
                    }
                    $lot = $type->lots->where('active', true)->last() ?? $type->lots->last();
                    if (! $lot) {
                        throw new TicketException(TicketException::SOLD_OUT, "O tipo {$type->name} não tem lote cadastrado.", 409, ['type_id' => $type->id]);
                    }
                    $warnings[] = "{$type->name}: nenhum lote à venda agora; o ingresso entra no {$lot->name}, além da quantidade.";
                }

                $price = $lot->priceFor($priceKind);

                if ($price === null) {
                    if ($site || ! $courtesy) {
                        throw new TicketException(TicketException::MEMBERS_ONLY, "Os ingressos {$type->name} ({$lot->name}) são só para sócios.", 422, ['type_id' => $type->id]);
                    }
                    $price = 0.0;
                }

                $occupied[$lot->id] = ($occupied[$lot->id] ?? 0) + 1;
                $allocations[] = [
                    'item' => $item,
                    'type' => $type,
                    'lot' => $lot,
                    'list_price' => $price,
                    'discount' => 0.0,
                    'amount' => $courtesy ? 0.0 : $price,
                ];
            }

            $coupon = $couponCode !== null
                ? $this->applyCoupon($event, $couponCode, $allocations, $priceKind, $site, $warnings)
                : null;

            foreach ($this->inventory->capacityOverflow($event, array_map(fn (array $a) => $a['type']->ticket_event_day_id, $allocations)) as $full) {
                $where = $full['day'] ? ' em ' . $full['day']->label() : '';
                if ($site) {
                    throw new TicketException(TicketException::CAPACITY_FULL, "A lotação do evento{$where} acabou.", 409, ['left' => $full['left'], 'day_id' => $full['day']?->id]);
                }
                $warnings[] = "A lotação do evento{$where} é {$event->capacity} e já há {$full['taken']} lugar(es) ocupado(s): este pedido passa do limite.";
            }

            ConfirmationRequired::unless($force, $warnings);

            $total = round(array_sum(array_column($allocations, 'amount')), 2);
            $free = $total <= 0;
            $now = Carbon::now();

            $order = new TicketOrder([
                'ticket_event_id' => $event->id,
                'buyer_kind' => $buyer->kind,
                'member_id' => $buyer->memberId,
                'buyer_cpf' => $buyer->cpf,
                'buyer_name' => $buyer->name,
                'buyer_email' => $buyer->email,
                'buyer_phone' => $buyer->phone,
                'buyer_title' => $buyer->title,
                'total' => $total,
                'ticket_coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'discount_total' => round(array_sum(array_column($allocations, 'discount')), 2),
                'status' => $free ? TicketOrder::STATUS_PAID : TicketOrder::STATUS_PENDING_PAYMENT,
                'hold_until' => $free ? null : $now->copy()->addMinutes(max(1, (int) config('tickets.hold_minutes', 10))),
                'paid_at' => $free ? $now : null,
                'origin' => $site ? TicketOrder::ORIGIN_SITE : TicketOrder::ORIGIN_COUNTER,
                'courtesy' => $courtesy,
                'created_by' => $counter?->id,
            ]);
            if (! $buyer->isMember()) {
                $order->access_token = Str::random(40);
            }
            $order->save();

            foreach ($allocations as $allocation) {
                $order->tickets()->create([
                    'ticket_event_id' => $event->id,
                    'ticket_type_id' => $allocation['type']->id,
                    'ticket_lot_id' => $allocation['lot']->id,
                    'price_kind' => $priceKind,
                    'list_price' => $allocation['list_price'],
                    'discount_amount' => $allocation['discount'],
                    'amount' => $allocation['amount'],
                    'holder_name' => $allocation['item']['holder_name'],
                    'holder_cpf' => $allocation['item']['holder_cpf'],
                    'code' => Ticket::newCode(),
                    'status' => $free ? Ticket::STATUS_VALID : Ticket::STATUS_PENDING,
                ]);
            }

            return $order;
        });

        if ($notify && $order->status === TicketOrder::STATUS_PAID) {
            $this->notifier->notify($order->fresh(), TicketNotifier::PAID);
        }

        return $order->fresh(['tickets.type', 'tickets.lot', 'event']);
    }

    /**
     * Emissão pelo Lara: o pedido sai já resolvido, numa transação só.
     *
     *   - `external`: pagamento recebido por fora (dinheiro, Pix, maquininha):
     *     cria e dá a baixa manual (`$payment` com `payment_method`
     *     `manual_*`, `paid_at`, `manual_notes`). Pode usar cupom.
     *   - `courtesy`: cortesia, valor zerado, já paga, sem pagamento gravado.
     *
     * Regras do site viram aviso (`$force`). Um e-mail só, no fim.
     *
     * @param  list<array{type_id: int, holder_name?: ?string, holder_cpf?: ?string}>  $items
     */
    public function issueAtCounter(TicketEvent $event, TicketBuyer $buyer, array $items, User $user, string $mode, array $payment = [], ?string $couponCode = null, bool $force = false): TicketOrder
    {
        if ($mode === self::ISSUE_EXTERNAL && ! array_key_exists($payment['payment_method'] ?? '', SchedulePayment::MANUAL_METHODS)) {
            throw new InvalidArgumentException('Escolha a forma em que o pagamento foi recebido.');
        }
        if (! in_array($mode, [self::ISSUE_EXTERNAL, self::ISSUE_COURTESY], true)) {
            throw new InvalidArgumentException('Escolha como os ingressos são emitidos.');
        }

        $order = DB::transaction(function () use ($event, $buyer, $items, $user, $mode, $payment, $couponCode, $force) {
            $order = $this->create($event, $buyer, $items, $user, $force, $mode === self::ISSUE_COURTESY, $couponCode, notify: false);

            if ($order->status === TicketOrder::STATUS_PENDING_PAYMENT) {
                $this->recordManual($order, $payment, $user, $force, notify: false);
            }

            return $order;
        });

        $order = $order->fresh(['tickets.type', 'tickets.lot', 'event', 'payment']);
        $this->notifier->notify($order, TicketNotifier::PAID);

        return $order;
    }

    /**
     * O site confere o cupom antes do pedido: existe, está valendo, vale para
     * esse público em algum tipo ativo e ainda tem uso. Só informa — quem
     * decide o desconto é o pedido (`create`), que confere tudo de novo.
     */
    public function checkCoupon(TicketEvent $event, string $code, string $priceKind): TicketCoupon
    {
        $coupon = TicketCoupon::where('ticket_event_id', $event->id)->where('code', TicketCoupon::normalizeCode($code))->first();

        if (! $coupon || ! $coupon->active || ! $coupon->withinDates()) {
            throw new TicketException(TicketException::COUPON_INVALID, 'Cupom inválido ou fora do prazo.', 422);
        }

        $typeIds = $event->types()->active()->pluck('id');
        if (! $typeIds->contains(fn ($id) => $coupon->appliesTo((int) $id, $priceKind))) {
            throw new TicketException(TicketException::COUPON_NOT_APPLICABLE, $priceKind === TicketLot::PRICE_MEMBER
                ? 'Este cupom não vale para sócios.'
                : 'Este cupom não vale para não sócios.', 422);
        }

        if ($coupon->max_uses !== null && $coupon->usedCount() >= $coupon->max_uses) {
            throw new TicketException(TicketException::COUPON_EXHAUSTED, "O cupom {$coupon->code} esgotou.", 409, ['remaining' => 0]);
        }

        return $coupon;
    }

    /**
     * Confere e aplica o cupom nos ingressos em montagem (por referência).
     * No balcão, cupom inativo, fora das datas ou sem uso sobrando vira aviso;
     * cupom inexistente ou que não vale para nenhum ingresso recusa sempre.
     *
     * @param  list<array<string, mixed>>  $allocations
     * @param  list<string>  $warnings
     */
    private function applyCoupon(TicketEvent $event, string $code, array &$allocations, string $priceKind, bool $site, array &$warnings): TicketCoupon
    {
        $coupon = TicketCoupon::where('ticket_event_id', $event->id)->where('code', $code)->first();

        if (! $coupon) {
            throw new TicketException(TicketException::COUPON_INVALID, 'Cupom não encontrado para este evento.', 422, ['code' => $code]);
        }

        if (! $coupon->active || ! $coupon->withinDates()) {
            if ($site) {
                throw new TicketException(TicketException::COUPON_INVALID, 'Este cupom não está valendo agora.', 422, ['code' => $code]);
            }
            $warnings[] = "O cupom {$coupon->code} está " . ($coupon->active ? 'fora das datas dele' : 'desativado') . '.';
        }

        $applicable = array_keys(array_filter($allocations, fn (array $a) => $a['amount'] > 0 && $coupon->appliesTo($a['type']->id, $priceKind)));

        if ($applicable === []) {
            throw new TicketException(TicketException::COUPON_NOT_APPLICABLE, "O cupom {$coupon->code} não vale para estes ingressos.", 422, ['code' => $code]);
        }

        if ($coupon->max_uses !== null) {
            $remaining = max(0, $coupon->max_uses - $coupon->usedCount());
            if (count($applicable) > $remaining) {
                if ($site) {
                    throw new TicketException(TicketException::COUPON_EXHAUSTED, $remaining > 0
                        ? "O cupom {$coupon->code} só vale para mais {$remaining} ingresso(s)."
                        : "O cupom {$coupon->code} esgotou.", 409, ['code' => $code, 'remaining' => $remaining]);
                }
                $warnings[] = "O cupom {$coupon->code} tem {$remaining} uso(s) sobrando e o pedido usa " . count($applicable) . '.';
            }
        }

        foreach ($applicable as $index) {
            $discount = $coupon->discountFor((float) $allocations[$index]['amount']);
            $allocations[$index]['discount'] = $discount;
            $allocations[$index]['amount'] = round($allocations[$index]['amount'] - $discount, 2);
        }

        return $coupon;
    }

    /**
     * Pagamento pela eRede, avisado pelo site. O mesmo tid reenviado para o
     * mesmo pedido devolve o pagamento já gravado; o valor tem de ser
     * exatamente o total. Como nos torneios, o Lara confia no tid (não
     * consulta a eRede ao gravar).
     *
     * @param  array{payment_method: string, paid_amount: float|string, payment_integration_id: string, paid_at?: ?string}  $data
     * @return array{payment: TicketPayment, created: bool}
     */
    public function pay(TicketOrder $order, array $data): array
    {
        $tid = (string) $data['payment_integration_id'];

        $existing = TicketPayment::where('payment_integration_id', $tid)->first();
        if ($existing) {
            if ($existing->ticket_order_id !== $order->id) {
                throw new TicketException(TicketException::INVALID_STATUS, 'Este pagamento já foi usado para outro pedido.', 409);
            }

            return ['payment' => $existing, 'created' => false];
        }

        $payment = DB::transaction(function () use ($order, $data) {
            $order = $this->lockPayable($order);
            $this->assertAmount($order, (float) $data['paid_amount']);

            $payment = TicketPayment::create([
                'ticket_order_id' => $order->id,
                'payment_method' => $data['payment_method'],
                'paid_amount' => round((float) $data['paid_amount'], 2),
                'paid_at' => ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : Carbon::now(),
                'payment_integration_id' => $data['payment_integration_id'],
                'status' => TicketPayment::STATUS_PAID,
            ]);

            $this->markPaid($order, $payment);

            return $payment;
        });

        $this->notifier->notify($payment->order()->first(), TicketNotifier::PAID);

        return ['payment' => $payment, 'created' => true];
    }

    /**
     * Baixa manual: a equipe registra o que foi pago fora da eRede. Registra
     * um fato já acontecido, então não exige vendas abertas; estoque estourado
     * (pedido expirado) vira aviso.
     *
     * @param  array{payment_method: string, paid_at?: ?string, manual_notes?: ?string}  $data
     */
    public function recordManual(TicketOrder $order, array $data, User $user, bool $force = false, bool $notify = true): TicketPayment
    {
        if (! array_key_exists($data['payment_method'] ?? '', SchedulePayment::MANUAL_METHODS)) {
            throw new InvalidArgumentException('Escolha a forma em que o pagamento foi recebido.');
        }

        $payment = DB::transaction(function () use ($order, $data, $user, $force) {
            $order = $this->lockPayable($order, $force);

            $payment = new TicketPayment([
                'ticket_order_id' => $order->id,
                'payment_method' => $data['payment_method'],
                'paid_amount' => round((float) $order->total, 2),
                'paid_at' => ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : Carbon::now(),
                'status' => TicketPayment::STATUS_PAID,
            ]);
            $payment->is_manual = true;
            $payment->recorded_by = $user->id;
            $payment->manual_notes = $data['manual_notes'] ?? null;
            $payment->save();

            $this->markPaid($order, $payment);

            return $payment;
        });

        if ($notify) {
            $this->notifier->notify($payment->order()->first(), TicketNotifier::PAID);
        }

        return $payment;
    }

    /** Quem comprou desiste pelo site. Só antes de pagar: depois, é com a organização. */
    public function withdraw(TicketOrder $order): TicketOrder
    {
        if ($order->status === TicketOrder::STATUS_PAID) {
            throw new TicketException(TicketException::PAID_CONTACT_ORGANIZER, 'Seu pedido já está pago. Para cancelar, fale com a organização do evento.');
        }

        if ($order->status !== TicketOrder::STATUS_PENDING_PAYMENT) {
            throw new TicketException(TicketException::INVALID_STATUS, 'Este pedido não está mais ativo.');
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => TicketOrder::STATUS_WITHDRAWN, 'withdrawn_at' => Carbon::now()]);
            $order->tickets()->where('status', Ticket::STATUS_PENDING)->update(['status' => Ticket::STATUS_EXPIRED]);
        });

        return $order;
    }

    /**
     * Expira os pedidos pendentes cujo prazo passou (`ingressos:expirar-pedidos`).
     * O estoque já estava livre desde o fim do prazo (`Ticket::occupying`);
     * aqui só a situação gravada acompanha.
     *
     * @return int pedidos expirados
     */
    public function expireOverdue(): int
    {
        $count = 0;

        TicketOrder::where('status', TicketOrder::STATUS_PENDING_PAYMENT)
            ->where('hold_until', '<=', Carbon::now())
            ->orderBy('id')
            ->chunkById(200, function (Collection $orders) use (&$count) {
                foreach ($orders as $order) {
                    DB::transaction(function () use ($order, &$count) {
                        // Só expira se continuar pendente: o pagamento pode ter chegado agora.
                        $updated = TicketOrder::whereKey($order->id)
                            ->where('status', TicketOrder::STATUS_PENDING_PAYMENT)
                            ->update(['status' => TicketOrder::STATUS_EXPIRED, 'expired_at' => Carbon::now()]);

                        if ($updated) {
                            Ticket::where('ticket_order_id', $order->id)->where('status', Ticket::STATUS_PENDING)->update(['status' => Ticket::STATUS_EXPIRED]);
                            $count++;
                        }
                    });
                }
            });

        return $count;
    }

    /**
     * A equipe cancela um ingresso válido, caso a caso. Com `$refund`,
     * devolve pela eRede o valor deste ingresso — antes de cancelar: se a
     * eRede recusar, nada muda. Baixa manual não tem estorno pelo Lara.
     * Sem ingresso ativo no pedido, o pedido vira `cancelled`.
     *
     * @return float o valor estornado (0 sem estorno)
     */
    public function cancelTicket(Ticket $ticket, User $user, string $reason, bool $refund = false): float
    {
        return $this->cancelTickets($ticket->order, collect([$ticket]), $user, $reason, $refund);
    }

    /**
     * Cancela de uma vez os ingressos válidos de um pedido (todos, se
     * `$tickets` for null), com UM estorno na eRede pela soma deles. Um e-mail
     * só para quem comprou.
     *
     * @param  Collection<int, Ticket>|null  $tickets
     * @return float o valor estornado (0 sem estorno)
     */
    public function cancelTickets(TicketOrder $order, ?Collection $tickets, User $user, string $reason, bool $refund = false): float
    {
        $tickets ??= $order->tickets()->where('status', Ticket::STATUS_VALID)->get();

        if ($tickets->isEmpty()) {
            throw new TicketException(TicketException::TICKET_NOT_VALID, 'Não há ingresso válido para cancelar neste pedido.');
        }

        foreach ($tickets as $ticket) {
            if ($ticket->ticket_order_id !== $order->id || $ticket->status !== Ticket::STATUS_VALID) {
                throw new TicketException(TicketException::TICKET_NOT_VALID, $ticket->status === Ticket::STATUS_USED
                    ? "O ingresso de {$ticket->holder_name} já foi usado na entrada: não pode ser cancelado."
                    : "O ingresso de {$ticket->holder_name} não está válido.");
            }
        }

        $refunded = 0.0;
        $toRefund = round((float) $tickets->sum('amount'), 2);

        if ($refund && $toRefund > 0) {
            $payment = $order->payment;
            if (! $payment) {
                throw new InvalidArgumentException('Este pedido não tem pagamento para estornar.');
            }
            $refunded = $this->refund($payment, $toRefund, $user);
        }

        DB::transaction(function () use ($tickets, $order, $user, $reason, $refunded, $toRefund) {
            // O estorno se divide pelos ingressos na proporção do valor de cada
            // um (o pagamento cobre o pedido inteiro): relatório por tipo e lote.
            $left = $refunded;
            foreach ($tickets->values() as $i => $ticket) {
                $share = $i === $tickets->count() - 1 ? $left
                    : ($toRefund > 0 ? round($refunded * (float) $ticket->amount / $toRefund, 2) : 0.0);
                $left = round($left - $share, 2);

                $ticket->update([
                    'status' => Ticket::STATUS_CANCELLED,
                    'cancelled_at' => Carbon::now(),
                    'cancelled_by' => $user->id,
                    'cancel_reason' => $reason,
                    'refunded_amount' => $share,
                ]);
            }

            if (! $order->tickets()->whereIn('status', Ticket::SOLD)->exists()) {
                $order->update([
                    'status' => TicketOrder::STATUS_CANCELLED,
                    'cancelled_at' => Carbon::now(),
                    'cancelled_by' => $user->id,
                    'cancel_reason' => $reason,
                ]);
            }
        });

        $this->notifier->notify($order->fresh(), TicketNotifier::CANCELLED, $tickets->map->fresh(['type', 'lot']), [
            'reason' => $reason,
            'refunded' => $refunded,
        ]);

        return $refunded;
    }

    /**
     * Troca o titular de um ingresso ainda não usado (a pessoa não vai mais e
     * passa para outra). Mantém tipo, lote, preço e código. O "um por CPF no
     * evento" vale para o novo titular. Ingresso de sócio é dele: trocar vira
     * aviso; CPF de sócio em pedido de não sócio, também.
     */
    public function changeHolder(Ticket $ticket, string $name, string $cpf, User $user, bool $force = false): Ticket
    {
        if (! in_array($ticket->status, [Ticket::STATUS_VALID, Ticket::STATUS_PENDING], true)) {
            throw new TicketException(TicketException::TICKET_NOT_VALID, 'Só ingresso válido ou aguardando pagamento troca de titular.');
        }

        [$holder] = $this->normalizeHolders([['type_id' => $ticket->ticket_type_id, 'holder_name' => $name, 'holder_cpf' => $cpf]], []);

        if ($holder['holder_cpf'] === $ticket->holder_cpf) {
            $ticket->update(['holder_name' => $holder['holder_name']]);

            return $ticket;
        }

        $order = $ticket->order;
        $warnings = [];
        if (! $order->isGuest()) {
            // Pedido de sócio: o novo titular também tem de ser do título.
            if (! isset($this->titleMembers($order->buyer_title)[$holder['holder_cpf']])) {
                $warnings[] = "{$holder['holder_name']} não é do título {$order->buyer_title}: o ingresso, com preço de sócio, vai para alguém de fora.";
            }
        } elseif ($this->isActiveMember($holder['holder_cpf'])) {
            $warnings[] = "{$holder['holder_name']} é sócio e vai ficar com um ingresso de não sócio.";
        }

        DB::transaction(function () use ($ticket, $holder, $warnings, $force) {
            $event = TicketEvent::whereKey($ticket->ticket_event_id)->lockForUpdate()->firstOrFail();
            $this->assertNoActiveTicket($event, [[$holder['holder_cpf'], $ticket->type->ticket_event_day_id]]);
            ConfirmationRequired::unless($force, $warnings);

            $ticket->update(['holder_name' => $holder['holder_name'], 'holder_cpf' => $holder['holder_cpf']]);
        });

        return $ticket->fresh();
    }

    /** Reenvia ao não sócio o e-mail com o link do pedido (o "esqueci o link" do site). */
    public function resendLink(TicketOrder $order): bool
    {
        return $this->notifier->notify($order, $order->status === TicketOrder::STATUS_PAID ? TicketNotifier::PAID : TicketNotifier::LINK);
    }

    /**
     * Nome e CPF de cada titular: preenchidos, CPF válido e sem a mesma pessoa
     * duas vezes no mesmo dia do pedido (diárias de dias diferentes passam;
     * passaporte cruza com tudo).
     *
     * @param  array<int, ?int>  $typeDays  type_id => dia (null = todos os dias)
     * @return list<array{type_id: int, holder_name: string, holder_cpf: string}>
     */
    private function normalizeHolders(array $items, array $typeDays): array
    {
        $seen = [];

        foreach (array_values($items) as $index => $item) {
            $name = trim(preg_replace('/\s+/', ' ', (string) ($item['holder_name'] ?? '')));
            $cpf = Cpf::digits((string) ($item['holder_cpf'] ?? ''));

            if (mb_strlen($name) < 3) {
                throw new TicketException(TicketException::INVALID_HOLDER, 'Informe o nome de quem vai usar cada ingresso.', 422, ['index' => $index, 'field' => 'holder_name']);
            }
            if (! Cpf::isValid($cpf)) {
                throw new TicketException(TicketException::INVALID_HOLDER, "CPF inválido para {$name}.", 422, ['index' => $index, 'field' => 'holder_cpf']);
            }
            $day = $typeDays[(int) ($item['type_id'] ?? 0)] ?? null;
            foreach ($seen[$cpf] ?? [] as $other) {
                if (TicketType::daysOverlap($day, $other)) {
                    throw new TicketException(TicketException::INVALID_HOLDER, "{$name} aparece em dois ingressos para o mesmo dia: cada pessoa tem um ingresso por dia.", 422, ['index' => $index, 'field' => 'holder_cpf']);
                }
            }
            $seen[$cpf][] = $day;

            $items[$index] = ['type_id' => (int) ($item['type_id'] ?? 0), 'holder_name' => $name, 'holder_cpf' => $cpf];
        }

        return array_values($items);
    }

    /**
     * As pessoas do título de quem compra (titular e dependentes ativos no
     * MultiClubes), por CPF. MultiClubes fora do ar: só o próprio comprador,
     * que já foi conferido no login.
     *
     * @return array<string, MemberProfile> cpf => perfil
     */
    public function titleMembers(?string $title, ?TicketBuyer $buyer = null): array
    {
        $family = [];

        if (filled($title)) {
            try {
                foreach ($this->profiles->byTitle((string) $title) as $profile) {
                    $family[Cpf::digits($profile->cpf)] = $profile;
                }
            } catch (\Throwable $e) {
                Log::warning('Ingressos: MultiClubes indisponível na busca do título; só o comprador.', ['title' => $title, 'error' => $e->getMessage()]);
            }
        }

        if ($buyer && ! isset($family[$buyer->cpf])) {
            $family[$buyer->cpf] = new MemberProfile($buyer->name, $buyer->cpf, (string) $buyer->title, null, null, $buyer->email, false);
        }

        return $family;
    }

    /**
     * Os titulares do pedido do sócio: cada item é uma pessoa do título (sem
     * CPF = o próprio comprador), com o nome do cadastro do clube. Pessoa de
     * fora do título: o site recusa (`holder_not_in_title`); o balcão avisa.
     *
     * @param  list<string>  $warnings
     */
    private function titleHolders(TicketBuyer $buyer, array $items, bool $site, array &$warnings): array
    {
        $family = $this->titleMembers($buyer->title, $buyer);

        foreach (array_values($items) as $index => $item) {
            $cpf = Cpf::digits((string) ($item['holder_cpf'] ?? '')) ?: $buyer->cpf;
            $items[$index]['holder_cpf'] = $cpf;

            if (isset($family[$cpf])) {
                $items[$index]['holder_name'] = $family[$cpf]->name;
                continue;
            }

            if ($site) {
                throw new TicketException(TicketException::HOLDER_NOT_IN_TITLE, 'O sócio compra para as pessoas do próprio título. Para outra pessoa, ela compra como não sócio (ou, se for sócia, pelo login dela).', 422, ['index' => $index]);
            }
            $warnings[] = ($item['holder_name'] ?? 'Uma pessoa') . " não é do título {$buyer->title} e vai ter ingresso com preço de sócio.";
        }

        return array_values($items);
    }

    /**
     * O CPF é de sócio de título ativo? MultiClubes fora do ar não trava a
     * venda ao não sócio: o pior caso é um sócio pagar o preço de não sócio.
     */
    private function isActiveMember(string $cpf): bool
    {
        try {
            return $this->profiles->byCpf($cpf) !== [];
        } catch (\Throwable $e) {
            Log::warning('Ingressos: MultiClubes indisponível na conferência de CPF de sócio; seguindo sem ela.', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Ninguém do pedido pode já ter ingresso ativo que cruze com o novo: no
     * mesmo dia, ou passaporte com qualquer dia.
     *
     * @param  list<array{0: string, 1: ?int}>  $holders  [cpf, dia do tipo (null = todos)]
     */
    private function assertNoActiveTicket(TicketEvent $event, array $holders, ?int $exceptOrderId = null): void
    {
        $existing = Ticket::occupying()
            ->with('type.day', 'type.event.days')
            ->where('ticket_event_id', $event->id)
            ->whereIn('holder_cpf', array_unique(array_column($holders, 0)))
            ->when($exceptOrderId, fn ($q) => $q->where('ticket_order_id', '!=', $exceptOrderId))
            ->get();

        foreach ($holders as [$cpf, $day]) {
            $taken = $existing->first(fn (Ticket $t) => $t->holder_cpf === $cpf && TicketType::daysOverlap($day, $t->type->ticket_event_day_id));

            if ($taken) {
                $label = $taken->type->validityLabel();

                throw new TicketException(
                    TicketException::ALREADY_HAS_TICKET,
                    "{$taken->holder_name} já tem ingresso para este evento" . ($label ? " ({$label})" : '') . '.',
                    409,
                    ['cpf' => Cpf::mask($taken->holder_cpf), 'day_id' => $taken->type->ticket_event_day_id],
                );
            }
        }
    }

    /**
     * O pedido travado e pronto para pagar: pendente (ou expirado pelo
     * prazo). Fora do prazo, confere de novo estoque, lotação e o "um por
     * CPF": sem lugar, `hold_expired` no site; com `$force` informado (baixa
     * manual), vira aviso.
     */
    private function lockPayable(TicketOrder $order, ?bool $force = null): TicketOrder
    {
        $event = TicketEvent::whereKey($order->ticket_event_id)->lockForUpdate()->firstOrFail();
        $order = TicketOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

        if (! in_array($order->status, [TicketOrder::STATUS_PENDING_PAYMENT, TicketOrder::STATUS_EXPIRED], true)) {
            throw new TicketException(TicketException::INVALID_STATUS, 'Este pedido não está aguardando pagamento.', 409);
        }

        if ($order->holding()) {
            return $order;
        }

        $tickets = $order->tickets()->with('lot', 'type')->get();
        $problems = [];

        $occupied = $this->inventory->occupiedByLot($tickets->pluck('ticket_lot_id'), $order->id);
        foreach ($tickets->groupBy('ticket_lot_id') as $lotTickets) {
            $lot = $lotTickets->first()->lot;
            if (($occupied[$lot->id] ?? 0) + $lotTickets->count() > $lot->quantity) {
                $problems[] = "O {$lot->name} esgotou depois que o prazo do pedido venceu.";
            }
        }

        foreach ($this->inventory->capacityOverflow($event, $tickets->map(fn (Ticket $t) => $t->type->ticket_event_day_id)->all(), $order->id) as $full) {
            $problems[] = 'A lotação do evento' . ($full['day'] ? ' em ' . $full['day']->label() : '') . ' acabou depois que o prazo do pedido venceu.';
        }

        try {
            $this->assertNoActiveTicket($event, $tickets->map(fn (Ticket $t) => [$t->holder_cpf, $t->type->ticket_event_day_id])->all(), $order->id);
        } catch (TicketException $e) {
            $problems[] = $e->getMessage();
        }

        $coupon = $order->coupon;
        $discounted = $tickets->where('discount_amount', '>', 0)->count();
        if ($coupon && $coupon->max_uses !== null && $discounted > 0 && $coupon->usedCount($order->id) + $discounted > $coupon->max_uses) {
            $problems[] = "O cupom {$coupon->code} esgotou depois que o prazo do pedido venceu.";
        }

        if ($force === null && $problems !== []) {
            throw new TicketException(TicketException::HOLD_EXPIRED, 'O prazo do pedido venceu e os ingressos não estão mais disponíveis. O valor será estornado.', 409, ['detail' => $problems]);
        }
        ConfirmationRequired::unless((bool) $force, $problems);

        return $order;
    }

    private function assertAmount(TicketOrder $order, float $paid): void
    {
        $total = round((float) $order->total, 2);

        if (abs(round($paid, 2) - $total) > 0.001) {
            throw new TicketException(TicketException::PAYMENT_MISMATCH, 'O valor pago não confere com o total do pedido.', 422, ['expected' => $total]);
        }
    }

    private function markPaid(TicketOrder $order, TicketPayment $payment): void
    {
        $order->update([
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => $payment->paid_at,
            'expired_at' => null,
        ]);

        Ticket::where('ticket_order_id', $order->id)
            ->whereIn('status', [Ticket::STATUS_PENDING, Ticket::STATUS_EXPIRED])
            ->update(['status' => Ticket::STATUS_VALID]);
    }

    private function refund(TicketPayment $payment, float $amount, User $user): float
    {
        if ($payment->is_manual) {
            throw new InvalidArgumentException('Pagamento registrado por baixa manual (fora da eRede): não há estorno pelo Lara. Se houver devolução, ela é feita fora do sistema.');
        }

        $remaining = round((float) $payment->paid_amount - (float) $payment->refunded_amount, 2);
        $amount = round(min($amount, $remaining), 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Não há valor disponível para estorno neste pagamento.');
        }

        $this->rede->refund($payment->payment_integration_id, $amount);

        $payment->refunded_amount = round((float) $payment->refunded_amount + $amount, 2);
        $payment->refunded_by = $user->id;
        $payment->refunded_at = Carbon::now();
        $payment->status = $payment->refunded_amount >= (float) $payment->paid_amount
            ? TicketPayment::STATUS_REFUNDED
            : TicketPayment::STATUS_PARTIALLY_REFUNDED;
        $payment->save();

        return $amount;
    }
}
