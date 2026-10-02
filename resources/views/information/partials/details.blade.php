@php
    /**
     * Renderização somente leitura de uma versão ($info), em duas colunas:
     *   esquerda -> imagem, nome e descrição
     *   direita  -> tags, demais campos, preços, horários e responsáveis
     *
     * Usada pela tela de detalhe e por cada versão do histórico — por isso o
     * nome aparece aqui e não só no título da página.
     */
    $imageUrl = $info->image ? asset('images/' . $info->image) : null;
    $nameParts = preg_split('/\s+/', trim($info->name), -1, PREG_SPLIT_NO_EMPTY);
    $initials = mb_strtoupper(collect($nameParts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));

    $fields = array_filter([
        'Status' => $info->status,
        'Vagas' => $info->slots,
        'Localização' => $info->location,
        'Taxa de matrícula' => filled($info->fee) ? 'R$ ' . number_format((float) $info->fee, 2, ',', '.') : null,
    ], fn ($value) => filled($value));

    $prices = $info->price_rows ?? [];
    $schedules = $info->schedule_rows ?? [];
    $responsibles = $info->responsible_rows ?? [];

    $money = fn ($value) => filled($value) ? 'R$ ' . number_format((float) $value, 2, ',', '.') : '—';
    $panel = 'rounded-card bg-surface p-5 shadow-card';
    $panelTitle = 'mb-3 text-xs font-bold uppercase tracking-[0.08em] text-ink-3';
    $th = 'px-5 py-2.5 text-left text-xs font-bold text-ink-3';
    $td = 'px-5 py-3';
@endphp

{{-- 10 colunas: 6/4 = 60% / 40%. --}}
<div class="grid grid-cols-1 gap-4 lg:grid-cols-10">

    {{-- ---------- Coluna esquerda (60%) ---------- --}}
    <div class="flex flex-col gap-4 lg:col-span-6">
        <section class="overflow-hidden rounded-card bg-surface shadow-card">
            <x-media :src="$imageUrl" :alt="'Imagem de ' . $info->name" area="info" :initials="$initials" logo ratio="short" />

            <div class="p-5">
                <h3 class="font-display text-lg font-semibold tracking-tight text-ink">{{ $info->name }}</h3>

                <div class="mt-4 border-t border-line pt-4">
                    <h4 class="{{ $panelTitle }}">Descrição</h4>
                    @if (filled($info->description))
                        <x-rich-editor readonly :value="$info->description" />
                    @else
                        <p class="text-sm italic text-ink-3">Sem descrição cadastrada.</p>
                    @endif
                </div>
            </div>
        </section>
    </div>

    {{-- ---------- Coluna direita (40%) ---------- --}}
    <div class="flex flex-col gap-4 lg:col-span-4">
        <section class="{{ $panel }}">
            <h4 class="{{ $panelTitle }}">Tags</h4>
            @if ($info->tags->isNotEmpty())
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($info->tags as $tag)
                        <a href="{{ route('information.index', ['q' => $tag->name]) }}"
                            class="rounded-full bg-area-info px-2.5 py-1 text-xs font-semibold text-area-info-ink no-underline hover:underline">#{{ $tag->name }}</a>
                    @endforeach
                </div>
            @else
                <p class="text-sm italic text-ink-3">Sem tags nesta versão.</p>
            @endif

            @if ($fields)
                <dl class="mt-4 grid grid-cols-2 gap-4 border-t border-line pt-4">
                    @foreach ($fields as $label => $value)
                        <div>
                            <dt class="text-xs font-bold text-ink-3">{{ $label }}</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-ink">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        @if ($prices)
            <section class="overflow-hidden rounded-card bg-surface shadow-card">
                <h4 class="{{ $panelTitle }} mb-0 px-5 pb-2 pt-4">Preços</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Título</th>
                                <th class="{{ $th }}">Sócio</th>
                                <th class="{{ $th }}">Não sócio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($prices as $price)
                                <tr class="border-b border-line last:border-0">
                                    <td class="{{ $td }} text-ink">{{ $price['name'] !== '' ? $price['name'] : '—' }}</td>
                                    <td class="{{ $td }} whitespace-nowrap font-mono font-semibold text-ink">{{ $money($price['associated']) }}</td>
                                    <td class="{{ $td }} whitespace-nowrap font-mono text-ink-2">{{ $money($price['not_associated']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($schedules)
            <section class="overflow-hidden rounded-card bg-surface shadow-card">
                <h4 class="{{ $panelTitle }} mb-0 px-5 pb-2 pt-4">Dias e horários</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Dia</th>
                                <th class="{{ $th }}">Horário</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($schedules as $schedule)
                                <tr class="border-b border-line last:border-0">
                                    <td class="{{ $td }} text-ink">{{ $schedule['day'] !== '' ? $schedule['day'] : '—' }}</td>
                                    <td class="{{ $td }} font-mono text-ink-2">
                                        @if ($schedule['start'] !== '' || $schedule['end'] !== '')
                                            {{ $schedule['start'] !== '' ? $schedule['start'] : '—' }} às {{ $schedule['end'] !== '' ? $schedule['end'] : '—' }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($responsibles)
            <section class="{{ $panel }}">
                <h4 class="{{ $panelTitle }}">Responsáveis</h4>
                <ul class="flex flex-col gap-2">
                    @foreach ($responsibles as $responsible)
                        @php
                            $digits = substr(preg_replace('/\D/', '', $responsible['contact']), 0, 11);
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-subtle px-4 py-2.5">
                            <span class="text-sm font-semibold text-ink">
                                {{ $responsible['name'] !== '' ? $responsible['name'] : 'Sem nome' }}
                            </span>
                            @if ($digits !== '')
                                <a href="https://wa.me/55{{ $digits }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1 font-mono text-sm font-semibold text-grena-ink no-underline hover:underline">
                                    <x-icon name="chat" class="h-3.5 w-3.5" />{{ $responsible['contact'] }}
                                </a>
                            @elseif ($responsible['contact'] !== '')
                                <span class="font-mono text-sm text-ink-2">{{ $responsible['contact'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</div>
