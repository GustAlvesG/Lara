@php
    use App\Models\FreelancerService;

    $blockMinutes = FreelancerService::BLOCK_MINUTES;

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
        'start_date' => (string) ($row['start_date'] ?? ''),
        'start_time' => (string) ($row['start_time'] ?? ''),
        'end_time' => (string) ($row['end_time'] ?? ''),
    ])->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Registro em Massa') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-full mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Registro em massa</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">
                    Vários contratos de uma vez. Valor e horas pagas são calculados no servidor.
                </p>
            </div>

            <a href="{{ route('freelancer-services.create') }}" class="inline-flex items-center px-4 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 rounded-xl font-bold shadow border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                Registro individual
            </a>
        </div>

        @include('partials.alerts')

        {{-- Tudo-ou-nada: nenhuma linha é gravada enquanto houver erro, então os
             problemas vêm juntos, numerados pela linha. --}}
        @if($errors->any())
            <div class="mb-6 bg-white dark:bg-gray-800 border-2 border-red-300 dark:border-red-700 rounded-2xl shadow-xl p-6">
                <p class="font-extrabold text-red-700 dark:text-red-400">
                    Nada foi registrado. Corrija e envie de novo.
                </p>
                <ul class="mt-3 space-y-1 text-sm text-red-700 dark:text-red-300 list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('confirm_weekly_limit'))
            <div class="mb-6 bg-amber-500 border border-amber-400 text-white px-6 py-4 rounded-2xl shadow-xl flex items-center gap-4">
                <div class="bg-white/20 p-2 rounded-full shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-extrabold text-lg leading-none">Atenção</p>
                    <p class="text-sm text-amber-50 mt-1">{{ session('confirm_weekly_limit') }}</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('freelancer-services.bulk.store') }}"
              x-data="{
                freelancers: {{ Js::from($freelancerOptions) }},
                functions: {{ Js::from($functionOptions) }},
                blockMinutes: {{ $blockMinutes }},
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
                        start_date: from ? from.start_date : '',
                        start_time: from ? from.start_time : '',
                        end_time: from ? from.end_time : '',
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
                rowPrice(row) {
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

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50 flex items-center justify-between gap-4">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">Contratos</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <span x-text="rows.length"></span> linha(s) · total estimado
                        <b class="text-gray-800 dark:text-gray-100" x-text="brl(total)"></b>
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-3 py-3 w-10">#</th>
                                <th class="px-3 py-3">Freelancer</th>
                                <th class="px-3 py-3">Função</th>
                                <th class="px-3 py-3">Evento / Local</th>
                                <th class="px-3 py-3">Data</th>
                                <th class="px-3 py-3">Início</th>
                                <th class="px-3 py-3">Término</th>
                                <th class="px-3 py-3">Duração / Valor</th>
                                <th class="px-3 py-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template x-for="(row, i) in rows" :key="i">
                                <tr class="align-top">
                                    <td class="px-3 py-3 text-gray-400 font-bold" x-text="i + 1"></td>

                                    <td class="px-3 py-3">
                                        <select :name="'services[' + i + '][freelancer_id]'" x-model="row.freelancer_id" required
                                            class="w-full min-w-[11rem] px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                            <option value="">Selecione...</option>
                                            <template x-for="f in freelancers" :key="f.id">
                                                <option :value="f.id" :disabled="f.incomplete"
                                                    x-text="f.name + (f.incomplete ? ' — cadastro incompleto' : '')"></option>
                                            </template>
                                        </select>
                                    </td>

                                    <td class="px-3 py-3">
                                        <select :name="'services[' + i + '][function_freelancer_id]'" x-model="row.function_freelancer_id" required
                                            class="w-full min-w-[10rem] px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                            <option value="">Selecione...</option>
                                            <template x-for="f in functions" :key="f.id">
                                                <option :value="f.id" x-text="f.name + ' (' + brl(f.price) + ' / ' + blockMinutes + 'min)'"></option>
                                            </template>
                                        </select>
                                    </td>

                                    <td class="px-3 py-3">
                                        <input type="text" :name="'services[' + i + '][location]'" x-model="row.location" required
                                            maxlength="255" placeholder="Ex: Confraternização - Salão Nobre"
                                            class="w-full min-w-[14rem] px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                    </td>

                                    <td class="px-3 py-3">
                                        <input type="date" :name="'services[' + i + '][start_date]'" x-model="row.start_date" required
                                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                    </td>

                                    <td class="px-3 py-3">
                                        <input type="time" :name="'services[' + i + '][start_time]'" x-model="row.start_time" required
                                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                    </td>

                                    <td class="px-3 py-3">
                                        <input type="time" :name="'services[' + i + '][end_time]'" x-model="row.end_time" required
                                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                        {{-- Término menor ou igual ao início significa turno que vira o dia. --}}
                                        <span x-show="crossesMidnight(row)" class="block mt-1 text-xs font-bold text-amber-600 dark:text-amber-400">
                                            termina no dia seguinte
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300 font-semibold" x-text="rowLabel(row)"></td>

                                    <td class="px-3 py-3 text-right">
                                        <button type="button" x-on:click="removeRow(i)" title="Remover linha"
                                            class="text-red-600 dark:text-red-400 font-bold px-2 hover:underline">×</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="p-6 border-t border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                    <button type="button" x-on:click="addRow()" x-bind:disabled="rows.length >= maxRows"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 rounded-xl font-bold shadow border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        + Adicionar linha
                    </button>
                    <span class="ml-3 text-xs text-gray-400 dark:text-gray-500">
                        A linha nova repete função, local, data e horários da anterior — só o freelancer fica em branco.
                        Máximo de {{ $maxRows }} linhas por envio.
                    </span>
                </div>
            </div>

            {{-- Mesma liberação do registro individual, pedida uma vez para o
                 lote. Aqui só pelo PIN: o código de e-mail é preso a um contrato
                 e não cobre um lote com várias linhas. --}}
            @if(session('confirm_weekly_limit'))
                <div class="mt-6 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border-2 border-amber-400 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-amber-50/60 dark:bg-amber-900/20">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Liberação do coordenador do setor Comercial</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Peça que o coordenador informe a <b>própria matrícula</b> e o <b>próprio PIN</b>.
                            Para liberar por código enviado ao e-mail dele, use o registro individual — o código
                            vale para um contrato de cada vez.
                        </p>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Matrícula do coordenador <span class="text-red-500">*</span></label>
                            <input type="text" name="coordinator_matricula" value="{{ old('coordinator_matricula') }}"
                                inputmode="numeric" autocomplete="off" required
                                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">PIN do coordenador <span class="text-red-500">*</span></label>
                            <input type="password" name="coordinator_pin" inputmode="numeric" maxlength="6"
                                pattern="[0-9]{6}" autocomplete="new-password" required placeholder="••••••"
                                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white tracking-[0.4em]">
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">6 dígitos. Não fica guardado na tela.</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('freelancer-services.index') }}" class="px-6 py-3 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancelar</a>
                @if(session('confirm_weekly_limit'))
                    <button type="submit" name="confirm_weekly_limit" value="1" class="px-6 py-3 bg-amber-500 text-white rounded-xl font-bold shadow-lg hover:bg-amber-600 transition">Liberar e registrar tudo</button>
                @else
                    <button type="submit" class="px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition">
                        Registrar <span x-text="rows.length"></span> contrato(s)
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
</x-app-layout>
