{{--
    Capa da agenda (identidade de Reservas desde 11/11/2026): faixa verde da
    quadra com a foto da arena, o dia em letra grande, a troca de dia, a
    ocupação do dia somando todos os locais e atalhos para cada modalidade.
    PDF do dia e Configurações seguem aqui.
--}}
@php
    $day = \Illuminate\Support\Carbon::parse($date);
    $dayButton = 'grid h-11 w-11 place-items-center rounded-full text-white/80 transition hover:bg-white/15 hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-white/30';
    $ghost = 'inline-flex h-11 items-center gap-2 rounded-full bg-white/10 px-4 text-sm font-bold text-white ring-1 ring-white/20 transition hover:-translate-y-px hover:bg-white/20 active:translate-y-0 active:scale-[.98] focus:outline-none focus-visible:ring-4 focus-visible:ring-white/30 motion-reduce:transform-none';

    // Estado de cada horário, como no time-card: bloqueado, reservado
    // (confirmado/pendente), passado ou livre.
    $slotState = static function (array $slot): string {
        if ($slot['excluded_by_rule'] ?? false) {
            return 'lock';
        }
        if (isset($slot['colided_member']) && $slot['colided_member'] !== null) {
            return ($slot['colided_status_id'] ?? null) == '1' ? 'ok' : 'warn';
        }

        return ($slot['past_date'] ?? false) === true ? 'past' : 'free';
    };

    $tally = ['ok' => 0, 'warn' => 0, 'free' => 0, 'lock' => 0, 'past' => 0];
    foreach ($modalities as $places) {
        foreach ($places as $place) {
            foreach (($place['time_options'] ?? []) as $slot) {
                $tally[$slotState($slot)]++;
            }
        }
    }
    $bookable = $tally['ok'] + $tally['warn'] + $tally['free'];
    $occupancy = $bookable > 0 ? round(($tally['ok'] + $tally['warn']) / $bookable * 100) : 0;
@endphp

<section class="res-court overflow-hidden rounded-[28px] shadow-card" aria-labelledby="agenda-dia">
    <div class="res-court-photo hidden lg:block" aria-hidden="true">
        <img src="{{ asset('images/auth/arena-areia.jpg') }}" alt="" class="auth-photo-img" decoding="async">
    </div>

    <div class="auth-enter flex flex-col gap-8 p-6 sm:p-10">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/65">Agenda do dia{{ $day->isToday() ? ' · hoje' : '' }}</p>
                <h2 id="agenda-dia" class="mt-2 font-display text-[28px] font-semibold leading-[1.05] tracking-tight first-letter:uppercase sm:text-[44px]">
                    {{ $day->translatedFormat('l') }}<span class="block text-white/70">{{ $day->translatedFormat('d \d\e F') }}</span>
                </h2>
            </div>

            {{-- Troca de dia. --}}
            <div class="flex items-center gap-1 rounded-full bg-black/20 p-1 ring-1 ring-white/15">
                <a href="{{ request()->fullUrlWithQuery(['date' => date('Y-m-d', strtotime($date . ' -1 day'))]) }}" class="{{ $dayButton }}" aria-label="Dia anterior">
                    <x-icon name="arrow-right" class="h-4 w-4 rotate-180" />
                </a>
                <form action="{{ url()->current() }}" method="GET">
                    <label for="report-date" class="sr-only">Data da agenda</label>
                    <input id="report-date" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                           class="h-11 cursor-pointer rounded-full border-0 bg-transparent px-2 font-mono text-sm font-semibold text-white [color-scheme:dark] focus:ring-0">
                </form>
                <a href="{{ request()->fullUrlWithQuery(['date' => date('Y-m-d', strtotime($date . ' +1 day'))]) }}" class="{{ $dayButton }}" aria-label="Próximo dia">
                    <x-icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </div>

        {{-- Ocupação do dia, somando todos os locais. --}}
        <div class="max-w-3xl">
            <div class="flex flex-wrap items-end gap-x-8 gap-y-3">
                <div>
                    <p class="font-mono text-[44px] font-semibold leading-none">{{ $occupancy }}%</p>
                    <p class="mt-1 text-sm text-white/70">dos horários reservados</p>
                </div>
                <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <div><dt class="text-white/65">Confirmados</dt><dd class="font-mono text-lg font-semibold">{{ $tally['ok'] }}</dd></div>
                    <div><dt class="text-white/65">Pendentes</dt><dd class="font-mono text-lg font-semibold text-[rgb(var(--res-clay))]">{{ $tally['warn'] }}</dd></div>
                    <div><dt class="text-white/65">Livres</dt><dd class="font-mono text-lg font-semibold">{{ $tally['free'] }}</dd></div>
                    <div><dt class="text-white/65">Bloqueados</dt><dd class="font-mono text-lg font-semibold">{{ $tally['lock'] }}</dd></div>
                </dl>
            </div>
            @if ($bookable > 0)
                <div class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-white/15" role="meter" aria-valuemin="0" aria-valuemax="100"
                     aria-valuenow="{{ $occupancy }}" aria-label="Ocupação do dia">
                    <span class="h-full bg-[rgb(134_239_172)]" style="width: {{ $tally['ok'] / $bookable * 100 }}%"></span>
                    <span class="h-full bg-[rgb(var(--res-clay))]" style="width: {{ $tally['warn'] / $bookable * 100 }}%"></span>
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-white/15 pt-6">
            {{-- Atalhos para cada modalidade da agenda. --}}
            @if (count($modalities) > 1)
                <nav class="flex flex-wrap gap-2" aria-label="Modalidades do dia">
                    @foreach ($modalities as $modalityName => $places)
                        <a href="#mod-{{ \Illuminate\Support\Str::slug($modalityName) }}"
                           class="inline-flex h-9 items-center rounded-full bg-white/10 px-3.5 text-sm font-bold text-white no-underline ring-1 ring-white/15 transition hover:bg-white hover:text-[rgb(var(--res-court))]">{{ $modalityName }}</a>
                    @endforeach
                </nav>
            @else
                <span></span>
            @endif

            <div class="flex flex-wrap gap-2">
                <button type="button" class="{{ $ghost }}" onclick="generatePDFTable({{ json_encode($modalities) }})">
                    <x-icon name="download" /> PDF do dia
                </button>
                <a href="{{ route('place-group.index') }}" class="{{ $ghost }} no-underline">
                    <x-icon name="sliders" /> Configurações
                </a>
            </div>
        </div>
    </div>
</section>
