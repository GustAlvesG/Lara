{{--
    Painel inicial: um bloco por área que a pessoa pode abrir, cada um na cor
    da sua área, com os números do dia e o atalho para a tela.
--}}
@php
    $th = 'px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $panel = 'rounded-card bg-surface p-5 shadow-card';
    $panelTitle = 'mb-4 text-xs font-bold uppercase tracking-[0.08em] text-ink-3';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page class="gap-8">

        {{-- Boas-vindas --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.1em] text-ink-3">{{ now()->translatedFormat('l, d \d\e F') }}</p>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink sm:text-[28px]">{{ __('Olá') }}, {{ \Illuminate\Support\Str::before($user->name, ' ') }}</h1>
            </div>
            <p class="text-sm text-ink-2">
                {{ __('Último acesso') }}:
                <span class="font-mono font-semibold text-ink">{{ $user->last_login_at ? \Illuminate\Support\Carbon::parse($user->last_login_at)->format('d/m/Y H:i') : __('primeiro acesso') }}</span>
            </p>
        </div>

        @include('partials.alerts')

        {{-- ============================== Avisos ============================= --}}
        <x-dashboard.section title="Avisos" area="info" glyph="bell" :href="route('avisos.index')" linkLabel="Ver todos">
            @if($avisos->isEmpty())
                <x-empty-state icon="bell">{{ __('Nenhum aviso ativo no momento.') }}</x-empty-state>
            @else
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($avisos as $aviso)
                        @include('avisos.partials.card', ['aviso' => $aviso])
                    @endforeach
                </div>
            @endif
        </x-dashboard.section>

        {{-- ========================= Home Assistant ========================= --}}
        @can('home-assistant')
            <x-dashboard.section title="Home Assistant" area="inicio" glyph="bolt" :href="route('home-assistant.index')" linkLabel="Gerenciar">
                @if($homeAssistant['contactors']->isEmpty())
                    <x-empty-state icon="bulb">{{ __('Nenhum interruptor cadastrado.') }}</x-empty-state>
                @else
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($homeAssistant['contactors'] as $contactor)
                            <x-dashboard.ha-switch :contactor="$contactor" :state="$homeAssistant['states'][$contactor->id]" />
                        @endforeach
                    </div>
                @endif
            </x-dashboard.section>
        @endcan

        {{-- ============================ InfoClube ============================ --}}
        {{-- InfoClube é de todo mundo logado. --}}
        <x-dashboard.section title="InfoClube" area="info" glyph="info" :href="route('information.index')">
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                <x-dashboard.stat-card glyph="info" label="Informações ativas" :value="$info['total']" :href="route('information.index')" />
            </div>
        </x-dashboard.section>

        {{-- =============================== SIV =============================== --}}
        @can('siv.busca')
            <x-dashboard.section title="SIV" area="portaria" glyph="car" :href="route('parking.search')" linkLabel="Buscar placa">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <x-dashboard.stat-card glyph="car" label="Veículos hoje" :value="$parking['today']" />
                    <x-dashboard.stat-card glyph="calendar" label="Veículos no mês" :value="$parking['month']" />
                    <x-dashboard.stat-card glyph="shield" label="Placas diretoria" :value="$parking['authTotal']"
                        :sub="$parking['authExpiring'] > 0 ? $parking['authExpiring'].' expiram em 30 dias' : null" />
                </div>

                <div class="{{ $panel }}">
                    <h3 class="{{ $panelTitle }}">{{ __('Detecções — últimos 14 dias') }}</h3>
                    <canvas id="parkingChart" height="90"></canvas>
                </div>
            </x-dashboard.section>
        @endcan

        {{-- ============================= Reservas ============================ --}}
        @can('reservas.agendamentos')
            <x-dashboard.section title="Reservas" area="reservas" glyph="calendar" :href="route('schedule.index')" linkLabel="Agenda">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <x-dashboard.stat-card glyph="calendar" label="Reservas hoje" :value="$reservations['today']" />
                    <x-dashboard.stat-card glyph="clock" label="Reservas futuras" :value="$reservations['upcomingCount']" />
                    <x-dashboard.stat-card glyph="money" label="Receita do mês" value="R$ {{ number_format($reservations['revenue'], 2, ',', '.') }}" />
                </div>

                <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                    <div class="{{ $panel }}">
                        <h3 class="{{ $panelTitle }}">{{ __('Reservas por status') }}</h3>
                        @if($reservations['chart']['data']->isEmpty())
                            <p class="py-10 text-center text-sm text-ink-3">{{ __('Sem reservas registradas.') }}</p>
                        @else
                            <canvas id="reservationChart" height="200"></canvas>
                        @endif
                    </div>

                    <div class="overflow-hidden rounded-card bg-surface shadow-card lg:col-span-2">
                        <h3 class="px-5 pt-5 text-xs font-bold uppercase tracking-[0.08em] text-ink-3">{{ __('Próximas reservas') }}</h3>
                        @if($reservations['upcoming']->isEmpty())
                            <p class="py-12 text-center text-sm text-ink-3">{{ __('Nenhuma reserva agendada.') }}</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-line">
                                            <th class="{{ $th }}">{{ __('Local') }}</th>
                                            <th class="{{ $th }}">{{ __('Membro') }}</th>
                                            <th class="{{ $th }}">{{ __('Início') }}</th>
                                            <th class="{{ $th }}">{{ __('Status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        @foreach($reservations['upcoming'] as $schedule)
                                            <tr class="transition hover:bg-subtle">
                                                <td class="px-5 py-3 font-semibold text-ink">{{ $schedule->place ? ($schedule->place->group?->name ? $schedule->place->group->name.' - '.$schedule->place->name : $schedule->place->name) : '—' }}</td>
                                                <td class="px-5 py-3 text-ink-2">{{ $schedule->member ? $schedule->member->name . ' (' . $schedule->member->title . ')' : '—' }}</td>
                                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-ink-2">{{ $schedule->start_schedule?->format('d/m/Y H:i') }}</td>
                                                <td class="px-5 py-3">
                                                    <x-pill :kind="match((int) $schedule->status_id) { 1 => 'ok', 3 => 'warn', 0 => 'danger', default => 'off' }">{{ $schedule->status->portuguese ?? '—' }}</x-pill>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </x-dashboard.section>
        @endcan

        {{-- ============================= Externos =========================== --}}
        <x-dashboard.section title="Externos" area="externos" glyph="users" :href="route('company.index')">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <x-dashboard.stat-card glyph="users" label="Empresas parceiras" :value="$partners['companies']" />
                <x-dashboard.stat-card glyph="user" label="Funcionários" :value="$partners['workers']" />
                <x-dashboard.stat-card glyph="check" tone="ok" label="Acessos permitidos hoje" :value="$partners['allowedToday']" />
                <x-dashboard.stat-card glyph="ban" tone="danger" label="Acessos negados hoje" :value="$partners['deniedToday']" />
            </div>

            <div class="{{ $panel }}">
                <h3 class="{{ $panelTitle }}">{{ __('Acessos — últimos 14 dias') }}</h3>
                <canvas id="partnersChart" height="80"></canvas>
            </div>
        </x-dashboard.section>

    </x-page>

    <x-slot name="js">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Chart === 'undefined') return;

                // As cores saem dos tokens do tema (claro ou escuro), não de
                // valores fixos: o gráfico acompanha o resto da tela.
                const css = getComputedStyle(document.documentElement);
                const token = (name, alpha) => {
                    const rgb = css.getPropertyValue('--' + name).trim().split(/\s+/).join(', ');
                    return alpha === undefined ? 'rgb(' + rgb + ')' : 'rgba(' + rgb + ', ' + alpha + ')';
                };

                Chart.defaults.color = token('ink-2');
                Chart.defaults.font.family = 'Figtree, sans-serif';
                const grid = token('line');

                @can('siv.busca')
                const parkingEl = document.getElementById('parkingChart');
                if (parkingEl) {
                    new Chart(parkingEl, {
                        type: 'line',
                        data: {
                            labels: @json($parking['chart']['labels']),
                            datasets: [{
                                label: 'Detecções',
                                data: @json($parking['chart']['data']),
                                borderColor: token('a-portaria-ink'),
                                backgroundColor: token('a-portaria-ink', 0.14),
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

                @can('reservas.agendamentos')
                const reservationEl = document.getElementById('reservationChart');
                if (reservationEl) {
                    new Chart(reservationEl, {
                        type: 'doughnut',
                        data: {
                            labels: @json($reservations['chart']['labels']),
                            datasets: [{
                                data: @json($reservations['chart']['data']),
                                backgroundColor: [token('a-reservas-ink'), token('ok'), token('warn'), token('danger'), token('grena'), token('ink-3')],
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
                                    backgroundColor: token('ok'),
                                    borderRadius: 4,
                                },
                                {
                                    label: 'Negados',
                                    data: @json($partners['chart']['denied']),
                                    backgroundColor: token('danger'),
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
