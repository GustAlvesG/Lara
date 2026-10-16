{{--
    Bot WhatsApp › Transferências e encerramentos. O diário dos atendimentos
    do O Lara: o que chegou a ele, o que saiu dele e o que foi encerrado, com
    o motivo (App\Services\PoliBot\AttendanceJournal). O resumo conta o
    período inteiro; clicar num motivo filtra a lista por ele.
--}}
@php
    use App\Models\BotAttendanceEvent as Evento;

    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $th = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $tipoPill = [Evento::RECEBIDA => 'info', Evento::ENVIADA => 'warn', Evento::ENCERRAMENTO => 'off'];
    $resultadoPill = [
        Evento::ATENDENTE => 'ok',
        Evento::FILA => 'ok',
        Evento::AGUARDANDO => 'warn',
        Evento::DEVOLVIDO => 'danger',
        Evento::FALHOU => 'danger',
    ];
    $nome = fn (?string $quem) => $quem === null ? null : ($times[$quem] ?? $quem);
    $filtro = fn (array $mudar) => route('poli-bot.events.index', array_filter([
        ...request()->only(['de', 'ate', 'tipo', 'motivo', 'q']),
        ...$mudar,
    ], 'filled'));
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Transferências e encerramentos">
            Por que cada conversa chegou ao O Lara, saiu dele ou foi encerrada. As ações do O Lara são anotadas quando
            ele as faz; as da Poli e dos atendentes, quando a Poli avisa.
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar name="q" :filters="['de', 'ate', 'tipo', 'motivo']" placeholder="Contato, telefone, atendente ou uuid">
            <x-slot:controls>
                <label for="de" class="sr-only">De</label>
                <input type="date" name="de" id="de" value="{{ $de->format('Y-m-d') }}" class="{{ $field }} w-full sm:w-44">

                <label for="ate" class="sr-only">Até</label>
                <input type="date" name="ate" id="ate" value="{{ $ate->format('Y-m-d') }}" class="{{ $field }} w-full sm:w-44">

                <label for="tipo" class="sr-only">Tipo</label>
                <select name="tipo" id="tipo" class="{{ $field }} w-full sm:w-48">
                    <option value="">Todos os tipos</option>
                    @foreach (Evento::KINDS as $chave => $rotulo)
                        <option value="{{ $chave }}" @selected($tipo === $chave)>{{ $rotulo }}s</option>
                    @endforeach
                </select>

                @if ($motivo)
                    <input type="hidden" name="motivo" value="{{ $motivo }}">
                @endif
            </x-slot:controls>
        </x-search-bar>

        {{-- Resumo do período, por motivo --}}
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-3" id="resumo">
            @foreach (Evento::KINDS as $kind => $rotulo)
                @php $linhas = $resumo[$kind] ?? collect(); @endphp
                <section class="rounded-card bg-surface p-4 shadow-card">
                    <div class="flex items-baseline justify-between gap-2">
                        <h2 class="text-sm font-bold text-ink">{{ $rotulo }}s</h2>
                        <p class="font-mono text-2xl font-semibold text-ink">{{ number_format($linhas->sum('total'), 0, ',', '.') }}</p>
                    </div>

                    @if ($linhas->isEmpty())
                        <p class="mt-2 text-xs text-ink-3">Nenhuma no período.</p>
                    @else
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($linhas as $l)
                                <li>
                                    <a href="{{ $filtro(['tipo' => $kind, 'motivo' => $l['reason'], 'page' => null]) }}"
                                       class="flex items-baseline justify-between gap-3 rounded-lg px-2 py-1 transition hover:bg-subtle {{ $motivo === $l['reason'] && $tipo === $kind ? 'bg-grena-tint text-grena-ink' : 'text-ink-2' }}">
                                        <span>{{ $l['label'] }}</span>
                                        <span class="font-mono font-semibold text-ink">{{ $l['total'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($kind === Evento::ENVIADA && $resultados->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-1.5 border-t border-line pt-3">
                            @foreach ($resultados as $resultado => $total)
                                <x-pill :kind="$resultadoPill[$resultado] ?? 'off'" :icon="false">{{ Evento::OUTCOMES[$resultado] ?? $resultado }}: {{ $total }}</x-pill>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        @if ($motivo)
            <p class="text-sm text-ink-2">
                Mostrando só <strong>{{ Evento::REASONS[$tipo][$motivo] ?? $motivo }}</strong>.
                <a href="{{ $filtro(['motivo' => null, 'page' => null]) }}" class="font-semibold text-grena-ink hover:underline">Ver todos os motivos</a>
            </p>
        @endif

        @if ($eventos->isEmpty())
            <x-empty-state icon="chat">
                Nada registrado com esses filtros. O registro começou com a versão que criou esta tela: dias anteriores
                aparecem vazios.
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Quando</th>
                                <th class="{{ $th }}">Contato</th>
                                <th class="{{ $th }}">Tipo e motivo</th>
                                <th class="{{ $th }}">Quem → para onde</th>
                                <th class="{{ $th }}">Resultado</th>
                            </tr>
                        </thead>
                        <tbody id="eventos" class="divide-y divide-line">
                            @foreach ($eventos as $e)
                                <tr class="align-top transition hover:bg-subtle" data-evento="{{ $e->id }}">
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-ink-2">
                                        {{ $e->occurred_at?->format('d/m/Y') }}<br>
                                        <span class="text-ink">{{ $e->occurred_at?->format('H:i:s') }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ $filtro(['q' => $e->contact_uuid, 'tipo' => null, 'motivo' => null, 'page' => null]) }}"
                                           class="font-semibold text-ink hover:text-grena-ink" title="Ver só este contato">
                                            {{ $e->contact_name ?: 'Sem nome' }}
                                        </a>
                                        @if ($e->contact_phone)
                                            <p class="font-mono text-xs text-ink-3">{{ $e->contact_phone }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-pill :kind="$tipoPill[$e->kind] ?? 'off'" :icon="false">{{ $e->kindLabel() }}</x-pill>
                                        <p class="mt-1 text-ink">{{ $e->reasonLabel() }}</p>
                                        @if ($e->detail)
                                            <p class="text-xs text-ink-2">{{ $e->detail }}</p>
                                        @endif
                                        @if ($e->flow_slug)
                                            <p class="font-mono text-xs text-ink-3">{{ $e->flow_slug }}{{ $e->step_key ? ' › ' . $e->step_key : '' }}</p>
                                        @endif
                                        @if ($e->poli_text)
                                            <p class="mt-1 text-xs italic text-ink-3">Poli: {{ $e->poli_text }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-ink-2">
                                        {{ $e->actor ?? '—' }}
                                        @if ($e->target)
                                            → <span class="text-ink">{{ $nome($e->target) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($e->outcome)
                                            <x-pill :kind="$resultadoPill[$e->outcome] ?? 'off'">{{ $e->outcomeLabel() }}</x-pill>
                                            @if ($e->outcome_detail)
                                                <p class="mt-1 text-xs text-ink-2">{{ $e->outcome_detail }}</p>
                                            @endif
                                        @else
                                            <span class="text-ink-3">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $eventos->links() }}
        @endif
    </x-page>
</x-app-layout>
