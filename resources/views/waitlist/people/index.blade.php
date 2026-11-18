{{--
    Os não sócios da fila de espera, com em quantas filas cada um aguarda.
    Busca por nome, CPF ou telefone. Filtro "Sem CPF" (18/11/2026) para a
    secretaria ir completando o CPF de quem veio das listas antigas.
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

        <x-search-bar placeholder="Buscar por nome, CPF ou telefone" :filters="['sem_cpf']" />

        @if ($withoutCpfCount > 0 || $withoutCpf)
            <nav class="flex flex-wrap gap-2" aria-label="CPF">
                @foreach (['' => 'Todos', '1' => 'Sem CPF (' . $withoutCpfCount . ')'] as $value => $text)
                    @php $current = ($withoutCpf ? '1' : '') === $value; @endphp
                    <a href="{{ route('waitlist.people.index', array_filter(['q' => $search, 'sem_cpf' => $value])) }}" @if ($current) aria-current="page" @endif
                       class="inline-flex h-10 items-center rounded-full px-4 text-sm font-bold transition {{ $current ? 'bg-grena text-white' : 'border border-line-strong bg-surface text-ink-2 hover:text-ink' }}">
                        {{ $text }}
                    </a>
                @endforeach
            </nav>
        @endif

        @if ($people->isEmpty())
            <x-empty-state icon="users">
                @if ($withoutCpf && $search === '')
                    Ninguém sem CPF.
                    <a href="{{ route('waitlist.people.index') }}" class="font-bold text-grena-ink hover:underline">Ver todos</a>.
                @elseif ($search !== '')
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
                                    <td class="{{ $td }} whitespace-nowrap font-mono text-ink-2">
                                        @if ($person->hasCpf())
                                            {{ \App\Support\Cpf::format($person->cpf) }}
                                        @else
                                            <x-pill kind="warn" :icon="false">Sem CPF</x-pill>
                                        @endif
                                    </td>
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
