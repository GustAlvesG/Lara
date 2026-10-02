@php
    $accessCount = count($data);
    $searchedDate = $datetime ? date('d/m/Y', strtotime($datetime)) : date('d/m/Y');
    $panel = 'rounded-card bg-surface p-5 shadow-card';
    $eyebrow = 'text-xs font-bold uppercase tracking-[0.08em] text-ink-3';
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

        {{-- Resumo --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="{{ $panel }}">
                <p class="{{ $eyebrow }}">Placa</p>
                <div class="mt-2"><x-plate :plate="$car['plate']" size="lg" /></div>
            </div>
            <div class="{{ $panel }}">
                <p class="{{ $eyebrow }}">Cor</p>
                <p class="mt-2 font-display text-2xl font-semibold text-ink">{{ isset($car['color']) ? strtoupper($car['color']) : '—' }}</p>
            </div>
            <div class="{{ $panel }}">
                <p class="{{ $eyebrow }}">Acessos no dia</p>
                <p class="mt-2 font-mono text-2xl font-semibold text-ink">{{ $accessCount }}</p>
            </div>
        </div>

        @if ($accessCount === 0)
            <x-empty-state icon="car">
                Nenhum acesso encontrado para a placa <b class="font-mono text-ink">{{ $car['plate'] }}</b> em {{ $searchedDate }}.
            </x-empty-state>
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
                        <x-search-bar mode="client" target="#historico-acessos" placeholder="Filtrar por horário, condutor, matrícula ou telefone" />
                    @endif

                    <div id="historico-acessos" class="flex flex-col gap-4">
                        @foreach ($data as $log)
                            @include('parking.partials.dataItemAccess', ['log' => $log])
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </x-page>
</x-app-layout>
