@php
    $service = $service ?? null;
    // Um contrato assinado ou cancelado vira somente leitura.
    $locked = $locked ?? false;
    $functionPrices = $functions->pluck('price', 'id');
    $blockMinutes = \App\Models\FreelancerService::BLOCK_MINUTES;
@endphp

<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden"
     x-data="{
        blockMinutes: {{ $blockMinutes }},
        functionPrices: {{ $functionPrices->toJson() }},
        functionId: '{{ old('function_freelancer_id', $service?->function_freelancer_id) }}',
        startTime: '{{ old('start_time', $service ? substr($service->start_time, 0, 5) : '') }}',
        endTime: '{{ old('end_time', $service ? substr($service->end_time, 0, 5) : '') }}',
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
            const rate = parseFloat(this.functionPrices[this.functionId] ?? 0);
            const b = this.blocks;
            if (!rate || b === null) return null;
            return (rate * b).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
     }">
    <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Dados do Serviço</h2>
    </div>

    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Freelancer <span class="text-red-500">*</span></label>
            <select name="freelancer_id" required @disabled($locked)
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed">
                <option value="">Selecione...</option>
                @foreach($freelancers as $freelancerOption)
                    <option value="{{ $freelancerOption->id }}" @selected((int) old('freelancer_id', $service?->freelancer_id) === $freelancerOption->id)>
                        {{ $freelancerOption->name }}
                    </option>
                @endforeach
            </select>
            @error('freelancer_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Função <span class="text-red-500">*</span></label>
            <select name="function_freelancer_id" required x-model="functionId" @disabled($locked)
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed">
                <option value="">Selecione...</option>
                @foreach($functions as $functionOption)
                    <option value="{{ $functionOption->id }}" @selected((int) old('function_freelancer_id', $service?->function_freelancer_id) === $functionOption->id)>
                        {{ $functionOption->name }} (R$ {{ number_format($functionOption->price, 2, ',', '.') }} / {{ $blockMinutes }}min)
                    </option>
                @endforeach
            </select>
            @error('function_freelancer_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Evento / Local <span class="text-red-500">*</span></label>
            <input type="text" name="location" value="{{ old('location', $service?->location) }}" required @disabled($locked)
                placeholder="Ex: Festa de Confraternização - Salão Nobre"
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed">
            @error('location')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Data <span class="text-red-500">*</span></label>
            <input type="date" name="start_date" value="{{ old('start_date', $service?->start_date?->format('Y-m-d')) }}" required @disabled($locked)
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed">
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Dia em que o turno começa.</p>
            @error('start_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Início <span class="text-red-500">*</span></label>
                <input type="time" name="start_time" x-model="startTime" required @disabled($locked)
                    class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed">
                @error('start_time')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Término <span class="text-red-500">*</span></label>
                <input type="time" name="end_time" x-model="endTime" required @disabled($locked)
                    class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed">
                @error('end_time')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <p class="col-span-2 text-xs" x-show="crossesMidnight" x-cloak>
                <span class="text-amber-600 dark:text-amber-400 font-semibold">Termina no dia seguinte.</span>
            </p>
        </div>

        <div class="md:col-span-2 p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-dashed border-gray-300 dark:border-gray-600 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Duração</p>
                @if($locked)
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-200">{{ $service->formattedDuration() }}</p>
                @else
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-200" x-text="durationLabel ?? '—'"></p>
                @endif
            </div>
            <div>
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Horas pagas</p>
                @if($locked)
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-200">{{ number_format($service->total_hours, 2, ',', '.') }} h</p>
                @else
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-200" x-text="billedLabel ?? '—'"></p>
                @endif
            </div>
            <div>
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Valor</p>
                @if($locked)
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-200">R$ {{ number_format($service->price, 2, ',', '.') }}</p>
                @else
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-200" x-text="estimatedPrice ? 'R$ ' + estimatedPrice : '—'"></p>
                @endif
            </div>
            <p class="sm:col-span-3 text-xs text-gray-400 dark:text-gray-500">
                Cobrado em blocos de {{ $blockMinutes }} minutos. Blocos incompletos não são pagos
                (ex.: 3h10 paga 3h00). Calculado no servidor ao salvar.
            </p>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Status</label>
            <select name="status_id" @disabled($locked)
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed">
                @foreach($statuses as $status)
                    <option value="{{ $status->id }}" @selected((int) old('status_id', $service?->status_id ?? 1) === $status->id)>
                        {{ $status->status }}
                    </option>
                @endforeach
            </select>
            @error('status_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
