<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Frota — Controle de Quilometragem
        </h2>
    </x-slot>

    <x-slot name="css"></x-slot>

    <div class="py-6">
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="p-4 bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="p-4 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-300 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-300 rounded-lg">
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Números do dia. "Aguardando baixa" é o card que importa: viagem
                 aberta demais quase sempre é retorno que ninguém registrou. --}}
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                @php
                    $cards = [
                        ['label' => 'Em rota agora',    'value' => $stats['open'],             'tone' => 'indigo'],
                        ['label' => 'Aguardando baixa', 'value' => $stats['open_overdue'],     'tone' => $stats['open_overdue'] > 0 ? 'red' : 'gray'],
                        ['label' => 'Saídas hoje',      'value' => $stats['departures_today'], 'tone' => 'gray'],
                        ['label' => 'Km hoje',          'value' => $stats['km_today'],         'tone' => 'gray'],
                        ['label' => 'Km no mês',        'value' => $stats['km_month'],         'tone' => 'gray'],
                    ];
                    $tones = [
                        'indigo' => 'text-indigo-600 dark:text-indigo-400',
                        'red'    => 'text-red-600 dark:text-red-400',
                        'gray'   => 'text-gray-800 dark:text-gray-200',
                    ];
                @endphp

                @foreach ($cards as $card)
                    <div class="p-4 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $card['label'] }}</p>
                        <p class="mt-1 text-2xl font-extrabold {{ $tones[$card['tone']] }}">{{ $card['value'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Viagem aberta há mais de {{ $alertHours }}h aparece destacada. O horário gravado é sempre o do registro.
                </p>
                <div class="flex gap-2">
                    <a href="{{ route('fleet.trips') }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-600 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Histórico
                    </a>
                    <a href="{{ route('fleet.vehicles') }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-600 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Veículos
                    </a>
                </div>
            </div>

            @if ($vehicles->isEmpty())
                <div class="p-6 bg-white dark:bg-gray-800 shadow sm:rounded-lg text-sm text-gray-500 dark:text-gray-400">
                    Nenhum veículo ativo cadastrado.
                    <a href="{{ route('fleet.vehicles.create') }}" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Cadastrar veículo</a>.
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach ($vehicles as $vehicle)
                    @php
                        $trip = $vehicle->openTrip;
                        $overdue = $trip && $trip->departure_at->lt(now()->subHours($alertHours));
                    @endphp

                    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg border {{ $overdue ? 'border-red-300 dark:border-red-700' : 'border-transparent' }}">
                        <div class="p-6 space-y-4">

                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200">{{ $vehicle->name }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $vehicle->plate ?: 'sem placa cadastrada' }}
                                        @if ($vehicle->current_odometer !== null)
                                            · {{ number_format($vehicle->current_odometer, 0, ',', '.') }} km
                                        @endif
                                    </p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-bold
                                    {{ $trip ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' }}">
                                    {{ $trip ? 'Em rota' : 'Na garagem' }}
                                </span>
                            </div>

                            @if ($trip)
                                <div class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                                    <p><span class="text-gray-400 dark:text-gray-500">Motorista:</span> <strong>{{ $trip->driver_name }}</strong></p>
                                    <p><span class="text-gray-400 dark:text-gray-500">Destino:</span> {{ $trip->destination }}</p>
                                    <p>
                                        <span class="text-gray-400 dark:text-gray-500">Saída:</span>
                                        {{ $trip->departure_at->format('d/m/Y H:i') }}
                                        · {{ number_format($trip->departure_odometer, 0, ',', '.') }} km
                                        <span class="{{ $overdue ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-400 dark:text-gray-500' }}">
                                            (há {{ intdiv($trip->durationMinutes(), 60) }}h{{ str_pad($trip->durationMinutes() % 60, 2, '0', STR_PAD_LEFT) }})
                                        </span>
                                    </p>
                                    @if ($trip->departure_obs)
                                        <p><span class="text-gray-400 dark:text-gray-500">Obs:</span> {{ $trip->departure_obs }}</p>
                                    @endif
                                </div>

                                <form method="POST" action="{{ route('fleet.return') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                                    @csrf
                                    <input type="hidden" name="trip_id" value="{{ $trip->id }}">

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Km de retorno</label>
                                        <input type="number" name="odometer" min="0" required
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Observação</label>
                                        <input type="text" name="obs"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit"
                                                class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                                            Registrar retorno
                                        </button>
                                    </div>

                                    {{-- A confirmação repete o envio com force: o número esquisito
                                         entra, mas marcado para conferência. --}}
                                    <label class="sm:col-span-3 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <input type="checkbox" name="force" value="1" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                        Confirmar mesmo com quilometragem fora do esperado
                                    </label>
                                </form>

                                <form method="POST" action="{{ route('fleet.trips.cancel', $trip) }}"
                                      onsubmit="return confirm('Cancelar esta saída? O veículo volta a ficar disponível.');">
                                    @csrf
                                    <input type="hidden" name="reason" value="Cancelado no painel da frota">
                                    <button type="submit" class="text-xs text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 hover:underline">
                                        Cancelar saída (registro errado)
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('fleet.departure') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @csrf
                                    <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Motorista</label>
                                        <input type="text" name="driver" required placeholder="Nome, matrícula ou CPF"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Destino</label>
                                        <input type="text" name="destination" required
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Km de saída</label>
                                        <input type="number" name="odometer" min="0" required
                                               value="{{ $vehicle->current_odometer }}"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Observação</label>
                                        <input type="text" name="obs"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>

                                    <label class="sm:col-span-2 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <input type="checkbox" name="force" value="1" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                        Confirmar mesmo com quilometragem menor que a última registrada
                                    </label>

                                    <div class="sm:col-span-2">
                                        <button type="submit"
                                                class="w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                                            Registrar saída
                                        </button>
                                    </div>
                                </form>
                            @endif

                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>

    <x-slot name="js"></x-slot>
</x-app-layout>
