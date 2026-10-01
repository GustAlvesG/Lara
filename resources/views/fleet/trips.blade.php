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
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $trips->links() }}</div>
        @endif
    </x-page>
</x-app-layout>
