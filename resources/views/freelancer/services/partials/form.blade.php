@php
    $service = $service ?? null;
    // Um contrato assinado ou cancelado vira somente leitura.
    $locked = $locked ?? false;
    $functionPrices = $functions->pluck('price', 'id');
    $blockMinutes = \App\Models\FreelancerService::BLOCK_MINUTES;
    // Por horas (padrão) ou valor fixo digitado — ver "Valor fixo" no model.
    $pricingFixed = \App\Models\FreelancerService::PRICING_FIXED;
    $pricingMode = old('pricing_mode', $service?->pricingMode() ?? \App\Models\FreelancerService::PRICING_HOURLY);
    $fixedPrice = old('fixed_price', $service?->isFixedPrice() ? (float) $service->price : '');
    $maxFixedPrice = \App\Models\FreelancerService::MAX_FIXED_PRICE;
@endphp

<div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden"
     x-data="{
        blockMinutes: {{ $blockMinutes }},
        functionPrices: {{ $functionPrices->toJson() }},
        functionId: '{{ old('function_freelancer_id', $service?->function_freelancer_id) }}',
        startTime: '{{ old('start_time', $service ? substr($service->start_time, 0, 5) : '') }}',
        endTime: '{{ old('end_time', $service ? substr($service->end_time, 0, 5) : '') }}',
        pricingMode: @js($pricingMode),
        fixedPrice: @js((string) $fixedPrice),
        get isFixed() { return this.pricingMode === @js($pricingFixed); },
        toMinutes(value) {
            if (!value) return null;
            const [h, m] = value.split(':').map(Number);
            return (h * 60) + m;
        },
        /* Fim menor ou igual ao início significa que o turno virou a meia-noite. */
        get crossesMidnight() {
            const s = this.toMinutes(this.startTime), e = this.toMinutes(this.endTime);
            return s !== null && e !== null && e <= s;
        },
        get durationMinutes() {
            const s = this.toMinutes(this.startTime), e = this.toMinutes(this.endTime);
            if (s === null || e === null || s === e) return null;
            return (e <= s ? e + 1440 : e) - s;
        },
        /* Arredonda para baixo: só bloco cumprido por inteiro é pago. */
        get blocks() {
            const m = this.durationMinutes;
            return m === null ? null : Math.floor(m / this.blockMinutes);
        },
        get durationLabel() {
            const m = this.durationMinutes;
            if (m === null) return null;
            const h = Math.floor(m / 60), r = m % 60;
            return r === 0 ? h + 'h' : h + 'h' + String(r).padStart(2, '0');
        },
        get billedLabel() {
            const b = this.blocks;
            if (b === null) return null;
            return (b * this.blockMinutes / 60).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + ' h';
        },
        get estimatedPrice() {
            /* Valor fixo: vale o que foi digitado, as horas não entram. */
            if (this.isFixed) {
                const fixed = parseFloat(this.fixedPrice);
                return fixed > 0
                    ? fixed.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : null;
            }
            const rate = parseFloat(this.functionPrices[this.functionId] ?? 0);
            const b = this.blocks;
            if (!rate || b === null) return null;
            return (rate * b).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
     }">
    <div class="p-6 border-b border-line bg-subtle">
        <h2 class="text-lg font-bold text-ink">Dados do Serviço</h2>
    </div>

    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-ink mb-1">Freelancer <span class="text-danger">*</span></label>
            <select name="freelancer_id" required @disabled($locked)
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink disabled:opacity-60 disabled:cursor-not-allowed">
                <option value="">Selecione...</option>
                @foreach($freelancers as $freelancerOption)
                    @php $incomplete = method_exists($freelancerOption, 'hasCompleteContractData') && !$freelancerOption->hasCompleteContractData(); @endphp
                    <option value="{{ $freelancerOption->id }}" @selected((int) old('freelancer_id', $service?->freelancer_id) === $freelancerOption->id) @disabled($incomplete)>
                        {{ $freelancerOption->name }}{{ $incomplete ? ' — cadastro incompleto' : '' }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-ink-3">Freelancers com cadastro incompleto ficam indisponíveis até os dados serem completados.</p>
            @error('freelancer_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Função <span class="text-danger">*</span></label>
            <select name="function_freelancer_id" required x-model="functionId" @disabled($locked)
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink disabled:opacity-60 disabled:cursor-not-allowed">
                <option value="">Selecione...</option>
                @foreach($functions as $functionOption)
                    <option value="{{ $functionOption->id }}" @selected((int) old('function_freelancer_id', $service?->function_freelancer_id) === $functionOption->id)>
                        {{ $functionOption->name }} (R$ {{ number_format($functionOption->price, 2, ',', '.') }} / {{ $blockMinutes }}min)
                    </option>
                @endforeach
            </select>
            @error('function_freelancer_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-ink mb-1">Evento / Local <span class="text-danger">*</span></label>
            <input type="text" name="location" value="{{ old('location', $service?->location) }}" required @disabled($locked)
                placeholder="Ex: Festa de Confraternização - Salão Nobre"
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink disabled:opacity-60 disabled:cursor-not-allowed">
            @error('location')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-ink-3">Apenas o evento/local. Esclarecimentos vão no campo abaixo.</p>
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-ink mb-1">Descrição / Justificativa</label>
            <textarea name="description" rows="3" @disabled($locked)
                placeholder="Observações e justificativas sobre o serviço (opcional)."
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink disabled:opacity-60 disabled:cursor-not-allowed">{{ old('description', $service?->description) }}</textarea>
            <p class="mt-1 text-xs text-ink-3">Campo informativo, não aparece no contrato nem altera o cálculo.</p>
            @error('description')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Data <span class="text-danger">*</span></label>
            <input type="date" name="start_date" value="{{ old('start_date', $service?->start_date?->format('Y-m-d')) }}" required @disabled($locked)
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink disabled:opacity-60 disabled:cursor-not-allowed">
            <p class="mt-1 text-xs text-ink-3">Dia em que o turno começa.</p>
            @error('start_date')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-ink mb-1">Início <span class="text-danger">*</span></label>
                <input type="time" name="start_time" x-model="startTime" required @disabled($locked)
                    class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink disabled:opacity-60 disabled:cursor-not-allowed">
                @error('start_time')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-bold text-ink mb-1">Término <span class="text-danger">*</span></label>
                <input type="time" name="end_time" x-model="endTime" required @disabled($locked)
                    class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink disabled:opacity-60 disabled:cursor-not-allowed">
                @error('end_time')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <p class="col-span-2 text-xs" x-show="crossesMidnight" x-cloak>
                <span class="text-warn font-semibold">Termina no dia seguinte.</span>
            </p>
        </div>

        {{-- Forma de cálculo do valor. O padrão é por horas; o valor fixo troca
             só a conta — o turno continua com início e término, que é por onde a
             portaria abre e o prazo da assinatura conta. --}}
        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-bold text-ink mb-1">Valor do contrato</label>
                <select name="pricing_mode" x-model="pricingMode" @disabled($locked)
                    class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink disabled:opacity-60 disabled:cursor-not-allowed">
                    @foreach(\App\Models\FreelancerService::PRICING_MODES as $mode => $label)
                        <option value="{{ $mode }}" @selected($pricingMode === $mode)>
                            {{ $label }}{{ $mode === \App\Models\FreelancerService::PRICING_HOURLY ? ' (padrão)' : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-ink-3">Por horas: calculado pela função e pelo período. Valor fixo: o valor digitado ao lado, qualquer que seja a duração.</p>
                @error('pricing_mode')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>

            <div x-show="isFixed" x-cloak>
                <label class="block text-sm font-bold text-ink mb-1">Valor fixo (R$) <span class="text-danger">*</span></label>
                {{-- Desabilitado fora do valor fixo para não ir no envio: um valor
                     esquecido no campo não pode acompanhar um contrato por horas. --}}
                <input type="number" name="fixed_price" x-model="fixedPrice" step="0.01" min="0.01" max="{{ $maxFixedPrice }}"
                    inputmode="decimal" placeholder="0,00"
                    :required="isFixed" :disabled="!isFixed || @js($locked)"
                    class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink disabled:opacity-60 disabled:cursor-not-allowed">
                <p class="mt-1 text-xs text-ink-3">É o valor que vai ao contrato e ao pagamento.</p>
                @error('fixed_price')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="md:col-span-2 p-4 rounded-xl bg-subtle border border-dashed border-line-strong grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <p class="text-xs font-bold text-ink-2 uppercase tracking-wide">Duração</p>
                @if($locked)
                    <p class="text-lg font-bold text-ink">{{ $service->formattedDuration() }}</p>
                @else
                    <p class="text-lg font-bold text-ink" x-text="durationLabel ?? '—'"></p>
                @endif
            </div>
            <div>
                <p class="text-xs font-bold text-ink-2 uppercase tracking-wide">Horas pagas</p>
                {{-- No valor fixo as horas não são o que se paga: a duração ao
                     lado continua dizendo quanto o turno durou. --}}
                @if($locked)
                    <p class="text-lg font-bold text-ink">{{ $service->isFixedPrice() ? '—' : number_format($service->total_hours, 2, ',', '.') . ' h' }}</p>
                @else
                    <p class="text-lg font-bold text-ink" x-text="isFixed ? '—' : (billedLabel ?? '—')"></p>
                @endif
            </div>
            <div>
                <p class="text-xs font-bold text-ink-2 uppercase tracking-wide">Valor</p>
                @if($locked)
                    <p class="text-lg font-bold text-ink">R$ {{ number_format($service->price, 2, ',', '.') }}</p>
                @else
                    <p class="text-lg font-bold text-ink" x-text="estimatedPrice ? 'R$ ' + estimatedPrice : '—'"></p>
                @endif
            </div>
            <p class="sm:col-span-3 text-xs text-ink-3" x-show="!isFixed">
                Cobrado em blocos de {{ $blockMinutes }} minutos. Blocos incompletos não são pagos
                (ex.: 3h10 paga 3h00). Calculado no servidor ao salvar.
            </p>
            <p class="sm:col-span-3 text-xs text-ink-3" x-show="isFixed" x-cloak>
                <b>Valor fixo:</b> o valor é o digitado, e não muda com a duração do turno.
            </p>
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Status</label>
            <select name="status_id" @disabled($locked)
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink disabled:opacity-60 disabled:cursor-not-allowed">
                @foreach($statuses as $status)
                    <option value="{{ $status->id }}" @selected((int) old('status_id', $service?->status_id ?? 1) === $status->id)>
                        {{ $status->status }}
                    </option>
                @endforeach
            </select>
            @error('status_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
