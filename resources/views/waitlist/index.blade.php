{{--
    InfoClube › Fila de Espera — as turmas, com quantos não sócios esperam em
    cada uma e há quanto tempo o primeiro espera. A fila dos sócios é a do
    MultiClubes e não aparece aqui.
--}}
@php
    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-3';
    $selectClass = 'h-11 w-full rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint sm:w-auto';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Fila de Espera">
            {{ $waitingPeople }} {{ $waitingPeople === 1 ? 'não sócio aguardando' : 'não sócios aguardando' }} vaga nas turmas.
            A fila dos sócios continua no MultiClubes e tem prioridade.
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('waitlist.classes.create') }}"><x-icon name="plus" /> Nova turma</x-secondary-button-a>
                <x-primary-button-a href="{{ route('waitlist.people.create') }}"><x-icon name="user-plus" /> Incluir pessoa</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')
        @include('waitlist.partials.switch', ['current' => 'turmas'])

        <x-search-bar placeholder="Buscar por modalidade ou turma" :filters="['situacao']">
            <x-slot:controls>
                <select name="situacao" class="{{ $selectClass }}" aria-label="Situação da turma">
                    @foreach ($situations as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['situacao'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-slot:controls>
        </x-search-bar>

        @if ($classes->isEmpty())
            <x-empty-state icon="class">
                @if (array_filter($filters))
                    Nenhuma turma com esses filtros.
                    <a href="{{ route('waitlist.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                @else
                    Nenhuma turma cadastrada.
                    <a href="{{ route('waitlist.classes.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar a primeira turma</a>.
                @endif
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Modalidade</th>
                                <th class="{{ $th }}">Turma</th>
                                <th class="{{ $th }}">Aguardando</th>
                                <th class="{{ $th }}">Primeiro da fila desde</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $th }} text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($classes as $class)
                                <tr class="border-b border-line transition last:border-0 hover:bg-subtle">
                                    <td class="{{ $td }} text-ink-2">{{ $class->modality }}</td>
                                    <td class="{{ $td }}">
                                        <a href="{{ route('waitlist.classes.show', $class) }}" class="font-semibold text-ink hover:text-grena-ink">{{ $class->name }}</a>
                                        @if ($class->notes)
                                            <p class="text-xs text-ink-3">{{ $class->notes }}</p>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} font-mono text-ink">{{ $class->waiting_count }}</td>
                                    <td class="{{ $td }} whitespace-nowrap text-ink-2">
                                        {{ $class->oldest_joined_at ? \Illuminate\Support\Carbon::parse($class->oldest_joined_at)->format('d/m/Y') : '—' }}
                                    </td>
                                    <td class="{{ $td }}">
                                        @if ($class->active)
                                            <x-pill kind="ok">Ativa</x-pill>
                                        @else
                                            <x-pill kind="off">Inativa</x-pill>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} whitespace-nowrap text-right">
                                        <a href="{{ route('waitlist.classes.show', $class) }}" title="Ver a fila" aria-label="Ver a fila de {{ $class->label() }}"
                                           class="inline-grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-subtle hover:text-ink">
                                            <x-icon name="list" />
                                        </a>
                                        <a href="{{ route('waitlist.classes.edit', $class) }}" title="Editar a turma" aria-label="Editar {{ $class->label() }}"
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

            {{ $classes->links() }}
        @endif
    </x-page>
</x-app-layout>
