<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

            {{-- Boas-vindas --}}
            <div class="bg-gradient-to-r from-red-800 to-red-600 dark:from-red-900 dark:to-red-700 rounded-2xl shadow-lg p-6 text-white flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold">{{ __('Olá') }}, {{ $user->name }} 👋</h1>
                    <p class="text-sm text-red-100 mt-1">{{ __('Bem-vindo ao seu painel de controle.') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs uppercase tracking-wider text-red-200">{{ __('Último acesso') }}</p>
                    <p class="text-sm font-semibold">
                        {{ $user->last_login_at ? \Illuminate\Support\Carbon::parse($user->last_login_at)->format('d/m/Y H:i') : __('Primeiro acesso') }}
                    </p>
                </div>
            </div>

            {{-- ============================ InfoClube ============================ --}}
            @can('view information')
                <x-dashboard.section title="InfoClube" color="teal"
                    :href="route('information.index')"
                    icon="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <x-dashboard.stat-card color="teal" label="Informações ativas" :value="$info['total']">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </x-dashboard.stat-card>

                        {{-- <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                            <h3 class="text-sm font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">{{ __('Principais categorias') }}</h3>
                            @if($info['categories']->isEmpty())
                                <p class="text-sm text-gray-400 dark:text-gray-500 py-4">{{ __('Nenhuma categoria registrada.') }}</p>
                            @else
                                @php $maxCat = max($info['categories']->max(), 1); @endphp
                                <div class="space-y-3">
                                    @foreach($info['categories'] as $category => $count)
                                        <div>
                                            <div class="flex justify-between text-sm mb-1">
                                                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $category }}</span>
                                                <span class="font-bold text-gray-500 dark:text-gray-400">{{ $count }}</span>
                                            </div>
                                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                                                <div class="bg-teal-500 h-2 rounded-full" style="width: {{ round($count / $maxCat * 100) }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div> --}}
                    </div>
                </x-dashboard.section>
            @endcan

            {{-- =============================== SIV =============================== --}}
            @can('search parking')
                <x-dashboard.section title="SIV" color="indigo"
                    :href="route('parking.search')" linkLabel="Buscar placa"
                    icon="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-dashboard.stat-card color="indigo" label="Veículos hoje" :value="$parking['today']">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13l1.5-4.5A2 2 0 016.4 7h11.2a2 2 0 011.9 1.5L21 13m-18 0h18m-18 0v5a1 1 0 001 1h1a1 1 0 001-1v-1h10v1a1 1 0 001 1h1a1 1 0 001-1v-5M6.5 16h.01M17.5 16h.01"/>
                        </x-dashboard.stat-card>
                        <x-dashboard.stat-card color="sky" label="Veículos no mês" :value="$parking['month']">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </x-dashboard.stat-card>
                        <x-dashboard.stat-card color="amber" label="Placas diretoria" :value="$parking['authTotal']"
                            :sub="$parking['authExpiring'] > 0 ? $parking['authExpiring'].' expiram em 30 dias' : null">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </x-dashboard.stat-card>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <h3 class="text-sm font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">{{ __('Detecções — últimos 14 dias') }}</h3>
                        <canvas id="parkingChart" height="90"></canvas>
                    </div>
                </x-dashboard.section>
            @endcan

            {{-- ============================= Reservas ============================ --}}
            @can('view reservations')
                <x-dashboard.section title="Reservas" color="violet"
                    :href="route('schedule.index')"
                    icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-dashboard.stat-card color="violet" label="Reservas hoje" :value="$reservations['today']">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </x-dashboard.stat-card>
                        <x-dashboard.stat-card color="indigo" label="Reservas futuras" :value="$reservations['upcomingCount']">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </x-dashboard.stat-card>
                        <x-dashboard.stat-card color="emerald" label="Receita do mês" value="R$ {{ number_format($reservations['revenue'], 2, ',', '.') }}">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </x-dashboard.stat-card>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                            <h3 class="text-sm font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">{{ __('Reservas por status') }}</h3>
                            @if($reservations['chart']['data']->isEmpty())
                                <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-10">{{ __('Sem reservas registradas.') }}</p>
                            @else
                                <canvas id="reservationChart" height="200"></canvas>
                            @endif
                        </div>

                        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                                <h3 class="text-sm font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Próximas reservas') }}</h3>
                            </div>
                            @if($reservations['upcoming']->isEmpty())
                                <p class="py-12 text-center text-gray-400 dark:text-gray-500 font-medium">{{ __('Nenhuma reserva agendada.') }}</p>
                            @else
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50/70 dark:bg-gray-700/50">
                                            <th class="px-6 py-3 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Local') }}</th>
                                            <th class="px-6 py-3 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Membro') }}</th>
                                            <th class="px-6 py-3 text-left text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Início') }}</th>
                                            <th class="px-6 py-3 text-center text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                        @foreach($reservations['upcoming'] as $schedule)
                                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">
                                                <td class="px-6 py-3.5 font-semibold text-gray-800 dark:text-gray-200">{{ $schedule->place ? ($schedule->place->group?->name ? $schedule->place->group->name.' - '.$schedule->place->name : $schedule->place->name) : '—' }}</td>
                                                <td class="px-6 py-3.5 text-gray-600 dark:text-gray-300">{{ $schedule->member->name . ' (' . $schedule->member->title . ')' ?? '—' }}</td>
                                                <td class="px-6 py-3.5 whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $schedule->start_schedule?->format('d/m/Y H:i') }}</td>
                                                <td class="px-6 py-3.5 text-center">
                                                    <span class="inline-flex px-2.5 py-1 bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-400 rounded-full text-[11px] font-black uppercase">
                                                        {{ $schedule->status->portuguese ?? '—' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </div>
                </x-dashboard.section>
            @endcan

            {{-- ============================= Parceiros =========================== --}}
            <x-dashboard.section title="Parceiros" color="emerald"
                :href="route('company.index')"
                icon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z">

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <x-dashboard.stat-card color="indigo" label="Empresas parceiras" :value="$partners['companies']"
                        :sub="$partners['workers'].' funcionários'">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </x-dashboard.stat-card>
                    <x-dashboard.stat-card color="sky" label="Funcionários" :value="$partners['workers']">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </x-dashboard.stat-card>
                    <x-dashboard.stat-card color="green" label="Acessos permitidos hoje" :value="$partners['allowedToday']">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </x-dashboard.stat-card>
                    <x-dashboard.stat-card color="red" label="Acessos negados hoje" :value="$partners['deniedToday']">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </x-dashboard.stat-card>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">{{ __('Acessos — últimos 14 dias') }}</h3>
                    <canvas id="partnersChart" height="80"></canvas>
                </div>
            </x-dashboard.section>

        </div>
    </div>

    <x-slot name="js">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Chart === 'undefined') return;

                const isDark = document.documentElement.classList.contains('dark');
                Chart.defaults.color = isDark ? '#9ca3af' : '#6b7280';
                Chart.defaults.font.family = 'Figtree, sans-serif';
                const grid = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';

                @can('search parking')
                const parkingEl = document.getElementById('parkingChart');
                if (parkingEl) {
                    new Chart(parkingEl, {
                        type: 'line',
                        data: {
                            labels: @json($parking['chart']['labels']),
                            datasets: [{
                                label: 'Detecções',
                                data: @json($parking['chart']['data']),
                                borderColor: '#6366f1',
                                backgroundColor: 'rgba(99,102,241,0.15)',
                                fill: true,
                                tension: 0.35,
                                pointRadius: 3,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: true,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: grid }, ticks: { precision: 0 } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }
                @endcan

                @can('view reservations')
                const reservationEl = document.getElementById('reservationChart');
                if (reservationEl) {
                    new Chart(reservationEl, {
                        type: 'doughnut',
                        data: {
                            labels: @json($reservations['chart']['labels']),
                            datasets: [{
                                data: @json($reservations['chart']['data']),
                                backgroundColor: ['#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#ec4899'],
                                borderWidth: 0,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: true,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
                @endcan

                const partnersEl = document.getElementById('partnersChart');
                if (partnersEl) {
                    new Chart(partnersEl, {
                        type: 'bar',
                        data: {
                            labels: @json($partners['chart']['labels']),
                            datasets: [
                                {
                                    label: 'Permitidos',
                                    data: @json($partners['chart']['allowed']),
                                    backgroundColor: '#10b981',
                                    borderRadius: 4,
                                },
                                {
                                    label: 'Negados',
                                    data: @json($partners['chart']['denied']),
                                    backgroundColor: '#ef4444',
                                    borderRadius: 4,
                                }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: true,
                            plugins: { legend: { position: 'top' } },
                            scales: {
                                y: { beginAtZero: true, stacked: true, grid: { color: grid }, ticks: { precision: 0 } },
                                x: { stacked: true, grid: { display: false } }
                            }
                        }
                    });
                }
            });
        </script>
    </x-slot>
</x-app-layout>
