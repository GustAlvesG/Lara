<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Sistema de Identificação de Veículos') }}
        </h2>
    </x-slot>

    @php
        $accessCount = count($data);
        $searchedDate = $datetime ? date('d/m/Y', strtotime($datetime)) : date('d/m/Y');
    @endphp

    <div class="py-10 bg-gray-50 dark:bg-gray-900 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" x-data="{ showSearch: {{ $accessCount ? 'false' : 'true' }} }">

            <div class="mb-8 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <a href="{{ route('parking.search') }}"
                       class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-500 dark:text-gray-400 hover:text-[#A00001] dark:hover:text-red-400 transition mb-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Voltar para a busca
                    </a>
                    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Dados do acesso</h1>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">Resultados de {{ $searchedDate }}.</p>
                </div>

                <button type="button" @click="showSearch = !showSearch"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span x-text="showSearch ? 'Fechar busca' : 'Nova busca'">Nova busca</span>
                </button>
            </div>

            <div x-show="showSearch" x-cloak class="mb-8">
                @include('parking.partials.form')
            </div>

            <!-- Resumo -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Placa</p>
                    <p class="mt-2 inline-block px-4 py-1.5 rounded-lg bg-gray-900 dark:bg-gray-900 text-white text-2xl font-black tracking-[0.15em] border-2 border-gray-700">
                        {{ $car['plate'] }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Cor</p>
                    <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">
                        {{ isset($car['color']) ? strtoupper($car['color']) : '—' }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Acessos no dia</p>
                    <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ $accessCount }}</p>
                </div>

            </div>

            @if($accessCount === 0)
                <div class="bg-white dark:bg-gray-800 p-12 text-center rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
                    <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 text-lg font-medium">
                        Nenhum acesso encontrado para a placa <span class="font-bold text-gray-700 dark:text-gray-200">{{ $car['plate'] }}</span> em {{ $searchedDate }}.
                    </p>
                </div>
            @endif

            <!-- Condutores prováveis -->
            @if(count($probaly))
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden mb-8">
                    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-lg font-extrabold text-gray-900 dark:text-white leading-tight">Condutores mais prováveis</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Baseado nos 10 acessos mais recentes desta placa.</p>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5">
                        @foreach ($probaly as $key => $item)
                            @php
                                $text = explode(' | ', $key);
                                $name = $text[0] ?? '—';
                                $phone = $text[1] ?? '';
                            @endphp
                            <div class="relative p-5 rounded-2xl border {{ $loop->first ? 'border-[#A00001]/30 bg-red-50/50 dark:bg-red-900/10 dark:border-red-800/50' : 'border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-700/30' }}">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 text-sm font-black
                                                {{ $loop->first ? 'bg-[#A00001] text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200' }}">
                                        {{ $loop->iteration }}º
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-bold text-gray-900 dark:text-white leading-tight truncate" title="{{ $name }}">
                                            {{ $name }}
                                        </h3>
                                        @if($phone)
                                            <a href="tel:{{ preg_replace('/\D/', '', $phone) }}"
                                               class="text-sm text-gray-500 dark:text-gray-400 hover:text-[#A00001] dark:hover:text-red-400 transition">
                                                {{ $phone }}
                                            </a>
                                        @endif
                                    </div>
                                    <span class="text-sm font-black {{ $loop->first ? 'text-[#A00001] dark:text-red-400' : 'text-gray-600 dark:text-gray-300' }}">
                                        {{ number_format($item, 1) }}%
                                    </span>
                                </div>

                                <div class="mt-3 h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-600 overflow-hidden">
                                    <div class="h-full rounded-full {{ $loop->first ? 'bg-[#A00001]' : 'bg-gray-400 dark:bg-gray-500' }}"
                                         style="width: {{ min($item, 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Histórico -->
            @if($accessCount)
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-extrabold text-gray-900 dark:text-white leading-tight">Histórico de acessos</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Registros capturados em {{ $searchedDate }}.</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 shrink-0">
                            {{ $accessCount }} {{ $accessCount === 1 ? 'registro' : 'registros' }}
                        </span>
                    </div>

                    <div class="p-6 space-y-5">
                        @foreach($data as $log)
                            @include('parking.partials.dataItemAccess', ['log' => $log])
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
