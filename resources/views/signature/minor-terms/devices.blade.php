{{--
    Assinaturas → Termo de Menores → Tablet. Gera o QR de pareamento (lido
    pelo tablet em /assinatura/kiosk/menores) e lista os tablets pareados, com
    o botão de desparear. Ver KioskDeviceService.
--}}
@php
    $th = 'px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $campo = 'w-full rounded-xl border-line-strong shadow-card focus:border-grena focus:ring-grena-tint';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Termo de Menores">
            Pareie o tablet de autoatendimento. Ele fica pareado por {{ $ttlHours }} horas; depois disso, pareie de novo.
        </x-page-title>

        @include('signature.minor-terms.partials.tabs', ['active' => 'devices'])
        @include('partials.alerts')

        <div class="rounded-card bg-surface p-6 shadow-card" id="pareamento">
            <h3 class="mb-2 font-display text-base font-semibold text-ink">Parear um tablet</h3>
            <ol class="mb-4 list-decimal space-y-1 pl-5 text-sm text-ink-2">
                <li>No tablet, abra <span class="break-all font-mono text-ink">{{ $tabletUrl }}</span>.</li>
                <li>Aqui, toque em <b>Gerar QR de pareamento</b>.</li>
                <li>Aponte a câmera do tablet para o QR. Em instantes ele mostra a tela do autoatendimento.</li>
            </ol>

            <div class="flex flex-wrap items-end gap-3" data-pair-form>
                <div class="w-full sm:w-72">
                    <label for="device_name" class="mb-1 block text-xs font-bold text-ink-2">Nome do tablet (opcional)</label>
                    <input type="text" id="device_name" maxlength="80" placeholder="Tablet da entrada" class="{{ $campo }}">
                </div>
                <x-primary-button type="button" data-pair-start>Gerar QR de pareamento</x-primary-button>
            </div>

            <div class="mt-5 hidden flex-col items-center gap-3 text-center" data-pair-qr>
                {{-- Fundo branco fixo: no tema escuro a câmera não lê um QR sem contraste. --}}
                <div class="rounded-xl bg-white p-3"><img alt="QR de pareamento" width="260" height="260" data-pair-img></div>
                <p class="hidden text-sm text-ink-2" data-pair-code-wrap>
                    Sem câmera? Toque em <b>Digitar código</b> no tablet e informe
                    <span class="font-mono text-lg font-bold tracking-widest text-ink" data-pair-code></span>
                </p>
                <p class="text-sm font-semibold text-ink" data-pair-status>Aguardando a leitura pelo tablet…</p>
            </div>
        </div>

        @if($devices->isEmpty())
            <x-empty-state icon="doc">Nenhum tablet pareado ainda.</x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Tablet</th>
                                <th class="{{ $th }}">Pareado</th>
                                <th class="{{ $th }}">Válido até</th>
                                <th class="{{ $th }}">Último acesso</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $th }}"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($devices as $device)
                                <tr class="transition hover:bg-subtle">
                                    <td class="px-5 py-3.5 font-semibold text-ink">{{ $device->label() }}</td>
                                    <td class="px-5 py-3.5 text-xs text-ink-2">
                                        <span class="font-mono">{{ $device->paired_at?->format('d/m/Y H:i') }}</span>
                                        @if($device->paired_by_name)<div>por {{ $device->paired_by_name }}</div>@endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-ink-2">{{ $device->expires_at?->format('d/m/Y H:i') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-ink-2">{{ $device->last_seen_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        <x-pill :kind="$device->isActive() ? 'ok' : ($device->revoked_at ? 'danger' : 'off')">{{ $device->statusLabel() }}</x-pill>
                                        @if($device->revoked_by_name)<div class="mt-1 text-xs text-ink-2">por {{ $device->revoked_by_name }}</div>@endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                        @if($device->isActive())
                                            <form action="{{ route('minor-terms.devices.revoke', $device) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <x-danger-button size="sm">Desparear</x-danger-button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-page>

    <script>
    (function () {
        var raiz = document.getElementById('pareamento');
        var botao = raiz.querySelector('[data-pair-start]');
        var caixa = raiz.querySelector('[data-pair-qr]');
        var status = raiz.querySelector('[data-pair-status]');
        var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        var timer = null;
        var limite = 0;

        function para() { clearInterval(timer); timer = null; }

        botao.addEventListener('click', function () {
            para();
            botao.disabled = true;

            fetch(@json(route('minor-terms.devices.pair')), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ name: document.getElementById('device_name').value || null }),
            })
                .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
                .then(function (dados) {
                    raiz.querySelector('[data-pair-img]').src = dados.qr_src;
                    raiz.querySelector('[data-pair-code-wrap]').classList.toggle('hidden', !dados.manual_code);
                    raiz.querySelector('[data-pair-code]').textContent = dados.manual_code || '';
                    caixa.classList.remove('hidden');
                    caixa.classList.add('flex');
                    status.textContent = 'Aguardando a leitura pelo tablet…';
                    limite = Date.now() + dados.expires_in * 1000;

                    timer = setInterval(function () {
                        if (Date.now() > limite) {
                            para();
                            status.textContent = 'O QR venceu sem ser lido. Gere outro.';
                            botao.disabled = false;
                            return;
                        }

                        fetch(dados.status_url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                            .then(function (r) { return r.json(); })
                            .then(function (s) {
                                if (s.paired) {
                                    para();
                                    status.textContent = 'Tablet pareado.';
                                    setTimeout(function () { window.location.reload(); }, 1500);
                                }
                            })
                            .catch(function () {});
                    }, 3000);
                })
                .catch(function (e) {
                    status.textContent = 'Não foi possível gerar o QR (' + e.message + ').';
                    caixa.classList.remove('hidden');
                    caixa.classList.add('flex');
                    botao.disabled = false;
                });
        });
    })();
    </script>
</x-app-layout>
