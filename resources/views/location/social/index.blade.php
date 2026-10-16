{{-- Reservas de espaço social, em qualquer estágio. A fila de análise
     (pré-reservas esperando contrato/aprovação) fica no topo, como atalho. --}}
@php
    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $pending = (int) ($counts['requested'] ?? 0);
    $awaiting = (int) ($counts['awaiting_payment'] ?? 0);
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Espaços sociais">
            Churrasqueiras e salões: pré-reserva, contrato, aprovação e pagamento pelo app.
            <x-slot:actions>
                @can(\App\Authorization\Permissions::RESERVAS_CONFIGURAR)
                    <x-secondary-button-a href="{{ route('social-booking.beverages.index') }}"><x-icon name="tag" /> Bebidas</x-secondary-button-a>
                @endcan
                <x-primary-button-a href="{{ route('social-booking.create') }}"><x-icon name="plus" /> Nova pré-reserva</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('social-booking.index', ['contact' => 'overdue']) }}" class="no-underline">
                <x-pill :kind="$overdueContact ? 'danger' : 'off'">{{ $overdueContact }} com contato atrasado</x-pill>
            </a>
            <a href="{{ route('social-booking.index', ['contact' => 'pending']) }}" class="no-underline">
                <x-pill :kind="$awaitingContact ? 'warn' : 'off'">{{ $awaitingContact }} aguardando contato</x-pill>
            </a>
            <a href="{{ route('social-booking.index', ['stage' => 'requested']) }}" class="no-underline">
                <x-pill kind="info">{{ $pending }} em análise</x-pill>
            </a>
            <a href="{{ route('social-booking.index', ['stage' => 'awaiting_payment']) }}" class="no-underline">
                <x-pill kind="warn">{{ $awaiting }} aguardando pagamento</x-pill>
            </a>
        </div>

        <x-search-bar name="member" :filters="['stage', 'contact', 'place_id', 'date_from', 'date_to']" placeholder="Sócio: nome ou CPF" label="Buscar sócio">
            <x-slot:controls>
                <label for="filtro-estagio" class="sr-only">Situação</label>
                <select id="filtro-estagio" name="stage" class="{{ $field }} w-full sm:w-52">
                    <option value="">Todas as situações</option>
                    @foreach ($stages as $value => $label)
                        <option value="{{ $value }}" @selected(request('stage') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label for="filtro-espaco" class="sr-only">Espaço</label>
                <select id="filtro-espaco" name="place_id" class="{{ $field }} w-full sm:w-56">
                    <option value="">Todos os espaços</option>
                    @foreach ($places as $place)
                        <option value="{{ $place->id }}" @selected((string) request('place_id') === (string) $place->id)>{{ optional($place->group)->name }} – {{ $place->name }}</option>
                    @endforeach
                </select>
                <label for="filtro-de" class="sr-only">Evento de</label>
                <input id="filtro-de" type="date" name="date_from" value="{{ request('date_from') }}" title="Evento de" class="{{ $field }} font-mono">
                <label for="filtro-ate" class="sr-only">Evento até</label>
                <input id="filtro-ate" type="date" name="date_to" value="{{ request('date_to') }}" title="Evento até" class="{{ $field }} font-mono">
            </x-slot:controls>
        </x-search-bar>

        @if ($bookings->isEmpty())
            <x-empty-state icon="calendar">
                Nenhuma reserva de espaço social com esses filtros.
                <a href="{{ route('social-booking.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">#</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Evento</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Sócio</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Espaço</th>
                                <th class="px-5 py-3.5 text-center text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Situação</th>
                                <th class="px-5 py-3.5 text-right text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Total</th>
                                <th class="px-5 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($bookings as $booking)
                                <tr class="hover:bg-subtle transition">
                                    <td class="px-5 py-3.5 font-mono text-xs text-ink-3">#{{ $booking->id }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <p class="font-semibold text-ink">{{ $booking->event_date->format('d/m/Y') }}{{ $booking->start_time ? ' · ' . substr($booking->start_time, 0, 5) : '' }}</p>
                                        <p class="text-xs text-ink-3">{{ $booking->event_type }}</p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-ink">{{ optional($booking->member)->name ?? 'Sócio não identificado' }}</p>
                                        <p class="font-mono text-xs text-ink-3">{{ optional($booking->member)->title }}</p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-ink">{{ optional($booking->place)->name ?? 'Espaço removido' }}</p>
                                        <p class="text-xs text-ink-3">{{ optional(optional($booking->place)->group)->name }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <x-pill :kind="$booking->stageKind()">{{ $booking->stageLabel() }}</x-pill>
                                        @if ($booking->contactOverdue())
                                            <p class="mt-1 text-[11px] font-bold text-danger">contato atrasado ({{ $booking->contact_due_at->format('d/m H:i') }})</p>
                                        @elseif ($booking->awaitingContact() && $booking->contact_due_at)
                                            <p class="mt-1 text-[11px] text-warn">contatar até {{ $booking->contact_due_at->format('d/m H:i') }}</p>
                                        @endif
                                        @if ($booking->stage === 'awaiting_payment' && $booking->payment_due_at)
                                            <p class="mt-1 text-[11px] text-ink-3">até {{ $booking->payment_due_at->format('d/m H:i') }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-semibold text-ink">R$ {{ number_format((float) $booking->total, 2, ',', '.') }}</td>
                                    <td class="px-5 py-3.5 text-right">
                                        <x-secondary-button-a size="sm" href="{{ route('social-booking.show', $booking) }}">Abrir</x-secondary-button-a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($bookings->hasPages())
                {{ $bookings->links() }}
            @endif
        @endif
    </x-page>
</x-app-layout>
