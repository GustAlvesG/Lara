{{--
    Lançamentos de um dia, com as compensações dos dois lados.

    Esta view não existia: o controller mandava renderizar `compTime.dayDetails`
    depois de chamar um método de serviço que também não existia, então a rota
    `comp-time.show.day.details` só sabia dar erro fatal.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $employee->name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Matrícula {{ $employee->employee_code }} — lançamentos de
                    {{ \Carbon\Carbon::parse($day)->format('d/m/Y') }}
                </p>
            </div>
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
        @php
            $formatMinutes = function ($minutes) {
                $minutes = (int) $minutes;
                $sign = $minutes < 0 ? '-' : '';
                $abs  = abs($minutes);
                return $sign . sprintf('%02d:%02d', intdiv($abs, 60), $abs % 60);
            };
        @endphp

        <div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">

            @if($dayDetails->isEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow px-6 py-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">
                        Nenhum lançamento de crédito ou débito neste dia.
                    </p>
                </div>
            @endif

            @foreach($dayDetails as $entry)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-4 flex-wrap">
                        <div class="flex items-center gap-3">
                            @if($entry->type === 'CREDIT')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">Crédito</span>
                            @elseif($entry->type === 'DEBIT')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">Débito</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">{{ $entry->type }}</span>
                            @endif

                            @if($entry->written_off)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200">Baixado</span>
                            @endif

                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                Horário previsto: {{ $entry->reference_time ?: '--' }}
                            </span>
                        </div>

                        <div class="flex items-center gap-6 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">
                                Bruto <strong class="font-mono text-gray-800 dark:text-gray-200">{{ $formatMinutes($entry->amount_minutes) }}</strong>
                            </span>
                            <span class="text-gray-500 dark:text-gray-400">
                                Saldo <strong class="font-mono text-gray-800 dark:text-gray-200">{{ $formatMinutes($entry->balance_minutes) }}</strong>
                            </span>
                            <span class="text-gray-500 dark:text-gray-400">
                                Vence {{ $entry->due_date ? \Carbon\Carbon::parse($entry->due_date)->format('d/m/Y') : '--' }}
                            </span>
                        </div>
                    </div>

                    <div class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-400 dark:text-gray-500">Apontamentos:</span>
                        <span class="font-mono ml-1">{{ $entry->entry_times ?: 'Não registrado' }}</span>
                    </div>

                    @if($entry->adjustments->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Compensado com</th>
                                        <th class="px-6 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Minutos</th>
                                        <th class="px-6 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Saldo antes</th>
                                        <th class="px-6 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Saldo depois</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($entry->adjustments as $adjustment)
                                        <tr>
                                            <td class="px-6 py-2 text-gray-700 dark:text-gray-300">
                                                {{ $adjustment->adjustment_date ? \Carbon\Carbon::parse($adjustment->adjustment_date)->format('d/m/Y') : '--' }}
                                            </td>
                                            <td class="px-6 py-2 text-right font-mono text-gray-700 dark:text-gray-300">{{ $formatMinutes($adjustment->amount_minutes) }}</td>
                                            <td class="px-6 py-2 text-right font-mono text-gray-500 dark:text-gray-400">{{ $formatMinutes($adjustment->before_adjustment_minutes) }}</td>
                                            <td class="px-6 py-2 text-right font-mono text-gray-500 dark:text-gray-400">{{ $formatMinutes($adjustment->after_adjustment_minutes) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            Nenhuma compensação registrada para este lançamento.
                        </div>
                    @endif
                </div>
            @endforeach

        </div>
    </x-slot>
</x-app-layout>
