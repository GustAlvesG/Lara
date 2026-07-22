@php
    $identified = max($todayParkingCount - $todayParkingNoPlate, 0);
    $identifiedRate = $todayParkingCount > 0 ? ($identified / $todayParkingCount) * 100 : 0;
@endphp

<div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-red-100 dark:bg-red-900/40 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-[#A00001] dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M5 17h14M5 17a2 2 0 01-2-2v-3l2-5a2 2 0 012-1h10a2 2 0 012 1l2 5v3a2 2 0 01-2 2M7 17v2m10-2v2M7 13h.01M17 13h.01"/>
            </svg>
        </div>
        <div class="min-w-0">
            <p class="text-3xl font-black text-gray-900 dark:text-white leading-none">{{ $todayParkingCount }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-1">Veículos registrados hoje</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <p class="text-3xl font-black text-gray-900 dark:text-white leading-none">{{ $identified }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-1">
                Placas identificadas
                <span class="text-emerald-600 dark:text-emerald-400 font-bold">({{ number_format($identifiedRate, 0) }}%)</span>
            </p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-2.99l-6.93-12a2 2 0 00-3.48 0l-6.93 12A2 2 0 005.07 19z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <p class="text-3xl font-black text-gray-900 dark:text-white leading-none">{{ $todayParkingNoPlate }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-1">Sem placa identificada</p>
        </div>
    </div>

</div>
