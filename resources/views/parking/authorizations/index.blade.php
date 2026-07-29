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

            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Push Manual</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Abre a cancela diretamente, sem depender da leitura da placa. Tempo selecionado + 5 segundos de segurança.</p>
                    </div>
                    <div class="flex items-end gap-3">
                        <div>
                            <label for="manual-push-seconds" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">
                                Tempo aberto (seg) - 
                            </label>
                            <input type="number" id="manual-push-seconds" min="0.1" step="0.1" value="2"
                                   class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>
                        <button type="button" id="manual-push-btn"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition disabled:opacity-50">
                            Abrir Cancela
                        </button>
                    </div>
                </div>
                <p id="manual-push-feedback" class="mt-3 text-sm hidden"></p>
            </div>

            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Gerenciar Placas</h3>
                    <a href="{{ route('parking-authorizations.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                        Nova Placa
                    </a>
                </div>

                <form method="GET" action="{{ route('parking-authorizations.index') }}"
                      class="flex flex-col sm:flex-row gap-3">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">

                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="text" name="q" value="{{ $search }}" placeholder="Buscar por placa ou nome..."
                               class="w-full pl-10 pr-4 py-2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>

                    <div class="flex gap-2">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                            Buscar
                        </button>
                        @if ($search !== '')
                            <a href="{{ route('parking-authorizations.index', ['sort' => $sort, 'direction' => $direction]) }}"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-600 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                Limpar
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            @php
                $columns = ['plate' => 'Placa', 'name' => 'Nome', 'expiration_date' => 'Validade'];
            @endphp

            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700 dark:text-gray-300">
                    <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            @foreach ($columns as $column => $label)
                                @php
                                    $active = $sort === $column;
                                    $nextDirection = $active && $direction === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <th class="py-3 px-4">
                                    <a href="{{ route('parking-authorizations.index', ['q' => $search, 'sort' => $column, 'direction' => $nextDirection]) }}"
                                       class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300 {{ $active ? 'text-indigo-600 dark:text-indigo-400' : '' }}">
                                        {{ $label }}
                                        @if ($active)
                                            <span aria-hidden="true">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </a>
                                </th>
                            @endforeach
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
                                    {{ $search !== '' ? 'Nenhuma placa encontrada para "'.$search.'".' : 'Nenhuma placa cadastrada.' }}
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

    <x-slot name="js">
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const btn = document.getElementById('manual-push-btn');
                const secondsInput = document.getElementById('manual-push-seconds');
                const feedback = document.getElementById('manual-push-feedback');

                btn.addEventListener('click', function () {
                    const seconds = parseFloat(secondsInput.value);

                    if (!seconds || seconds <= 0) {
                        feedback.textContent = 'Informe um tempo válido em segundos.';
                        feedback.className = 'mt-3 text-sm text-red-600 dark:text-red-400';
                        return;
                    }

                    btn.disabled = true;
                    feedback.textContent = 'Enviando...';
                    feedback.className = 'mt-3 text-sm text-gray-500 dark:text-gray-400';

                    // O dispositivo não responde a preflight CORS, então usamos
                    // modo 'no-cors' com Content-Type simples (sem application/json)
                    // para evitar o OPTIONS e garantir que o POST realmente saia.
                    // Como consequência, a resposta fica opaca e não dá para
                    // confirmar programaticamente se o comando teve sucesso.
                    fetch('http://192.168.100.96:8017/trigger/lara-push', {
                        method: 'POST',
                        mode: 'no-cors',
                        headers: { 'Content-Type': 'text/plain' },
                        body: JSON.stringify({
                            pin: 17,
                            active_high: true,
                            pulse_seconds: seconds
                        })
                    })
                        .then(function () {
                            feedback.textContent = 'Comando enviado ao dispositivo.';
                            feedback.className = 'mt-3 text-sm text-green-600 dark:text-green-400';
                        })
                        .catch(function (error) {
                            console.error(error);
                            feedback.textContent = 'Falha ao acionar a cancela. Verifique a conexão com o dispositivo.';
                            feedback.className = 'mt-3 text-sm text-red-600 dark:text-red-400';
                        })
                        .finally(function () {
                            btn.disabled = false;
                        });
                });
            });
        </script>
    </x-slot>
</x-app-layout>
