{{-- Emissão de ingressos pelo Lara: pagamento recebido por fora (baixa
     manual, com cupom opcional) ou cortesia (valor zerado). Sócio: para as
     pessoas do título dele (CPF de cada uma). Não sócio: um titular por
     ingresso (nome e CPF). Tipo de lugar marcado (mesa, bistrô…): o lugar é
     escolhido no mapa (TicketSeatMap.viewer), e o titular é o responsável. --}}
@php
    use App\Models\Ticket\TicketOrder;
    use App\Services\Ticket\TicketOrderService;

    $select = 'mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink';
    $money = fn ($v) => $v === null ? 'só sócios' : 'R$ ' . number_format((float) $v, 2, ',', '.');
    $buyerKind = old('buyer_kind', TicketOrder::BUYER_GUEST);
    $mode = old('mode', array_key_first($modes));
    $holders = old('holders', [['type_id' => $event->types->first()?->id, 'holder_name' => '', 'holder_cpf' => '']]);
    // O mapa do tipo de lugar marcado, já no dia da diária (JSON do painel, com a situação dos lugares).
    $mapUrl = fn ($type) => $type->isSeated() ? route('tickets.maps.state', [$event, $type->ticket_map_id, 'dia' => $type->ticket_event_day_id]) : null;
    $typeOptions = $event->types->map(fn ($t) => ['id' => $t->id, 'label' => $t->fullName(), 'map_url' => $mapUrl($t)])->values();
    $oldSeats = \App\Models\Ticket\TicketSeat::whereIn('id', collect($holders)->pluck('seat_id')->filter()->all())->pluck('label', 'id');
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Emitir ingressos — ' . $event->title" :back="route('tickets.orders.index', $event)">
            Para quem paga direto na secretaria (dinheiro, Pix, maquininha) ou para cortesias. O preço é o do lote à venda agora; fora do período de vendas ou sem estoque, o Lara avisa e pede confirmação.
        </x-page-title>

        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        @include('partials.alerts')

        @if ($modes === [])
            <x-empty-state icon="lock">Emitir pede a permissão de baixa manual ou de cortesia.</x-empty-state>
        @elseif ($event->types->isEmpty())
            <x-empty-state icon="ticket">O evento não tem tipo de ingresso ativo.</x-empty-state>
        @else
            <form method="POST" action="{{ route('tickets.issue.store', $event) }}" class="flex flex-col gap-6" id="issue-form">
                @csrf

                <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-2">
                    <h2 class="text-sm font-black uppercase tracking-widest text-ink-3 sm:col-span-2">Como</h2>
                    <fieldset class="flex flex-col gap-2">
                        <legend class="text-sm font-medium text-ink-2">Emissão</legend>
                        @foreach ($modes as $value => $label)
                            <label class="flex items-center gap-2 text-sm text-ink"><input type="radio" name="mode" value="{{ $value }}" @checked($mode === $value) data-mode> {{ $label }}</label>
                        @endforeach
                    </fieldset>
                    <fieldset class="flex flex-col gap-2">
                        <legend class="text-sm font-medium text-ink-2">Quem compra</legend>
                        <label class="flex items-center gap-2 text-sm text-ink"><input type="radio" name="buyer_kind" value="{{ TicketOrder::BUYER_MEMBER }}" @checked($buyerKind === TicketOrder::BUYER_MEMBER) data-buyer> Sócio (para as pessoas do título dele, preço de sócio)</label>
                        <label class="flex items-center gap-2 text-sm text-ink"><input type="radio" name="buyer_kind" value="{{ TicketOrder::BUYER_GUEST }}" @checked($buyerKind === TicketOrder::BUYER_GUEST) data-buyer> Não sócio (um ou mais ingressos, preço de não sócio)</label>
                    </fieldset>
                </section>

                <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4" data-for-buyer="{{ TicketOrder::BUYER_MEMBER }}">
                    <h2 class="text-sm font-black uppercase tracking-widest text-ink-3 sm:col-span-4">Sócio</h2>
                    <div><x-input-label for="member_cpf" value="CPF do sócio" /><x-text-input id="member_cpf" name="member_cpf" class="mt-1" :value="old('member_cpf')" /></div>
                    <div><x-input-label for="member_title" value="Matrícula (se o CPF estiver em mais de um título)" /><x-text-input id="member_title" name="member_title" class="mt-1" :value="old('member_title')" /></div>
                    <p class="text-xs text-ink-3 sm:col-span-4">Os dados e o e-mail vêm do cadastro do clube (MultiClubes). O sócio compra para as pessoas do título dele — titular e dependentes: nos ingressos abaixo, informe o CPF de cada uma (o nome vem do cadastro; CPF vazio = o próprio sócio).</p>
                </section>

                <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4" data-for-buyer="{{ TicketOrder::BUYER_GUEST }}">
                    <h2 class="text-sm font-black uppercase tracking-widest text-ink-3 sm:col-span-4">Quem compra</h2>
                    <div class="sm:col-span-2"><x-input-label for="buyer_name" value="Nome" /><x-text-input id="buyer_name" name="buyer_name" class="mt-1" :value="old('buyer_name')" /></div>
                    <div><x-input-label for="buyer_cpf" value="CPF" /><x-text-input id="buyer_cpf" name="buyer_cpf" class="mt-1" :value="old('buyer_cpf')" /></div>
                    <div><x-input-label for="buyer_phone" value="Telefone" /><x-text-input id="buyer_phone" name="buyer_phone" class="mt-1" :value="old('buyer_phone')" /></div>
                    <div class="sm:col-span-2"><x-input-label for="buyer_email" value="E-mail (para receber os ingressos)" /><x-text-input type="email" id="buyer_email" name="buyer_email" class="mt-1" :value="old('buyer_email')" /></div>
                </section>

                <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4">
                    <h2 class="text-sm font-black uppercase tracking-widest text-ink-3 sm:col-span-4">Ingressos (um por pessoa)</h2>
                    <p class="text-xs text-ink-3 sm:col-span-4">Não sócio: nome e CPF de cada pessoa. Sócio: só o CPF de cada pessoa do título (nome do cadastro; CPF vazio = o próprio sócio). Mesa, bistrô e camarote: escolha o lugar no mapa; o titular é o responsável pelo lugar, que não é entrada (cada pessoa precisa do ingresso dela).</p>
                    <div class="flex flex-col gap-3 sm:col-span-4" id="holders">
                        @foreach (array_values($holders) as $i => $holder)
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-5 sm:items-end" data-holder>
                                <div>
                                    <x-input-label value="Tipo" />
                                    <select name="holders[{{ $i }}][type_id]" class="{{ $select }}" data-type-select>
                                        @foreach ($typeOptions as $option)
                                            <option value="{{ $option['id'] }}" data-map-url="{{ $option['map_url'] }}" @selected((int) ($holder['type_id'] ?? 0) === $option['id'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div><x-input-label value="Nome" /><x-text-input name="holders[{{ $i }}][holder_name]" class="mt-1" :value="$holder['holder_name'] ?? ''" /></div>
                                <div><x-input-label value="CPF" /><x-text-input name="holders[{{ $i }}][holder_cpf]" class="mt-1" :value="$holder['holder_cpf'] ?? ''" /></div>
                                <div data-seat-cell>
                                    <input type="hidden" name="holders[{{ $i }}][seat_id]" value="{{ $holder['seat_id'] ?? '' }}" data-seat-id>
                                    <x-input-label value="Lugar" />
                                    <x-secondary-button type="button" class="mt-1 w-full" data-pick-seat><x-icon name="grid" /> <span data-seat-label>{{ $oldSeats[$holder['seat_id'] ?? 0] ?? 'Escolher no mapa' }}</span></x-secondary-button>
                                </div>
                                <div><x-secondary-button type="button" data-remove-holder>Tirar</x-secondary-button></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="sm:col-span-4"><x-secondary-button type="button" id="add-holder"><x-icon name="plus" /> Mais um ingresso</x-secondary-button></div>

                    @if ($event->types->contains(fn ($t) => $t->isSeated()))
                        <div class="flex flex-col gap-3 rounded-xl border border-line p-4 sm:col-span-4" id="seat-picker" style="display: none;">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-ink">Escolha o lugar <span class="font-normal text-ink-3" data-picker-title></span></p>
                                <x-secondary-button type="button" size="sm" id="seat-picker-close">Fechar</x-secondary-button>
                            </div>
                            @include('tickets.maps.partials.viewer', ['id' => 'seat-picker-map'])
                        </div>
                    @endif
                </section>

                <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4" data-for-mode="{{ TicketOrderService::ISSUE_EXTERNAL }}">
                    <h2 class="text-sm font-black uppercase tracking-widest text-ink-3 sm:col-span-4">Pagamento recebido</h2>
                    @include('tickets.partials.manual-payment-fields', ['key' => 'issue', 'methods' => $manualMethods])
                    <div class="sm:col-span-2">
                        <x-input-label for="coupon_code" value="Cupom (opcional)" />
                        <x-text-input id="coupon_code" name="coupon_code" class="mt-1 font-mono uppercase" :value="old('coupon_code')" list="coupon-list" />
                        <datalist id="coupon-list">
                            @foreach ($event->coupons as $coupon)
                                <option value="{{ $coupon->code }}">{{ $coupon->valueLabel() }}</option>
                            @endforeach
                        </datalist>
                    </div>
                    <p class="text-xs text-ink-3 sm:col-span-4">O valor é calculado pelo Lara (lote vigente, menos o cupom) e aparece no pedido depois de emitido.</p>
                </section>

                <section class="rounded-card bg-surface p-5 shadow-card">
                    <h2 class="mb-3 text-sm font-black uppercase tracking-widest text-ink-3">Preços agora</h2>
                    <ul class="flex flex-col gap-1 text-sm text-ink-2">
                        @foreach ($summary as $row)
                            <li>
                                <strong class="text-ink">{{ $row['type']->fullName() }}</strong>:
                                @if ($row['lot'])
                                    {{ $row['lot']->name }} ({{ $row['remaining'] === null ? 'sem limite' : $row['remaining'] . ' restante(s)' }}) · sócio {{ $money($row['lot']->price_member) }} · não sócio {{ $money($row['lot']->price_non_member) }}
                                @else
                                    nenhum lote à venda agora (o Lara avisa e usa o último lote ativo)
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>

                <div class="flex justify-end gap-2">
                    <x-secondary-button-a href="{{ route('tickets.orders.index', $event) }}">Cancelar</x-secondary-button-a>
                    <x-primary-button>Emitir</x-primary-button>
                </div>
            </form>

            <template id="holder-template">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-5 sm:items-end" data-holder>
                    <div>
                        <x-input-label value="Tipo" />
                        <select data-name="type_id" class="{{ $select }}" data-type-select>
                            @foreach ($typeOptions as $option)
                                <option value="{{ $option['id'] }}" data-map-url="{{ $option['map_url'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><x-input-label value="Nome" /><x-text-input data-name="holder_name" class="mt-1" /></div>
                    <div><x-input-label value="CPF" /><x-text-input data-name="holder_cpf" class="mt-1" /></div>
                    <div data-seat-cell>
                        <input type="hidden" data-name="seat_id" value="" data-seat-id>
                        <x-input-label value="Lugar" />
                        <x-secondary-button type="button" class="mt-1 w-full" data-pick-seat><x-icon name="grid" /> <span data-seat-label>Escolher no mapa</span></x-secondary-button>
                    </div>
                    <div><x-secondary-button type="button" data-remove-holder>Tirar</x-secondary-button></div>
                </div>
            </template>

            <script src="{{ asset('js/tickets/seat-map.js') }}?v={{ filemtime(public_path('js/tickets/seat-map.js')) }}"></script>

            <script>
                (function () {
                    const form = document.getElementById('issue-form');
                    const holders = document.getElementById('holders');
                    const template = document.getElementById('holder-template');

                    // Renumera os campos para o PHP receber holders[0..n].
                    const renumber = () => holders.querySelectorAll('[data-holder]').forEach((row, i) => {
                        row.querySelectorAll('select, input').forEach((field) => {
                            const name = field.dataset.name || (field.name.match(/\]\[(\w+)\]$/) || [])[1];
                            if (name) { field.dataset.name = name; field.name = `holders[${i}][${name}]`; }
                        });
                    });

                    document.getElementById('add-holder').addEventListener('click', () => {
                        holders.appendChild(template.content.cloneNode(true));
                        renumber();
                    });
                    holders.addEventListener('click', (e) => {
                        if (e.target.closest('[data-remove-holder]') && holders.querySelectorAll('[data-holder]').length > 1) {
                            e.target.closest('[data-holder]').remove();
                            renumber();
                        }
                    });

                    // Mostra só o que vale para o comprador e a emissão escolhidos;
                    // o que está escondido não vai no envio.
                    const apply = () => {
                        const buyer = form.querySelector('[data-buyer]:checked')?.value;
                        const mode = form.querySelector('[data-mode]:checked')?.value;
                        form.querySelectorAll('[data-for-buyer]').forEach((el) => {
                            const on = el.dataset.forBuyer === buyer;
                            el.classList.toggle('hidden', !on);
                            el.querySelectorAll('input, select').forEach((f) => f.disabled = !on);
                        });
                        form.querySelectorAll('[data-for-mode]').forEach((el) => {
                            const on = el.dataset.forMode === mode;
                            el.classList.toggle('hidden', !on);
                            el.querySelectorAll('input, select').forEach((f) => f.disabled = !on);
                        });
                    };
                    form.querySelectorAll('[data-buyer], [data-mode]').forEach((el) => el.addEventListener('change', apply));

                    // Lugar marcado: o botão "Lugar" só aparece no tipo com mapa;
                    // trocar o tipo apaga o lugar escolhido.
                    const picker = document.getElementById('seat-picker');
                    let viewer = null;
                    const mapUrl = (row) => row.querySelector('[data-type-select]').selectedOptions[0]?.dataset.mapUrl || '';
                    const clearSeat = (row) => {
                        row.querySelector('[data-seat-id]').value = '';
                        row.querySelector('[data-seat-label]').textContent = 'Escolher no mapa';
                    };
                    const syncSeat = (row, clear) => {
                        row.querySelector('[data-seat-cell]').style.visibility = mapUrl(row) ? 'visible' : 'hidden';
                        if (clear) clearSeat(row);
                    };
                    const closePicker = () => {
                        if (picker) picker.style.display = 'none';
                        if (viewer) { viewer.destroy(); viewer = null; }
                    };
                    holders.addEventListener('change', (e) => {
                        if (e.target.matches('[data-type-select]')) syncSeat(e.target.closest('[data-holder]'), true);
                    });
                    holders.addEventListener('click', (e) => {
                        const btn = e.target.closest('[data-pick-seat]');
                        if (!btn || !picker) return;
                        const row = btn.closest('[data-holder]');
                        const select = row.querySelector('[data-type-select]');
                        closePicker();
                        picker.style.display = '';
                        picker.querySelector('[data-picker-title]').textContent = '— ' + select.selectedOptions[0].textContent;
                        viewer = TicketSeatMap.viewer(document.getElementById('seat-picker-map'), {
                            mode: 'pick',
                            url: mapUrl(row),
                            typeId: Number(select.value),
                            selectedId: row.querySelector('[data-seat-id]').value,
                            onPick: (seat, data, why) => {
                                if (!seat) {
                                    if (why === 'taken') alert('O lugar escolhido acabou de ser reservado por outra pessoa. Escolha outro.');
                                    clearSeat(row);
                                    return;
                                }
                                const repeated = [...holders.querySelectorAll('[data-holder]')].some((r) => r !== row
                                    && r.querySelector('[data-seat-id]').value === String(seat.id)
                                    && r.querySelector('[data-type-select]').value === select.value);
                                if (repeated) {
                                    alert('O lugar ' + seat.label + ' já está em outro ingresso deste pedido.');
                                    return;
                                }
                                row.querySelector('[data-seat-id]').value = seat.id;
                                row.querySelector('[data-seat-label]').textContent = seat.label;
                                closePicker();
                            },
                        });
                        picker.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    });
                    document.getElementById('seat-picker-close')?.addEventListener('click', closePicker);
                    document.getElementById('add-holder').addEventListener('click', () => {
                        const rows = holders.querySelectorAll('[data-holder]');
                        syncSeat(rows[rows.length - 1], true);
                    });
                    holders.querySelectorAll('[data-holder]').forEach((row) => syncSeat(row, false));

                    renumber();
                    apply();
                })();
            </script>
        @endif
    </x-page>
</x-app-layout>
