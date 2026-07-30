@php
    /**
     * Renderização somente leitura de uma versão ($info), em duas colunas:
     *   esquerda -> imagem, nome e descrição
     *   direita  -> tags e demais campos
     *
     * Usada pela tela de detalhe e por cada versão do histórico — por isso o
     * nome aparece aqui e não só no cabeçalho da página.
     */
    $fallbackImage = 'https://placehold.co/600x400/7d0400/ffffff?text=' . urlencode($info->name);
    $imageUrl = $info->image ? asset('images/' . $info->image) : $fallbackImage;

    $attributes = array_filter([
        'Status' => $info->status,
        'Vagas' => $info->slots,
        'Localização' => $info->location,
        'Taxa de Matrícula' => filled($info->fee) ? 'R$ ' . number_format((float) $info->fee, 2, ',', '.') : null,
    ], fn ($value) => filled($value));

    $prices = $info->price_rows ?? [];
    $schedules = $info->schedule_rows ?? [];
    $responsibles = $info->responsible_rows ?? [];

    $money = fn ($value) => filled($value) ? 'R$ ' . number_format((float) $value, 2, ',', '.') : '—';
    $cardClass = 'rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800';
@endphp

{{-- 10 colunas: 6/4 = 60% / 40%. --}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-10">

    {{-- ---------- Coluna esquerda (60%) ---------- --}}
    <div class="space-y-6 lg:col-span-6">
        <section class="{{ $cardClass }}">
            <img src="{{ $imageUrl }}" alt="Imagem de {{ $info->name }}"
                 onerror="this.onerror=null;this.src='{{ $fallbackImage }}';"
                 class="mb-4 h-56 w-full rounded-lg border border-gray-200 object-cover dark:border-gray-700">

            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $info->name }}</h3>

            <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                <h4 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Descrição
                </h4>
                @if (filled($info->description))
                    <x-rich-editor readonly :value="$info->description" />
                @else
                    <p class="text-sm italic text-gray-500 dark:text-gray-400">Sem descrição cadastrada.</p>
                @endif
            </div>
        </section>
    </div>

    {{-- ---------- Coluna direita (40%) ---------- --}}
    <div class="space-y-6 lg:col-span-4">

        <section class="{{ $cardClass }}">
            <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Tags
            </h4>
            @if ($info->tags->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($info->tags as $tag)
                        <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300">
                            #{{ $tag->name }}
                        </span>
                    @endforeach
                </div>
            @else
                <p class="text-sm italic text-gray-500 dark:text-gray-400">Sem tags nesta versão.</p>
            @endif

            @if ($attributes)
                <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                    @foreach ($attributes as $label => $value)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        @if ($prices)
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h4 class="border-b border-gray-200 px-6 py-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Preços
                </h4>
                <div class="overflow-x-auto">
                    <table class="info-detail-table">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Sócio</th>
                                <th>Não Sócio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($prices as $price)
                                <tr>
                                    <td class="text-gray-900 dark:text-gray-100">{{ $price['name'] !== '' ? $price['name'] : '—' }}</td>
                                    <td class="font-semibold text-gray-900 dark:text-gray-100">{{ $money($price['associated']) }}</td>
                                    <td class="text-gray-700 dark:text-gray-300">{{ $money($price['not_associated']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($schedules)
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h4 class="border-b border-gray-200 px-6 py-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Dias e Horários
                </h4>
                <div class="overflow-x-auto">
                    <table class="info-detail-table">
                        <thead>
                            <tr>
                                <th>Dia</th>
                                <th>Horário</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($schedules as $schedule)
                                <tr>
                                    <td class="text-gray-900 dark:text-gray-100">{{ $schedule['day'] !== '' ? $schedule['day'] : '—' }}</td>
                                    <td class="text-gray-700 dark:text-gray-300">
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
            <section class="{{ $cardClass }}">
                <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Responsáveis
                </h4>
                <ul class="space-y-2">
                    @foreach ($responsibles as $responsible)
                        @php
                            $digits = substr(preg_replace('/\D/', '', $responsible['contact']), 0, 11);
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 px-4 py-2 dark:bg-gray-700/40">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $responsible['name'] !== '' ? $responsible['name'] : 'Sem nome' }}
                            </span>
                            @if ($digits !== '')
                                <a href="https://wa.me/55{{ $digits }}" target="_blank" rel="noopener"
                                   class="text-sm font-medium text-[#7E1417] hover:underline dark:text-red-400">
                                    {{ $responsible['contact'] }}
                                </a>
                            @elseif ($responsible['contact'] !== '')
                                <span class="text-sm text-gray-600 dark:text-gray-300">{{ $responsible['contact'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</div>
