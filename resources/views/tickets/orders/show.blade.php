{{-- Um pedido: quem comprou, ingressos (troca de titular, cancelamento),
     pagamento (baixa manual do pendente), reenvio do e-mail, PDF e impressão
     na térmica (abre numa aba e conta a via). Ingresso de lugar marcado
     (mesa…): mostra o lugar e permite trocar no mapa. No fim, o histórico:
     quem fez o quê. --}}
@php
    use App\Authorization\Permissions as P;
    use App\Models\SchedulePayment;
    use App\Models\Ticket\Ticket;
    use App\Models\Ticket\TicketLot;
    use App\Models\Ticket\TicketOrder;
    use App\Models\Ticket\TicketOrderLog;
    use App\Models\Ticket\TicketPayment;
    use App\Support\Cpf;

    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $th = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $select = 'mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink';
    $label = 'text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $valid = $order->tickets->where('status', Ticket::STATUS_VALID);
    // Cortesia de não sócio: o titular pode ficar sem CPF.
    $anonymousOk = $order->courtesy && $order->isGuest();
    $ticketKind = fn (string $status) => match ($status) {
        Ticket::STATUS_VALID => 'ok',
        Ticket::STATUS_USED => 'info',
        Ticket::STATUS_CANCELLED => 'danger',
        default => 'off',
    };
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Pedido #' . $order->id" :back="route('tickets.orders.index', $order->event)">
            {{ $order->event->title }} · {{ $order->event->datesLabel() }}
            <x-slot:actions>
                @if ($order->status === TicketOrder::STATUS_PAID)
                    <x-secondary-button-a href="{{ route('tickets.orders.pdf', $order) }}"><x-icon name="download" /> PDF</x-secondary-button-a>
                    @if ($valid->isNotEmpty())
                        <form method="POST" action="{{ route('tickets.orders.print', $order) }}" target="_blank">
                            @csrf
                            <x-secondary-button type="submit"><x-icon name="printer" /> Imprimir {{ $valid->count() > 1 ? 'os ' . $valid->count() : '' }} (térmica)</x-secondary-button>
                        </form>
                    @endif
                @endif
                @if ($order->buyer_email)
                    <form method="POST" action="{{ route('tickets.orders.resend', $order) }}">
                        @csrf
                        <x-secondary-button type="submit"><x-icon name="mail" /> Reenviar e-mail</x-secondary-button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4">
            <div>
                <p class="{{ $label }}">Situação</p>
                <p class="mt-1"><x-pill :kind="$order->status === TicketOrder::STATUS_PAID ? 'ok' : ($order->status === TicketOrder::STATUS_CANCELLED ? 'danger' : 'off')">{{ TicketOrder::STATUS_LABELS[$order->status] }}</x-pill></p>
                @if ($order->status === TicketOrder::STATUS_PENDING_PAYMENT && $order->hold_until)
                    <p class="mt-1 text-xs text-ink-3">{{ $order->holding() ? 'Lugar guardado até ' . $order->hold_until->format('d/m H:i') : 'Prazo vencido em ' . $order->hold_until->format('d/m H:i') }}</p>
                @endif
                @if ($order->cancel_reason)<p class="mt-1 text-xs text-ink-3">Motivo: {{ $order->cancel_reason }}</p>@endif
            </div>
            <div>
                <p class="{{ $label }}">Quem comprou</p>
                <p class="mt-1 font-semibold text-ink">{{ $order->buyer_name }}</p>
                <p class="text-xs text-ink-3">{{ TicketOrder::BUYER_LABELS[$order->buyer_kind] }} · {{ $order->buyer_cpf ? Cpf::format($order->buyer_cpf) : 'sem CPF' }}@if ($order->buyer_title) · matrícula {{ $order->buyer_title }}@endif</p>
                <p class="text-xs text-ink-3">{{ $order->buyer_email ?: 'sem e-mail' }}@if ($order->buyer_phone) · {{ $order->buyer_phone }}@endif</p>
            </div>
            <div>
                <p class="{{ $label }}">Valor</p>
                <p class="mt-1 font-mono text-ink">{{ $order->courtesy ? 'Cortesia' : $money($order->total) }}</p>
                @if ((float) $order->discount_total > 0)<p class="text-xs text-ink-3">Desconto {{ $money($order->discount_total) }} (cupom {{ $order->coupon_code }})</p>@endif
            </div>
            <div>
                <p class="{{ $label }}">Origem</p>
                <p class="mt-1 text-ink-2">{{ $order->origin === TicketOrder::ORIGIN_COUNTER ? 'Emitido no Lara' . ($order->creator ? ' por ' . $order->creator->name : '') : 'Site' }}</p>
                <p class="text-xs text-ink-3">{{ $order->created_at->format('d/m/Y H:i') }}</p>
            </div>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Ingressos</h2>
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="{{ $th }}">Titular</th>
                                <th class="{{ $th }}">Ingresso</th>
                                <th class="{{ $th }}">Código</th>
                                <th class="{{ $th }}">Valor</th>
                                <th class="{{ $th }}">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($order->tickets as $ticket)
                                <tr>
                                    <td class="px-4 py-3"><p class="font-semibold text-ink">{{ $ticket->holder_name }}</p><p class="text-xs text-ink-3">{{ $ticket->holder_cpf ? Cpf::format($ticket->holder_cpf) : 'sem CPF (entra pelo código)' }}</p></td>
                                    <td class="px-4 py-3 text-ink-2">
                                        {{ $ticket->type?->fullName() }}
                                        @if ($ticket->seat)<span class="block font-semibold text-ink">Lugar {{ $ticket->seat->fullLabel() }} <span class="font-normal text-xs text-ink-3">· {{ $ticket->type?->map?->name }} · não é entrada</span></span>@endif
                                        <span class="block text-xs text-ink-3">{{ $ticket->lot?->name }} · {{ TicketLot::PRICE_LABELS[$ticket->price_kind] ?? '' }}</span>
                                    </td>
                                    <td class="px-4 py-3 font-mono">{{ in_array($ticket->status, Ticket::SOLD, true) ? $ticket->code : '—' }}</td>
                                    <td class="px-4 py-3 font-mono">
                                        {{ $money($ticket->amount) }}
                                        @if ((float) $ticket->discount_amount > 0)<span class="block text-xs text-ink-3">de {{ $money($ticket->list_price) }}</span>@endif
                                        @if ((float) $ticket->refunded_amount > 0)<span class="block text-xs text-danger">estornado {{ $money($ticket->refunded_amount) }}</span>@endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-pill :kind="$ticketKind($ticket->status)">{{ Ticket::STATUS_LABELS[$ticket->status] }}</x-pill>
                                        @if ($ticket->checkins->count() > 1)
                                            {{-- Passaporte: uma entrada por dia. --}}
                                            @foreach ($ticket->checkins as $entry)<span class="block text-xs text-ink-3">entrou {{ $entry->checked_in_at->format('d/m H:i') }}{{ $entry->checker ? ' · ' . $entry->checker->name : '' }}</span>@endforeach
                                        @elseif ($ticket->checked_in_at)<span class="block text-xs text-ink-3">entrou {{ $ticket->checked_in_at->format('d/m H:i') }}{{ $ticket->checker ? ' · ' . $ticket->checker->name : '' }}</span>@endif
                                        @if ($ticket->print_count > 0)<span class="block text-xs text-ink-3">impresso {{ $ticket->print_count }}× · {{ $ticket->printed_at?->format('d/m H:i') }}</span>@endif
                                        @if ($ticket->status === Ticket::STATUS_VALID && $order->status === TicketOrder::STATUS_PAID && $order->tickets->count() > 1)
                                            <form method="POST" action="{{ route('tickets.orders.print', $order) }}" target="_blank" class="mt-1">
                                                @csrf
                                                <input type="hidden" name="ticket_ids[]" value="{{ $ticket->id }}">
                                                <button type="submit" class="text-xs font-semibold text-grena hover:underline">{{ $ticket->print_count > 0 ? 'Imprimir ' . ($ticket->print_count + 1) . 'ª via' : 'Imprimir só este' }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                                @if (in_array($ticket->status, [Ticket::STATUS_VALID, Ticket::STATUS_PENDING], true))
                                    <tr class="bg-subtle/40">
                                        <td colspan="5" class="px-4 py-3">
                                            <details>
                                                <summary class="cursor-pointer text-sm font-semibold text-ink-2">Trocar titular</summary>
                                                <form method="POST" action="{{ route('tickets.orders.holder', $ticket) }}" class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                                                    @csrf @method('PUT')
                                                    <div class="sm:col-span-2"><x-input-label for="h{{ $ticket->id }}-name" value="Nome" /><x-text-input id="h{{ $ticket->id }}-name" name="holder_name" class="mt-1" :value="$ticket->holder_name" :required="! $anonymousOk" /></div>
                                                    <div><x-input-label for="h{{ $ticket->id }}-cpf" :value="$anonymousOk ? 'CPF (opcional na cortesia)' : 'CPF'" /><x-text-input id="h{{ $ticket->id }}-cpf" name="holder_cpf" class="mt-1" :value="Cpf::format($ticket->holder_cpf)" :required="! $anonymousOk" /></div>
                                                    <div class="sm:text-right"><x-primary-button>Trocar</x-primary-button></div>
                                                </form>
                                            </details>
                                            @if ($ticket->seat && $ticket->type?->ticket_map_id)
                                                <details class="mt-2" data-seat-change="{{ $ticket->id }}">
                                                    <summary class="cursor-pointer text-sm font-semibold text-ink-2">Trocar lugar</summary>
                                                    <form method="POST" action="{{ route('tickets.orders.seat', $ticket) }}" class="mt-3 flex flex-col gap-3">
                                                        @csrf @method('PUT')
                                                        <input type="hidden" name="seat_id" value="" data-seat-id>
                                                        <p class="text-xs text-ink-3">Clique num lugar livre do mesmo tipo. Tipo, lote, valor e código não mudam.</p>
                                                        @include('tickets.maps.partials.viewer', ['id' => 'seat-change-' . $ticket->id])
                                                        <div class="flex items-center justify-end gap-3">
                                                            <span class="text-sm text-ink-2" data-seat-label>Lugar atual: {{ $ticket->seat->label }}</span>
                                                            <x-primary-button disabled data-seat-submit>Trocar para o lugar escolhido</x-primary-button>
                                                        </div>
                                                    </form>
                                                    <script type="application/json" data-seat-config>{!! json_encode([
                                                        'url' => route('tickets.maps.state', [$order->event, $ticket->type->ticket_map_id, 'dia' => $ticket->type->ticket_event_day_id]),
                                                        'typeId' => $ticket->ticket_type_id,
                                                        'currentSeatId' => $ticket->ticket_seat_id,
                                                    ], JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
                                                </details>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if ($order->payment)
            <section class="rounded-card bg-surface p-5 shadow-card">
                <h2 class="mb-3 text-sm font-black uppercase tracking-widest text-ink-3">Pagamento</h2>
                <p class="text-sm text-ink-2">
                    {{ $order->payment->is_manual ? 'Baixa manual (' . (SchedulePayment::MANUAL_METHODS[$order->payment->payment_method] ?? $order->payment->payment_method) . ')' : 'eRede · tid ' . $order->payment->payment_integration_id }}
                    · {{ $money($order->payment->paid_amount) }} em {{ $order->payment->paid_at?->format('d/m/Y H:i') }}
                    · {{ TicketPayment::STATUS_LABELS[$order->payment->status] ?? $order->payment->status }}
                </p>
                @if ($order->payment->is_manual)
                    <p class="text-xs text-ink-3">Registrada por {{ $order->payment->recordedBy?->name ?? '—' }}@if ($order->payment->manual_notes) · {{ $order->payment->manual_notes }}@endif</p>
                @endif
                @if ((float) $order->payment->refunded_amount > 0)
                    <p class="text-xs text-danger">Estornado {{ $money($order->payment->refunded_amount) }} por {{ $order->payment->refundedBy?->name ?? '—' }} em {{ $order->payment->refunded_at?->format('d/m/Y H:i') }}</p>
                @endif
            </section>
        @endif

        @if (in_array($order->status, [TicketOrder::STATUS_PENDING_PAYMENT, TicketOrder::STATUS_EXPIRED], true))
            @can(P::INGRESSOS_PAGAMENTOS_BAIXA_MANUAL)
                <section class="rounded-card bg-surface p-5 shadow-card">
                    <h2 class="mb-1 text-sm font-black uppercase tracking-widest text-ink-3">Baixa manual</h2>
                    <p class="mb-4 text-sm text-ink-3">Para o que foi pago fora da eRede. Os ingressos são liberados e enviados por e-mail.</p>
                    <form method="POST" action="{{ route('tickets.orders.manual', $order) }}" class="grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                        @csrf
                        @include('tickets.partials.manual-payment-fields', ['key' => 'pay', 'methods' => $manualMethods])
                        <div class="sm:col-span-4 sm:text-right"><x-primary-button>Dar baixa de {{ $money($order->total) }}</x-primary-button></div>
                    </form>
                </section>
            @endcan
        @endif

        @if ($order->tickets->contains(fn ($t) => $t->seat !== null))
            <script src="{{ asset('js/tickets/seat-map.js') }}?v={{ filemtime(public_path('js/tickets/seat-map.js')) }}"></script>
            <script>
                // O mapa só carrega quando a troca de lugar é aberta.
                document.querySelectorAll('[data-seat-change]').forEach((box) => {
                    let viewer = null;
                    box.addEventListener('toggle', () => {
                        if (!box.open) { viewer && viewer.destroy(); viewer = null; return; }
                        const config = JSON.parse(box.querySelector('[data-seat-config]').textContent);
                        const form = box.querySelector('form');
                        viewer = TicketSeatMap.viewer(document.getElementById('seat-change-' + box.dataset.seatChange), {
                            mode: 'pick',
                            url: config.url,
                            typeId: config.typeId,
                            currentSeatId: config.currentSeatId,
                            selectedId: config.currentSeatId,
                            onPick: (seat) => {
                                const changed = seat && seat.id !== config.currentSeatId;
                                form.querySelector('[data-seat-id]').value = changed ? seat.id : '';
                                form.querySelector('[data-seat-submit]').disabled = !changed;
                                form.querySelector('[data-seat-label]').textContent = changed ? 'Novo lugar: ' + seat.label : 'Lugar atual';
                            },
                        });
                    });
                });
            </script>
        @endif

        @if ($valid->isNotEmpty())
            <section class="rounded-card bg-surface p-5 shadow-card">
                <h2 class="mb-1 text-sm font-black uppercase tracking-widest text-ink-3">Cancelar ingressos</h2>
                <p class="mb-4 text-sm text-ink-3">Ingresso já usado não é cancelado. O estorno devolve pela eRede a soma dos marcados; baixa manual não tem estorno pelo Lara.</p>
                <form method="POST" action="{{ route('tickets.orders.cancel', $order) }}" class="flex flex-col gap-4" onsubmit="return confirm('Cancelar os ingressos marcados?')">
                    @csrf
                    <div class="flex flex-wrap gap-4">
                        @foreach ($valid as $ticket)
                            <label class="flex items-center gap-2 text-sm text-ink-2"><input type="checkbox" name="ticket_ids[]" value="{{ $ticket->id }}" checked> {{ $ticket->holder_name }} ({{ $money($ticket->amount) }})</label>
                        @endforeach
                    </div>
                    <div><x-input-label for="cancel-reason" value="Motivo" /><x-text-input id="cancel-reason" name="reason" class="mt-1" required maxlength="500" /></div>
                    @if ($order->payment && ! $order->payment->is_manual)
                        @can(P::INGRESSOS_PAGAMENTOS_ESTORNAR)
                            <label class="flex items-center gap-2 text-sm text-ink-2"><input type="checkbox" name="refund" value="1"> Estornar na eRede</label>
                        @endcan
                    @endif
                    <div class="text-right"><x-danger-button>Cancelar ingressos</x-danger-button></div>
                </form>
            </section>
        @endif

        <section class="flex flex-col gap-3">
            <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Histórico</h2>
            @if ($order->logs->isEmpty())
                <p class="text-sm text-ink-3">Sem registro (pedido de antes do histórico, de 28/10/2026).</p>
            @else
                <div class="overflow-x-auto rounded-card bg-surface shadow-card">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-line bg-subtle"><th class="{{ $th }}">Quando</th><th class="{{ $th }}">Quem</th><th class="{{ $th }}">O quê</th><th class="{{ $th }}">Detalhe</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($order->logs as $log)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-ink-2">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-ink">{{ $log->user?->name ?? ($log->action === TicketOrderLog::EXPIRED ? 'Lara (rotina)' : 'Site') }}</td>
                                    <td class="px-4 py-3 text-ink-2">{{ $log->label() }}</td>
                                    <td class="px-4 py-3 text-ink-3">{{ $log->summary() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </x-page>
</x-app-layout>
