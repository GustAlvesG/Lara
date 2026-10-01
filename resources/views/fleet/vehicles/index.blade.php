{{--
    Cadastro dos veículos da frota. Tabela (é cadastro: nome, placa, km,
    viagens lado a lado) com busca na página — a lista vem inteira.
--}}
@php
    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-3';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Veículos">
            Veículos cadastrados. A portaria enxerga apenas os ativos.

            <x-slot:actions>
                @can(\App\Authorization\Permissions::SIV_FROTA)
                    <x-secondary-button-a href="{{ route('fleet.index') }}"><x-icon name="car" /> Painel da frota</x-secondary-button-a>
                @endcan
                <x-primary-button-a href="{{ route('fleet.vehicles.create') }}"><x-icon name="plus" /> Novo veículo</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if ($vehicles->isEmpty())
            <x-empty-state icon="car">
                Nenhum veículo cadastrado.
                <a href="{{ route('fleet.vehicles.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar o primeiro</a>.
            </x-empty-state>
        @else
            <x-search-bar mode="client" target="#veiculos" placeholder="Buscar por nome, placa ou descrição" />

            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Nome</th>
                                <th class="{{ $th }}">Placa</th>
                                <th class="{{ $th }}">Descrição</th>
                                <th class="{{ $th }} text-right">Km atual</th>
                                <th class="{{ $th }} text-right">Viagens</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $th }} text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="veiculos">
                            @foreach ($vehicles as $vehicle)
                                <tr data-search="{{ $vehicle->name }} {{ $vehicle->plate }} {{ $vehicle->description }} {{ $vehicle->active ? 'ativo' : 'inativo' }}"
                                    class="border-b border-line transition last:border-0 hover:bg-subtle">
                                    <td class="{{ $td }} font-semibold text-ink">{{ $vehicle->name }}</td>
                                    <td class="{{ $td }}">
                                        @if ($vehicle->plate)
                                            <x-plate :plate="$vehicle->plate" size="sm" />
                                        @else
                                            <span class="text-ink-3">—</span>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} text-ink-2">{{ $vehicle->description ?: '—' }}</td>
                                    <td class="{{ $td }} text-right font-mono text-ink">
                                        {{ $vehicle->current_odometer !== null ? number_format($vehicle->current_odometer, 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="{{ $td }} text-right font-mono text-ink">{{ $vehicle->trips_count }}</td>
                                    <td class="{{ $td }}">
                                        <x-pill :kind="$vehicle->active ? 'ok' : 'off'">{{ $vehicle->active ? 'Ativo' : 'Inativo' }}</x-pill>
                                    </td>
                                    <td class="{{ $td }} whitespace-nowrap text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="{{ route('fleet.vehicles.edit', $vehicle) }}" title="Editar" aria-label="Editar {{ $vehicle->name }}"
                                               class="grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-subtle hover:text-ink">
                                                <x-icon name="pencil" />
                                            </a>
                                            <form method="POST" action="{{ route('fleet.vehicles.destroy', $vehicle) }}" onsubmit="return confirm('Excluir este veículo?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Excluir" aria-label="Excluir {{ $vehicle->name }}"
                                                        class="grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-page>
</x-app-layout>
