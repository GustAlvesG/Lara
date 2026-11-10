{{--
    Os não sócios da fila de espera, com em quantas filas cada um aguarda.
    Busca por nome, CPF ou telefone.
--}}
@php
    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-3';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Fila de Espera">
            Pessoas cadastradas na fila de espera de não sócios.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('waitlist.people.create') }}"><x-icon name="user-plus" /> Incluir pessoa</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')
        @include('waitlist.partials.switch', ['current' => 'pessoas'])

        <x-search-bar placeholder="Buscar por nome, CPF ou telefone" />

        @if ($people->isEmpty())
            <x-empty-state icon="users">
                @if ($search !== '')
                    Ninguém corresponde a “{{ $search }}”.
                    <a href="{{ route('waitlist.people.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                @else
                    Ninguém cadastrado na fila de espera.
                    <a href="{{ route('waitlist.people.create') }}" class="font-bold text-grena-ink hover:underline">Incluir a primeira pessoa</a>.
                @endif
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Nome</th>
                                <th class="{{ $th }}">CPF</th>
                                <th class="{{ $th }}">Idade</th>
                                <th class="{{ $th }}">Telefone</th>
                                <th class="{{ $th }}">Aguardando em</th>
                                <th class="{{ $th }} text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($people as $person)
                                <tr class="border-b border-line transition last:border-0 hover:bg-subtle">
                                    <td class="{{ $td }}">
                                        <a href="{{ route('waitlist.people.show', $person) }}" class="font-semibold text-ink hover:text-grena-ink">{{ $person->name }}</a>
                                    </td>
                                    <td class="{{ $td }} whitespace-nowrap font-mono text-ink-2">{{ \App\Support\Cpf::format($person->cpf) }}</td>
                                    <td class="{{ $td }} text-ink-2">{{ $person->age() !== null ? $person->age() . ' anos' : '—' }}</td>
                                    <td class="{{ $td }} whitespace-nowrap font-mono text-ink-2">{{ $person->phone }}</td>
                                    <td class="{{ $td }} text-ink-2">
                                        {{ $person->waiting_count }} {{ $person->waiting_count === 1 ? 'turma' : 'turmas' }}
                                    </td>
                                    <td class="{{ $td }} whitespace-nowrap text-right">
                                        <a href="{{ route('waitlist.people.show', $person) }}" title="Abrir a ficha" aria-label="Abrir a ficha de {{ $person->name }}"
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

            {{ $people->links() }}
        @endif
    </x-page>
</x-app-layout>
