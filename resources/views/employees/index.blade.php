{{--
    Lista de funcionários (RH). Tabela, e não cartões: é cadastro, e o que se
    compara linha a linha é matrícula, setor e situação. Paginada, então a
    busca vai ao servidor.
--}}
@php
    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-3';
    $selectClass = 'h-11 w-full rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint sm:w-auto';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Funcionários">
            Quem trabalha no clube. É por este cadastro que o funcionário pede carro de aplicativo e retira veículo da frota.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('employees.create') }}"><x-icon name="plus" /> Novo funcionário</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar placeholder="Buscar por nome, matrícula ou CPF" :filters="['sector_id', 'situation']">
            <x-slot:controls>
                <select name="sector_id" class="{{ $selectClass }}" aria-label="Setor">
                    <option value="">Todos os setores</option>
                    @foreach ($sectors as $sectorId => $sectorName)
                        <option value="{{ $sectorId }}" @selected((string) ($filters['sector_id'] ?? '') === (string) $sectorId)>{{ $sectorName }}</option>
                    @endforeach
                </select>
                <select name="situation" class="{{ $selectClass }}" aria-label="Situação">
                    @foreach ($situations as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['situation'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-slot:controls>
        </x-search-bar>

        @if ($employees->isEmpty())
            <x-empty-state icon="users">
                @if (array_filter($filters))
                    Nenhum funcionário com esses filtros.
                    <a href="{{ route('employees.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                @else
                    Nenhum funcionário cadastrado.
                    <a href="{{ route('employees.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar ou importar uma planilha</a>.
                @endif
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Nome</th>
                                <th class="{{ $th }}">Matrícula</th>
                                <th class="{{ $th }}">CPF</th>
                                <th class="{{ $th }}">Setor</th>
                                <th class="{{ $th }}">Usuário</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $th }} text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employees as $employee)
                                <tr class="border-b border-line transition last:border-0 hover:bg-subtle">
                                    <td class="{{ $td }}">
                                        <a href="{{ route('employees.show', $employee) }}" class="font-semibold text-ink hover:text-grena-ink">{{ $employee->name }}</a>
                                        @if ($employee->position)
                                            <p class="text-xs text-ink-3">{{ $employee->position }}</p>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} font-mono text-ink">{{ $employee->employee_code }}</td>
                                    <td class="{{ $td }} whitespace-nowrap font-mono text-ink-2">{{ \App\Support\Cpf::format($employee->cpf) }}</td>
                                    <td class="{{ $td }} text-ink-2">{{ $employee->sector?->name ?? '—' }}</td>
                                    <td class="{{ $td }} text-ink-2">{{ $employee->user?->name ?? '—' }}</td>
                                    <td class="{{ $td }}">@include('employees.partials.situation')</td>
                                    <td class="{{ $td }} whitespace-nowrap text-right">
                                        <a href="{{ route('employees.show', $employee) }}" title="Abrir a ficha" aria-label="Abrir a ficha de {{ $employee->name }}"
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

            {{ $employees->links() }}
        @endif
    </x-page>
</x-app-layout>
