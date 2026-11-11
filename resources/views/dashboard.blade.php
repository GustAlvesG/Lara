{{--
    Painel inicial (refeito em 10/11/2026): faixa de boas-vindas com a foto do
    clube e os números do dia, os avisos novos em destaque e um painel por
    módulo que a pessoa pode abrir, cada um na sua cor.

    Leve de propósito — é a primeira tela de todo mundo: só números e listas
    curtas, sem gráfico e sem script de terceiros. Os dados vêm prontos do
    DashboardController (cache de 60 s nos números do clube); esta tela não
    consulta o banco.
--}}
@php
    $firstName = \Illuminate\Support\Str::before($user->name, ' ') ?: $user->name;
    $lastLogin = $user->last_login_at ? \Illuminate\Support\Carbon::parse($user->last_login_at)->format('d/m/Y \à\s H:i') : null;

    // "Hoje no clube": só os números do dia que a pessoa pode ver.
    $today = array_filter([
        isset($parking) ? ['Veículos na portaria', $parking['today']] : null,
        isset($reservations) ? ['Reservas para hoje', $reservations['today']] : null,
        ['Acessos de externos liberados', $partners['allowedToday']],
        ['Acessos de externos negados', $partners['deniedToday']],
    ]);
    // Classe inteira (não montada) para o build do Tailwind enxergar.
    $todayCols = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-4'][count($today)];

    $privacyLabels = ['pessoa' => 'Pessoal', 'setor' => 'Setor', 'publico' => 'Público', 'grupo' => 'Grupo'];
    $featured = $avisos->first();
    $others = $avisos->slice(1);
@endphp

{{-- Sem capa: é o início, a saudação faz esse papel e o logo já leva aqui. --}}
<x-app-layout :bootstrap-grid="false" :cover="false">
    <x-page class="gap-6">

        {{-- ========================== Boas-vindas =========================== --}}
        <section class="relative isolate overflow-hidden rounded-card bg-ink text-white shadow-card" aria-labelledby="ola">
            {{-- Uma das fotos do clube, sorteada a cada abertura, com o movimento lento
                 das telas de entrada (App\View\ClubPhotos, 11/11/2026). Decorativa: alt vazio. --}}
            @php $clubPhoto = \App\View\ClubPhotos::random(); @endphp
            <img src="{{ asset($clubPhoto['src']) }}" alt=""
                 class="auth-photo-img auth-photo-drift absolute inset-0 -z-10 h-full w-full object-cover"
                 style="{{ \App\View\ClubPhotos::style($clubPhoto) }}" data-club-photo="{{ $clubPhoto['key'] }}" decoding="async">
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-black/85 via-black/60 to-black/25" aria-hidden="true"></div>

            <div class="auth-enter flex flex-col gap-8 p-6 sm:p-8 lg:flex-row lg:items-end lg:justify-between lg:gap-10 lg:p-10">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-white/70">{{ now()->translatedFormat('l, d \d\e F') }}</p>
                    <h1 id="ola" class="mt-2 font-display text-[30px] font-semibold leading-tight tracking-tight sm:text-[40px]">{{ __('Olá') }}, {{ $firstName }}</h1>
                    <p class="mt-2 text-sm text-white/75">
                        @if ($lastLogin)
                            {{ __('Último acesso') }}: <span class="font-mono font-semibold text-white">{{ $lastLogin }}</span>
                        @else
                            {{ __('Primeiro acesso. Bem-vindo ao Lara.') }}
                        @endif
                    </p>
                </div>

                <dl class="grid shrink-0 grid-cols-2 gap-px overflow-hidden rounded-2xl bg-white/15 {{ $todayCols }}" aria-label="Hoje no clube">
                    @foreach ($today as [$label, $value])
                        <div class="flex min-w-[8.5rem] flex-col-reverse gap-1 bg-black/35 px-4 py-3.5 backdrop-blur-sm">
                            <dt class="text-xs font-semibold leading-snug text-white/75">{{ $label }}</dt>
                            <dd class="font-mono text-2xl font-semibold leading-none">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        @include('partials.alerts')

        {{-- ============================== Avisos ============================= --}}
        {{-- Só os que a pessoa ainda não abriu; sem nenhum, o bloco some. --}}
        @if ($featured)
            <x-dashboard.panel title="Avisos novos" area="info" glyph="bell" :href="route('avisos.index')" linkLabel="Ver todos">
                <div class="grid grid-cols-1 gap-4 {{ $others->isNotEmpty() ? 'lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]' : '' }}">
                    <a href="{{ route('avisos.show', $featured) }}"
                       class="group flex min-w-0 flex-col overflow-hidden rounded-2xl bg-subtle no-underline transition hover:-translate-y-0.5 focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint sm:flex-row">
                        @if ($featured->image)
                            <span class="block aspect-[16/9] shrink-0 overflow-hidden sm:aspect-auto sm:w-2/5">
                                <img src="{{ asset('images/avisos/' . $featured->image) }}" alt="" loading="lazy" onerror="this.parentElement.remove()"
                                     class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
                            </span>
                        @endif
                        <span class="flex min-w-0 flex-1 flex-col gap-2 p-5">
                            <span class="flex flex-wrap gap-1.5">
                                <x-pill kind="info" :icon="false">{{ $privacyLabels[$featured->privacy] ?? 'Setor' }}</x-pill>
                                @if ($featured->mandatory)
                                    <x-pill kind="warn" :icon="false">Leitura obrigatória</x-pill>
                                @endif
                            </span>
                            <span class="font-display text-lg font-semibold leading-snug tracking-tight text-ink">{{ $featured->title }}</span>
                            @if ($featured->content)
                                <span class="line-clamp-3 text-sm text-ink-2">{{ \Illuminate\Support\Str::limit(trim(strip_tags($featured->content)), 220) }}</span>
                            @endif
                            <span class="mt-auto pt-1 text-xs text-ink-3">{{ $featured->creator->name ?? '—' }} · {{ $featured->created_at->diffForHumans() }}</span>
                        </span>
                    </a>

                    @if ($others->isNotEmpty())
                        <ul class="flex flex-col divide-y divide-line">
                            @foreach ($others as $aviso)
                                <li>
                                    <a href="{{ route('avisos.show', $aviso) }}"
                                       class="group flex items-start gap-3 rounded-xl px-2 py-3 no-underline transition hover:bg-subtle focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                                        <span class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-area-info text-area-info-ink">
                                            <x-icon name="bell" class="h-4 w-4" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="line-clamp-2 font-bold leading-snug text-ink group-hover:text-grena-ink">{{ $aviso->title }}</span>
                                            <span class="mt-0.5 block text-xs text-ink-3">{{ $aviso->creator->name ?? '—' }} · {{ $aviso->created_at->diffForHumans() }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-dashboard.panel>
        @endif

        {{-- ============================= Módulos ============================= --}}
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">

            {{-- Reservas: o painel maior, com as próximas reservas. --}}
            @can('reservas.agendamentos')
                <x-dashboard.panel title="Reservas" area="reservas" glyph="calendar" :href="route('schedule.index')" linkLabel="Agenda"
                                   class="md:col-span-2 lg:row-span-3">
                    <div class="grid grid-cols-2 gap-4 border-b border-line pb-5">
                        <x-dashboard.metric label="Reservas futuras" :value="$reservations['upcomingCount']" />
                        <x-dashboard.metric label="Receita do mês" value="R$ {{ number_format($reservations['revenue'], 2, ',', '.') }}" />
                    </div>

                    <div class="flex flex-1 flex-col">
                        <h3 class="text-xs font-bold uppercase tracking-[0.08em] text-ink-3">{{ __('Próximas reservas') }}</h3>
                        @if ($reservations['upcoming']->isEmpty())
                            <p class="mt-3 text-sm text-ink-3">{{ __('Nenhuma reserva marcada daqui para a frente.') }}</p>
                        @else
                            <ul class="mt-2 divide-y divide-line">
                                @foreach ($reservations['upcoming'] as $schedule)
                                    <li>
                                        <a href="{{ route('schedule.show', $schedule->id) }}"
                                           class="-mx-2 flex items-center gap-4 rounded-xl px-2 py-3 no-underline transition hover:bg-subtle focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                                            <span class="grid w-14 shrink-0 place-items-center rounded-xl bg-area-reservas py-1.5 text-area-reservas-ink">
                                                <span class="font-mono text-lg font-semibold leading-none">{{ $schedule->start_schedule?->format('d') }}</span>
                                                <span class="text-[11px] font-bold uppercase">{{ $schedule->start_schedule?->translatedFormat('M') }}</span>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate font-semibold text-ink">{{ $schedule->place ? ($schedule->place->group?->name ? $schedule->place->group->name.' - '.$schedule->place->name : $schedule->place->name) : '—' }}</span>
                                                <span class="block truncate text-sm text-ink-2">
                                                    <span class="font-mono">{{ $schedule->start_schedule?->format('H:i') }}</span>
                                                    · {{ $schedule->member ? $schedule->member->name . ' (' . $schedule->member->title . ')' : '—' }}
                                                </span>
                                            </span>
                                            <x-pill :kind="match((int) $schedule->status_id) { 1 => 'ok', 3 => 'warn', 0 => 'danger', default => 'off' }">{{ $schedule->status->portuguese ?? '—' }}</x-pill>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </x-dashboard.panel>
            @endcan

            @can('siv.busca')
                <x-dashboard.panel title="SIV" area="portaria" glyph="car" :href="route('parking.search')" linkLabel="Buscar placa">
                    <x-dashboard.metric label="Placas da diretoria" :value="$parking['authTotal']"
                        :sub="$parking['authExpiring'] > 0 ? $parking['authExpiring'].' expiram em 30 dias' : null" />
                </x-dashboard.panel>
            @endcan

            <x-dashboard.panel title="Externos" area="externos" glyph="users" :href="route('company.index')">
                <div class="grid grid-cols-2 gap-4">
                    <x-dashboard.metric label="Empresas parceiras" :value="$partners['companies']" />
                    <x-dashboard.metric label="Funcionários" :value="$partners['workers']" />
                </div>
            </x-dashboard.panel>

            {{-- InfoClube é de todo mundo logado. --}}
            <x-dashboard.panel title="InfoClube" area="info" glyph="info" :href="route('information.index')" linkLabel="Informações">
                <x-dashboard.metric label="Informações ativas" :value="$info['total']" />
            </x-dashboard.panel>
        </div>

        {{-- ========================= Home Assistant ========================= --}}
        @can('home-assistant')
            <x-dashboard.panel title="Home Assistant" area="inicio" glyph="bolt" :href="route('home-assistant.index')" linkLabel="Gerenciar">
                @if ($homeAssistant['contactors']->isEmpty())
                    <x-empty-state icon="bulb">{{ __('Nenhum interruptor cadastrado.') }}</x-empty-state>
                @else
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($homeAssistant['contactors'] as $contactor)
                            <x-dashboard.ha-switch :contactor="$contactor" :state="$homeAssistant['states'][$contactor->id]" />
                        @endforeach
                    </div>
                @endif
            </x-dashboard.panel>
        @endcan

    </x-page>
</x-app-layout>
