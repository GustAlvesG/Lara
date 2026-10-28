<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketCheckin;
use App\Models\Ticket\TicketCoupon;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketLot;
use App\Models\Ticket\TicketMap;
use App\Models\Ticket\TicketOrder;
use App\Models\Ticket\TicketPayment;
use App\Models\Ticket\TicketSeat;
use App\Models\Ticket\TicketSeatClaim;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Support\Cpf;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dados de teste dos Ingressos para homologação: um evento em cada situação e
 * os cenários de lote, cupom, pedido, pagamento, cortesia, cancelamento e
 * check-in, para testar o painel do Lara, o site e o Monitor de Acesso.
 *
 * O que ele NÃO faz, de propósito:
 *
 *  - **Não roda em produção** (ambiente `production` ou banco `lara`).
 *  - **Não manda e-mail nem chama a eRede ou o MultiClubes**: pedidos,
 *    pagamentos e estornos são gravados direto nas tabelas.
 *  - **Não mexe em nada que não criou**: os eventos levam o prefixo `[TESTE]`,
 *    e é por ele que `--so-limpar` apaga (com tipos, lotes, cupons, pedidos e
 *    ingressos). Rodar de novo limpa o anterior e recria.
 *
 *     php artisan ingressos:dados-teste
 *     php artisan ingressos:dados-teste --socio=12345678909
 *     php artisan ingressos:dados-teste --so-limpar
 */
class TicketTestData extends Command
{
    protected $signature = 'ingressos:dados-teste
                            {--socio= : CPF de uma conta do site real, para ganhar um pedido pendente (pagar pelo site)}
                            {--usuario= : Usuário do Lara que aparece nas emissões e baixas (id); padrão: o primeiro}
                            {--so-limpar : Só apaga os dados de teste anteriores}';

    protected $description = 'Gera (ou limpa) dados de teste de Ingressos em homologação, com o prefixo [TESTE].';

    public const PREFIX = '[TESTE]';

    private ?User $user = null;
    private int $cpf = 0;
    private int $tid = 0;

    /** @var list<array{0: string, 1: string}> */
    private array $report = [];

    public function handle(): int
    {
        if (app()->environment('production') || config('database.connections.mysql.database') === 'lara') {
            $this->error('Isto cria eventos e pedidos falsos. Não roda em produção (ambiente production ou banco "lara").');

            return self::FAILURE;
        }

        $removed = $this->clean();
        $this->info("Dados de teste anteriores apagados: {$removed} evento(s).");

        if ($this->option('so-limpar')) {
            return self::SUCCESS;
        }

        $this->user = $this->option('usuario') ? User::find($this->option('usuario')) : User::query()->orderBy('id')->first();

        DB::transaction(function () {
            $this->openSales();
            $this->happeningNow();
            $this->festival();
            $this->reveillon();
            $this->simple(TicketEvent::STATUS_DRAFT, 'Rascunho', now()->addDays(40));
            $this->simple(TicketEvent::STATUS_FINISHED, 'Encerrado', now()->subDays(10));
            $this->simple(TicketEvent::STATUS_CANCELLED, 'Cancelado', now()->addDays(15));
        });

        $this->table(['Evento', 'O que tem'], $this->report);

        return self::SUCCESS;
    }

    /** Apaga os eventos [TESTE] com tudo o que é deles. */
    private function clean(): int
    {
        $ids = TicketEvent::withTrashed()->where('title', 'like', self::PREFIX . '%')->pluck('id');

        DB::transaction(function () use ($ids) {
            $orderIds = TicketOrder::whereIn('ticket_event_id', $ids)->pluck('id');
            TicketCheckin::whereIn('ticket_id', Ticket::whereIn('ticket_order_id', $orderIds)->select('id'))->delete();
            Ticket::whereIn('ticket_order_id', $orderIds)->delete();
            // A diária aponta para o dia, e o tipo de lugar para o mapa, sem cascade: saem antes do evento.
            TicketType::whereIn('ticket_event_id', $ids)->where(fn ($q) => $q->whereNotNull('ticket_event_day_id')->orWhereNotNull('ticket_map_id'))->delete();
            TicketPayment::whereIn('ticket_order_id', $orderIds)->delete();
            TicketOrder::whereIn('id', $orderIds)->delete();
            TicketEvent::withTrashed()->whereIn('id', $ids)->get()->each->forceDelete();
        });

        return $ids->count();
    }

    private function openSales(): void
    {
        $event = $this->event('Baile de Verão (vendas abertas)', now()->addDays(20)->setTime(22, 0), [
            'capacity' => 500, 'max_per_order' => 6, 'venue' => 'Salão Nobre',
        ]);

        $pista = $this->type($event, 'Pista', 1);
        $first = $this->lot($pista, '1º lote', 1, 10, 40, 70, null, now()->addDays(5));
        $second = $this->lot($pista, '2º lote', 2, 100, 50, 90);
        $mesa = $this->type($event, 'Mesa (só sócios)', 2);
        $mesaLot = $this->lot($mesa, 'Lote único', 1, 20, 120, null);
        $kids = $this->type($event, 'Infantil (grátis)', 3);
        $kidsLot = $this->lot($kids, 'Lote único', 1, 50, 0, 0);

        $off = $this->coupon($event, 'TESTE10', TicketCoupon::KIND_PERCENT, 10);
        $this->coupon($event, 'MESA30', TicketCoupon::KIND_FIXED, 30, ['audience' => TicketLot::PRICE_MEMBER, 'ticket_type_ids' => [$mesa->id]]);
        $this->coupon($event, 'ESGOTADO', TicketCoupon::KIND_PERCENT, 50, ['max_uses' => 1]);

        // 1º lote quase esgotado: 8 pagos + 1 pendente no prazo (sobra 1).
        for ($i = 0; $i < 8; $i++) {
            $this->order($event, [[$pista, $first]], ['paid' => 'erede']);
        }
        $this->order($event, [[$pista, $first]], ['pending' => true]);
        $this->order($event, [[$pista, $second], [$pista, $second]], ['paid' => 'erede', 'coupon' => $off]);
        $this->order($event, [[$pista, $second]], ['expired' => true]);
        $this->order($event, [[$pista, $second]], ['paid' => 'manual_pix', 'counter' => true]);
        $this->order($event, [[$mesa, $mesaLot]], ['paid' => 'courtesy', 'counter' => true, 'kind' => TicketOrder::BUYER_MEMBER]);
        $this->order($event, [[$kids, $kidsLot]], ['paid' => 'free']);
        $this->order($event, [[$pista, $second], [$pista, $second]], ['paid' => 'erede', 'cancel_one' => true]);
        // Cupom "ESGOTADO" com o único uso gasto.
        $this->order($event, [[$pista, $second]], ['paid' => 'erede', 'coupon' => TicketCoupon::where('code', 'ESGOTADO')->where('ticket_event_id', $event->id)->first()]);

        $socio = $this->option('socio') ? Member::where('cpf', Cpf::digits($this->option('socio')))->first() : null;
        if ($socio) {
            $this->order($event, [[$pista, $second]], ['pending' => true, 'kind' => TicketOrder::BUYER_MEMBER, 'member' => $socio, 'hold' => 60]);
        } elseif ($this->option('socio')) {
            $this->warn('Nenhuma conta do site com o CPF ' . $this->option('socio') . ': o pedido de sócio não foi criado.');
        }

        $this->report[] = [$event->title, '1º lote da Pista com 1 de 10 sobrando (vira para o 2º); Mesa só para sócios; Infantil grátis; cupons TESTE10 (10%), MESA30 (R$ 30 só sócios na Mesa) e ESGOTADO (usado); pedidos pagos (eRede, cupom, Pix no balcão, cortesia, grátis), pendente, expirado e um cancelado em parte com estorno' . ($socio ? '; pedido pendente do sócio ' . $socio->name . ' (pagar pelo site em 60 min)' : '')];
    }

    private function happeningNow(): void
    {
        $event = $this->event('Show de Hoje (check-in)', now()->addHour(), ['venue' => 'Quadra Coberta']);
        $type = $this->type($event, 'Pista', 1);
        $lot = $this->lot($type, 'Lote único', 1, 200, 30, 60);

        for ($i = 0; $i < 3; $i++) {
            $this->order($event, [[$type, $lot]], ['paid' => 'erede']);
        }
        $used = $this->order($event, [[$type, $lot]], ['paid' => 'manual_cash', 'counter' => true]);
        $used->tickets()->update(['status' => Ticket::STATUS_USED, 'checked_in_at' => now()->subMinutes(10), 'checked_in_by' => $this->user?->id, 'checkin_source' => Ticket::CHECKIN_LARA]);

        $codes = $event->tickets()->where('status', Ticket::STATUS_VALID)->get()->map(fn ($t) => $t->code . ' (' . Cpf::format($t->holder_cpf) . ')')->implode(', ');
        $this->report[] = [$event->title, "entrada aberta agora; 3 válidos para testar no Lara e no Monitor de Acesso: {$codes}; 1 já usado"];
    }

    /**
     * Evento de dois dias, o primeiro começando daqui a 1 h: diária de cada dia
     * e passaporte, para testar a entrada por dia no Lara e no monitor.
     */
    private function festival(): void
    {
        $first = now()->addHour();
        $event = $this->event('Festival de Dois Dias', $first, [
            'venue' => 'Parque Aquático',
            'ends_at' => $first->copy()->addDay()->addHours(5),
            'sales_ends_at' => $first->copy()->addDay()->subMinutes(30),
            'capacity' => 300,
        ]);
        $sat = $event->days()->create(['name' => 'Dia 1', 'starts_at' => $first, 'ends_at' => $first->copy()->addHours(5)]);
        $sun = $event->days()->create(['name' => 'Dia 2', 'starts_at' => $first->copy()->addDay(), 'ends_at' => $first->copy()->addDay()->addHours(5)]);

        $day1 = $event->types()->create(['name' => 'Diária', 'sort_order' => 1, 'ticket_event_day_id' => $sat->id]);
        $day2 = $event->types()->create(['name' => 'Diária', 'sort_order' => 2, 'ticket_event_day_id' => $sun->id]);
        $pass = $this->type($event, 'Passaporte', 3);
        $lot1 = $this->lot($day1, 'Lote único', 1, 150, 40, 70);
        $lot2 = $this->lot($day2, 'Lote único', 1, 150, 40, 70);
        $lotPass = $this->lot($pass, 'Lote único', 1, 100, 70, 120);

        $passport = $this->order($event, [[$pass, $lotPass]], ['paid' => 'erede']);
        $this->order($event, [[$day1, $lot1]], ['paid' => 'erede']);
        $this->order($event, [[$day2, $lot2]], ['paid' => 'erede']);

        $code = $passport->tickets()->first()->code;
        $this->report[] = [$event->title, "dias com diária e passaporte (lotação 300 por dia); entrada do Dia 1 aberta agora; passaporte {$code} (entra hoje e de novo amanhã), uma diária de cada dia"];
    }

    /**
     * Réveillon com lugar marcado: mapas de mesas (Vip e Comum, preços
     * diferentes no mesmo mapa), bistrôs e camarotes, com palco, pista,
     * piscina, bar e banheiros desenhados; algumas mesas vendidas e uma
     * reservada (pendente), para testar o mapa no Lara e no site.
     */
    private function reveillon(): void
    {
        $event = $this->event('Réveillon (lugar marcado)', now()->addDays(60)->setTime(22, 0), [
            'venue' => 'Sede Social', 'capacity' => 800, 'max_per_order' => 6,
            'ends_at' => now()->addDays(61)->setTime(5, 0),
        ]);

        $mesas = $event->maps()->create(['name' => 'Mesas', 'width' => 1000, 'height' => 640, 'sort_order' => 1, 'areas' => [
            ['label' => 'Palco', 'shape' => 'rect', 'x' => 350, 'y' => 20, 'w' => 300, 'h' => 80, 'color' => 'stage'],
            ['label' => 'Pista de dança', 'shape' => 'rect', 'x' => 380, 'y' => 120, 'w' => 240, 'h' => 120, 'color' => 'service'],
            ['label' => 'Piscina', 'shape' => 'ellipse', 'x' => 760, 'y' => 470, 'w' => 210, 'h' => 140, 'color' => 'water'],
            ['label' => 'Bar', 'shape' => 'rect', 'x' => 30, 'y' => 20, 'w' => 140, 'h' => 60, 'color' => 'warn'],
            ['label' => 'Banheiros', 'shape' => 'rect', 'x' => 30, 'y' => 560, 'w' => 160, 'h' => 60, 'color' => 'service'],
        ]]);
        $vip = $this->seatGrid($mesas, 'V', 'Vip', 2, 6, 200, 280, 100, 70, 'circle', 50, 4);
        $this->seatGrid($mesas, 'M', 'Comum', 3, 7, 230, 440, 75, 60, 'circle', 44, 4);

        $bistros = $event->maps()->create(['name' => 'Bistrôs', 'width' => 800, 'height' => 500, 'sort_order' => 2, 'areas' => [
            ['label' => 'Palco', 'shape' => 'rect', 'x' => 250, 'y' => 20, 'w' => 300, 'h' => 70, 'color' => 'stage'],
            ['label' => 'Bar', 'shape' => 'rect', 'x' => 620, 'y' => 380, 'w' => 150, 'h' => 80, 'color' => 'warn'],
        ]]);
        $bistro = $this->seatGrid($bistros, 'B', null, 3, 6, 140, 170, 90, 90, 'square', 40, 2);

        $camarotes = $event->maps()->create(['name' => 'Camarotes', 'width' => 900, 'height' => 400, 'sort_order' => 3, 'areas' => [
            ['label' => 'Palco', 'shape' => 'rect', 'x' => 300, 'y' => 300, 'w' => 300, 'h' => 80, 'color' => 'stage'],
            ['label' => 'Pista', 'shape' => 'rect', 'x' => 200, 'y' => 170, 'w' => 500, 'h' => 110, 'color' => 'service'],
        ]]);
        $this->seatGrid($camarotes, 'C', null, 1, 6, 115, 80, 134, 0, 'rect', 110, 10, 70);

        $pista = $this->type($event, 'Pista', 1);
        $pistaLot = $this->lot($pista, 'Lote único', 1, 600, 120, 200);
        $mesaVip = $event->types()->create(['name' => 'Mesa Vip', 'sort_order' => 2, 'ticket_map_id' => $mesas->id, 'seat_category' => 'Vip']);
        $mesaVipLot = $this->lot($mesaVip, 'Lote único', 1, 100, 400, 600);
        $mesa = $event->types()->create(['name' => 'Mesa', 'sort_order' => 3, 'ticket_map_id' => $mesas->id, 'seat_category' => 'Comum']);
        $mesaLot = $this->lot($mesa, 'Lote único', 1, 100, 200, 320);
        $bistroType = $event->types()->create(['name' => 'Bistrô', 'sort_order' => 4, 'ticket_map_id' => $bistros->id]);
        $bistroLot = $this->lot($bistroType, 'Lote único', 1, 100, 120, 180);
        $camarote = $event->types()->create(['name' => 'Camarote', 'sort_order' => 5, 'ticket_map_id' => $camarotes->id]);
        $this->lot($camarote, 'Lote único', 1, 100, 1500, 2200);

        // Duas mesas Vip vendidas (uma com as entradas da família), uma reservada e um bistrô vendido.
        $this->order($event, [[$mesaVip, $mesaVipLot, $vip[0]], [$pista, $pistaLot], [$pista, $pistaLot]], ['paid' => 'erede']);
        $this->order($event, [[$mesaVip, $mesaVipLot, $vip[1]]], ['paid' => 'manual_pix', 'counter' => true]);
        $this->order($event, [[$mesaVip, $mesaVipLot, $vip[6]]], ['pending' => true, 'hold' => 60]);
        $this->order($event, [[$bistroType, $bistroLot, $bistro[0]]], ['paid' => 'erede']);
        $this->order($event, [[$mesa, $mesaLot, TicketSeat::where('ticket_map_id', $mesas->id)->where('label', 'M5')->first()]], ['expired' => true]);

        $this->report[] = [$event->title, 'lugar marcado: mapa Mesas (Vip V1–V12 e Comum M1–M21, preços diferentes; palco, pista, piscina, bar e banheiros), Bistrôs (B1–B18) e Camarotes (C1–C6); V1 e V2 vendidas, V7 reservada (pendente por 60 min), B1 vendido, M5 com pedido expirado (livre de novo)'];
    }

    /**
     * Uma grade de lugares no mapa, com rótulos em sequência (V1, V2…).
     *
     * @return list<TicketSeat>
     */
    private function seatGrid(TicketMap $map, string $prefix, ?string $category, int $rows, int $cols, int $x0, int $y0, int $dx, int $dy, string $shape, int $size, int $places, ?int $height = null): array
    {
        $seats = [];
        $n = 1;
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $seats[] = $map->seats()->create([
                    'label' => $prefix . $n++, 'category' => $category, 'shape' => $shape,
                    'x' => $x0 + $c * $dx, 'y' => $y0 + $r * $dy, 'w' => $size, 'h' => $height ?? $size, 'places' => $places,
                ]);
            }
        }

        return $seats;
    }

    private function simple(string $status, string $label, Carbon $startsAt): void
    {
        $event = $this->event($label, $startsAt, ['status' => $status]);
        $type = $this->type($event, 'Pista', 1);
        $lot = $this->lot($type, 'Lote único', 1, 100, 40, 80);

        if ($status !== TicketEvent::STATUS_DRAFT) {
            $order = $this->order($event, [[$type, $lot], [$type, $lot]], ['paid' => 'erede']);
            if ($status === TicketEvent::STATUS_FINISHED) {
                $order->tickets()->update(['status' => Ticket::STATUS_USED, 'checked_in_at' => $startsAt, 'checkin_source' => Ticket::CHECKIN_MONITOR]);
            }
        }

        $this->report[] = [$event->title, TicketEvent::STATUS_LABELS[$status] . ($status === TicketEvent::STATUS_DRAFT ? ', sem venda' : ', com um pedido pago de 2 ingressos')];
    }

    private function event(string $title, Carbon $startsAt, array $attributes = []): TicketEvent
    {
        return TicketEvent::create(array_merge([
            'title' => self::PREFIX . ' ' . $title,
            'description' => 'Evento de teste gerado por ingressos:dados-teste.',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(5),
            'sales_starts_at' => now()->subDays(5),
            'sales_ends_at' => $startsAt->copy()->subMinutes(30),
            'max_per_order' => 4,
            'status' => TicketEvent::STATUS_PUBLISHED,
            'created_by' => $this->user?->id,
        ], $attributes));
    }

    private function type(TicketEvent $event, string $name, int $sort): TicketType
    {
        return $event->types()->create(['name' => $name, 'sort_order' => $sort]);
    }

    private function lot(TicketType $type, string $name, int $sequence, int $quantity, float $member, ?float $nonMember, ?Carbon $startsAt = null, ?Carbon $endsAt = null): TicketLot
    {
        return $type->lots()->create([
            'name' => $name, 'sequence' => $sequence, 'quantity' => $quantity,
            'price_member' => $member, 'price_non_member' => $nonMember,
            'starts_at' => $startsAt, 'ends_at' => $endsAt,
        ]);
    }

    private function coupon(TicketEvent $event, string $code, string $kind, float $value, array $attributes = []): TicketCoupon
    {
        return $event->coupons()->create(array_merge(['code' => $code, 'kind' => $kind, 'value' => $value, 'description' => 'Cupom de teste', 'created_by' => $this->user?->id], $attributes));
    }

    /**
     * Um pedido gravado direto (sem e-mail nem eRede).
     *
     * @param  list<array{0: TicketType, 1: TicketLot, 2?: ?TicketSeat}>  $items
     * @param  array{paid?: string, pending?: bool, expired?: bool, counter?: bool, kind?: string, member?: Member, coupon?: ?TicketCoupon, cancel_one?: bool, hold?: int}  $o
     */
    private function order(TicketEvent $event, array $items, array $o): TicketOrder
    {
        $kind = $o['kind'] ?? TicketOrder::BUYER_GUEST;
        $priceKind = $kind === TicketOrder::BUYER_MEMBER ? TicketLot::PRICE_MEMBER : TicketLot::PRICE_NON_MEMBER;
        $courtesy = ($o['paid'] ?? null) === 'courtesy';
        $coupon = $o['coupon'] ?? null;
        $member = $o['member'] ?? null;

        $holders = [];
        $lines = [];
        foreach ($items as $item) {
            [$type, $lot] = $item;
            $cpf = $member ? Cpf::digits($member->cpf) : $this->nextCpf();
            $price = (float) ($lot->priceFor($priceKind) ?? 0);
            $discount = $coupon && ! $courtesy && $coupon->appliesTo($type->id, $priceKind) ? $coupon->discountFor($price) : 0.0;
            $lines[] = ['type' => $type, 'lot' => $lot, 'seat' => $item[2] ?? null, 'list' => $price, 'discount' => $discount, 'amount' => $courtesy ? 0.0 : round($price - $discount, 2), 'cpf' => $cpf];
            $holders[] = $member?->name ?? 'Pessoa Teste ' . $this->cpf;
        }

        $total = round(array_sum(array_column($lines, 'amount')), 2);
        $paid = isset($o['paid']) || $total <= 0;
        $status = match (true) {
            ! empty($o['expired']) => TicketOrder::STATUS_EXPIRED,
            $paid => TicketOrder::STATUS_PAID,
            default => TicketOrder::STATUS_PENDING_PAYMENT,
        };

        $order = new TicketOrder([
            'ticket_event_id' => $event->id,
            'buyer_kind' => $kind,
            'member_id' => $member?->id,
            'buyer_cpf' => $lines[0]['cpf'],
            'buyer_name' => $member?->name ?? $holders[0],
            'buyer_email' => $member?->email ?? 'teste+' . $lines[0]['cpf'] . '@teste.local',
            'buyer_title' => $member?->title,
            'total' => $total,
            'ticket_coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'discount_total' => round(array_sum(array_column($lines, 'discount')), 2),
            'status' => $status,
            'hold_until' => $status === TicketOrder::STATUS_PENDING_PAYMENT ? now()->addMinutes($o['hold'] ?? 10) : now()->subMinutes(20),
            'paid_at' => $paid ? now()->subHours(2) : null,
            'expired_at' => $status === TicketOrder::STATUS_EXPIRED ? now()->subMinutes(10) : null,
            'origin' => ! empty($o['counter']) ? TicketOrder::ORIGIN_COUNTER : TicketOrder::ORIGIN_SITE,
            'courtesy' => $courtesy,
            'created_by' => ! empty($o['counter']) ? $this->user?->id : null,
        ]);
        if ($kind === TicketOrder::BUYER_GUEST) {
            $order->access_token = Str::random(40);
        }
        $order->save();

        foreach ($lines as $i => $line) {
            $ticket = $order->tickets()->create([
                'ticket_event_id' => $event->id,
                'ticket_type_id' => $line['type']->id,
                'ticket_lot_id' => $line['lot']->id,
                'ticket_seat_id' => $line['seat']?->id,
                'price_kind' => $priceKind,
                'list_price' => $line['list'],
                'discount_amount' => $line['discount'],
                'amount' => $line['amount'],
                'holder_name' => $holders[$i],
                'holder_cpf' => $line['cpf'],
                'code' => Ticket::newCode(),
                'status' => match ($status) {
                    TicketOrder::STATUS_PAID => Ticket::STATUS_VALID,
                    TicketOrder::STATUS_EXPIRED => Ticket::STATUS_EXPIRED,
                    default => Ticket::STATUS_PENDING,
                },
            ]);

            // O lugar preso ao ingresso (o expirado já soltou o dele).
            if ($line['seat'] && $status !== TicketOrder::STATUS_EXPIRED) {
                TicketSeatClaim::create(['ticket_seat_id' => $line['seat']->id, 'ticket_id' => $ticket->id, 'day_key' => $line['type']->dayKey()]);
            }
        }

        $method = $o['paid'] ?? null;
        if ($paid && $total > 0 && $method !== null) {
            $payment = new TicketPayment([
                'ticket_order_id' => $order->id,
                'payment_method' => $method === 'erede' ? 'credit' : $method,
                'paid_amount' => $total,
                'paid_at' => now()->subHours(2),
                'payment_integration_id' => $method === 'erede' ? 'TESTE-' . (++$this->tid) : null,
            ]);
            if ($method !== 'erede') {
                $payment->is_manual = true;
                $payment->recorded_by = $this->user?->id;
                $payment->manual_notes = 'Baixa de teste';
            }
            $payment->save();

            if (! empty($o['cancel_one'])) {
                $ticket = $order->tickets()->first();
                $ticket->update(['status' => Ticket::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => $this->user?->id, 'cancel_reason' => 'Cancelado no teste', 'refunded_amount' => $ticket->amount]);
                $payment->forceFill(['refunded_amount' => $ticket->amount, 'refunded_at' => now(), 'refunded_by' => $this->user?->id, 'status' => TicketPayment::STATUS_PARTIALLY_REFUNDED])->save();
            }
        }

        return $order;
    }

    /**
     * Um CPF válido novo a cada chamada (um ingresso por CPF no evento):
     * base de 9 dígitos em sequência, com os dígitos verificadores calculados.
     */
    private function nextCpf(): string
    {
        $base = str_pad((string) (910000000 + (++$this->cpf)), 9, '0', STR_PAD_LEFT);

        foreach ([10, 11] as $weight) {
            $sum = 0;
            for ($i = 0; $i < $weight - 1; $i++) {
                $sum += (int) $base[$i] * ($weight - $i);
            }
            $digit = ($sum * 10) % 11;
            $base .= $digit === 10 ? '0' : (string) $digit;
        }

        return $base;
    }
}
