@php
    /**
     * Renderização somente leitura de uma versão ($info), refeita em
     * 10/11/2026, em duas colunas:
     *   esquerda -> foto, nome, tags e descrição
     *   direita  -> a ficha: preço em destaque, valores, dias e horários,
     *               dados da turma e responsáveis com o WhatsApp
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
    $lead = collect($prices)->first(fn ($price) => filled($price['associated']));

    $panel = 'rounded-card bg-surface p-5 shadow-card sm:p-6';
    $panelTitle = 'mb-3 text-xs font-bold uppercase tracking-[0.1em] text-ink-3';
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,8fr)_minmax(0,5fr)] lg:items-start">

    {{-- ---------- Foto, nome e descrição ---------- --}}
    <article class="overflow-hidden rounded-card bg-surface shadow-card">
        <x-media :src="$imageUrl" :alt="'Imagem de ' . $info->name" area="info" :initials="$initials" logo ratio="video" />

        <div class="flex flex-col gap-4 p-5 sm:p-7">
            @if ($info->tags->isNotEmpty())
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($info->tags as $tag)
                        <a href="{{ route('information.index', ['q' => $tag->name]) }}"
                           class="inline-flex min-h-8 items-center rounded-full bg-area-info px-3 text-xs font-bold text-area-info-ink no-underline transition hover:-translate-y-px">#{{ $tag->name }}</a>
                    @endforeach
                </div>
            @endif

            <h3 class="font-display text-2xl font-semibold leading-tight tracking-tight text-ink sm:text-[28px]">{{ $info->name }}</h3>

            @if (filled($info->description))
                <div class="text-[15px] leading-relaxed text-ink">
                    <x-rich-editor readonly :value="$info->description" />
                </div>
            @else
                <p class="text-sm italic text-ink-3">Sem descrição cadastrada.</p>
            @endif
        </div>
    </article>

    {{-- ---------- Ficha ---------- --}}
    <aside class="flex flex-col gap-4 lg:sticky lg:top-[var(--split-top)]">
        <section class="{{ $panel }}">
            @if ($lead)
                <p class="text-xs font-bold uppercase tracking-[0.1em] text-ink-3">{{ $lead['name'] !== '' ? $lead['name'] : 'A partir de' }} · sócio</p>
                <p class="mt-1 font-mono text-[34px] font-semibold leading-none tracking-tight text-grena-ink">{{ $money($lead['associated']) }}</p>
                @if (filled($lead['not_associated']))
                    <p class="mt-2 text-sm text-ink-2">Não sócio: <span class="font-mono font-semibold text-ink">{{ $money($lead['not_associated']) }}</span></p>
                @endif
            @else
                <p class="text-sm text-ink-2">Sem valores cadastrados nesta versão. Consulte o responsável.</p>
            @endif

            @if (count($prices) > 1)
                <div class="mt-5 border-t border-line pt-4">
                    <h4 class="{{ $panelTitle }}">Todos os valores</h4>
                    <table class="w-full text-sm">
                        <thead class="sr-only">
                            <tr><th>Título</th><th>Sócio</th><th>Não sócio</th></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($prices as $price)
                                <tr>
                                    <td class="py-2 pr-3 text-ink">{{ $price['name'] !== '' ? $price['name'] : '—' }}</td>
                                    <td class="whitespace-nowrap py-2 pr-3 text-right font-mono font-semibold text-ink">{{ $money($price['associated']) }}</td>
                                    <td class="whitespace-nowrap py-2 text-right font-mono text-ink-3">{{ $money($price['not_associated']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-ink-3">Sócio à esquerda, não sócio à direita.</p>
                </div>
            @endif

            @if ($fields)
                <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-line pt-4">
                    @foreach ($fields as $label => $value)
                        <div class="min-w-0">
                            <dt class="text-xs font-bold text-ink-3">{{ $label }}</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-ink">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        @if ($schedules)
            <section class="{{ $panel }}">
                <h4 class="{{ $panelTitle }}">Dias e horários</h4>
                <ul class="divide-y divide-line">
                    @foreach ($schedules as $schedule)
                        <li class="flex items-center justify-between gap-3 py-2.5 text-sm">
                            <span class="font-semibold text-ink">{{ $schedule['day'] !== '' ? $schedule['day'] : '—' }}</span>
                            <span class="font-mono text-ink-2">
                                @if ($schedule['start'] !== '' || $schedule['end'] !== '')
                                    {{ $schedule['start'] !== '' ? $schedule['start'] : '—' }} às {{ $schedule['end'] !== '' ? $schedule['end'] : '—' }}
                                @else
                                    —
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($responsibles)
            <section class="{{ $panel }}">
                <h4 class="{{ $panelTitle }}">{{ count($responsibles) === 1 ? 'Responsável' : 'Responsáveis' }}</h4>
                <ul class="flex flex-col gap-2">
                    @foreach ($responsibles as $responsible)
                        @php
                            $digits = substr(preg_replace('/\D/', '', $responsible['contact']), 0, 11);
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-subtle px-4 py-3">
                            <span class="text-sm font-semibold text-ink">
                                {{ $responsible['name'] !== '' ? $responsible['name'] : 'Sem nome' }}
                            </span>
                            @if ($digits !== '')
                                <a href="https://wa.me/55{{ $digits }}" target="_blank" rel="noopener"
                                   class="inline-flex min-h-9 items-center gap-1.5 rounded-full bg-ok-soft px-3 font-mono text-sm font-semibold text-ok no-underline transition hover:-translate-y-px focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
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
    </aside>
</div>
