{{--
    Histórico de viagens. Tabela, não cartões: aqui se compara linha com
    linha (saída, retorno, km). Os filtros vão ao servidor — é paginado.
--}}
@php
    $label = 'mb-1 block text-xs font-bold text-ink-2';
    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-3 align-top';
    $tones = ['open' => 'warn', 'closed' => 'ok'];
    $filtered = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
    $canCorrect = auth()->user()?->can(\App\Authorization\Permissions::SIV_VIAGENS_CORRIGIR_KM) ?? false;
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Histórico de viagens">
            Todas as saídas e retornos da frota.

            @can(\App\Authorization\Permissions::SIV_FROTA)
                <x-slot:actions>
                    <x-secondary-button-a href="{{ route('fleet.index') }}"><x-icon name="car" /> Painel da frota</x-secondary-button-a>
                </x-slot:actions>
            @endcan
        </x-page-title>

        @include('partials.alerts')

        <form method="GET" action="{{ route('fleet.trips') }}" role="search"
              class="grid grid-cols-1 items-end gap-3 rounded-card bg-surface p-5 shadow-card sm:grid-cols-2 lg:grid-cols-6">
            <div>
                <label for="f-veiculo" class="{{ $label }}">Veículo</label>
                <x-select-input id="f-veiculo" name="vehicle_id">
                    <option value="">Todos</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected(($filters['vehicle_id'] ?? '') == $vehicle->id)>{{ $vehicle->name }}</option>
                    @endforeach
                </x-select-input>
            </div>

            <div>
                <label for="f-situacao" class="{{ $label }}">Situação</label>
                <x-select-input id="f-situacao" name="status">
                    <option value="">Todas</option>
                    @foreach ($statuses as $value => $text)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $text }}</option>
                    @endforeach
                </x-select-input>
            </div>

            <div>
                <label for="f-motorista" class="{{ $label }}">Motorista</label>
                <x-text-input type="search" id="f-motorista" name="driver" value="{{ $filters['driver'] ?? '' }}" placeholder="Nome ou matrícula" />
            </div>

            <div>
                <label for="f-de" class="{{ $label }}">Saída de</label>
                <x-text-input type="date" id="f-de" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="font-mono text-sm" />
            </div>

            <div>
                <label for="f-ate" class="{{ $label }}">Saída até</label>
                <x-text-input type="date" id="f-ate" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="font-mono text-sm" />
            </div>

            <div class="flex items-center gap-2">
                <x-primary-button><x-icon name="filter" /> Filtrar</x-primary-button>
                @if ($filtered)
                    <a href="{{ route('fleet.trips') }}" class="px-1 text-sm font-bold text-grena-ink hover:underline">Limpar</a>
                @endif
            </div>

            {{-- Filtro de conferência: as viagens cujo hodômetro só entrou
                 porque alguém confirmou o número fora do esperado. --}}
            <label class="flex items-center gap-2 text-xs text-ink-2 lg:col-span-6">
                <input type="checkbox" name="only_alerts" value="1" @checked($filters['only_alerts'] ?? false)
                       class="rounded border-line-strong text-grena focus:ring-grena-tint">
                Somente viagens com quilometragem a conferir
            </label>
        </form>

        @if ($trips->isEmpty())
            <x-empty-state icon="history">
                Nenhuma viagem encontrada.
                @if ($filtered)
                    <a href="{{ route('fleet.trips') }}" class="font-bold text-grena-ink hover:underline">Limpar os filtros</a>.
                @endif
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Veículo</th>
                                <th class="{{ $th }}">Motorista</th>
                                <th class="{{ $th }}">Destino</th>
                                <th class="{{ $th }}">Saída</th>
                                <th class="{{ $th }}">Retorno</th>
                                <th class="{{ $th }} text-right">Km</th>
                                <th class="{{ $th }}">Situação</th>
                                @if ($canCorrect)
                                    <th class="{{ $th }}"><span class="sr-only">Ações</span></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($trips as $trip)
                                <tr class="border-b border-line transition last:border-0 hover:bg-subtle">
                                    <td class="{{ $td }}">
                                        <b class="font-semibold text-ink">{{ $trip->vehicle?->name ?? '—' }}</b>
                                        @if ($trip->vehicle?->plate)
                                            <span class="mt-1 block"><x-plate :plate="$trip->vehicle->plate" size="sm" /></span>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} text-ink">
                                        {{ $trip->driver_name }}
                                        @if ($trip->employee)
                                            <span class="block text-xs text-ink-3">matrícula <span class="font-mono">{{ $trip->employee->employee_code }}</span></span>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} text-ink">
                                        {{ $trip->destination }}
                                        @if ($trip->departure_obs || $trip->return_obs)
                                            <span class="block text-xs text-ink-3">{{ trim(($trip->departure_obs ?? '') . ' ' . ($trip->return_obs ?? '')) }}</span>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} whitespace-nowrap font-mono text-ink">
                                        {{ $trip->departure_at->format('d/m/Y H:i') }}
                                        <span class="block text-xs text-ink-3">{{ number_format($trip->departure_odometer, 0, ',', '.') }} km</span>
                                    </td>
                                    <td class="{{ $td }} whitespace-nowrap font-mono text-ink">
                                        @if ($trip->return_at)
                                            {{ $trip->return_at->format('d/m/Y H:i') }}
                                            <span class="block text-xs text-ink-3">{{ number_format($trip->return_odometer, 0, ',', '.') }} km</span>
                                        @else
                                            <span class="text-ink-3">—</span>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} text-right font-mono font-semibold text-ink">
                                        {{ $trip->distance_km !== null ? number_format($trip->distance_km, 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="{{ $td }}">
                                        <x-pill :kind="$tones[$trip->status] ?? 'off'">{{ $trip->statusLabel() }}</x-pill>
                                        @if ($trip->odometer_alert)
                                            <span class="mt-1 block text-xs font-bold text-danger">km a conferir</span>
                                        @endif
                                        {{-- Histórico das correções de km: o número que a portaria
                                             registrou não some, fica aqui com quem trocou e por quê. --}}
                                        @php $corrections = $trip->relationLoaded('corrections') ? $trip->corrections : collect(); @endphp
                                        @if ($corrections->isNotEmpty())
                                            <details class="mt-1 text-xs text-ink-3">
                                                <summary class="cursor-pointer font-bold text-ink-2">km corrigida ({{ $corrections->count() }})</summary>
                                                <ul class="mt-1 space-y-1">
                                                    @foreach ($corrections as $correction)
                                                        <li>
                                                            <span class="font-mono">{{ $correction->created_at?->format('d/m/Y H:i') }}</span>
                                                            {{ $correction->user_name ?? 'Sistema' }}: {{ $correction->summary() }}.
                                                            <span class="block italic">{{ $correction->reason }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    </td>
                                    @if ($canCorrect)
                                        <td class="{{ $td }} text-right">
                                            @if ($trip->status !== \App\Models\Fleet\FleetTrip::STATUS_CANCELED)
                                                @php
                                                    $payload = [
                                                        'action'    => route('fleet.trips.correct', $trip),
                                                        'title'     => ($trip->vehicle?->name ?? 'Veículo') . ' · ' . $trip->driver_name . ' · ' . $trip->departure_at->format('d/m/Y H:i'),
                                                        'open'      => $trip->isOpen(),
                                                        'departure' => $trip->departure_odometer,
                                                        'return'    => $trip->return_odometer,
                                                    ];
                                                @endphp
                                                <x-secondary-button type="button" class="whitespace-nowrap"
                                                    x-data
                                                    x-on:click="$dispatch('fleet-correct', {{ \Illuminate\Support\Js::from($payload) }}); $dispatch('open-modal', 'corrigir-km')">
                                                    <x-icon name="pencil" /> Corrigir km
                                                </x-secondary-button>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $trips->links() }}</div>
        @endif

        @if ($canCorrect)
            {{-- Uma janela só para a página; o botão de cada linha manda os
                 dados da viagem. Depois de um erro, ela reabre com o que foi
                 digitado (old()). --}}
            @php
                $blank = ['action' => '', 'title' => '', 'open' => false, 'departure' => null, 'return' => null, 'reason' => ''];
                $reopen = session('correcting_trip')
                    ? [
                        'action'    => route('fleet.trips.correct', session('correcting_trip')),
                        'title'     => 'Corrija e envie de novo',
                        'open'      => old('return_odometer') === null,
                        'departure' => old('departure_odometer'),
                        'return'    => old('return_odometer'),
                        'reason'    => old('reason'),
                    ]
                    : null;
                $initial = $reopen ?? $blank;
            @endphp
            <x-modal name="corrigir-km" :show="(bool) $reopen" maxWidth="md" focusable>
                <form method="POST" class="space-y-4 p-6"
                      x-data="{ trip: {{ \Illuminate\Support\Js::from($initial) }} }"
                      x-on:fleet-correct.window="trip = Object.assign({ reason: '' }, $event.detail)"
                      x-bind:action="trip.action">
                    @csrf
                    @method('PUT')

                    <div>
                        <h2 class="text-lg font-bold text-ink">Corrigir quilometragem</h2>
                        <p class="text-sm text-ink-3" x-text="trip.title"></p>
                    </div>

                    <div>
                        <label for="c-saida" class="{{ $label }}">Km de saída</label>
                        <x-text-input type="number" min="0" id="c-saida" name="departure_odometer" x-model="trip.departure" required class="font-mono" />
                    </div>

                    <div x-show="!trip.open">
                        <label for="c-retorno" class="{{ $label }}">Km de retorno</label>
                        <x-text-input type="number" min="0" id="c-retorno" name="return_odometer" x-model="trip.return" x-bind:disabled="trip.open" x-bind:required="!trip.open" class="font-mono" />
                    </div>
                    <p x-show="trip.open" class="text-xs text-ink-3">O veículo ainda está na rua: só a km de saída pode ser corrigida.</p>

                    <div>
                        <label for="c-motivo" class="{{ $label }}">Motivo da correção</label>
                        <x-text-input id="c-motivo" name="reason" x-model="trip.reason" required maxlength="500" placeholder="Ex.: porteiro digitou 452100 no lugar de 45210" />
                    </div>

                    <p class="text-xs text-ink-3">Os km rodados e a km atual do veículo são recalculados. O número anterior fica guardado no histórico da viagem.</p>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'corrigir-km')">Cancelar</x-secondary-button>
                        <x-primary-button>Salvar correção</x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endif
    </x-page>
</x-app-layout>
