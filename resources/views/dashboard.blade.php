{{--
    Painel inicial: um bloco por módulo que a pessoa pode abrir, cada um na sua
    cor, com os números do dia e o atalho para a tela.

    Leve de propósito — é a primeira tela de todo mundo: só números e listas
    curtas, sem gráfico e sem script de terceiros. Ver DashboardController.
--}}
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
        {{-- Só os que a pessoa ainda não abriu; sem nenhum, o bloco some. --}}
        @if($avisos->isNotEmpty())
            <x-dashboard.section title="Avisos novos" area="info" glyph="bell" :href="route('avisos.index')" linkLabel="Ver todos">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($avisos as $aviso)
                        @include('avisos.partials.card', ['aviso' => $aviso])
                    @endforeach
                </div>
            </x-dashboard.section>
        @endif

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

        {{-- =============================== SIV =============================== --}}
        @can('siv.busca')
            <x-dashboard.section title="SIV" area="portaria" glyph="car" :href="route('parking.search')" linkLabel="Buscar placa">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-dashboard.stat-card glyph="car" label="Veículos hoje" :value="$parking['today']" />
                    <x-dashboard.stat-card glyph="shield" label="Placas diretoria" :value="$parking['authTotal']"
                        :sub="$parking['authExpiring'] > 0 ? $parking['authExpiring'].' expiram em 30 dias' : null" />
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

                @if($reservations['upcoming']->isNotEmpty())
                    <div class="overflow-hidden rounded-card bg-surface shadow-card">
                        <h3 class="px-5 pt-4 text-xs font-bold uppercase tracking-[0.08em] text-ink-3">{{ __('Próximas reservas') }}</h3>
                        <ul class="divide-y divide-line">
                            @foreach($reservations['upcoming'] as $schedule)
                                <li>
                                    <a href="{{ route('schedule.show', $schedule->id) }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-3 transition hover:bg-subtle">
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold text-ink">{{ $schedule->place ? ($schedule->place->group?->name ? $schedule->place->group->name.' - '.$schedule->place->name : $schedule->place->name) : '—' }}</span>
                                            <span class="block truncate text-sm text-ink-2">{{ $schedule->member ? $schedule->member->name . ' (' . $schedule->member->title . ')' : '—' }}</span>
                                        </span>
                                        <span class="flex shrink-0 items-center gap-3">
                                            <span class="font-mono text-xs text-ink-2">{{ $schedule->start_schedule?->format('d/m H:i') }}</span>
                                            <x-pill :kind="match((int) $schedule->status_id) { 1 => 'ok', 3 => 'warn', 0 => 'danger', default => 'off' }">{{ $schedule->status->portuguese ?? '—' }}</x-pill>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
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
        </x-dashboard.section>

        {{-- ============================ InfoClube ============================ --}}
        {{-- InfoClube é de todo mundo logado. --}}
        <x-dashboard.section title="InfoClube" area="info" glyph="info" :href="route('information.index')">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-dashboard.stat-card glyph="info" label="Informações ativas" :value="$info['total']" :href="route('information.index')" />
            </div>
        </x-dashboard.section>

    </x-page>
</x-app-layout>
