@php
    $accessCount = count($data);
    $searchedDate = $datetime ? date('d/m/Y', strtotime($datetime)) : date('d/m/Y');
    $appCarRequests = collect($appCarRequests ?? []);

    // Cor lida pela câmera → amostra (é a pintura do carro, não cor da tela).
    $colorName = isset($car['color']) ? mb_strtolower(trim($car['color'])) : null;
    $swatch = [
        'branco' => '#f4f4f2', 'branca' => '#f4f4f2', 'preto' => '#1b1b1d', 'preta' => '#1b1b1d',
        'prata' => '#c4c8cc', 'cinza' => '#7d8288', 'vermelho' => '#b3261e', 'vermelha' => '#b3261e',
        'azul' => '#1f4e9c', 'verde' => '#2f6b3a', 'amarelo' => '#e2b007', 'amarela' => '#e2b007',
        'marrom' => '#6b4423', 'bege' => '#d8c7a3', 'laranja' => '#e06a1b', 'vinho' => '#6a1b2c',
        'dourado' => '#c9a227', 'dourada' => '#c9a227', 'rosa' => '#d77a9b', 'roxo' => '#5b3a7a',
    ][$colorName] ?? null;

    // Linha do dia: cada passagem no seu horário, de 0h a 24h. entry_date vem
    // "H:i:s d/m/Y"; a posição é o minuto do dia.
    $passes = [];
    foreach ($data as $i => $log) {
        $time = explode(' ', $log['entry_date'])[0] ?? '';
        if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            $passes[] = ['n' => $i + 1, 'time' => $time, 'left' => round(((int) $m[1] * 60 + (int) $m[2]) / 1440 * 100, 2)];
        }
    }
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page x-data="{ showSearch: {{ $accessCount ? 'false' : 'true' }} }">
        <x-page-title title="Dados do acesso" :back="route('parking.search')">
            Resultados de {{ $searchedDate }}.

            <x-slot:actions>
                <x-secondary-button x-on:click="showSearch = !showSearch">
                    <x-icon name="search" /> <span x-text="showSearch ? 'Fechar busca' : 'Nova busca'">Nova busca</span>
                </x-secondary-button>
            </x-slot:actions>
        </x-page-title>

        <div x-show="showSearch" x-cloak>
            @include('parking.partials.form')
        </div>

        {{-- Placa consultada (identidade do SIV, 11/11/2026): a placa grande no
             visor, a cor, quantas passagens e a linha do dia. --}}
        <section class="siv-night relative isolate overflow-hidden rounded-[28px] shadow-card" aria-label="Placa consultada">
            <div class="auth-enter flex flex-col gap-8 p-6 sm:p-10">
                <div class="flex flex-wrap items-end justify-between gap-8">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/60">Placa consultada · {{ $searchedDate }}</p>
                        <div class="siv-viewfinder mt-3 inline-block">
                            <x-plate :plate="$car['plate']" size="xl" />
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-10 gap-y-4">
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-[0.14em] text-white/60">Cor</dt>
                            <dd class="mt-2 flex items-center gap-2.5 font-display text-2xl font-semibold">
                                @if ($swatch)
                                    <span class="h-6 w-6 shrink-0 rounded-full ring-2 ring-white/40" style="background-color: {{ $swatch }}" aria-hidden="true"></span>
                                @endif
                                {{ isset($car['color']) ? strtoupper($car['color']) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-[0.14em] text-white/60">Acessos no dia</dt>
                            <dd class="mt-2 font-mono text-[32px] font-semibold leading-none">{{ $accessCount }}</dd>
                        </div>
                    </dl>
                </div>

                @if ($passes)
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-white/60">Passagens no dia</p>
                        <div class="siv-rail mt-2">
                            @foreach ($passes as $pass)
                                <a href="#acesso-{{ $pass['n'] }}" class="siv-dot" style="left: {{ $pass['left'] }}%"
                                   title="{{ $pass['time'] }}" aria-label="Ir para o acesso das {{ $pass['time'] }}"></a>
                            @endforeach
                        </div>
                        <div class="flex justify-between font-mono text-[11px] text-white/50" aria-hidden="true">
                            <span>0h</span><span>6h</span><span>12h</span><span>18h</span><span>24h</span>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        @if ($accessCount === 0)
            <x-empty-state icon="car">
                Nenhum acesso encontrado para a placa <b class="font-mono text-ink">{{ $car['plate'] }}</b> em {{ $searchedDate }}.
            </x-empty-state>
        @endif

        {{-- Pedidos de carro de aplicativo feitos para esta placa. Aparecem mesmo
             sem leitura da câmera: o pedido casa pela placa, não pelo horário. --}}
        @if ($appCarRequests->isNotEmpty())
            <section class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Pedidos de carro de aplicativo</h2>
                        <p class="text-sm text-ink-2">Pedidos feitos ou liberados em {{ $searchedDate }} para esta placa.</p>
                    </div>
                    @can(\App\Authorization\Permissions::EXTERNOS_CARROS_APLICATIVO)
                        <a href="{{ route('company.uber.requests', ['q' => strtoupper($car['plate'])]) }}" class="text-sm font-bold text-grena-ink no-underline hover:underline">Ver em Carros de aplicativo</a>
                    @endcan
                </div>

                <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                    @foreach ($appCarRequests as $appCar)
                        @php
                            $appCarKind = match ($appCar->status) {
                                \App\Models\UberAccessRequest::STATUS_CONCLUIDO => 'ok',
                                \App\Models\UberAccessRequest::STATUS_EXPIRADO => 'off',
                                default => 'warn',
                            };
                        @endphp
                        <div class="rounded-2xl border border-line p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h3 class="truncate font-bold leading-tight text-ink">{{ $appCar->requester_name ?: 'Pedido sem nome' }}</h3>
                                    @if ($appCar->matricula)
                                        <p class="font-mono text-sm text-ink-2">Mat./CPF {{ $appCar->matricula }}</p>
                                    @endif
                                </div>
                                <x-pill :kind="$appCarKind">{{ $appCar->statusLabel() }}</x-pill>
                            </div>

                            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <div>
                                    <dt class="text-xs font-bold text-ink-3">Pedido às</dt>
                                    <dd class="font-mono text-ink">{{ optional($appCar->created_at)->format('H:i d/m') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-bold text-ink-3">Entrada liberada às</dt>
                                    <dd class="font-mono text-ink">{{ optional($appCar->accessed_at)->format('H:i d/m') ?? '—' }}</dd>
                                </div>
                                @if ($appCar->club_location)
                                    <div>
                                        <dt class="text-xs font-bold text-ink-3">Local no clube</dt>
                                        <dd class="text-ink">{{ $appCar->club_location }}</dd>
                                    </div>
                                @endif
                                @if ($appCar->contact_phone)
                                    <div>
                                        <dt class="text-xs font-bold text-ink-3">WhatsApp</dt>
                                        <dd><a href="tel:{{ preg_replace('/\D/', '', $appCar->contact_phone) }}" class="font-mono text-ink-2 no-underline hover:text-grena-ink">{{ $appCar->contact_phone }}</a></dd>
                                    </div>
                                @endif
                            </dl>

                            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                                @if ($appCar->memberValidationLabel())
                                    <x-pill :kind="match ($appCar->member_validation) {
                                        \App\Models\UberAccessRequest::MEMBER_VALIDATION_VALIDADO => 'ok',
                                        \App\Models\UberAccessRequest::MEMBER_VALIDATION_FUNCIONARIO_BLOQUEADO => 'danger',
                                        default => 'warn',
                                    }">{{ $appCar->memberValidationLabel() }}{{ $appCar->member_validation_detail ? ' — ' . $appCar->member_validation_detail : '' }}</x-pill>
                                @endif
                                @if ($appCar->screenshot_url)
                                    <a href="{{ $appCar->screenshot_url }}" target="_blank" rel="noopener" class="font-bold text-grena-ink no-underline hover:underline">Abrir print da corrida</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Condutores prováveis --}}
        @if (count($probaly))
            <section class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Condutores mais prováveis</h2>
                    <p class="text-sm text-ink-2">Baseado nos 10 acessos mais recentes desta placa.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-3">
                    @foreach ($probaly as $key => $item)
                        @php
                            $text = explode(' | ', $key);
                            $name = $text[0] ?? '—';
                            $phone = $text[1] ?? '';
                            // Só o externo traz o terceiro trecho (o tipo); associado não.
                            $kindLabel = $text[2] ?? '';
                        @endphp
                        <div class="rounded-2xl border p-4 {{ $loop->first ? 'border-grena/40 bg-grena-tint' : 'border-line bg-subtle' }}">
                            <div class="flex items-start gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full font-mono text-sm font-semibold {{ $loop->first ? 'bg-grena text-white' : 'bg-line text-ink' }}">
                                    {{ $loop->iteration }}º
                                </span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate font-bold leading-tight text-ink" title="{{ $name }}">{{ $name }}</h3>
                                    @if ($phone)
                                        <a href="tel:{{ preg_replace('/\D/', '', $phone) }}" class="font-mono text-sm text-ink-2 no-underline hover:text-grena-ink">{{ $phone }}</a>
                                    @endif
                                    @if ($kindLabel)
                                        <span class="block text-xs font-bold text-ink-3">{{ $kindLabel }}</span>
                                    @endif
                                </div>
                                <span class="font-mono text-sm font-semibold {{ $loop->first ? 'text-grena-ink' : 'text-ink-2' }}">{{ number_format($item, 1) }}%</span>
                            </div>

                            <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-line" role="presentation">
                                <div class="h-full rounded-full {{ $loop->first ? 'bg-grena' : 'bg-ink-3' }}" style="width: {{ min($item, 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Histórico --}}
        @if ($accessCount)
            <section class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Histórico de acessos</h2>
                        <p class="text-sm text-ink-2">Registros capturados em {{ $searchedDate }}.</p>
                    </div>
                    <span class="rounded-full bg-subtle px-3 py-1 font-mono text-xs font-semibold text-ink-2">
                        {{ $accessCount }} {{ $accessCount === 1 ? 'registro' : 'registros' }}
                    </span>
                </div>

                <div class="flex flex-col gap-4 p-5">
                    {{-- Os registros do dia já vêm todos: a busca filtra aqui
                         mesmo, por horário, nome, matrícula ou telefone. --}}
                    @if ($accessCount > 1)
                        <x-search-bar mode="client" target="#historico-acessos" placeholder="Filtrar por horário, nome, matrícula, empresa ou telefone" />
                    @endif

                    <div id="historico-acessos" class="flex flex-col gap-5">
                        @foreach ($data as $log)
                            @include('parking.partials.dataItemAccess', ['log' => $log, 'n' => $loop->iteration])
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </x-page>
</x-app-layout>
