@php
    $plateValue = $plate ?? old('plate');
    $dateValue = $datetime ?? old('datetime', date('Y-m-d'));
@endphp

<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">

    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-[#A00001] dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-extrabold text-gray-900 dark:text-white leading-tight">Buscar veículo</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Informe a placa e a data do acesso.</p>
        </div>
    </div>

    <form action="{{ route('parking.show') }}" method="POST" class="p-6"
          x-data="{ loading: false }" @submit="loading = true">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">

            <div class="md:col-span-6">
                <label for="plate" class="block font-semibold text-sm text-gray-700 dark:text-gray-300 mb-1.5">
                    Placa do veículo
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-400 pointer-events-none">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 7h16M4 12h16M4 17h16M3 5h18a1 1 0 011 1v12a1 1 0 01-1 1H3a1 1 0 01-1-1V6a1 1 0 011-1z"/>
                        </svg>
                    </span>
                    <input id="plate" name="plate" required autofocus autocomplete="off"
                           value="{{ $plateValue }}"
                           placeholder="ABC1D23"
                           maxlength="8"
                           oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                           class="w-full pl-11 pr-4 py-3 tracking-[0.2em] font-bold uppercase border border-gray-200 dark:border-gray-600 rounded-xl shadow-sm outline-none transition
                                  focus:ring-2 focus:ring-[#A00001] focus:border-[#A00001]
                                  bg-white dark:bg-gray-900 text-gray-900 dark:text-white dark:placeholder-gray-600 placeholder:tracking-normal placeholder:font-normal">
                </div>
            </div>

            <div class="md:col-span-3">
                <label for="datetime" class="block font-semibold text-sm text-gray-700 dark:text-gray-300 mb-1.5">
                    Data do acesso
                </label>
                <input type="date" id="datetime" name="datetime" value="{{ $dateValue }}"
                       class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl shadow-sm outline-none transition
                              focus:ring-2 focus:ring-[#A00001] focus:border-[#A00001]
                              bg-white dark:bg-gray-900 text-gray-900 dark:text-white dark:[color-scheme:dark]">
            </div>

            <div class="md:col-span-3">
                <button type="submit" x-bind:disabled="loading"
                        class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg
                               hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02] disabled:opacity-60 disabled:cursor-wait disabled:transform-none">
                    <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <svg x-show="loading" x-cloak class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    <span x-text="loading ? 'Buscando...' : 'Buscar'">Buscar</span>
                </button>
            </div>

        </div>
    </form>
</div>
