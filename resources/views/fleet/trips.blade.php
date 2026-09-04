<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Frota — Histórico de Viagens
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

            <div class="p-6 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <form method="GET" action="{{ route('fleet.trips') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Veículo</label>
                        <select name="vehicle_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm text-sm">
                            <option value="">Todos</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" @selected(($filters['vehicle_id'] ?? '') == $vehicle->id)>{{ $vehicle->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Situação</label>
                        <select name="status" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm text-sm">
                            <option value="">Todas</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Motorista</label>
                        <input type="text" name="driver" value="{{ $filters['driver'] ?? '' }}"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Saída de</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Saída até</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm text-sm">
                    </div>

                    <div class="flex gap-2">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                            Filtrar
                        </button>
                        <a href="{{ route('fleet.trips') }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-600 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Limpar
                        </a>
                    </div>

                    {{-- Filtro de conferência: as viagens cujo hodômetro só entrou
                         porque alguém confirmou o número fora do esperado. --}}
                    <label class="lg:col-span-6 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <input type="checkbox" name="only_alerts" value="1" @checked($filters['only_alerts'] ?? false)
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                        Somente viagens com quilometragem a conferir
                    </label>
                </form>
            </div>

            <div class="p-6 bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700 dark:text-gray-300">
                    <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="py-2 pr-3">Veículo</th>
                            <th class="py-2 pr-3">Motorista</th>
                            <th class="py-2 pr-3">Destino</th>
                            <th class="py-2 pr-3">Saída</th>
                            <th class="py-2 pr-3">Retorno</th>
                            <th class="py-2 pr-3 text-right">Km</th>
                            <th class="py-2 pr-3">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($trips as $trip)
                            <tr class="border-b border-gray-100 dark:border-gray-700/60">
                                <td class="py-3 pr-3 font-semibold">
                                    {{ $trip->vehicle?->name ?? '—' }}
                                    @if ($trip->vehicle?->plate)
                                        <span class="block text-xs font-normal text-gray-400 dark:text-gray-500">{{ $trip->vehicle->plate }}</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-3">
                                    {{ $trip->driver_name }}
                                    @if ($trip->employee)
                                        <span class="block text-xs text-gray-400 dark:text-gray-500">matrícula {{ $trip->employee->employee_code }}</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-3">
                                    {{ $trip->destination }}
                                    @if ($trip->departure_obs || $trip->return_obs)
                                        <span class="block text-xs text-gray-400 dark:text-gray-500">
                                            {{ trim(($trip->departure_obs ?? '') . ' ' . ($trip->return_obs ?? '')) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 pr-3 whitespace-nowrap">
                                    {{ $trip->departure_at->format('d/m/Y H:i') }}
                                    <span class="block text-xs text-gray-400 dark:text-gray-500 font-mono">{{ number_format($trip->departure_odometer, 0, ',', '.') }} km</span>
                                </td>
                                <td class="py-3 pr-3 whitespace-nowrap">
                                    @if ($trip->return_at)
                                        {{ $trip->return_at->format('d/m/Y H:i') }}
                                        <span class="block text-xs text-gray-400 dark:text-gray-500 font-mono">{{ number_format($trip->return_odometer, 0, ',', '.') }} km</span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-3 text-right font-semibold">
                                    {{ $trip->distance_km !== null ? number_format($trip->distance_km, 0, ',', '.') : '—' }}
                                </td>
                                <td class="py-3 pr-3">
                                    @php
                                        $tone = match ($trip->status) {
                                            'open'     => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                            'closed'   => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                            default    => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                                        };
                                    @endphp
                                    <span class="px-2 py-1 rounded-full text-xs font-bold {{ $tone }}">{{ $trip->statusLabel() }}</span>
                                    @if ($trip->odometer_alert)
                                        <span class="block mt-1 text-xs text-red-600 dark:text-red-400 font-semibold">km a conferir</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-gray-400 dark:text-gray-500">Nenhuma viagem encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $trips->links() }}
                </div>
            </div>

        </div>
    </div>

    <x-slot name="js"></x-slot>
</x-app-layout>
