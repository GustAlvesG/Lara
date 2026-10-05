{{--
    Painel da frota: um cartão por veículo ativo, com a saída ou o retorno
    na mesma tela. Sem foto do veículo, por decisão: o nome basta para a
    portaria. A busca filtra na página (a lista de ativos vem inteira).
--}}
@php
    $stats_cards = [
        ['Em rota agora', $stats['open'], 'text-grena-ink'],
        ['Aguardando baixa', $stats['open_overdue'], $stats['open_overdue'] > 0 ? 'text-danger' : 'text-ink'],
        ['Saídas hoje', $stats['departures_today'], 'text-ink'],
        ['Km hoje', $stats['km_today'], 'text-ink'],
        ['Km no mês', $stats['km_month'], 'text-ink'],
    ];
    $label = 'mb-1 block text-xs font-bold text-ink-2';
    $check = 'rounded border-line-strong text-grena focus:ring-grena-tint';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Frota">
            Controle de quilometragem. Viagem aberta há mais de {{ $alertHours }}h aparece destacada; o horário gravado é sempre o do registro.

            <x-slot:actions>
                @can(\App\Authorization\Permissions::SIV_VIAGENS)
                    <x-secondary-button-a href="{{ route('fleet.trips') }}"><x-icon name="history" /> Histórico</x-secondary-button-a>
                @endcan
                @can(\App\Authorization\Permissions::SIV_VEICULOS)
                    <x-secondary-button-a href="{{ route('fleet.vehicles') }}"><x-icon name="car" /> Veículos</x-secondary-button-a>
                @endcan
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft p-4 text-sm text-danger" role="alert">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Números do dia. "Aguardando baixa" é o que importa: viagem aberta
             demais quase sempre é retorno que ninguém registrou. --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            @foreach ($stats_cards as [$title, $value, $tone])
                <div class="rounded-card bg-surface p-4 shadow-card">
                    <p class="text-xs font-bold uppercase tracking-[0.08em] text-ink-3">{{ $title }}</p>
                    <p class="mt-1 font-mono text-2xl font-semibold {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        @if ($vehicles->isEmpty())
            <x-empty-state icon="car">
                Nenhum veículo ativo cadastrado.
                @can(\App\Authorization\Permissions::SIV_VEICULOS)
                    <a href="{{ route('fleet.vehicles.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar veículo</a>.
                @endcan
            </x-empty-state>
        @else
            <x-search-bar mode="client" target="#frota" placeholder="Buscar veículo, placa, motorista ou destino" />

            <div id="frota" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @foreach ($vehicles as $vehicle)
                    @php
                        $trip = $vehicle->openTrip;
                        $overdue = $trip && $trip->departure_at->lt(now()->subHours($alertHours));
                        $search = implode(' ', array_filter([$vehicle->name, $vehicle->plate, $trip?->driver_name, $trip?->destination, $trip ? 'em rota' : 'no clube']));
                    @endphp

                    <article data-search="{{ $search }}"
                             class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-card {{ $overdue ? 'ring-2 ring-danger/50' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl" style="{{ \App\View\AreaColor::style('portaria') }}">
                                    <x-icon name="car" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="truncate text-[15.5px] font-bold text-ink">{{ $vehicle->name }}</h3>
                                    <p class="flex flex-wrap items-center gap-2 text-xs text-ink-2">
                                        @if ($vehicle->plate)
                                            <x-plate :plate="$vehicle->plate" size="sm" />
                                        @else
                                            sem placa cadastrada
                                        @endif
                                        @if ($vehicle->current_odometer !== null)
                                            <span class="font-mono">{{ number_format($vehicle->current_odometer, 0, ',', '.') }} km</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            @if ($trip)
                                <x-pill :kind="$overdue ? 'danger' : 'warn'">{{ $overdue ? 'Aguardando baixa' : 'Em rota' }}</x-pill>
                            @else
                                <x-pill kind="ok">No Clube</x-pill>
                            @endif
                        </div>

                        @if ($trip)
                            <dl class="grid gap-1 text-sm text-ink">
                                <div><dt class="inline text-ink-3">Motorista:</dt> <dd class="inline font-bold">{{ $trip->driver_name }}</dd></div>
                                <div><dt class="inline text-ink-3">Destino:</dt> <dd class="inline">{{ $trip->destination }}</dd></div>
                                <div>
                                    <dt class="inline text-ink-3">Saída:</dt>
                                    <dd class="inline font-mono">
                                        {{ $trip->departure_at->format('d/m/Y H:i') }} · {{ number_format($trip->departure_odometer, 0, ',', '.') }} km
                                        <span class="{{ $overdue ? 'font-semibold text-danger' : 'text-ink-3' }}">
                                            (há {{ intdiv($trip->durationMinutes(), 60) }}h{{ str_pad($trip->durationMinutes() % 60, 2, '0', STR_PAD_LEFT) }})
                                        </span>
                                    </dd>
                                </div>
                                @if ($trip->departure_obs)
                                    <div><dt class="inline text-ink-3">Obs:</dt> <dd class="inline">{{ $trip->departure_obs }}</dd></div>
                                @endif
                            </dl>

                            <form method="POST" action="{{ route('fleet.return') }}" class="grid grid-cols-1 items-end gap-3 border-t border-line pt-4 sm:grid-cols-3">
                                @csrf
                                <input type="hidden" name="trip_id" value="{{ $trip->id }}">

                                <div>
                                    <label for="retorno-km-{{ $trip->id }}" class="{{ $label }}">Km de retorno</label>
                                    <x-text-input type="number" id="retorno-km-{{ $trip->id }}" name="odometer" min="0" required class="font-mono" />
                                </div>
                                <div>
                                    <label for="retorno-obs-{{ $trip->id }}" class="{{ $label }}">Observação</label>
                                    <x-text-input type="text" id="retorno-obs-{{ $trip->id }}" name="obs" />
                                </div>
                                <x-primary-button class="w-full"><x-icon name="check" /> Registrar retorno</x-primary-button>

                                {{-- A confirmação repete o envio com force: o número esquisito
                                     entra, mas marcado para conferência. --}}
                                <label class="flex items-center gap-2 text-xs text-ink-2 sm:col-span-3">
                                    <input type="checkbox" name="force" value="1" class="{{ $check }}">
                                    Confirmar mesmo com quilometragem fora do esperado
                                </label>
                            </form>

                            <form method="POST" action="{{ route('fleet.trips.cancel', $trip) }}"
                                  onsubmit="return confirm('Cancelar esta saída? O veículo volta a ficar disponível.');">
                                @csrf
                                <input type="hidden" name="reason" value="Cancelado no painel da frota">
                                <button type="submit" class="text-xs font-semibold text-ink-3 hover:text-danger hover:underline">
                                    Cancelar saída (registro errado)
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('fleet.departure') }}" class="grid grid-cols-1 gap-3 border-t border-line pt-4 sm:grid-cols-2">
                                @csrf
                                <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">

                                <div>
                                    <label for="saida-motorista-{{ $vehicle->id }}" class="{{ $label }}">Motorista</label>
                                    <x-text-input type="text" id="saida-motorista-{{ $vehicle->id }}" name="driver" required placeholder="Nome, matrícula ou CPF" />
                                </div>
                                <div>
                                    <label for="saida-destino-{{ $vehicle->id }}" class="{{ $label }}">Destino</label>
                                    <x-text-input type="text" id="saida-destino-{{ $vehicle->id }}" name="destination" required />
                                </div>
                                <div>
                                    <label for="saida-km-{{ $vehicle->id }}" class="{{ $label }}">Km de saída</label>
                                    <x-text-input type="number" id="saida-km-{{ $vehicle->id }}" name="odometer" min="0" required value="{{ $vehicle->current_odometer }}" class="font-mono" />
                                </div>
                                <div>
                                    <label for="saida-obs-{{ $vehicle->id }}" class="{{ $label }}">Observação</label>
                                    <x-text-input type="text" id="saida-obs-{{ $vehicle->id }}" name="obs" />
                                </div>

                                <label class="flex items-center gap-2 text-xs text-ink-2 sm:col-span-2">
                                    <input type="checkbox" name="force" value="1" class="{{ $check }}">
                                    Confirmar mesmo com quilometragem menor que a última registrada
                                </label>

                                <x-primary-button class="w-full sm:col-span-2"><x-icon name="arrow-right" /> Registrar saída</x-primary-button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </x-page>
</x-app-layout>
