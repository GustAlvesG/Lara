@php
    use App\Models\FreelancerService;

    $blockMinutes = FreelancerService::BLOCK_MINUTES;
    $pricingHourly = FreelancerService::PRICING_HOURLY;
    $pricingFixed = FreelancerService::PRICING_FIXED;

    // Freelancer com cadastro incompleto não gera contrato: aparece na lista
    // marcado e desabilitado, como no registro individual.
    $freelancerOptions = $freelancers->map(fn($f) => [
        'id' => $f->id,
        'name' => $f->name,
        'incomplete' => !$f->hasCompleteContractData(),
    ])->values();

    $functionOptions = $functions->map(fn($f) => [
        'id' => $f->id,
        'name' => $f->name,
        'price' => (float) $f->price,
    ])->values();

    // Devolve o que foi digitado quando a validação recusa o lote.
    $initialRows = collect(old('services', []))->map(fn($row) => [
        'freelancer_id' => (string) ($row['freelancer_id'] ?? ''),
        'function_freelancer_id' => (string) ($row['function_freelancer_id'] ?? ''),
        'location' => (string) ($row['location'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'start_date' => (string) ($row['start_date'] ?? ''),
        'start_time' => (string) ($row['start_time'] ?? ''),
        'end_time' => (string) ($row['end_time'] ?? ''),
        'pricing_mode' => (string) ($row['pricing_mode'] ?? $pricingHourly),
        'fixed_price' => (string) ($row['fixed_price'] ?? ''),
    ])->values();
@endphp

<x-app-layout>
<div class="py-6">
    <div class="max-w-full mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">Registro em massa</h1>
                <p class="text-ink-2 font-medium">
                    Vários contratos de uma vez. Valor e horas pagas são calculados no servidor —
                    a não ser na linha marcada como valor fixo, que paga o valor digitado.
                </p>
            </div>

            <a href="{{ route('freelancer-services.create') }}" class="inline-flex items-center px-4 py-3 bg-surface text-ink rounded-xl font-bold shadow-card border border-line hover:bg-subtle transition">
                Registro individual
            </a>
        </div>

        @include('partials.alerts')

        {{-- Tudo-ou-nada: nenhuma linha é gravada enquanto houver erro, então os
             problemas vêm juntos, numerados pela linha. --}}
        @if($errors->any())
            <div class="mb-6 bg-surface border-2 border-danger/40 rounded-2xl shadow-pop p-6">
                <p class="font-extrabold text-danger">
                    Nada foi registrado. Corrija e envie de novo.
                </p>
                <ul class="mt-3 space-y-1 text-sm text-danger list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('confirm_weekly_limit'))
            <div class="mb-6 bg-warn border border-warn/40 text-white dark:text-canvas px-6 py-4 rounded-2xl shadow-pop flex items-center gap-4">
                <div class="bg-white/20 p-2 rounded-full shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-extrabold text-lg leading-none">Atenção</p>
                    <p class="text-sm opacity-90 mt-1">{{ session('confirm_weekly_limit') }}</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('freelancer-services.bulk.store') }}"
              x-data="{
                freelancers: {{ Js::from($freelancerOptions) }},
                functions: {{ Js::from($functionOptions) }},
                blockMinutes: {{ $blockMinutes }},
                pricingHourly: @js($pricingHourly),
                pricingFixed: @js($pricingFixed),
                maxRows: {{ $maxRows }},
                rows: {{ Js::from($initialRows) }},

                init() {
                    if (this.rows.length === 0) this.rows.push(this.blank());
                },
                blank(from = null) {
                    return {
                        freelancer_id: '',
                        function_freelancer_id: from ? from.function_freelancer_id : '',
                        /* O caso comum é o mesmo evento com várias pessoas: a
                           linha nova nasce com local, data e horários repetidos. */
                        location: from ? from.location : '',
                        /* A justificativa costuma ser própria de cada contrato,
                           então não é herdada: a linha nova começa sem ela. */
                        description: '',
                        start_date: from ? from.start_date : '',
                        start_time: from ? from.start_time : '',
                        end_time: from ? from.end_time : '',
                        /* O valor fixo é combinado com cada pessoa: a linha nova
                           volta ao padrão, por horas. */
                        pricing_mode: this.pricingHourly,
                        fixed_price: '',
                    };
                },
                addRow() {
                    if (this.rows.length >= this.maxRows) return;
                    this.rows.push(this.blank(this.rows[this.rows.length - 1] ?? null));
                },
                removeRow(i) {
                    this.rows.splice(i, 1);
                    if (this.rows.length === 0) this.rows.push(this.blank());
                },

                toMinutes(value) {
                    if (!value) return null;
                    const [h, m] = value.split(':').map(Number);
                    return (h * 60) + m;
                },
                /* Mesma conta do registro individual: blocos de 15 min, sempre
                   arredondados para baixo. */
                blocks(row) {
                    const s = this.toMinutes(row.start_time), e = this.toMinutes(row.end_time);
                    if (s === null || e === null || s === e) return null;
                    const duration = (e <= s ? e + 1440 : e) - s;
                    return Math.floor(duration / this.blockMinutes);
                },
                crossesMidnight(row) {
                    const s = this.toMinutes(row.start_time), e = this.toMinutes(row.end_time);
                    return s !== null && e !== null && e <= s;
                },
                isFixed(row) {
                    return row.pricing_mode === this.pricingFixed;
                },
                rowPrice(row) {
                    /* Valor fixo: vale o digitado, as horas não entram. */
                    if (this.isFixed(row)) {
                        const fixed = parseFloat(row.fixed_price);
                        return fixed > 0 ? fixed : null;
                    }
                    const fn = this.functions.find(f => String(f.id) === String(row.function_freelancer_id));
                    const b = this.blocks(row);
                    return (!fn || b === null) ? null : fn.price * b;
                },
                rowLabel(row) {
                    const b = this.blocks(row);
                    if (b === null) return '—';
                    const minutes = b * this.blockMinutes;
                    const h = Math.floor(minutes / 60), r = minutes % 60;
                    const duration = r === 0 ? h + 'h' : h + 'h' + String(r).padStart(2, '0');
                    const price = this.rowPrice(row);
                    return price === null ? duration : duration + ' · ' + this.brl(price);
                },
                get total() {
                    return this.rows.reduce((sum, row) => sum + (this.rowPrice(row) ?? 0), 0);
                },
                brl(value) {
                    return 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
              }">
            @csrf

            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                <div class="p-6 border-b border-line bg-subtle flex items-center justify-between gap-4">
                    <h2 class="text-lg font-bold text-ink">Contratos</h2>
                    <p class="text-sm text-ink-2">
                        <span x-text="rows.length"></span> linha(s) · total estimado
                        <b class="text-ink" x-text="brl(total)"></b>
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-ink-3 uppercase tracking-wider bg-subtle">
                            <tr>
                                <th class="px-3 py-3 w-10">#</th>
                                <th class="px-3 py-3">Freelancer</th>
                                <th class="px-3 py-3">Função</th>
                                <th class="px-3 py-3">Evento / Local</th>
                                <th class="px-3 py-3">Descrição / Justificativa</th>
                                <th class="px-3 py-3">Data</th>
                                <th class="px-3 py-3">Início</th>
                                <th class="px-3 py-3">Término</th>
                                <th class="px-3 py-3">Valor</th>
                                <th class="px-3 py-3">Duração / Valor</th>
                                <th class="px-3 py-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <template x-for="(row, i) in rows" :key="i">
                                <tr class="align-top">
                                    <td class="px-3 py-3 text-ink-3 font-bold" x-text="i + 1"></td>

                                    <td class="px-3 py-3">
                                        <select :name="'services[' + i + '][freelancer_id]'" x-model="row.freelancer_id" required
                                            class="w-full min-w-[11rem] px-3 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none bg-surface text-ink">
                                            <option value="">Selecione...</option>
                                            <template x-for="f in freelancers" :key="f.id">
                                                <option :value="f.id" :disabled="f.incomplete"
                                                    x-text="f.name + (f.incomplete ? ' — cadastro incompleto' : '')"></option>
                                            </template>
                                        </select>
                                    </td>

                                    <td class="px-3 py-3">
                                        <select :name="'services[' + i + '][function_freelancer_id]'" x-model="row.function_freelancer_id" required
                                            class="w-full min-w-[10rem] px-3 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none bg-surface text-ink">
                                            <option value="">Selecione...</option>
                                            <template x-for="f in functions" :key="f.id">
                                                <option :value="f.id" x-text="f.name + ' (' + brl(f.price) + ' / ' + blockMinutes + 'min)'"></option>
                                            </template>
                                        </select>
                                    </td>

                                    <td class="px-3 py-3">
                                        <input type="text" :name="'services[' + i + '][location]'" x-model="row.location" required
                                            maxlength="255" placeholder="Ex: Confraternização - Salão Nobre"
                                            class="w-full min-w-[14rem] px-3 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none bg-surface text-ink">
                                    </td>

                                    <td class="px-3 py-3">
                                        <textarea :name="'services[' + i + '][description]'" x-model="row.description" rows="2"
                                            maxlength="2000" placeholder="Opcional — não vai ao contrato"
                                            class="w-full min-w-[14rem] px-3 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none bg-surface text-ink"></textarea>
                                    </td>

                                    <td class="px-3 py-3">
                                        <input type="date" :name="'services[' + i + '][start_date]'" x-model="row.start_date" required
                                            class="w-full px-3 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none bg-surface text-ink">
                                    </td>

                                    <td class="px-3 py-3">
                                        <input type="time" :name="'services[' + i + '][start_time]'" x-model="row.start_time" required
                                            class="w-full px-3 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none bg-surface text-ink">
                                    </td>

                                    <td class="px-3 py-3">
                                        <input type="time" :name="'services[' + i + '][end_time]'" x-model="row.end_time" required
                                            class="w-full px-3 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none bg-surface text-ink">
                                        {{-- Término menor ou igual ao início significa turno que vira o dia. --}}
                                        <span x-show="crossesMidnight(row)" class="block mt-1 text-xs font-bold text-warn">
                                            termina no dia seguinte
                                        </span>
                                    </td>

                                    <td class="px-3 py-3">
                                        <select :name="'services[' + i + '][pricing_mode]'" x-model="row.pricing_mode"
                                            class="w-full min-w-[8rem] px-3 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none bg-surface text-ink">
                                            @foreach(FreelancerService::PRICING_MODES as $mode => $label)
                                                <option value="{{ $mode }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        {{-- Só existe na linha de valor fixo: um valor esquecido
                                             não pode acompanhar um contrato por horas. --}}
                                        <template x-if="isFixed(row)">
                                            <input type="number" :name="'services[' + i + '][fixed_price]'" x-model="row.fixed_price" required
                                                step="0.01" min="0.01" max="{{ FreelancerService::MAX_FIXED_PRICE }}" inputmode="decimal" placeholder="R$ 0,00"
                                                class="mt-2 w-full min-w-[8rem] px-3 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none bg-surface text-ink">
                                        </template>
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap text-ink font-semibold" x-text="rowLabel(row)"></td>

                                    <td class="px-3 py-3 text-right">
                                        <button type="button" x-on:click="removeRow(i)" title="Remover linha"
                                            class="text-danger font-bold px-2 hover:underline">×</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="p-6 border-t border-line bg-subtle">
                    <button type="button" x-on:click="addRow()" x-bind:disabled="rows.length >= maxRows"
                        class="inline-flex items-center px-4 py-2 bg-surface text-ink rounded-xl font-bold shadow-card border border-line hover:bg-subtle transition disabled:opacity-50 disabled:cursor-not-allowed">
                        + Adicionar linha
                    </button>
                    <span class="ml-3 text-xs text-ink-3">
                        A linha nova repete função, local, data e horários da anterior — freelancer e descrição ficam em branco, e o valor volta a ser por horas.
                        Máximo de {{ $maxRows }} linhas por envio.
                    </span>
                </div>
            </div>

            {{-- Mesma liberação do registro individual, pedida uma vez para o
                 lote. Aqui só pelo PIN: o código de e-mail é preso a um contrato
                 e não cobre um lote com várias linhas. --}}
            @if(session('confirm_weekly_limit'))
                <div class="mt-6 bg-surface rounded-2xl shadow-pop border-2 border-warn/40 overflow-hidden">
                    <div class="p-6 border-b border-line bg-warn-soft">
                        <h2 class="text-lg font-bold text-ink">Liberação do coordenador do setor Comercial</h2>
                        <p class="mt-1 text-sm text-ink-2">
                            Peça que um coordenador informe a <b>própria matrícula</b> e o <b>próprio PIN</b>.
                            O código por e-mail não serve aqui: ele vale para um contrato de cada vez — para
                            esse caminho, use o registro individual.
                        </p>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-ink mb-1">Matrícula do coordenador <span class="text-danger">*</span></label>
                            <input type="text" name="coordinator_matricula" value="{{ old('coordinator_matricula') }}"
                                inputmode="numeric" autocomplete="off" required
                                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-warn-soft focus:border-warn outline-none transition bg-surface text-ink">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-ink mb-1">PIN do coordenador <span class="text-danger">*</span></label>
                            <input type="password" name="coordinator_pin" inputmode="numeric" maxlength="6"
                                pattern="[0-9]{6}" autocomplete="new-password" required placeholder="••••••"
                                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-warn-soft focus:border-warn outline-none transition bg-surface text-ink tracking-[0.4em]">
                            <p class="mt-1 text-xs text-ink-3">6 dígitos. Não fica guardado na tela.</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('freelancer-services.index') }}" class="px-6 py-3 rounded-xl font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                @if(session('confirm_weekly_limit'))
                    <button type="submit" name="confirm_weekly_limit" value="1" class="px-6 py-3 bg-warn text-white dark:text-canvas rounded-xl font-bold shadow-card hover:bg-warn/90 transition">Liberar e registrar tudo</button>
                @else
                    <button type="submit" class="px-6 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition">
                        Registrar <span x-text="rows.length"></span> contrato(s)
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
</x-app-layout>
