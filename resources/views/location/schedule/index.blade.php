{{-- Todos os agendamentos, em qualquer status. Busca por sócio (nome ou CPF)
     no servidor, junto dos filtros de status, local e período. --}}
@php
    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Todos os agendamentos" :back="route('schedule.index')">
            Reservas em qualquer status, inclusive canceladas e expiradas.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('schedule.index') }}"><x-icon name="plus" /> Novo agendamento</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar name="member" :filters="['status_id', 'place_id', 'date_from', 'date_to']" placeholder="Sócio: nome ou CPF" label="Buscar sócio">
            <x-slot:controls>
                <label for="filtro-status" class="sr-only">Status</label>
                <select id="filtro-status" name="status_id" class="{{ $field }} w-full sm:w-44">
                    <option value="">Todos os status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->id }}" @selected((string) request('status_id') === (string) $status->id)>{{ $status->portuguese ?? $status->name }}</option>
                    @endforeach
                </select>
                <label for="filtro-local" class="sr-only">Local</label>
                <select id="filtro-local" name="place_id" class="{{ $field }} w-full sm:w-56">
                    <option value="">Todos os locais</option>
                    @foreach($places as $place)
                        <option value="{{ $place->id }}" @selected((string) request('place_id') === (string) $place->id)>{{ optional($place->group)->name }} – {{ $place->name }}</option>
                    @endforeach
                </select>
                <label for="filtro-de" class="sr-only">De</label>
                <input id="filtro-de" type="date" name="date_from" value="{{ request('date_from') }}" title="De" class="{{ $field }} font-mono">
                <label for="filtro-ate" class="sr-only">Até</label>
                <input id="filtro-ate" type="date" name="date_to" value="{{ request('date_to') }}" title="Até" class="{{ $field }} font-mono">
            </x-slot:controls>
        </x-search-bar>

        <!-- TABELA -->
        @if($schedules->isEmpty())
            <x-empty-state icon="calendar">
                Nenhum agendamento encontrado com esses filtros.
                <a href="{{ route('schedule.list') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
            </x-empty-state>
        @else
        <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">#</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Sócio</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Local</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Data / Horário</th>
                                <th class="px-5 py-3.5 text-center text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Status</th>
                                <th class="px-5 py-3.5 text-right text-[11px] font-bold text-ink-3 uppercase tracking-[0.08em]">Valor</th>
                                <th class="px-5 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($schedules as $schedule)
                                @php
                                    [$statusKind, $statusText] = match((int) $schedule->status_id) {
                                        1 => ['ok', 'Confirmada'],
                                        3 => ['warn', 'Pendente'],
                                        0 => ['danger', 'Cancelada'],
                                        4 => ['off', 'Expirada'],
                                        10 => ['off', 'Antiga'],
                                        default => ['off', $schedule->status->portuguese ?? '?'],
                                    };
                                @endphp
                                <tr class="hover:bg-subtle transition">
                                    <td class="px-5 py-3.5 font-mono text-xs text-ink-3">#{{ $schedule->id }}</td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-ink">{{ optional($schedule->member)->name ?? 'Sócio não identificado' }}</p>
                                        <p class="font-mono text-xs text-ink-3">{{ optional($schedule->member)->cpf }}</p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-ink">{{ optional($schedule->place)->name ?? 'Local removido' }}</p>
                                        <p class="text-xs text-ink-3">{{ optional(optional($schedule->place)->group)->name }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <p class="font-semibold text-ink">{{ \Carbon\Carbon::parse($schedule->start_schedule)->format('d/m/Y') }}</p>
                                        <p class="text-xs text-ink-3">
                                            {{ \Carbon\Carbon::parse($schedule->start_schedule)->format('H:i') }}
                                            às
                                            {{ \Carbon\Carbon::parse($schedule->end_schedule)->format('H:i') }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <x-pill :kind="$statusKind">{{ $statusText }}</x-pill>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-semibold text-ink">
                                        R$ {{ number_format($schedule->price ?? 0, 2, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <x-secondary-button-a size="sm" href="{{ route('schedule.show', $schedule->id) }}">Detalhes</x-secondary-button-a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

        </div>

        @if($schedules->hasPages())
            {{ $schedules->links() }}
        @endif
        @endif
    </x-page>

</x-app-layout>
