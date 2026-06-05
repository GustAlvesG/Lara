<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Parceiros Terceirizados
                </h2>
            </div>

            @include('partials.crud.search-create', [
                'buttonCreateText' => 'Novo',
                'routeCreate' => route('company.create')
            ])
        </div>
    </x-slot>

    <x-slot name="css">
    </x-slot>

    <div class="py-6">
        <br>
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6 page-group">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg page" data-limit="5" data-actual="">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="elements-container">
                    @foreach ($companies as $company)
                        @php
                            $imageUrl = str_contains($company->image ?? '', 'http')
                                ? $company->image
                                : ($company->image ? asset('images/' . $company->image) : '');
                            $fallbackImage = 'https://placehold.co/600x400/1E3A8A/ffffff?text=' . urlencode($company->name);
                            $today = now()->toDateString();
                            $hasRules = $company->rules->isNotEmpty();
                            $hasActiveRule = $company->rules->contains(function ($rule) use ($today) {
                                return $rule->type === 'include'
                                    && $rule->start_date <= $today
                                    && ($rule->end_date === null || $rule->end_date >= $today);
                            });
                            $accessAllowed = $accessStatuses[$company->id] ?? false;
                            $workersCount = $company->workers->count();
                            $rulesCount   = $company->rules->count();
                        @endphp

                        <div class="elements">
                            <a href="{{ route('company.show', $company->id) }}" class="block transform hover:scale-[1.02] transition duration-300">
                                <div class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-2xl border border-gray-100 dark:border-gray-700 h-full">

                                    <!-- Imagem -->
                                    <div class="h-36 overflow-hidden">
                                        <img class="w-full h-full object-cover"
                                            src="{{ $imageUrl }}"
                                            onerror="this.onerror=null;this.src='{{ $fallbackImage }}';"
                                            alt="Imagem de {{ $company->name }}"
                                            style="max-height: 200px">
                                    </div>

                                    <!-- Conteúdo -->
                                    <div class="p-4">

                                        <!-- Título + badge de acesso -->
                                        <div class="flex items-start justify-between mb-2">
                                            <h3 class="text-xl font-extrabold text-gray-900 dark:text-white leading-tight">
                                                {{ $company->name }}
                                            </h3>

                                            @if ($accessAllowed)
                                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 whitespace-nowrap ml-2 flex-shrink-0">
                                                    <span class="w-2 h-2 rounded-full bg-green-500 inline-block animate-pulse"></span>
                                                    Acesso
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 whitespace-nowrap ml-2 flex-shrink-0">
                                                    <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                                                    Bloqueado
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Contadores -->
                                        <div class="flex gap-4 mb-3">
                                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                {{ $workersCount }} {{ $workersCount === 1 ? 'Funcionário' : 'Funcionários' }}
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                </svg>
                                                {{ $rulesCount }} {{ $rulesCount === 1 ? 'Regra' : 'Regras' }}
                                            </span>
                                        </div>

                                        <!-- Alertas -->
                                        @if (!$hasRules)
                                            <div class="flex items-center gap-2 text-xs text-yellow-700 bg-yellow-50 dark:bg-yellow-900/30 dark:text-yellow-300 rounded-lg px-3 py-2">
                                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                </svg>
                                                Nenhuma regra cadastrada
                                            </div>
                                        @elseif (!$hasActiveRule)
                                            <div class="flex items-center gap-2 text-xs text-orange-700 bg-orange-50 dark:bg-orange-900/30 dark:text-orange-300 rounded-lg px-3 py-2">
                                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Nenhuma regra vigente
                                            </div>
                                        @endif

                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-center sm:px-6 lg:px-8 space-y-6 my-3">
                <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg pagination">
                    @include('partials.navPagination')
                </div>
            </div>
        </div>
    </div>

    <x-slot name="js">
        <script src="{{ asset('js/information/filter.js') }}"></script>
    </x-slot>
</x-app-layout>
