{{-- Um evento: dados, situação, e os tipos de ingresso com os lotes (lote
     vigente, ocupados, preços). Pedidos e check-in têm telas próprias. --}}
@php
    use App\Authorization\Permissions as P;
    use App\Models\Ticket\TicketEvent;

    // A tela abre para todo o módulo; editar evento, tipos e lotes é de `gerenciar`.
    $manage = auth()->user()->can(P::INGRESSOS_GERENCIAR);
    $money = fn ($v) => $v === null ? '—' : 'R$ ' . number_format((float) $v, 2, ',', '.');
    $th = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $window = fn ($lot) => match (true) {
        $lot->starts_at && $lot->ends_at => $lot->starts_at->format('d/m H:i') . ' a ' . $lot->ends_at->format('d/m H:i'),
        (bool) $lot->starts_at => 'a partir de ' . $lot->starts_at->format('d/m H:i'),
        (bool) $lot->ends_at => 'até ' . $lot->ends_at->format('d/m H:i'),
        default => 'janela do evento',
    };
    $actions = [
        TicketEvent::STATUS_PUBLISHED => ['Publicar no site', 'primary'],
        TicketEvent::STATUS_DRAFT => ['Voltar para rascunho', 'secondary'],
        TicketEvent::STATUS_FINISHED => ['Encerrar evento', 'secondary'],
        TicketEvent::STATUS_CANCELLED => ['Cancelar evento', 'danger'],
    ];
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="$event->title" :back="route('tickets.index')">
            {{ $event->datesLabel() }}@if ($event->venue) · {{ $event->venue }}@endif
            <x-slot:actions>
                @can(P::INGRESSOS_VENDAS)
                    <x-secondary-button-a href="{{ route('tickets.orders.index', $event) }}"><x-icon name="list" /> Pedidos</x-secondary-button-a>
                    @if (auth()->user()->can(P::INGRESSOS_PAGAMENTOS_BAIXA_MANUAL) || auth()->user()->can(P::INGRESSOS_CORTESIAS))
                        <x-secondary-button-a href="{{ route('tickets.issue.create', $event) }}"><x-icon name="plus" /> Emitir</x-secondary-button-a>
                    @endif
                @endcan
                @can(P::INGRESSOS_CUPONS)
                    <x-secondary-button-a href="{{ route('tickets.coupons.index', $event) }}"><x-icon name="tag" /> Cupons</x-secondary-button-a>
                @endcan
                @can(P::INGRESSOS_CHECKIN)
                    <x-secondary-button-a href="{{ route('tickets.checkin.index', ['event' => $event->id]) }}"><x-icon name="check" /> Check-in</x-secondary-button-a>
                @endcan
                @can(P::INGRESSOS_FINANCEIRO)
                    <x-secondary-button-a href="{{ route('tickets.finance.show', $event) }}"><x-icon name="money" /> Financeiro</x-secondary-button-a>
                @endcan
                @if ($manage)
                    <x-secondary-button-a href="{{ route('tickets.edit', $event) }}"><x-icon name="pencil" /> Editar</x-secondary-button-a>
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
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Situação</p>
                <p class="mt-1"><x-pill :kind="$event->status === TicketEvent::STATUS_PUBLISHED ? 'ok' : ($event->status === TicketEvent::STATUS_CANCELLED ? 'danger' : 'off')">{{ TicketEvent::STATUS_LABELS[$event->status] }}</x-pill></p>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Vendas</p>
                <p class="mt-1 font-mono text-ink">{{ $event->sales_starts_at->format('d/m/Y H:i') }} a {{ $event->sales_ends_at->format('d/m/Y H:i') }}</p>
                <p class="text-xs text-ink-3">{{ $event->salesOpen() ? 'Abertas agora' : 'Fechadas agora' }} · até {{ $event->max_per_order }} por pedido</p>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Lotação</p>
                @if ($event->capacity && $event->days->isNotEmpty())
                    {{-- Evento de vários dias: a lotação vale para cada dia (diárias do dia + passaportes). --}}
                    <p class="mt-1 font-mono text-ink">{{ $event->capacity }} por dia</p>
                    @foreach ($event->days as $day)
                        <p class="font-mono text-xs text-ink-2">{{ $day->label() }}: {{ $capacityByDay[$day->id] ?? $event->capacity }} livre(s)</p>
                    @endforeach
                @else
                    <p class="mt-1 font-mono text-ink">{{ $event->capacity ? $capacityLeft . ' de ' . $event->capacity . ' livre(s)' : 'Sem limite' }}</p>
                @endif
                <p class="text-xs text-ink-3">{{ $pendingOrders }} pedido(s) aguardando pagamento</p>
            </div>
            <div class="flex flex-wrap items-end gap-2 sm:justify-end">
                @if ($manage)
                @foreach ($transitions as $status)
                    <form method="POST" action="{{ route('tickets.status', $event) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="status" value="{{ $status }}">
                        @if ($actions[$status][1] === 'primary')
                            <x-primary-button>{{ $actions[$status][0] }}</x-primary-button>
                        @elseif ($actions[$status][1] === 'danger')
                            <x-danger-button>{{ $actions[$status][0] }}</x-danger-button>
                        @else
                            <x-secondary-button type="submit">{{ $actions[$status][0] }}</x-secondary-button>
                        @endif
                    </form>
                @endforeach
                @php $others = array_diff(array_keys(TicketEvent::STATUS_LABELS), $transitions, [$event->status]); @endphp
                @if ($others)
                    {{-- Fora do caminho normal (ex.: reabrir um encerrado): o Lara avisa e pede confirmação. --}}
                    <form method="POST" action="{{ route('tickets.status', $event) }}" class="flex items-center gap-2">
                        @csrf @method('PUT')
                        <select name="status" aria-label="Outra situação" class="h-10 rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink">
                            @foreach ($others as $status)
                                <option value="{{ $status }}">{{ TicketEvent::STATUS_LABELS[$status] }}</option>
                            @endforeach
                        </select>
                        <x-secondary-button type="submit">Mudar</x-secondary-button>
                    </form>
                @endif
                @endif
            </div>
            @if ($event->description)
                <p class="whitespace-pre-line text-sm text-ink-2 sm:col-span-4">{{ $event->description }}</p>
            @endif
        </section>

        @if ($manage || $event->days->isNotEmpty())
            @include('tickets.partials.days')
        @endif

        <section class="flex flex-col gap-4">
            <div>
                <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Tipos de ingresso e lotes</h2>
                <p class="text-sm text-ink-3">O site vende o lote de menor ordem que está ativo, dentro da janela e com sobra. Esgotou ou passou a data, o próximo abre na hora. Lote sem quantidade não esgota: fecha pela data ou por <strong>Virar lote agora</strong>. Ocupados = pagos + pedidos ainda no prazo de pagamento.</p>
                @if ($manage && $event->types->isNotEmpty())
                    {{-- Fecha o lote à venda de cada tipo e abre o seguinte; o Lara mostra o que vira e pede confirmação. --}}
                    <form method="POST" action="{{ route('tickets.lots.turn', $event) }}" class="mt-3">
                        @csrf
                        <x-secondary-button type="submit"><x-icon name="arrow-right" /> Virar lote agora</x-secondary-button>
                    </form>
                @endif
            </div>

            @forelse ($event->types as $type)
                @php
                    $row = $summary[$type->id] ?? null;
                    $nextSequence = ($type->lots->max('sequence') ?? 0) + 1;
                @endphp
                <article class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-card">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-bold text-ink">{{ $type->name }} @if ($type->validityLabel())<x-pill kind="info">{{ $type->ticket_event_day_id ? 'Diária · ' : '' }}{{ $type->validityLabel() }}</x-pill>@endif @unless ($type->active)<x-pill kind="off">inativo</x-pill>@endunless</h3>
                            @if ($type->description)<p class="text-sm text-ink-2">{{ $type->description }}</p>@endif
                            <p class="mt-1 text-xs text-ink-3">
                                @if ($row && $row['lot'])
                                    À venda agora: <strong>{{ $row['lot']->name }}</strong> · {{ $row['remaining'] === null ? 'sem limite' : $row['remaining'] . ' restante(s)' }} · sócio {{ $money($row['lot']->price_member) }} · não sócio {{ $row['lot']->price_non_member === null ? 'não vendido' : $money($row['lot']->price_non_member) }}
                                @elseif ($row && $row['next_lot'])
                                    Nenhum lote à venda agora; o {{ $row['next_lot']->name }} abre em {{ $row['next_lot']->starts_at->format('d/m/Y H:i') }}.
                                @else
                                    Nenhum lote à venda agora (esgotado, fora da janela ou sem lote ativo).
                                @endif
                            </p>
                        </div>
                        @if ($manage)
                            <form method="POST" action="{{ route('tickets.types.destroy', [$event, $type]) }}" onsubmit="return confirm('Apagar o tipo {{ addslashes($type->name) }} e os lotes dele?')">
                                @csrf @method('DELETE')
                                <x-danger-button size="sm">Apagar tipo</x-danger-button>
                            </form>
                        @endif
                    </div>

                    @if ($type->lots->isNotEmpty())
                        <div class="overflow-x-auto rounded-xl border border-line">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-line bg-subtle">
                                        <th class="{{ $th }}">Ordem</th>
                                        <th class="{{ $th }}">Lote</th>
                                        <th class="{{ $th }}">Janela</th>
                                        <th class="{{ $th }}">Ocupados</th>
                                        <th class="{{ $th }}">Sócio</th>
                                        <th class="{{ $th }}">Não sócio</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($type->lots as $lot)
                                        <tr @class(['bg-ok-soft/40' => $row && $row['lot']?->id === $lot->id])>
                                            <td class="px-4 py-3 font-mono">{{ $lot->sequence }}</td>
                                            <td class="px-4 py-3 font-semibold text-ink">
                                                {{ $lot->name }}
                                                @if ($row && $row['lot']?->id === $lot->id)<x-pill kind="ok">à venda</x-pill>@endif
                                                @unless ($lot->active)<x-pill kind="off">inativo</x-pill>@endunless
                                            </td>
                                            <td class="px-4 py-3 text-ink-2">{{ $window($lot) }}</td>
                                            <td class="px-4 py-3 font-mono text-ink-2">{{ $occupied[$lot->id] ?? 0 }} / {{ $lot->quantity ?? 'sem limite' }}<span class="block text-xs text-ink-3">{{ (int) ($sold[$lot->id] ?? 0) }} pago(s)</span></td>
                                            <td class="px-4 py-3 font-mono">{{ $money($lot->price_member) }}</td>
                                            <td class="px-4 py-3 font-mono">{{ $lot->price_non_member === null ? 'Só sócios' : $money($lot->price_non_member) }}</td>
                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                @if ($manage)
                                                    <form method="POST" action="{{ route('tickets.types.lots.destroy', [$event, $type, $lot]) }}" class="inline" onsubmit="return confirm('Apagar o {{ addslashes($lot->name) }}?')">
                                                        @csrf @method('DELETE')
                                                        <x-danger-button size="sm">Apagar</x-danger-button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                        @if ($manage)
                                        <tr class="bg-subtle/40">
                                            <td colspan="7" class="px-4 py-3">
                                                <details>
                                                    <summary class="cursor-pointer text-sm font-semibold text-ink-2">Editar {{ $lot->name }}</summary>
                                                    <form method="POST" action="{{ route('tickets.types.lots.update', [$event, $type, $lot]) }}" class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                                                        @csrf @method('PUT')
                                                        @include('tickets.partials.lot-fields', ['key' => 'l' . $lot->id, 'lot' => $lot, 'suggestedName' => '', 'suggestedSequence' => $lot->sequence])
                                                        <label class="flex items-center gap-2 text-sm text-ink-2"><input type="checkbox" name="active" value="1" @checked($lot->active)> Ativo</label>
                                                        <div class="sm:col-span-3 sm:text-right"><x-primary-button>Salvar lote</x-primary-button></div>
                                                    </form>
                                                    <p class="mt-2 text-xs text-ink-3">Preço novo vale para as próximas vendas; quem já comprou mantém o valor.</p>
                                                </details>
                                            </td>
                                        </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($manage)
                    <details @if ($type->lots->isEmpty()) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold text-grena">Novo lote em {{ $type->name }}</summary>
                        <form method="POST" action="{{ route('tickets.types.lots.store', [$event, $type]) }}" class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                            @csrf
                            @include('tickets.partials.lot-fields', ['key' => 't' . $type->id . '-new', 'lot' => null, 'suggestedName' => $nextSequence . 'º lote', 'suggestedSequence' => $nextSequence])
                            <div class="sm:col-span-4 sm:text-right"><x-primary-button>Criar lote</x-primary-button></div>
                        </form>
                    </details>

                    <details>
                        <summary class="cursor-pointer text-sm font-semibold text-ink-2">Editar tipo</summary>
                        <form method="POST" action="{{ route('tickets.types.update', [$event, $type]) }}" class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                            @csrf @method('PUT')
                            <div class="sm:col-span-2"><x-input-label for="type-{{ $type->id }}-name" value="Nome" /><x-text-input id="type-{{ $type->id }}-name" name="name" class="mt-1" :value="$type->name" required maxlength="255" /></div>
                            <div><x-input-label for="type-{{ $type->id }}-sort" value="Ordem na vitrine" /><x-text-input type="number" min="0" id="type-{{ $type->id }}-sort" name="sort_order" class="mt-1" :value="$type->sort_order" /></div>
                            <label class="flex items-center gap-2 text-sm text-ink-2"><input type="checkbox" name="active" value="1" @checked($type->active)> Ativo (à venda)</label>
                            @include('tickets.partials.type-day-select', ['id' => 'type-' . $type->id . '-day', 'selected' => $type->ticket_event_day_id])
                            <div class="sm:col-span-4"><x-input-label for="type-{{ $type->id }}-description" value="Descrição" /><x-text-input id="type-{{ $type->id }}-description" name="description" class="mt-1" :value="$type->description" maxlength="1000" /></div>
                            <div class="sm:col-span-4 sm:text-right"><x-primary-button>Salvar tipo</x-primary-button></div>
                        </form>
                    </details>
                    @endif
                </article>
            @empty
                <x-empty-state icon="ticket">Nenhum tipo de ingresso. Crie o primeiro abaixo (ex.: Pista, Mesa, Infantil).</x-empty-state>
            @endforelse

            @if ($manage)
            <div class="rounded-card bg-surface p-5 shadow-card">
                <h3 class="mb-4 text-sm font-black uppercase tracking-widest text-ink-3">Novo tipo de ingresso</h3>
                <form method="POST" action="{{ route('tickets.types.store', $event) }}" class="grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                    @csrf
                    <div class="sm:col-span-2"><x-input-label for="new-type-name" value="Nome" /><x-text-input id="new-type-name" name="name" class="mt-1" required maxlength="255" placeholder="Ex.: Pista" /></div>
                    <div><x-input-label for="new-type-sort" value="Ordem na vitrine" /><x-text-input type="number" min="0" id="new-type-sort" name="sort_order" class="mt-1" :value="$event->types->count() + 1" /></div>
                    @include('tickets.partials.type-day-select', ['id' => 'new-type-day', 'selected' => null])
                    <div class="sm:col-span-4"><x-input-label for="new-type-description" value="Descrição (opcional)" /><x-text-input id="new-type-description" name="description" class="mt-1" maxlength="1000" /></div>
                    <div class="sm:col-span-4 sm:text-right"><x-primary-button>Criar tipo</x-primary-button></div>
                </form>
            </div>
            @endif
        </section>
    </x-page>
</x-app-layout>
