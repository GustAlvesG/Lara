{{-- Mesas cativas de um mapa: o lugar guardado para uma matrícula (só um
     sócio desse título compra) até a liberação do evento, N dias antes do
     início. Cadastrar, tirar (uma ou todas) e o prazo do evento. --}}
@php
    use App\Services\Ticket\TicketCaptiveService;

    $select = 'mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink';
    $th = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $releaseAt = $event->captiveReleaseAt();
    $stateKind = fn (string $state) => match ($state) {
        TicketCaptiveService::STATE_BOUGHT => 'ok',
        TicketCaptiveService::STATE_WAITING => 'info',
        TicketCaptiveService::STATE_OTHER => 'danger',
        default => 'off',
    };
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Mesas cativas — ' . $map->name" :back="route('tickets.maps.index', $event)">
            {{ $event->title }}. A mesa cativa fica guardada para uma matrícula: só um sócio desse título compra o lugar (no site, logado; no balcão, emitindo para ele), em todos os dias do evento. Depois do prazo de liberação, quem não comprou perde a cativa e o lugar vai para a venda geral.
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('tickets.maps.edit', [$event, $map]) }}"><x-icon name="pencil" /> Desenho</x-secondary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section class="rounded-card bg-surface p-5 shadow-card">
            <h2 class="mb-1 text-sm font-black uppercase tracking-widest text-ink-3">Liberação (vale para todas as cativas do evento)</h2>
            <p class="mb-4 text-sm text-ink-2">
                @if ($releaseAt)
                    {{ $event->captivesReleased() ? 'Liberadas desde' : 'As cativas não compradas viram lugar comum em' }} <strong>{{ $releaseAt->format('d/m/Y \à\s H\h') }}</strong> ({{ $event->captive_release_days }} dia(s) antes do início, {{ $event->starts_at->format('d/m/Y') }}).
                @else
                    Sem prazo: as cativas só deixam de ser cativas quando forem tiradas aqui.
                @endif
            </p>
            <form method="POST" action="{{ route('tickets.maps.captives.release', [$event, $map]) }}" class="flex flex-wrap items-end gap-3">
                @csrf @method('PUT')
                <div><x-input-label for="release-days" value="Liberar quantos dias antes do evento" /><x-text-input type="number" id="release-days" name="captive_release_days" class="mt-1 w-40" min="0" max="365" :value="old('captive_release_days', $event->captive_release_days)" placeholder="sem prazo" /></div>
                <x-primary-button>Salvar prazo</x-primary-button>
            </form>
        </section>

        <section class="rounded-card bg-surface p-5 shadow-card">
            <h2 class="mb-4 text-sm font-black uppercase tracking-widest text-ink-3">Guardar lugar para uma matrícula</h2>
            @if ($free->isEmpty())
                <p class="text-sm text-ink-3">Todos os lugares do mapa já são cativos.</p>
            @else
                <form method="POST" action="{{ route('tickets.maps.captives.store', [$event, $map]) }}" class="grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                    @csrf
                    <div>
                        <x-input-label for="captive-seat" value="Lugar" />
                        <select id="captive-seat" name="seat_id" class="{{ $select }}" required>
                            @foreach ($free as $seat)
                                <option value="{{ $seat->id }}" @selected((int) old('seat_id') === $seat->id)>{{ $seat->label }}{{ $seat->category ? ' · ' . $seat->category : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><x-input-label for="captive-title" value="Matrícula" /><x-text-input id="captive-title" name="captive_title" class="mt-1" required maxlength="20" :value="old('captive_title')" /></div>
                    <div><x-input-label for="captive-name" value="Nome (se o cadastro do clube estiver fora do ar)" /><x-text-input id="captive-name" name="captive_name" class="mt-1" maxlength="255" :value="old('captive_name')" /></div>
                    <div class="sm:text-right"><x-primary-button>Guardar</x-primary-button></div>
                    <p class="text-xs text-ink-3 sm:col-span-4">O nome do titular vem do cadastro do clube (MultiClubes). Para trocar a matrícula de uma cativa, tire a cativa e guarde de novo.</p>
                </form>
            @endif
        </section>

        <section class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Cativas deste mapa ({{ count($rows) }})</h2>
                @if ($rows !== [])
                    <form method="POST" action="{{ route('tickets.maps.captives.clear', [$event, $map]) }}" onsubmit="return confirm('Tirar todas as mesas cativas deste mapa? Os lugares vão para a venda geral.')">
                        @csrf @method('DELETE')
                        <x-danger-button size="sm">Tirar todas</x-danger-button>
                    </form>
                @endif
            </div>
            @if ($rows === [])
                <x-empty-state icon="star">Nenhuma mesa cativa neste mapa.</x-empty-state>
            @else
                <div class="overflow-x-auto rounded-card bg-surface shadow-card">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-line bg-subtle"><th class="{{ $th }}">Lugar</th><th class="{{ $th }}">Matrícula</th><th class="{{ $th }}">Titular</th><th class="{{ $th }}">Situação</th><th class="{{ $th }}"></th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-ink">{{ $row['seat']->label }}@if ($row['seat']->category) <span class="text-xs font-normal text-ink-3">· {{ $row['seat']->category }}</span>@endif</td>
                                    <td class="px-4 py-3 font-mono">{{ $row['seat']->captive_title }}</td>
                                    <td class="px-4 py-3 text-ink-2">{{ $row['seat']->captive_name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <x-pill :kind="$stateKind($row['state'])">{{ TicketCaptiveService::STATE_LABELS[$row['state']] }}</x-pill>
                                        @foreach ($row['tickets'] as $ticket)
                                            <a href="{{ route('tickets.orders.show', $ticket->ticket_order_id) }}" class="block text-xs text-grena hover:underline">pedido #{{ $ticket->ticket_order_id }} · {{ $ticket->holder_name }}</a>
                                        @endforeach
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('tickets.maps.captives.destroy', [$event, $map, $row['seat']]) }}" onsubmit="return confirm('Tirar a cativa de {{ addslashes($row['seat']->label) }}? O lugar vai para a venda geral.')">
                                            @csrf @method('DELETE')
                                            <x-secondary-button type="submit" size="sm">Tirar cativa</x-secondary-button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </x-page>
</x-app-layout>
