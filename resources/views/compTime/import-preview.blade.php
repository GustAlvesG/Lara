<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Banco de Horas — Revisão de Importação
            </h2>
            <a href="{{ route('comp-time.index') }}"
               class="flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Voltar
            </a>
        </div>
    </x-slot>

    <x-slot name="slot">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-8">

            @php
                $formatMinutes = function (int $minutes): string {
                    $sign = $minutes < 0 ? '-' : '';
                    $abs  = abs($minutes);
                    return $sign . sprintf('%02d:%02d', intdiv($abs, 60), $abs % 60);
                };
                $totalDuplicates = count($duplicates);
                $totalNew        = count($newEntries);
            @endphp

            {{-- Aviso de duplicatas --}}
            <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 rounded-xl p-5 flex gap-4">
                <div class="shrink-0 text-amber-500">
                    <svg class="w-6 h-6 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-amber-800 dark:text-amber-200">
                        {{ $totalDuplicates }} {{ $totalDuplicates === 1 ? 'entrada duplicada encontrada' : 'entradas duplicadas encontradas' }}
                    </p>
                    <p class="text-sm text-amber-700 dark:text-amber-300 mt-0.5">
                        Revise a tabela abaixo e marque as entradas que devem substituir os valores atuais.
                        Entradas duplicadas aceitas terão os saldos recalculados automaticamente.
                        {{ $totalNew > 0 ? $totalNew . ' entrada(s) nova(s) serão inseridas normalmente.' : '' }}
                    </p>
                </div>
            </div>

            {{-- Formulário de confirmação --}}
            <form method="POST" action="{{ route('comp-time.confirm-import', $uuid) }}">
                @csrf

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                            Entradas Duplicadas
                        </h3>
                        <div class="flex gap-3 text-sm">
                            <button type="button" onclick="toggleAll(true)"
                                    class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                Marcar todas
                            </button>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <button type="button" onclick="toggleAll(false)"
                                    class="text-gray-500 dark:text-gray-400 hover:underline">
                                Desmarcar todas
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Funcionário</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Data</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipo</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Valor Atual</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Novo Valor</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Substituir?</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($duplicates as $dup)
                                    @php
                                        $changed  = $dup['amount_minutes'] !== $dup['old_amount_minutes'];
                                        $increased = $dup['amount_minutes'] > $dup['old_amount_minutes'];
                                        $rowClass = $changed
                                            ? 'bg-amber-50/60 dark:bg-amber-900/10'
                                            : 'bg-gray-50/40 dark:bg-gray-700/20';
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-200">
                                            <span class="font-medium">{{ $dup['employee_name'] }}</span>
                                            <span class="text-xs text-gray-400 ml-1">#{{ $dup['employee_code'] }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ \Carbon\Carbon::parse($dup['entry_date'])->format('d/m/Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($dup['type'] === 'CREDIT')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                                    Crédito
                                                </span>
                                            @elseif($dup['type'] === 'DEBIT')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                                    Débito
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                    Padrão
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-gray-600 dark:text-gray-400">
                                            {{ $formatMinutes($dup['old_amount_minutes']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono font-semibold">
                                            @if(!$changed)
                                                <span class="text-gray-500 dark:text-gray-400">{{ $formatMinutes($dup['amount_minutes']) }}</span>
                                            @elseif($increased)
                                                <span class="text-green-600 dark:text-green-400">{{ $formatMinutes($dup['amount_minutes']) }}</span>
                                            @else
                                                <span class="text-red-600 dark:text-red-400">{{ $formatMinutes($dup['amount_minutes']) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="checkbox"
                                                   name="accepted_ids[]"
                                                   value="{{ $dup['existing_entry_id'] }}"
                                                   class="dup-checkbox h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                                   checked>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($totalNew > 0)
                    <div class="mt-4 bg-white dark:bg-gray-800 rounded-xl shadow px-6 py-4 flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>
                            <strong class="text-gray-800 dark:text-gray-200">{{ $totalNew }}</strong>
                            {{ $totalNew === 1 ? 'entrada nova será inserida' : 'entradas novas serão inseridas' }} sem conflito.
                        </span>
                    </div>
                @endif

                <div class="mt-6 flex justify-end gap-4">
                    <a href="{{ route('comp-time.index') }}"
                       class="px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                        Cancelar
                    </a>
                    <button type="submit"
                            class="px-5 py-2.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow transition">
                        Confirmar Importação
                    </button>
                </div>
            </form>

        </div>
    </x-slot>
</x-app-layout>

<script>
function toggleAll(checked) {
    document.querySelectorAll('.dup-checkbox').forEach(cb => cb.checked = checked);
}
</script>
