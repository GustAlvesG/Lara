{{-- Emissão de ingressos pelo Lara: pagamento recebido por fora (baixa
     manual, com cupom opcional) ou cortesia (valor zerado). Sócio: para as
     pessoas do título dele (CPF de cada uma). Não sócio: um titular por
     ingresso (nome e CPF). --}}
@php
    use App\Models\Ticket\TicketOrder;
    use App\Services\Ticket\TicketOrderService;

    $select = 'mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink';
    $money = fn ($v) => $v === null ? 'só sócios' : 'R$ ' . number_format((float) $v, 2, ',', '.');
    $buyerKind = old('buyer_kind', TicketOrder::BUYER_GUEST);
    $mode = old('mode', array_key_first($modes));
    $holders = old('holders', [['type_id' => $event->types->first()?->id, 'holder_name' => '', 'holder_cpf' => '']]);
    $typeOptions = $event->types->map(fn ($t) => ['id' => $t->id, 'label' => $t->name])->values();
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
                    <p class="text-xs text-ink-3 sm:col-span-4">Não sócio: nome e CPF de cada pessoa. Sócio: só o CPF de cada pessoa do título (nome do cadastro; CPF vazio = o próprio sócio).</p>
                    <div class="flex flex-col gap-3 sm:col-span-4" id="holders">
                        @foreach (array_values($holders) as $i => $holder)
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4 sm:items-end" data-holder>
                                <div>
                                    <x-input-label value="Tipo" />
                                    <select name="holders[{{ $i }}][type_id]" class="{{ $select }}">
                                        @foreach ($event->types as $type)
                                            <option value="{{ $type->id }}" @selected((int) ($holder['type_id'] ?? 0) === $type->id)>{{ $type->fullName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div><x-input-label value="Nome" /><x-text-input name="holders[{{ $i }}][holder_name]" class="mt-1" :value="$holder['holder_name'] ?? ''" /></div>
                                <div><x-input-label value="CPF" /><x-text-input name="holders[{{ $i }}][holder_cpf]" class="mt-1" :value="$holder['holder_cpf'] ?? ''" /></div>
                                <div><x-secondary-button type="button" data-remove-holder>Tirar</x-secondary-button></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="sm:col-span-4"><x-secondary-button type="button" id="add-holder"><x-icon name="plus" /> Mais um ingresso</x-secondary-button></div>
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
                                    {{ $row['lot']->name }} ({{ $row['remaining'] }} restante(s)) · sócio {{ $money($row['lot']->price_member) }} · não sócio {{ $money($row['lot']->price_non_member) }}
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
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-4 sm:items-end" data-holder>
                    <div>
                        <x-input-label value="Tipo" />
                        <select data-name="type_id" class="{{ $select }}">
                            @foreach ($typeOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><x-input-label value="Nome" /><x-text-input data-name="holder_name" class="mt-1" /></div>
                    <div><x-input-label value="CPF" /><x-text-input data-name="holder_cpf" class="mt-1" /></div>
                    <div><x-secondary-button type="button" data-remove-holder>Tirar</x-secondary-button></div>
                </div>
            </template>

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
                    renumber();
                    apply();
                })();
            </script>
        @endif
    </x-page>
</x-app-layout>
