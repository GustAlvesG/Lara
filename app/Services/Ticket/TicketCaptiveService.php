<?php

namespace App\Services\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketMap;
use App\Models\Ticket\TicketSeat;
use App\Services\MultiClubes\MemberProfile;
use App\Services\MultiClubes\MemberProfileLookup;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Mesa cativa (docs/funcionalidades/ingressos.md): o lugar do mapa guardado
 * para uma matrícula (título). Até o evento liberar (`captive_release_days`
 * dias antes do início), só um sócio desse título compra o lugar — pelo site
 * ou no balcão; depois, é um lugar comum. A regra de venda fica no
 * TicketOrderService (`seat_captive`); aqui, o cadastro.
 *
 * Vale para todos os dias do evento. Copiar o mapa leva as cativas junto.
 */
class TicketCaptiveService
{
    public const STATE_BOUGHT = 'bought';
    public const STATE_WAITING = 'waiting';
    public const STATE_RELEASED = 'released';
    public const STATE_OTHER = 'other';

    /** @var array<string, string> */
    public const STATE_LABELS = [
        self::STATE_BOUGHT => 'Comprada pela matrícula',
        self::STATE_WAITING => 'Aguardando a compra',
        self::STATE_RELEASED => 'Liberada para a venda geral',
        self::STATE_OTHER => 'Vendida a outra pessoa',
    ];

    public function __construct(private MemberProfileLookup $profiles)
    {
    }

    /**
     * Guarda o lugar para a matrícula. O nome do titular vem do MultiClubes;
     * MultiClubes fora do ar, fica o nome digitado. Matrícula que o
     * MultiClubes não conhece é recusada.
     */
    public function assign(TicketSeat $seat, string $title, ?string $name = null): TicketSeat
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 20) {
            throw ValidationException::withMessages(['captive_title' => 'Informe a matrícula (até 20 caracteres).']);
        }

        $profiles = null;
        try {
            $profiles = $this->profiles->byTitle($title);
        } catch (\Throwable $e) {
            Log::warning('Ingressos: MultiClubes indisponível ao cadastrar mesa cativa; fica o nome digitado.', ['title' => $title, 'error' => $e->getMessage()]);
        }

        if ($profiles === []) {
            throw ValidationException::withMessages(['captive_title' => "A matrícula {$title} não tem título ativo no cadastro do clube."]);
        }

        $titular = $profiles ? (collect($profiles)->first(fn (MemberProfile $p) => $p->titular) ?? $profiles[0]) : null;
        $name = $titular?->name ?? (filled($name) ? mb_substr(trim($name), 0, 255) : null);

        $seat->update(['captive_title' => $titular?->title ?? $title, 'captive_name' => $name]);

        return $seat;
    }

    public function clear(TicketSeat $seat): void
    {
        $seat->update(['captive_title' => null, 'captive_name' => null]);
    }

    /** @return int quantas cativas saíram */
    public function clearAll(TicketMap $map): int
    {
        return $map->seats()->whereNotNull('captive_title')->update(['captive_title' => null, 'captive_name' => null]);
    }

    /** Dias antes do início em que as cativas viram lugar comum (null = só tirando a cativa). */
    public function setReleaseDays(TicketEvent $event, ?int $days): TicketEvent
    {
        if ($days !== null && ($days < 0 || $days > 365)) {
            throw ValidationException::withMessages(['captive_release_days' => 'Informe de 0 a 365 dias.']);
        }

        $event->update(['captive_release_days' => $days]);

        return $event;
    }

    /**
     * As cativas do mapa com a situação de cada uma: comprada pela matrícula,
     * aguardando, liberada (prazo passou) ou vendida a outra pessoa (depois da
     * liberação). Um lugar pode ter um ingresso por dia: conta qualquer dia.
     *
     * @return list<array{seat: TicketSeat, state: string, tickets: \Illuminate\Support\Collection<int, Ticket>}>
     */
    public function rows(TicketMap $map): array
    {
        $map->loadMissing('event');
        $released = $map->event->captivesReleased();

        $seats = $map->seats()->whereNotNull('captive_title')->orderBy('label')->get();
        $tickets = Ticket::occupying()->with('order')
            ->whereIn('ticket_seat_id', $seats->pluck('id'))
            ->get()
            ->groupBy('ticket_seat_id');

        return $seats->map(function (TicketSeat $seat) use ($tickets, $released) {
            $mine = ($tickets[$seat->id] ?? collect());
            $state = match (true) {
                $mine->contains(fn (Ticket $t) => TicketSeat::sameTitle($t->order->buyer_title, $seat->captive_title)) => self::STATE_BOUGHT,
                $mine->isNotEmpty() => self::STATE_OTHER,
                $released => self::STATE_RELEASED,
                default => self::STATE_WAITING,
            };

            return ['seat' => $seat, 'state' => $state, 'tickets' => $mine];
        })->all();
    }
}
