<?php

namespace App\Services\Ticket;

use App\Exceptions\ConfirmationRequired;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketEventDay;
use App\Models\Ticket\TicketLot;
use App\Models\Ticket\TicketOrder;
use App\Models\Ticket\TicketType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Configuração do evento no painel: situação, dias, tipos e lotes.
 *
 * Como nos torneios, no Lara quase tudo é permitido mediante aviso
 * (ConfirmationRequired). Recusa de verdade só onde gravar estragaria o
 * histórico: apagar tipo ou lote que já tem ingresso (desative), publicar
 * evento sem nada à venda, mudar o dia de um tipo vendido e dia fora do evento
 * ou cruzando com outro.
 */
class TicketSetupService
{
    /** O caminho normal de cada situação; as outras passam por aviso. */
    public const TRANSITIONS = [
        TicketEvent::STATUS_DRAFT => [TicketEvent::STATUS_PUBLISHED, TicketEvent::STATUS_CANCELLED],
        TicketEvent::STATUS_PUBLISHED => [TicketEvent::STATUS_FINISHED, TicketEvent::STATUS_CANCELLED],
        TicketEvent::STATUS_FINISHED => [],
        TicketEvent::STATUS_CANCELLED => [],
    ];

    public function __construct(private TicketInventory $inventory)
    {
    }

    public function changeStatus(TicketEvent $event, string $status, bool $force = false): void
    {
        if ($status === $event->status) {
            return;
        }

        $warnings = [];

        if (! in_array($status, self::TRANSITIONS[$event->status] ?? [], true)) {
            $warnings[] = 'Mudança fora do caminho normal: de ' . TicketEvent::STATUS_LABELS[$event->status] . ' para ' . TicketEvent::STATUS_LABELS[$status] . '.';
        }

        if ($status === TicketEvent::STATUS_PUBLISHED) {
            $sellable = $event->types()->active()->whereHas('lots', fn ($q) => $q->where('active', true))->exists();
            if (! $sellable) {
                throw ValidationException::withMessages(['status' => 'Cadastre ao menos um tipo de ingresso ativo, com lote ativo, antes de publicar.']);
            }
        }

        $sold = $event->tickets()->whereIn('status', Ticket::SOLD)->count();
        $pending = $event->orders()->where('status', TicketOrder::STATUS_PENDING_PAYMENT)->count();

        if ($status === TicketEvent::STATUS_CANCELLED && $sold > 0) {
            $warnings[] = "Há {$sold} ingresso(s) pago(s). Cancelar o evento não estorna sozinho: cancele os ingressos em Pedidos, com ou sem estorno.";
        }
        if ($status === TicketEvent::STATUS_DRAFT && ($sold > 0 || $pending > 0)) {
            $warnings[] = "O evento tem {$sold} ingresso(s) pago(s) e {$pending} pedido(s) aguardando pagamento: em rascunho ele some do site, e quem comprou não vê mais o evento na vitrine.";
        }

        ConfirmationRequired::unless($force, $warnings);

        $event->update(['status' => $status]);
    }

    /**
     * Grava um dia do evento: dentro do período do evento, sem cruzar com
     * outro dia (a diária e o passaporte dependem de saber qual dia está
     * aberto na portaria).
     *
     * @param  array{name?: ?string, starts_at: string, ends_at: string}  $data
     */
    public function saveDay(TicketEvent $event, array $data, ?TicketEventDay $day = null): TicketEventDay
    {
        $starts = Carbon::parse($data['starts_at']);
        $ends = Carbon::parse($data['ends_at']);

        if ($starts->lessThan($event->starts_at) || $ends->greaterThan($event->ends_at)) {
            throw ValidationException::withMessages(['starts_at' => 'O dia precisa estar dentro do período do evento (' . $event->datesLabel() . '). Ajuste as datas do evento antes, se preciso.']);
        }

        $clash = $event->days()
            ->when($day, fn ($q) => $q->whereKeyNot($day->id))
            ->where('starts_at', '<', $ends)
            ->where('ends_at', '>', $starts)
            ->first();

        if ($clash) {
            throw ValidationException::withMessages(['starts_at' => "Este horário cruza com {$clash->label()} ({$clash->hoursLabel()})."]);
        }

        if ($day) {
            $day->update($data);

            return $day;
        }

        return $event->days()->create($data);
    }

    /** Apaga o dia, se nenhum tipo é diária dele (mude o tipo ou apague antes). */
    public function deleteDay(TicketEventDay $day): void
    {
        if ($day->types()->exists()) {
            throw ValidationException::withMessages(['day' => "Há tipo de ingresso (diária) em {$day->label()}: apague o tipo ou mude o dia dele antes."]);
        }

        $day->delete();
    }

    /**
     * Grava o tipo. O dia (diária) ou a falta dele (passaporte) não muda
     * depois que o tipo tem ingresso: mudaria para quando vale o que já foi
     * vendido.
     */
    public function saveType(TicketEvent $event, array $data, ?TicketType $type = null): TicketType
    {
        $day = $data['ticket_event_day_id'] ?? null;
        $data['ticket_event_day_id'] = $day === null || $day === '' ? null : (int) $day;

        if ($data['ticket_event_day_id'] !== null && ! $event->days()->whereKey($data['ticket_event_day_id'])->exists()) {
            throw ValidationException::withMessages(['ticket_event_day_id' => 'Escolha um dia deste evento.']);
        }

        if ($type && $type->ticket_event_day_id !== $data['ticket_event_day_id'] && $type->tickets()->exists()) {
            throw ValidationException::withMessages(['ticket_event_day_id' => "O tipo {$type->name} já tem ingresso: o dia em que ele vale não muda. Crie outro tipo."]);
        }

        if ($type) {
            $type->update($data);

            return $type;
        }

        return $event->types()->create($data);
    }

    /** Apaga o tipo, se não tem ingresso; com ingresso, a saída é desativar. */
    public function deleteType(TicketType $type): void
    {
        if ($type->tickets()->exists()) {
            throw ValidationException::withMessages(['type' => "O tipo {$type->name} já tem ingresso: desative em vez de apagar."]);
        }

        $type->delete();
    }

    public function deleteLot(TicketLot $lot): void
    {
        if ($lot->tickets()->exists()) {
            throw ValidationException::withMessages(['lot' => "O {$lot->name} já tem ingresso: desative em vez de apagar."]);
        }

        $lot->delete();
    }

    /**
     * Grava o lote. Quantidade abaixo do que já está ocupado não apaga nada:
     * só fecha o lote, e o Lara avisa antes.
     */
    public function saveLot(TicketType $type, array $data, ?TicketLot $lot = null, bool $force = false): TicketLot
    {
        $warnings = [];

        if ($lot) {
            $occupied = $this->inventory->occupiedByLot([$lot->id])[$lot->id] ?? 0;
            if (isset($data['quantity']) && (int) $data['quantity'] < $occupied) {
                $warnings[] = "O {$lot->name} já tem {$occupied} lugar(es) ocupado(s): com quantidade {$data['quantity']} ele fica esgotado (nenhum ingresso é cancelado).";
            }
            if ($occupied > 0 && ((float) $data['price_member'] !== (float) $lot->price_member || $this->nullableFloat($data['price_non_member'] ?? null) !== $this->nullableFloat($lot->price_non_member))) {
                $warnings[] = 'O lote já tem ingresso: o preço novo vale só para as próximas vendas; quem comprou mantém o valor pago.';
            }
        }

        ConfirmationRequired::unless($force, $warnings);

        if ($lot) {
            $lot->update($data);

            return $lot;
        }

        return $type->lots()->create($data);
    }

    /**
     * "Virar lote agora": em cada tipo ativo, o lote à venda fecha agora e o
     * seguinte abre (o primeiro de ordem maior que está ativo, não acabou e
     * tem sobra — se ele só abriria mais tarde, o início vem para agora). Tipo
     * sem lote seguinte continua no atual; tipo sem lote à venda fica de fora.
     * Pedidos pendentes não mudam: o ingresso guarda o lote em que coube.
     *
     * Sempre pede confirmação (`$force`), mostrando o que vira.
     *
     * @return list<array{type: TicketType, from: TicketLot, to: TicketLot}>
     */
    public function turnLots(TicketEvent $event, bool $force = false): array
    {
        return DB::transaction(function () use ($event, $force) {
            $event = TicketEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $event->load('days', 'types.lots', 'types.day');
            $occupied = $this->inventory->occupiedByLot($event->types->flatMap->lots->pluck('id'));
            $now = Carbon::now();
            $plan = [];
            $notes = [];

            foreach ($event->types->where('active', true) as $type) {
                $current = $this->inventory->currentLot($type, $occupied, $now);
                if (! $current) {
                    continue;
                }

                $next = $type->lots->first(fn (TicketLot $lot) => $lot->active
                    && ($lot->sequence > $current->sequence || ($lot->sequence === $current->sequence && $lot->id > $current->id))
                    && ($lot->ends_at === null || $lot->ends_at->greaterThan($now))
                    && $this->inventory->hasRoom($lot, $occupied));

                if (! $next) {
                    $notes[] = "{$type->fullName()}: o {$current->name} é o último lote e continua à venda.";

                    continue;
                }

                $plan[] = ['type' => $type, 'from' => $current, 'to' => $next];
            }

            if ($plan === []) {
                throw ValidationException::withMessages(['lots' => 'Nenhum tipo tem lote seguinte para virar.']);
            }

            $money = fn ($v) => $v === null ? 'não vendido' : 'R$ ' . number_format((float) $v, 2, ',', '.');
            ConfirmationRequired::unless($force, array_merge(array_map(
                fn (array $p) => "{$p['type']->fullName()}: o {$p['from']->name} fecha agora e o {$p['to']->name} abre (sócio {$money($p['to']->price_member)}, não sócio {$money($p['to']->price_non_member)}).",
                $plan,
            ), $notes));

            foreach ($plan as $p) {
                // Um segundo antes: no instante exato o fim ainda está dentro da janela.
                $p['from']->update(['ends_at' => $now->copy()->subSecond()]);
                if ($p['to']->starts_at && $p['to']->starts_at->greaterThan($now)) {
                    $p['to']->update(['starts_at' => $now]);
                }
            }

            return $plan;
        });
    }

    private function nullableFloat($value): ?float
    {
        return $value === null || $value === '' ? null : round((float) $value, 2);
    }
}
