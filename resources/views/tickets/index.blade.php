{{-- Ingressos › Eventos: lista com busca pelo nome e filtro de situação. --}}
@php
    use App\Models\Ticket\TicketEvent;

    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $th = 'px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $kind = fn (string $status) => match ($status) {
        TicketEvent::STATUS_PUBLISHED => 'ok',
        TicketEvent::STATUS_CANCELLED => 'danger',
        default => 'off',
    };
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Ingressos">
            Eventos com venda de ingressos no site de locação: tipos de ingresso, lotes por quantidade e data, preço de sócio e de não sócio.
            <x-slot:actions>
                @can(\App\Authorization\Permissions::INGRESSOS_GERENCIAR)
                    <x-primary-button-a href="{{ route('tickets.create') }}"><x-icon name="plus" /> Novo evento</x-primary-button-a>
                @endcan
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar :filters="['status']" placeholder="Nome do evento">
            <x-slot:controls>
                <label for="filtro-situacao" class="sr-only">Situação</label>
                <select id="filtro-situacao" name="status" class="{{ $field }} w-full sm:w-52">
                    <option value="">Todas as situações</option>
                    @foreach (TicketEvent::STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-slot:controls>
        </x-search-bar>

        @if ($events->isEmpty())
            <x-empty-state icon="ticket">Nenhum evento encontrado.</x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="{{ $th }}">Evento</th>
                                <th class="{{ $th }}">Quando</th>
                                <th class="{{ $th }}">Vendas</th>
                                <th class="{{ $th }}">Vendidos</th>
                                <th class="{{ $th }} text-center">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($events as $event)
                                <tr>
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('tickets.show', $event) }}" class="font-semibold text-ink no-underline hover:underline">{{ $event->title }}</a>
                                        <p class="text-xs text-ink-3">{{ $event->venue ?: 'Local não informado' }} · {{ $event->types_count }} tipo(s) de ingresso</p>
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-ink-2">{{ $event->datesLabel() }}</td>
                                    <td class="px-5 py-3.5 text-ink-2">
                                        {{ $event->sales_starts_at->format('d/m H:i') }} a {{ $event->sales_ends_at->format('d/m H:i') }}
                                        @if ($event->salesOpen())<x-pill kind="ok">abertas</x-pill>@endif
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-ink-2">{{ $event->sold_count }}@if ($event->capacity) / {{ $event->capacity }}@endif</td>
                                    <td class="px-5 py-3.5 text-center"><x-pill :kind="$kind($event->status)">{{ TicketEvent::STATUS_LABELS[$event->status] ?? $event->status }}</x-pill></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            {{ $events->links() }}
        @endif
    </x-page>
</x-app-layout>
