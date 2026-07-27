@php
    $logDateTime = explode(' ', $log['entry_date']);
    $logTime = $logDateTime[0] ?? '';
    $logDate = $logDateTime[1] ?? '';
    $drivers = $log['access'] ?? [];
    $driverCount = is_countable($drivers) ? count($drivers) : 0;
    $imageUrl = asset('storage/img_car/' . $log['file']);
@endphp

<div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-700/20 overflow-hidden">

    <div class="px-5 py-3 flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-gray-100 dark:border-gray-700 bg-white/70 dark:bg-gray-800/60">
        <span class="inline-flex items-center gap-1.5 text-sm font-bold text-gray-900 dark:text-white">
            <svg class="w-4 h-4 text-[#A00001] dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $logTime }}
        </span>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $logDate }}</span>
        <span class="ml-auto px-2.5 py-0.5 rounded-full text-xs font-bold
            {{ $driverCount ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300' : 'bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300' }}">
            {{ $driverCount }} {{ $driverCount === 1 ? 'condutor' : 'condutores' }}
        </span>
    </div>

    <div class="p-5 grid grid-cols-1 md:grid-cols-12 gap-5">

        <div class="md:col-span-4">
            <a href="{{ $imageUrl }}" target="_blank" rel="noopener"
               class="block group relative rounded-xl overflow-hidden border border-gray-200 dark:border-gray-600 shadow-sm">
                <img src="{{ $imageUrl }}"
                     onerror="this.onerror=null;this.src='https://placehold.co/400x300/e5e7eb/9ca3af?text=Sem+Foto';"
                     alt="Foto do veículo em {{ $logDate }} {{ $logTime }}"
                     loading="lazy"
                     class="w-full h-auto object-cover transition duration-200 group-hover:scale-[1.03]">
                <span class="absolute bottom-2 right-2 px-2 py-1 rounded-md bg-black/60 text-white text-[11px] font-semibold opacity-0 group-hover:opacity-100 transition">
                    Ampliar
                </span>
            </a>
        </div>

        <div class="md:col-span-8">
            @if($driverCount)
                <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-700">
                    <table class="min-w-full text-left">
                        <thead class="bg-white dark:bg-gray-800">
                            <tr>
                                <th class="py-2.5 px-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Mat.</th>
                                <th class="py-2.5 px-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Nome</th>
                                <th class="py-2.5 px-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Telefone</th>
                                <th class="py-2.5 px-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Horário</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800">
                            @foreach($drivers as $driver)
                                <tr class="hover:bg-red-50/60 dark:hover:bg-red-900/10 transition duration-100">
                                    <td class="py-2.5 px-3 text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ $driver->TitleCode }}</td>
                                    <td class="py-2.5 px-3 text-sm text-gray-700 dark:text-gray-300">{{ $driver->Name }}</td>
                                    <td class="py-2.5 px-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                        <a href="tel:{{ preg_replace('/\D/', '', $driver->Telephone) }}"
                                           class="hover:text-[#A00001] dark:hover:text-red-400 transition">{{ $driver->Telephone }}</a>
                                    </td>
                                    <td class="py-2.5 px-3 text-sm font-semibold text-gray-900 dark:text-white whitespace-nowrap">{{ $driver->date }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="h-full min-h-[8rem] flex flex-col items-center justify-center text-center rounded-xl border border-dashed border-gray-200 dark:border-gray-600 p-6">
                    <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M18 9v3m0 3h.01M9 20H4a2 2 0 01-2-2v-1a5 5 0 0110 0v1a2 2 0 01-2 2h-1zm3-13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Nenhum condutor associado a este acesso.</p>
                </div>
            @endif
        </div>

    </div>
</div>
