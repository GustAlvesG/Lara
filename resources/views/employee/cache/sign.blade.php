@php
    use App\Models\FunctionFreelancer;

    // Faixas da função, para a tela mostrar o valor enquanto o funcionário
    // ajusta o horário. Quem calcula o que será pago é o servidor.
    $rates = $cache->functionFreelancer->ratesByHour()->map(fn($p) => (float) $p);
@endphp

<x-cache-sign-layout title="Assinar Cachê" heading="Assinar cachê" :employee="$employee">

    <div class="bg-white rounded-2xl shadow-xl p-5">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Evento</p>
        <p class="text-xl font-extrabold leading-tight">{{ $cache->location }}</p>
        <p class="text-gray-500">{{ $cache->functionFreelancer->name }} · {{ $cache->event_date->format('d/m/Y') }}</p>
        @if($cache->description)
            <p class="text-sm text-gray-500 mt-2">{{ $cache->description }}</p>
        @endif

        <div class="mt-4 bg-gray-50 rounded-xl p-4">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Horário previsto</p>
            <p class="font-bold">{{ $cache->formattedExpectedPeriod() }} · faixa de {{ $cache->expected_hours }}h</p>
        </div>
    </div>

    <form method="POST" action="{{ route('employee-caches.sign.store', $cache) }}"
          x-data="cacheSignature({{ Js::from($rates) }}, '{{ substr($cache->expected_start_time, 0, 5) }}', '{{ substr($cache->expected_end_time, 0, 5) }}')"
          @submit="prepare($event)"
          class="bg-white rounded-2xl shadow-xl p-5 space-y-5">
        @csrf

        <div>
            <h2 class="text-lg font-extrabold">Horário que você cumpriu</h2>
            <p class="text-sm text-gray-500">
                Comece pelo previsto e corrija se tiver sido diferente. Informar o horário real é
                o que este passo tem de mais importante.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Início</label>
                <input type="time" name="start_time" x-model="startTime" required
                    class="w-full px-4 py-4 text-lg border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Término</label>
                <input type="time" name="end_time" x-model="endTime" required
                    class="w-full px-4 py-4 text-lg border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none">
                <p x-show="crossesMidnight" x-cloak class="mt-1 text-xs font-bold text-amber-600">Termina no dia seguinte.</p>
            </div>
        </div>

        <div class="bg-gray-50 rounded-xl p-4 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Duração · faixa</p>
                <p class="font-bold" x-text="durationLabel + ' · faixa de ' + billedHours + 'h'"></p>
            </div>
            <div class="text-right">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Valor</p>
                <p class="text-xl font-extrabold" x-text="formatMoney(price)"></p>
            </div>
        </div>

        <div x-show="diverges" x-cloak class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
            O horário ficou diferente do previsto. Pode assinar assim mesmo — o cachê passará pela
            conferência do coordenador e da gerência antes do pagamento.
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Assinatura</label>
            <div class="relative border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 h-48 overflow-hidden">
                <canvas x-ref="canvas" class="absolute inset-0 w-full h-full touch-none"></canvas>
                <p x-show="!hasInk" x-cloak class="absolute inset-0 flex items-center justify-center text-gray-400 font-semibold pointer-events-none">
                    Assine aqui com o dedo
                </p>
            </div>
            <input type="hidden" name="signature" x-ref="signature">
            <button type="button" @click="clearSignature()" class="mt-2 text-sm font-bold text-gray-500 underline">Limpar assinatura</button>
        </div>

        <button type="submit" :disabled="!hasInk"
            class="w-full px-6 py-4 bg-[#A00001] text-white rounded-xl font-bold text-lg shadow-lg hover:bg-[#800000] transition disabled:opacity-40">
            Confirmar assinatura
        </button>

        <a href="{{ route('employee-caches.sign.list') }}" class="block text-center text-sm font-bold text-gray-500 underline">Voltar</a>
    </form>

    <script>
        // A conta aqui é só para o funcionário ver o valor antes de assinar. O
        // que vale é o cálculo do servidor, refeito no momento de gravar.
        function cacheSignature(rates, expectedStart, expectedEnd) {
            return {
                rates,
                expectedStart,
                expectedEnd,
                startTime: expectedStart,
                endTime: expectedEnd,
                hasInk: false,
                ctx: null,
                drawing: false,
                last: null,

                init() {
                    const canvas = this.$refs.canvas;
                    const resize = () => {
                        const rect = canvas.getBoundingClientRect();

                        if (!rect.width) return;

                        const dpr = window.devicePixelRatio || 1;
                        canvas.width = rect.width * dpr;
                        canvas.height = rect.height * dpr;
                        this.ctx = canvas.getContext('2d');
                        this.ctx.setTransform(1, 0, 0, 1, 0, 0);
                        this.ctx.scale(dpr, dpr);
                        this.ctx.lineWidth = 2.5;
                        this.ctx.lineCap = 'round';
                        this.ctx.lineJoin = 'round';
                        this.ctx.strokeStyle = '#1f2937';
                    };

                    resize();
                    window.addEventListener('resize', resize);

                    const point = (e) => {
                        const rect = canvas.getBoundingClientRect();

                        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
                    };

                    canvas.addEventListener('pointerdown', (e) => {
                        this.drawing = true;
                        this.last = point(e);
                        e.preventDefault();
                    });

                    canvas.addEventListener('pointermove', (e) => {
                        if (!this.drawing) return;

                        const p = point(e);
                        this.ctx.beginPath();
                        this.ctx.moveTo(this.last.x, this.last.y);
                        this.ctx.lineTo(p.x, p.y);
                        this.ctx.stroke();
                        this.last = p;
                        this.hasInk = true;
                        e.preventDefault();
                    });

                    ['pointerup', 'pointerleave', 'pointercancel'].forEach((evt) =>
                        canvas.addEventListener(evt, () => { this.drawing = false; })
                    );
                },

                clearSignature() {
                    const canvas = this.$refs.canvas;
                    this.ctx.clearRect(0, 0, canvas.width, canvas.height);
                    this.hasInk = false;
                },

                prepare(event) {
                    if (!this.hasInk) {
                        event.preventDefault();

                        return;
                    }

                    this.$refs.signature.value = this.$refs.canvas.toDataURL('image/png');
                },

                get minutes() {
                    if (!this.startTime || !this.endTime) return 0;

                    const [sh, sm] = this.startTime.split(':').map(Number);
                    const [eh, em] = this.endTime.split(':').map(Number);
                    let diff = (eh * 60 + em) - (sh * 60 + sm);

                    if (diff <= 0) diff += 24 * 60;

                    return diff;
                },

                get crossesMidnight() {
                    return Boolean(this.startTime && this.endTime && this.endTime <= this.startTime);
                },

                // Soma 15 minutos e toma a hora cheia; piso de 2h, teto de 11h.
                get billedHours() {
                    if (!this.minutes) return 0;

                    return Math.min(11, Math.max(2, Math.floor((this.minutes + 15) / 60)));
                },

                get durationLabel() {
                    if (!this.minutes) return '—';

                    const h = Math.floor(this.minutes / 60);
                    const m = this.minutes % 60;

                    return m === 0 ? `${h}h` : `${h}h${String(m).padStart(2, '0')}`;
                },

                get price() {
                    return Number(this.rates[this.billedHours] ?? 0);
                },

                get diverges() {
                    return this.startTime !== this.expectedStart || this.endTime !== this.expectedEnd;
                },

                formatMoney(value) {
                    return 'R$ ' + Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
            };
        }
    </script>
</x-cache-sign-layout>
