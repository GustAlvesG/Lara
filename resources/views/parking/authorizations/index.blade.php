<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Placas Autorizadas
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

            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Gerenciar Placas</h3>
                <a href="{{ route('parking-authorizations.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    Nova Placa
                </a>
            </div>

            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700 dark:text-gray-300">
                    <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="py-3 px-4">Placa</th>
                            <th class="py-3 px-4">Nome</th>
                            <th class="py-3 px-4">Validade</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($authorizations as $item)
                            @php $expired = $item->expiration_date->lt(\Carbon\Carbon::today()); @endphp
                            <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="py-3 px-4 font-mono font-bold tracking-widest">{{ $item->plate }}</td>
                                <td class="py-3 px-4">{{ $item->name }}</td>
                                <td class="py-3 px-4">{{ $item->expiration_date->format('d/m/Y') }}</td>
                                <td class="py-3 px-4">
                                    @if ($expired)
                                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                            Expirada
                                        </span>
                                    @else
                                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                            Válida
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right space-x-2">
                                    <a href="{{ route('parking-authorizations.edit', $item) }}"
                                       class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-semibold text-xs uppercase">
                                        Editar
                                    </a>
                                    <form method="POST" action="{{ route('parking-authorizations.destroy', $item) }}" class="inline"
                                          onsubmit="return confirm('Confirma a remoção desta placa?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 font-semibold text-xs uppercase">
                                            Remover
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-gray-400 dark:text-gray-500">
                                    Nenhuma placa cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $authorizations->links() }}
                </div>
            </div>

        </div>
    </div>

    <x-slot name="js"></x-slot>
</x-app-layout>
