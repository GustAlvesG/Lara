<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Frota — Veículos
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

            <div class="p-6 bg-white dark:bg-gray-800 shadow sm:rounded-lg space-y-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Veículos cadastrados</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">A portaria enxerga apenas os ativos.</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('fleet.index') }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-600 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Painel
                        </a>
                        <a href="{{ route('fleet.vehicles.create') }}"
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                            Novo Veículo
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-700 dark:text-gray-300">
                        <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="py-2 pr-3">Nome</th>
                                <th class="py-2 pr-3">Placa</th>
                                <th class="py-2 pr-3">Descrição</th>
                                <th class="py-2 pr-3 text-right">Km atual</th>
                                <th class="py-2 pr-3 text-right">Viagens</th>
                                <th class="py-2 pr-3">Situação</th>
                                <th class="py-2 pr-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vehicles as $vehicle)
                                <tr class="border-b border-gray-100 dark:border-gray-700/60">
                                    <td class="py-3 pr-3 font-semibold">{{ $vehicle->name }}</td>
                                    <td class="py-3 pr-3 font-mono">{{ $vehicle->plate ?: '—' }}</td>
                                    <td class="py-3 pr-3">{{ $vehicle->description ?: '—' }}</td>
                                    <td class="py-3 pr-3 text-right font-mono">
                                        {{ $vehicle->current_odometer !== null ? number_format($vehicle->current_odometer, 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="py-3 pr-3 text-right">{{ $vehicle->trips_count }}</td>
                                    <td class="py-3 pr-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-bold
                                            {{ $vehicle->active ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                            {{ $vehicle->active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                    <td class="py-3 pr-3 text-right whitespace-nowrap">
                                        <a href="{{ route('fleet.vehicles.edit', $vehicle) }}"
                                           class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Editar</a>
                                        <form method="POST" action="{{ route('fleet.vehicles.destroy', $vehicle) }}" class="inline"
                                              onsubmit="return confirm('Excluir este veículo?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ml-3 text-red-600 dark:text-red-400 font-semibold hover:underline">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-gray-400 dark:text-gray-500">Nenhum veículo cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <x-slot name="js"></x-slot>
</x-app-layout>
