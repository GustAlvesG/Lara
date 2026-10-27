{{-- Check-in na entrada do evento. O campo fica sempre com o foco: o leitor
     de QR (que "digita" o código e dá Enter) e a digitação usam o mesmo
     caminho. Em celular com câmera, o botão Ler QR usa o BarcodeDetector do
     navegador, quando existe. --}}
@php
    $result = session('checkin');
    $select = 'h-11 rounded-xl border border-line-strong bg-surface px-3 text-ink';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Check-in de ingressos">
            Leia o QR do ingresso ou digite o código (ING-…) ou o CPF de quem vai entrar. Enter registra a entrada; cada ingresso entra uma vez (o passaporte, uma vez por dia).
        </x-page-title>

        @include('partials.alerts')

        <form method="GET" action="{{ route('tickets.checkin.index') }}" class="flex flex-wrap items-end gap-3 rounded-card bg-surface p-5 shadow-card">
            <div>
                <x-input-label for="event" value="Evento" />
                <select id="event" name="event" class="{{ $select }} mt-1 w-full sm:w-96" onchange="this.form.submit()">
                    @if ($events->isEmpty() && ! $event)
                        <option value="">Nenhum evento com a entrada aberta agora</option>
                    @endif
                    @foreach ($events as $item)
                        <option value="{{ $item->id }}" @selected($event?->id === $item->id)>{{ $item->title }} — {{ $item->datesLabel() }}</option>
                    @endforeach
                    @if ($upcoming->whereNotIn('id', $events->pluck('id'))->isNotEmpty())
                        <optgroup label="Próximos (entrada ainda fechada)">
                            @foreach ($upcoming->whereNotIn('id', $events->pluck('id')) as $item)
                                <option value="{{ $item->id }}" @selected($event?->id === $item->id)>{{ $item->title }} — {{ $item->datesLabel() }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
            </div>
            @if ($event && $counts)
                @if ($counts['day'])
                    {{-- Evento de vários dias: o contador é do dia (diárias dele + passaportes). --}}
                    <p class="text-sm text-ink-2"><span class="font-mono text-2xl font-bold text-ink">{{ $counts['used'] }}</span> de {{ $counts['sold'] }} entraram em {{ $counts['day']->label() }}</p>
                    <p class="text-xs text-ink-3">Entrada do dia de {{ $counts['day']->checkinOpensAt()->format('d/m H:i') }} a {{ $counts['day']->ends_at->format('d/m H:i') }}{{ $counts['day']->checkinOpen() ? '' : ' (fechada agora)' }}</p>
                @else
                    <p class="text-sm text-ink-2"><span class="font-mono text-2xl font-bold text-ink">{{ $counts['used'] }}</span> de {{ $counts['sold'] }} entraram</p>
                    <p class="text-xs text-ink-3">Entrada de {{ $event->checkinOpensAt()->format('d/m H:i') }} a {{ $event->ends_at->format('d/m H:i') }}{{ $event->checkinOpen() ? '' : ' (fechada agora)' }}</p>
                @endif
            @endif
        </form>

        @if ($event)
            <form method="POST" action="{{ route('tickets.checkin.store', $event) }}" class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-card" id="checkin-form">
                @csrf
                <x-input-label for="target" value="Código do ingresso ou CPF" />
                <div class="flex flex-wrap gap-2">
                    <x-text-input id="target" name="target" class="flex-1 font-mono text-lg uppercase" autofocus autocomplete="off" required placeholder="ING-XXXXXXXX ou 000.000.000-00" />
                    <x-primary-button>Registrar entrada</x-primary-button>
                    <x-secondary-button type="submit" name="only_check" value="1">Só consultar</x-secondary-button>
                    <x-secondary-button type="button" id="scan-button" class="hidden"><x-icon name="eye" /> Ler QR</x-secondary-button>
                </div>
                <video id="scan-video" class="hidden max-h-72 rounded-xl" playsinline muted></video>
            </form>
        @endif

        @if ($result)
            <section @class([
                'rounded-card p-6 shadow-card',
                'bg-ok-soft text-ok' => $result['allowed'],
                'bg-danger-soft text-danger' => ! $result['allowed'],
            ]) role="status">
                <p class="text-xs font-black uppercase tracking-widest">{{ $result['allowed'] ? (($result['consult'] ?? false) ? 'Pode entrar (só consulta)' : 'Entrada liberada') : 'Não entra' }}</p>
                <p class="mt-1 font-display text-3xl font-bold">{{ $result['title'] }}</p>
                <p class="mt-1 text-base">{{ $result['message'] }}</p>
                @if (! empty($result['ticket']))
                    <p class="mt-3 text-sm">
                        <span class="font-mono font-bold">{{ $result['ticket']['code'] }}</span>
                        · CPF {{ $result['ticket']['cpf'] }} · {{ $result['ticket']['type'] }} ({{ $result['ticket']['lot'] }}) · {{ $result['ticket']['status'] }}
                        @if ($result['ticket']['checked_in_by']) · registrado por {{ $result['ticket']['checked_in_by'] }}@endif
                        @can(\App\Authorization\Permissions::INGRESSOS_VENDAS)
                            · <a href="{{ route('tickets.orders.show', $result['ticket']['order_id']) }}">pedido #{{ $result['ticket']['order_id'] }}</a>
                        @endcan
                    </p>
                    <p class="mt-1 text-xs">Confira o documento com foto: o ingresso é pessoal.</p>
                @endif
            </section>
        @endif
    </x-page>

    <script>
        (function () {
            const input = document.getElementById('target');
            const button = document.getElementById('scan-button');
            const video = document.getElementById('scan-video');
            if (!input || !button || !('BarcodeDetector' in window) || !navigator.mediaDevices) return;

            button.classList.remove('hidden');
            button.addEventListener('click', async () => {
                const detector = new BarcodeDetector({ formats: ['qr_code'] });
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                video.srcObject = stream;
                video.classList.remove('hidden');
                await video.play();

                const scan = async () => {
                    const codes = await detector.detect(video).catch(() => []);
                    if (codes.length) {
                        stream.getTracks().forEach((t) => t.stop());
                        input.value = codes[0].rawValue;
                        document.getElementById('checkin-form').submit();
                        return;
                    }
                    requestAnimationFrame(scan);
                };
                scan();
            });
        })();
    </script>
</x-app-layout>
