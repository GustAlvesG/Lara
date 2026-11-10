{{--
    A fila de uma turma: quem aguarda, na ordem de chegada (a posição não muda
    por ligação sem resposta), com a última ligação. As ações ficam na ficha
    da pessoa. A aba "Saíram da fila" mostra os encerrados.
--}}
@php
    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-3';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="$waitlistClass->label()" :back="route('waitlist.index')">
            {{ $waitingCount }} {{ $waitingCount === 1 ? 'pessoa aguardando' : 'pessoas aguardando' }}.
            @unless ($waitlistClass->active)
                <x-pill kind="off" class="ml-1">Turma inativa</x-pill>
            @endunless
            @if ($waitlistClass->notes)
                <span class="block">{{ $waitlistClass->notes }}</span>
            @endif
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('waitlist.classes.edit', $waitlistClass) }}"><x-icon name="pencil" /> Editar turma</x-secondary-button-a>
                @if ($waitlistClass->active)
                    <x-primary-button-a href="{{ route('waitlist.people.create') }}"><x-icon name="user-plus" /> Incluir pessoa</x-primary-button-a>
                @endif
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <p class="rounded-2xl bg-warn-soft px-5 py-3 text-sm text-warn">
            Antes de ligar para um não sócio, confira no MultiClubes se há sócio na fila desta turma: o sócio tem prioridade.
        </p>

        <nav class="flex flex-wrap gap-2" aria-label="Situação">
            @foreach (['' => 'Aguardando', 'encerrados' => 'Saíram da fila'] as $value => $label)
                @php $current = ($closed ? 'encerrados' : '') === $value; @endphp
                <a href="{{ route('waitlist.classes.show', array_filter([$waitlistClass, 'situacao' => $value])) }}" @if ($current) aria-current="page" @endif
                   class="inline-flex h-10 items-center rounded-full px-4 text-sm font-bold transition {{ $current ? 'bg-grena text-white' : 'border border-line-strong bg-surface text-ink-2 hover:text-ink' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        @if ($entries->isEmpty())
            <x-empty-state icon="users">
                {{ $closed ? 'Ninguém saiu da fila desta turma ainda.' : 'Ninguém aguardando nesta turma.' }}
            </x-empty-state>
        @elseif (!$closed)
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Posição</th>
                                <th class="{{ $th }}">Nome</th>
                                <th class="{{ $th }}">Idade</th>
                                <th class="{{ $th }}">Telefone</th>
                                <th class="{{ $th }}">Na fila desde</th>
                                <th class="{{ $th }}">Última ligação</th>
                                <th class="{{ $th }} text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $index => $entry)
                                <tr class="border-b border-line transition last:border-0 hover:bg-subtle">
                                    <td class="{{ $td }} font-mono font-bold text-ink">{{ $index + 1 }}º</td>
                                    <td class="{{ $td }}">
                                        <a href="{{ route('waitlist.people.show', $entry->person) }}#fila-{{ $entry->id }}" class="font-semibold text-ink hover:text-grena-ink">{{ $entry->person->name }}</a>
                                        @if ($entry->person->isMinor() && $entry->person->guardian_name)
                                            <p class="text-xs text-ink-3">Responsável: {{ $entry->person->guardian_name }}</p>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} text-ink-2">{{ $entry->person->age() !== null ? $entry->person->age() . ' anos' : '—' }}</td>
                                    <td class="{{ $td }} whitespace-nowrap font-mono text-ink-2">{{ $entry->person->phone }}</td>
                                    <td class="{{ $td }} whitespace-nowrap text-ink-2">
                                        {{ $entry->joined_at->format('d/m/Y') }}
                                        <p class="text-xs text-ink-3">{{ $entry->daysWaiting() }} {{ $entry->daysWaiting() === 1 ? 'dia' : 'dias' }}</p>
                                    </td>
                                    <td class="{{ $td }} text-ink-2">
                                        @if ($entry->latestContact)
                                            {{ $entry->latestContact->resultLabel() }}
                                            <p class="text-xs text-ink-3">{{ $entry->latestContact->created_at->format('d/m/Y H:i') }}</p>
                                        @else
                                            <span class="text-ink-3">Nenhuma</span>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} whitespace-nowrap text-right">
                                        <a href="{{ route('waitlist.people.show', $entry->person) }}#fila-{{ $entry->id }}" title="Registrar ligação ou tirar da fila"
                                           aria-label="Abrir a ficha de {{ $entry->person->name }}"
                                           class="inline-grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-subtle hover:text-ink">
                                            <x-icon name="pencil" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Nome</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $th }}">Entrou</th>
                                <th class="{{ $th }}">Saiu</th>
                                <th class="{{ $th }}">Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr class="border-b border-line transition last:border-0 hover:bg-subtle">
                                    <td class="{{ $td }}">
                                        <a href="{{ route('waitlist.people.show', $entry->person) }}#fila-{{ $entry->id }}" class="font-semibold text-ink hover:text-grena-ink">{{ $entry->person->name }}</a>
                                    </td>
                                    <td class="{{ $td }}"><x-pill :kind="$entry->statusKind()">{{ $entry->statusLabel() }}</x-pill></td>
                                    <td class="{{ $td }} whitespace-nowrap text-ink-2">{{ $entry->joined_at->format('d/m/Y') }}</td>
                                    <td class="{{ $td }} whitespace-nowrap text-ink-2">
                                        {{ $entry->closed_at?->format('d/m/Y') ?? '—' }}
                                        @if ($entry->closer)
                                            <p class="text-xs text-ink-3">por {{ $entry->closer->name }}</p>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} text-ink-2">{{ $entry->close_reason ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $entries->links() }}
        @endif
    </x-page>
</x-app-layout>
