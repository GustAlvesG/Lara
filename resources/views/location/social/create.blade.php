{{-- Pré-reserva de espaço social lançada no balcão, em nome do sócio. O
     calendário vem de social-booking.availability (mesma regra do app); o
     sócio é buscado no MultiClubes pela matrícula, como na agenda das quadras.
     O valor mostrado é estimativa: o servidor recalcula ao gravar.
     Quem aprova pode fechar a reserva de uma vez (contrato + aprovação); quem
     dá baixa manual pode, além disso, registrar o pagamento já recebido. --}}
@php
    $field = 'w-full h-11 px-3.5 rounded-xl border border-line-strong bg-surface text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $canClose = auth()->user()->can(\App\Authorization\Permissions::RESERVAS_SOCIAIS_APROVAR);
    $canRecordPayment = $canClose && auth()->user()->can(\App\Authorization\Permissions::RESERVAS_PAGAMENTOS_BAIXA_MANUAL);
    $oldGuests = old('guests', []);
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Nova pré-reserva" :back="route('social-booking.index')">
            O dia fica preso para o sócio até a equipe aprovar ou recusar.
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if ($places->isEmpty())
            <x-empty-state icon="home">
                Nenhum espaço social ativo. Cadastre um grupo com categoria <strong>Social</strong> e os espaços dele em Reservas › Locais e Grupos.
            </x-empty-state>
        @else
        <form method="POST" action="{{ route('social-booking.store') }}" id="social-form" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            @csrf
            <input type="hidden" name="cpf" value="{{ old('cpf') }}">
            <input type="hidden" name="title" value="{{ old('title') }}">
            <input type="hidden" name="birthDate" value="{{ old('birthDate') }}">
            <input type="hidden" name="date" id="date" value="{{ old('date') }}">

            <div class="space-y-6 lg:col-span-2">
                {{-- Espaço e data --}}
                <section class="rounded-card bg-surface p-5 shadow-card">
                    <h2 class="mb-4 text-sm font-black uppercase tracking-widest text-ink-3">Espaço e data</h2>
                    <x-input-label for="place_id" value="Espaço" />
                    <select name="place_id" id="place_id" class="{{ $field }} mt-1" required>
                        <option value="">Escolha…</option>
                        @foreach ($places as $place)
                            <option value="{{ $place->id }}"
                                data-price="{{ (float) $place->price }}"
                                data-max-guests="{{ (int) $place->max_guests }}"
                                data-extra-guests="{{ (int) $place->extra_guests }}"
                                data-extra-price="{{ (float) $place->extra_guest_price }}"
                                data-capacity="{{ $place->capacity }}"
                                data-base-tables="{{ (int) $place->base_tables }}"
                                data-base-chairs="{{ (int) $place->base_chairs }}"
                                data-duration="{{ (int) $place->event_duration_hours }}"
                                data-earliest="{{ $place->event_earliest_start ? substr($place->event_earliest_start, 0, 5) : '' }}"
                                data-latest="{{ $place->event_latest_start ? substr($place->event_latest_start, 0, 5) : '' }}"
                                @selected((string) old('place_id') === (string) $place->id)>
                                {{ optional($place->group)->name }} – {{ $place->name }} (R$ {{ number_format((float) $place->price, 2, ',', '.') }})
                            </option>
                        @endforeach
                    </select>
                    <p id="place-info" class="mt-2 text-xs text-ink-3"></p>

                    <div class="mt-5 flex items-center justify-between">
                        <x-secondary-button size="sm" id="prev-month" aria-label="Mês anterior">‹</x-secondary-button>
                        <p id="month-label" class="font-bold capitalize text-ink"></p>
                        <x-secondary-button size="sm" id="next-month" aria-label="Próximo mês">›</x-secondary-button>
                    </div>
                    <div id="calendar" class="mt-3 grid grid-cols-7 gap-1.5 text-center text-sm"></div>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <x-pill kind="ok" :icon="false">Livre</x-pill>
                        <x-pill kind="danger" :icon="false">Reservado</x-pill>
                        <x-pill kind="warn" :icon="false">Bloqueado</x-pill>
                        <x-pill kind="off" :icon="false">Fora da janela</x-pill>
                    </div>
                    <p class="mt-3 text-sm font-bold text-ink">Data escolhida: <span id="date-label" class="font-mono">{{ old('date') ? \Illuminate\Support\Carbon::parse(old('date'))->format('d/m/Y') : '—' }}</span></p>
                    <div id="start-time-box" class="mt-4 hidden">
                        <x-input-label for="start_time" value="Horário de início" />
                        <x-text-input type="time" name="start_time" id="start_time" class="mt-1 sm:w-40" :value="old('start_time')" />
                        <p id="start-time-info" class="mt-1 text-xs text-ink-3"></p>
                    </div>
                </section>

                {{-- Sócio --}}
                <section class="rounded-card bg-surface p-5 shadow-card">
                    <h2 class="mb-4 text-sm font-black uppercase tracking-widest text-ink-3">Sócio</h2>
                    <x-input-label for="member-search" value="Matrícula" />
                    <input type="text" id="member-search" class="{{ $field }} mt-1" placeholder="Digite a matrícula (mín. 5 dígitos)" autocomplete="off">
                    <ul id="member-results" class="mt-2 hidden divide-y divide-line rounded-xl border border-line bg-surface"></ul>
                    <p id="member-selected" class="mt-2 text-sm font-bold text-ok">{{ old('cpf') ? 'Sócio selecionado (matrícula ' . old('title') . ')' : '' }}</p>
                </section>

                {{-- Evento --}}
                <section class="rounded-card bg-surface p-5 shadow-card">
                    <h2 class="mb-4 text-sm font-black uppercase tracking-widest text-ink-3">Evento</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-1">
                            <x-input-label for="event_type" value="Tipo de evento" />
                            <x-text-input name="event_type" id="event_type" class="mt-1" :value="old('event_type')" required maxlength="120" placeholder="Ex.: Aniversário" />
                        </div>
                        <div>
                            <x-input-label for="expected_guests" value="Pessoas (total)" />
                            <x-text-input type="number" min="1" name="expected_guests" id="expected_guests" class="mt-1" :value="old('expected_guests')" />
                        </div>
                        <div>
                            <x-input-label for="extra_guests" value="Não sócios extras" />
                            <x-text-input type="number" min="0" name="extra_guests" id="extra_guests" class="mt-1" :value="old('extra_guests', 0)" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-input-label for="notes" value="Observações" />
                        <textarea name="notes" id="notes" rows="3" maxlength="2000" class="mt-1 w-full rounded-xl border border-line-strong bg-surface px-3.5 py-2 text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint">{{ old('notes') }}</textarea>
                    </div>
                </section>

                {{-- Bebidas e mobiliário --}}
                <section class="rounded-card bg-surface p-5 shadow-card">
                    <h2 class="mb-1 text-sm font-black uppercase tracking-widest text-ink-3">Bebidas, mesas e cadeiras</h2>
                    <p id="furniture-info" class="mb-4 text-xs text-ink-3"></p>
                    @include('location.social.partials.catalog-items', [
                        'catalog' => $beverages,
                        'quantities' => collect(old('items', []))->pluck('quantity', 'beverage_id'),
                    ])
                </section>

                {{-- Convidados --}}
                <section class="rounded-card bg-surface p-5 shadow-card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-black uppercase tracking-widest text-ink-3">Convidados não sócios <span id="guest-limit" class="normal-case tracking-normal"></span></h2>
                        <x-secondary-button size="sm" id="add-guest"><x-icon name="plus" /> Convidado</x-secondary-button>
                    </div>
                    <p class="mb-3 text-xs text-ink-3">Opcional agora: a lista pode ser completada depois (pelo sócio no app, até a véspera, ou aqui).</p>
                    <div id="guests" class="space-y-2">
                        @foreach ($oldGuests as $i => $guest)
                            @include('location.social.partials.guest-row', ['i' => $i, 'guest' => $guest])
                        @endforeach
                    </div>
                    <template id="guest-template">
                        @include('location.social.partials.guest-row', ['i' => '__I__', 'guest' => []])
                    </template>
                </section>

                {{-- Fechar agora: contrato + aprovação (+ pagamento recebido) no mesmo envio --}}
                @if ($canClose)
                    <section class="rounded-card bg-surface p-5 shadow-card">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="complete" value="1" id="complete" class="mt-1 h-5 w-5 rounded border-line-strong text-grena focus:ring-grena-tint" @checked(old('complete'))>
                            <span>
                                <span class="block text-sm font-black uppercase tracking-widest text-ink-3">Fechar a reserva agora</span>
                                <span class="block text-xs text-ink-3">O contrato já está assinado: a reserva sai aprovada e o pagamento vai para o perfil do sócio no site. Sem marcar, fica como pré-reserva em análise.</span>
                            </span>
                        </label>

                        <div id="complete-box" class="mt-4 hidden space-y-4">
                            <div>
                                <x-input-label for="contract_file" value="Contrato assinado (opcional)" />
                                <input type="file" name="contract_file" id="contract_file" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-xs text-ink-2">
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div><x-input-label for="discount" value="Desconto (R$)" /><x-text-input type="number" step="0.01" min="0" id="discount" name="discount" class="mt-1" :value="old('discount', 0)" /></div>
                                <div><x-input-label for="discount_reason" value="Motivo do desconto" /><x-text-input id="discount_reason" name="discount_reason" class="mt-1" :value="old('discount_reason')" maxlength="255" /></div>
                            </div>

                            @if ($canRecordPayment)
                                <label class="flex items-start gap-3 border-t border-line pt-4">
                                    <input type="checkbox" name="paid_now" value="1" id="paid_now" class="mt-1 h-5 w-5 rounded border-line-strong text-grena focus:ring-grena-tint" @checked(old('paid_now'))>
                                    <span>
                                        <span class="block text-sm font-bold text-ink">O sócio já pagou, fora da plataforma</span>
                                        <span class="block text-xs text-ink-3">Baixa manual pelo total: a reserva sai confirmada e os convidados liberados na portaria.</span>
                                    </span>
                                </label>
                                <div id="paid-box" class="hidden grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="payment_method" value="Forma de pagamento" />
                                        <select name="payment_method" id="payment_method" class="{{ $field }} mt-1">
                                            <option value="">Escolha…</option>
                                            @foreach (\App\Models\SchedulePayment::MANUAL_METHODS as $value => $label)
                                                <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div><x-input-label for="paid_at" value="Pago em" /><x-text-input type="datetime-local" id="paid_at" name="paid_at" class="mt-1" :value="old('paid_at', now()->format('Y-m-d\TH:i'))" /></div>
                                    <div class="sm:col-span-2"><x-input-label for="payment_notes" value="Comprovante / observação" /><x-text-input id="payment_notes" name="payment_notes" class="mt-1" :value="old('payment_notes')" maxlength="500" placeholder="Ex.: Pix ID E123…, recibo 456" /></div>
                                </div>
                            @endif
                        </div>
                    </section>
                @endif
            </div>

            {{-- Resumo --}}
            <aside class="space-y-4">
                <section class="sticky top-24 rounded-card bg-surface p-5 shadow-card">
                    <h2 class="mb-4 text-sm font-black uppercase tracking-widest text-ink-3">Resumo (estimado)</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-ink-2">Locação</dt><dd class="font-mono" id="sum-base">R$ 0,00</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-2">Convidados extras</dt><dd class="font-mono" id="sum-extras">R$ 0,00</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-2">Bebidas e mobiliário</dt><dd class="font-mono" id="sum-items">R$ 0,00</dd></div>
                        <div class="flex justify-between border-t border-line pt-2 text-base font-bold"><dt>Total</dt><dd class="font-mono" id="sum-total">R$ 0,00</dd></div>
                    </dl>
                    <p class="mt-3 text-sm text-ink-2" id="sum-furniture"></p>
                    <p class="mt-3 text-xs text-ink-3" id="sum-discount-note">Desconto, se houver, é dado na aprovação.</p>
                    <x-primary-button class="mt-5 w-full" id="submit-social">Registrar pré-reserva</x-primary-button>
                </section>
            </aside>
        </form>
        @endif
    </x-page>

    <script>
        (function () {
            const AVAILABILITY_URL = "{{ route('social-booking.availability') }}";
            const API_TOKEN = "{{ config('services.api.token') }}";
            const API_MEMBERS_SEARCH_URL = "{{ route('member.getByTitle') }}";
            const form = document.getElementById('social-form');
            if (!form) return;

            const placeSelect = document.getElementById('place_id');
            const dateInput = document.getElementById('date');
            const money = v => (v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            const kinds = { available: 'bg-ok-soft text-ok hover:ring-2 hover:ring-ok cursor-pointer', reserved: 'bg-danger-soft text-danger', blocked: 'bg-warn-soft text-warn', out_of_window: 'bg-subtle text-ink-3' };
            let month = (dateInput.value || new Date().toISOString().slice(0, 7)).slice(0, 7);

            function selectedPlace() { return placeSelect.options[placeSelect.selectedIndex]; }

            function updateSummary() {
                const opt = selectedPlace();
                const base = opt && opt.value ? parseFloat(opt.dataset.price) : 0;
                const extraPrice = opt && opt.value ? parseFloat(opt.dataset.extraPrice) : 0;
                const extras = (parseInt(document.getElementById('extra_guests').value) || 0) * extraPrice;
                let items = 0, tables = 0, chairs = 0;
                document.querySelectorAll('.catalog-qty').forEach(i => {
                    const qty = parseInt(i.value) || 0;
                    items += qty * parseFloat(i.dataset.price);
                    tables += qty * parseInt(i.dataset.tables);
                    chairs += qty * parseInt(i.dataset.chairs);
                });
                document.getElementById('sum-base').innerText = money(base);
                document.getElementById('sum-extras').innerText = money(extras);
                document.getElementById('sum-items').innerText = money(items);
                // Fechar agora: o desconto entra já, e o botão diz o que vai acontecer.
                const complete = document.getElementById('complete');
                const closing = !!(complete && complete.checked);
                const paidNow = closing && !!document.getElementById('paid_now')?.checked;
                const discount = closing ? (parseFloat(document.getElementById('discount').value) || 0) : 0;
                document.getElementById('sum-total').innerText = money(Math.max(0, base + extras + items - discount));
                document.getElementById('sum-discount-note').innerText = closing
                    ? (discount > 0 ? `Com desconto de ${money(discount)}.` : 'Sem desconto.')
                    : 'Desconto, se houver, é dado na aprovação.';
                if (complete) {
                    document.getElementById('complete-box').classList.toggle('hidden', !closing);
                    const paidBox = document.getElementById('paid-box');
                    if (paidBox) {
                        paidBox.classList.toggle('hidden', !paidNow);
                        document.getElementById('payment_method').required = paidNow;
                    }
                }
                document.getElementById('submit-social').innerText = paidNow
                    ? 'Registrar reserva paga'
                    : (closing ? 'Aprovar e enviar para pagamento' : 'Registrar pré-reserva');

                const box = document.getElementById('start-time-box');
                const duration = opt && opt.value ? parseInt(opt.dataset.duration) || 0 : 0;
                box.classList.toggle('hidden', !duration);
                document.getElementById('start_time').required = !!duration;
                if (duration) {
                    const start = document.getElementById('start_time').value;
                    let info = `Duração de ${duration} h`;
                    if (opt.dataset.earliest || opt.dataset.latest) info += `; início ${opt.dataset.earliest ? 'a partir das ' + opt.dataset.earliest : ''}${opt.dataset.earliest && opt.dataset.latest ? ' e ' : ''}${opt.dataset.latest ? 'até as ' + opt.dataset.latest : ''}`;
                    if (start) {
                        const [h, m] = start.split(':').map(Number);
                        const endH = (h + duration) % 24;
                        info += ` — termina às ${String(endH).padStart(2, '0')}:${String(m).padStart(2, '0')}${h + duration >= 24 ? ' (dia seguinte)' : ''}`;
                    }
                    document.getElementById('start-time-info').innerText = info + '.';
                }

                if (opt && opt.value) {
                    const baseTables = parseInt(opt.dataset.baseTables) || 0;
                    const baseChairs = parseInt(opt.dataset.baseChairs) || 0;
                    document.getElementById('furniture-info').innerText = `O espaço inclui ${baseTables} mesa(s) e ${baseChairs} cadeira(s).`;
                    document.getElementById('sum-furniture').innerText = `Mesas: ${baseTables + tables} · Cadeiras: ${baseChairs + chairs}`;
                    const extraField = document.getElementById('extra_guests');
                    extraField.max = opt.dataset.extraGuests;
                    const limit = parseInt(opt.dataset.maxGuests) + (parseInt(extraField.value) || 0);
                    document.getElementById('guest-limit').innerText = `(até ${limit})`;
                    document.getElementById('place-info').innerText =
                        `Inclui ${opt.dataset.maxGuests} não sócio(s); até ${opt.dataset.extraGuests} extra(s) a ${money(parseFloat(opt.dataset.extraPrice))} cada` +
                        (opt.dataset.capacity ? `; capacidade total ${opt.dataset.capacity} pessoas.` : '.');
                }
            }

            async function loadCalendar() {
                const cal = document.getElementById('calendar');
                const [y, m] = month.split('-').map(Number);
                document.getElementById('month-label').innerText = new Date(y, m - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
                cal.innerHTML = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'].map(d => `<div class="text-[11px] font-bold text-ink-3">${d}</div>`).join('');
                if (!placeSelect.value) {
                    cal.insertAdjacentHTML('beforeend', '<p class="col-span-7 py-6 text-ink-3">Escolha o espaço para ver as datas.</p>');
                    return;
                }
                const res = await fetch(`${AVAILABILITY_URL}?place_id=${placeSelect.value}&month=${month}`, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { cal.insertAdjacentHTML('beforeend', '<p class="col-span-7 py-6 text-danger">Não foi possível carregar o calendário.</p>'); return; }
                const data = await res.json();
                const offset = new Date(y, m - 1, 1).getDay();
                cal.insertAdjacentHTML('beforeend', '<div></div>'.repeat(offset));
                data.days.forEach(day => {
                    const selected = day.date === dateInput.value ? ' ring-2 ring-grena' : '';
                    const el = document.createElement(day.status === 'available' ? 'button' : 'div');
                    if (day.status === 'available') el.type = 'button';
                    el.className = `rounded-lg py-2 font-mono font-bold ${kinds[day.status]}${selected}`;
                    el.innerText = Number(day.date.slice(8));
                    el.title = day.date;
                    if (day.status === 'available') el.addEventListener('click', () => {
                        dateInput.value = day.date;
                        document.getElementById('date-label').innerText = day.date.split('-').reverse().join('/');
                        loadCalendar();
                    });
                    cal.appendChild(el);
                });
            }

            function shiftMonth(delta) {
                const [y, m] = month.split('-').map(Number);
                const d = new Date(y, m - 1 + delta, 1);
                month = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
                loadCalendar();
            }

            document.getElementById('prev-month').addEventListener('click', () => shiftMonth(-1));
            document.getElementById('next-month').addEventListener('click', () => shiftMonth(1));
            placeSelect.addEventListener('change', () => { dateInput.value = ''; document.getElementById('date-label').innerText = '—'; updateSummary(); loadCalendar(); });
            form.addEventListener('input', updateSummary);
            form.addEventListener('change', updateSummary);

            // Convidados: linhas a partir do <template>, com índice único.
            let guestIndex = {{ count($oldGuests) }};
            document.getElementById('add-guest').addEventListener('click', () => {
                const html = document.getElementById('guest-template').innerHTML.replaceAll('__I__', guestIndex++);
                document.getElementById('guests').insertAdjacentHTML('beforeend', html);
            });
            document.getElementById('guests').addEventListener('click', e => {
                if (e.target.closest('[data-remove-guest]')) e.target.closest('[data-guest-row]').remove();
            });

            // Sócio: mesma busca por matrícula da agenda das quadras.
            const search = document.getElementById('member-search');
            const results = document.getElementById('member-results');
            let timer;
            search.addEventListener('input', () => {
                clearTimeout(timer);
                const q = search.value.trim();
                if (q.length < 5) { results.classList.add('hidden'); return; }
                timer = setTimeout(async () => {
                    results.innerHTML = '<li class="p-3 text-xs text-ink-3">Pesquisando…</li>';
                    results.classList.remove('hidden');
                    try {
                        const res = await fetch(API_MEMBERS_SEARCH_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${API_TOKEN}` }, body: JSON.stringify({ title: q }) });
                        const data = await res.json();
                        results.innerHTML = '';
                        if (!data.length) { results.innerHTML = '<li class="p-3 text-xs font-bold text-danger">Nenhuma pessoa com essa matrícula.</li>'; return; }
                        data.forEach(m => {
                            const li = document.createElement('li');
                            li.className = 'cursor-pointer p-3 text-sm hover:bg-grena-tint';
                            li.textContent = `${m.Name} — matrícula ${m.title} (${m.Titular == 1 ? 'titular' : 'dependente'})`;
                            li.addEventListener('click', () => {
                                form.querySelector('input[name="cpf"]').value = m.document;
                                form.querySelector('input[name="title"]').value = m.title;
                                form.querySelector('input[name="birthDate"]').value = (m.birth_date || '').split(' ')[0];
                                document.getElementById('member-selected').innerText = `Selecionado: ${m.Name} (matrícula ${m.title})`;
                                results.classList.add('hidden');
                            });
                            results.appendChild(li);
                        });
                    } catch (e) {
                        results.innerHTML = '<li class="p-3 text-xs text-danger">Erro ao consultar o MultiClubes.</li>';
                    }
                }, 350);
            });

            form.addEventListener('submit', e => {
                if (!dateInput.value) { e.preventDefault(); document.getElementById('date-label').innerText = 'escolha uma data livre no calendário'; }
                else if (!form.querySelector('input[name="cpf"]').value) { e.preventDefault(); document.getElementById('member-selected').innerText = 'Escolha o sócio.'; }
            });

            updateSummary();
            loadCalendar();
        })();
    </script>
</x-app-layout>
