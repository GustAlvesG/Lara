{{--
    Ficha do funcionário: situação (a mesma regra do Uber e da Frota),
    vínculo com usuário, cadastro, férias e afastamentos, excluir/restaurar.
--}}
@php
    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-3';
    $label = 'block text-xs font-bold uppercase tracking-wide text-ink-3';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title :title="$employee->name" :back="route('employees.index')">
            Matrícula <span class="font-mono">{{ $employee->employee_code }}</span> · CPF <span class="font-mono">{{ \App\Support\Cpf::format($employee->cpf) }}</span>
            <x-slot:actions>
                @include('employees.partials.situation')
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-3 sm:p-6">
            <div>
                <span class="{{ $label }}">Usuário do painel</span>
                @if ($employee->user)
                    @can(\App\Authorization\Permissions::USUARIOS_GERENCIAR)
                        <a href="{{ route('users.edit', $employee->user->id) }}" class="font-semibold text-grena-ink hover:underline">{{ $employee->user->name }}</a>
                    @else
                        <span class="font-semibold text-ink">{{ $employee->user->name }}</span>
                    @endcan
                    <p class="text-xs text-ink-3">Mesma matrícula. O setor do funcionário vale para ele também.</p>
                @else
                    <span class="text-ink-2">Nenhum</span>
                    <p class="text-xs text-ink-3">Nenhum usuário tem a matrícula {{ $employee->employee_code }}.</p>
                @endif
            </div>
            <div>
                <span class="{{ $label }}">Origem do cadastro</span>
                <span class="text-ink">{{ \App\Models\Employee::SOURCES[$employee->source] ?? $employee->source }}</span>
            </div>
            <div>
                <span class="{{ $label }}">Estrutura no espelho de ponto</span>
                <span class="text-ink">{{ $employee->department ?: '—' }}</span>
            </div>
        </section>

        @if ($employee->trashed())
            <section class="flex flex-wrap items-center justify-between gap-3 rounded-card bg-subtle p-5">
                <p class="text-sm text-ink-2">Este cadastro foi excluído e não vale para carro de aplicativo nem para a frota. Restaurar traz tudo de volta.</p>
                <form method="POST" action="{{ route('employees.restore', $employee) }}">
                    @csrf
                    <x-primary-button><x-icon name="check" /> Restaurar</x-primary-button>
                </form>
            </section>
        @else
            <form method="POST" action="{{ route('employees.update', $employee) }}" class="rounded-card bg-surface p-5 shadow-card sm:p-6">
                @csrf
                @method('PUT')

                <h3 class="mb-4 font-display text-base font-semibold text-ink">Cadastro</h3>

                @include('employees.partials.form')

                <div class="mt-6 flex flex-wrap justify-end gap-2.5 border-t border-line pt-5">
                    <x-primary-button><x-icon name="check" /> Salvar</x-primary-button>
                </div>
            </form>

            <section class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="border-b border-line p-5 sm:px-6">
                    <h3 class="font-display text-base font-semibold text-ink">Férias e afastamentos</h3>
                    <p class="mt-1 text-sm text-ink-2">
                        Enquanto um período vale, o funcionário não pede carro de aplicativo nem retira veículo da frota.
                        Deixar o fim em branco registra um período <strong>em aberto</strong>.
                    </p>
                </div>

                <form method="POST" action="{{ route('employees.absences.store', $employee) }}" class="grid grid-cols-1 items-end gap-4 border-b border-line p-5 sm:grid-cols-5 sm:px-6">
                    @csrf
                    <div>
                        <x-input-label for="type" value="Tipo" class="mb-1.5" />
                        <x-select-input name="type" id="type">
                            @foreach (\App\Models\EmployeeAbsence::TYPES as $value => $typeLabel)
                                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $typeLabel }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <div>
                        <x-input-label for="start_date" value="Início" class="mb-1.5" />
                        <x-text-input type="date" name="start_date" id="start_date" required value="{{ old('start_date') }}" />
                    </div>
                    <div>
                        <x-input-label for="end_date" value="Fim (opcional)" class="mb-1.5" />
                        <x-text-input type="date" name="end_date" id="end_date" value="{{ old('end_date') }}" />
                    </div>
                    <div>
                        <x-input-label for="notes" value="Observação" class="mb-1.5" />
                        <x-text-input type="text" name="notes" id="notes" maxlength="255" value="{{ old('notes') }}" />
                    </div>
                    <x-primary-button class="justify-center"><x-icon name="plus" /> Registrar</x-primary-button>

                    @foreach (['type', 'start_date', 'end_date', 'notes'] as $field)
                        <x-input-error :messages="$errors->get($field)" class="sm:col-span-5" />
                    @endforeach
                </form>

                @if ($employee->absences->isEmpty())
                    <p class="p-5 text-sm text-ink-3 sm:px-6">Nenhuma férias ou afastamento registrado.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-line">
                                    <th class="{{ $th }}">Tipo</th>
                                    <th class="{{ $th }}">Início</th>
                                    <th class="{{ $th }}">Fim</th>
                                    <th class="{{ $th }}">Observação</th>
                                    <th class="{{ $th }} text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($employee->absences as $absence)
                                    <tr class="border-b border-line last:border-0">
                                        <td class="{{ $td }} text-ink">
                                            {{ $absence->type_label }}
                                            @if ($absence->coversDate(now()))
                                                <x-pill kind="warn" class="ml-1">Vigente</x-pill>
                                            @endif
                                        </td>
                                        <td class="{{ $td }} font-mono text-ink-2">{{ $absence->start_date->format('d/m/Y') }}</td>
                                        <td class="{{ $td }} font-mono text-ink-2">{{ $absence->end_date?->format('d/m/Y') ?? 'Em aberto' }}</td>
                                        <td class="{{ $td }} text-ink-2">{{ $absence->notes ?: '—' }}</td>
                                        <td class="{{ $td }} text-right">
                                            <form method="POST" action="{{ route('employees.absences.destroy', [$employee, $absence]) }}" onsubmit="return confirm('Remover este registro?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Remover" aria-label="Remover {{ mb_strtolower($absence->type_label) }} de {{ $absence->start_date->format('d/m/Y') }}"
                                                        class="inline-grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line p-5">
                <p class="text-sm text-ink-2">
                    Excluir tira o funcionário das listas e da autenticação, mas guarda o histórico (banco de horas, viagens, pedidos de carro). Para quem saiu da empresa, prefira registrar a rescisão.
                </p>
                <form method="POST" action="{{ route('employees.destroy', $employee) }}" onsubmit="return confirm('Excluir {{ addslashes($employee->name) }}?');">
                    @csrf
                    @method('DELETE')
                    <x-danger-button><x-icon name="trash" /> Excluir</x-danger-button>
                </form>
            </section>
        @endif
    </x-page>
</x-app-layout>
