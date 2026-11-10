{{--
    Ficha do não sócio: as filas em que aguarda (posição, ligações, registrar
    ligação, tirar da fila), a inclusão em outras turmas, o histórico das
    filas de que já saiu e o cadastro. Cada fila tem a âncora #fila-{id}, que
    é para onde a lista da turma e as ações voltam.
--}}
@php
    $label = 'block text-xs font-bold uppercase tracking-wide text-ink-3';
    $selectClass = 'h-11 w-full rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title :title="$person->name" :back="route('waitlist.people.index')">
            CPF <span class="font-mono">{{ \App\Support\Cpf::format($person->cpf) }}</span>
            @if ($person->age() !== null)
                · {{ $person->age() }} anos
            @endif
            · <span class="font-mono">{{ $person->phone }}</span>
            @if ($person->phone_alt)
                · <span class="font-mono">{{ $person->phone_alt }}</span>
            @endif
        </x-page-title>

        @include('partials.alerts')

        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                <p class="font-bold">Confira o que falta:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($person->guardian_name || $person->email || $person->notes)
            <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-2 sm:p-6">
                @if ($person->guardian_name)
                    <div>
                        <span class="{{ $label }}">Responsável</span>
                        <span class="text-ink">{{ $person->guardian_name }}</span>
                        @if ($person->guardian_phone)
                            <span class="block font-mono text-sm text-ink-2">{{ $person->guardian_phone }}</span>
                        @endif
                    </div>
                @endif
                @if ($person->email)
                    <div>
                        <span class="{{ $label }}">E-mail</span>
                        <span class="text-ink">{{ $person->email }}</span>
                    </div>
                @endif
                @if ($person->notes)
                    <div class="sm:col-span-2">
                        <span class="{{ $label }}">Observação</span>
                        <p class="whitespace-pre-line text-ink">{{ $person->notes }}</p>
                    </div>
                @endif
            </section>
        @endif

        {{-- Filas em que aguarda --}}
        <h3 class="mt-2 font-display text-base font-semibold text-ink">Aguardando em {{ $waiting->count() }} {{ $waiting->count() === 1 ? 'turma' : 'turmas' }}</h3>

        @forelse ($waiting as $entry)
            @php $position = $entry->position(); @endphp
            <section id="fila-{{ $entry->id }}" class="scroll-mt-24 overflow-hidden rounded-card bg-surface shadow-card">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line p-5 sm:px-6">
                    <div>
                        <a href="{{ route('waitlist.classes.show', $entry->waitlistClass) }}" class="font-display text-base font-semibold text-ink hover:text-grena-ink">{{ $entry->waitlistClass->label() }}</a>
                        <p class="mt-1 text-sm text-ink-2">
                            Na fila desde {{ $entry->joined_at->format('d/m/Y') }} ({{ $entry->daysWaiting() }} {{ $entry->daysWaiting() === 1 ? 'dia' : 'dias' }})
                            @if ($entry->creator) · inclusão por {{ $entry->creator->name }} @endif
                        </p>
                    </div>
                    <x-pill kind="info" :icon="false">{{ $position }}º da fila</x-pill>
                </div>

                <form method="POST" action="{{ route('waitlist.entries.contact', $entry) }}" class="grid grid-cols-1 items-end gap-3 border-b border-line p-5 sm:grid-cols-[minmax(0,16rem)_1fr_auto] sm:px-6">
                    @csrf
                    <div>
                        <x-input-label for="result-{{ $entry->id }}" value="Resultado da ligação" class="mb-1.5" />
                        <select name="result" id="result-{{ $entry->id }}" required class="{{ $selectClass }}">
                            <option value="">Escolha…</option>
                            @foreach (\App\Models\Waitlist\WaitlistContact::RESULTS as $value => $text)
                                <option value="{{ $value }}">{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="notes-{{ $entry->id }}" value="Observação" class="mb-1.5" />
                        <x-text-input type="text" name="notes" id="notes-{{ $entry->id }}" maxlength="255" placeholder="Ex.: ligar depois das 18h" />
                    </div>
                    <x-primary-button class="justify-center"><x-icon name="chat" /> Registrar ligação</x-primary-button>
                </form>

                @if ($entry->contacts->isNotEmpty())
                    <ul class="divide-y divide-line border-b border-line">
                        @foreach ($entry->contacts as $contact)
                            <li class="flex flex-wrap items-baseline justify-between gap-2 px-5 py-3 text-sm sm:px-6">
                                <span class="text-ink">
                                    <strong>{{ $contact->resultLabel() }}</strong>
                                    @if ($contact->notes) — {{ $contact->notes }} @endif
                                </span>
                                <span class="text-xs text-ink-3">
                                    {{ $contact->created_at->format('d/m/Y H:i') }}@if ($contact->user) · {{ $contact->user->name }}@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="border-b border-line px-5 py-3 text-sm text-ink-3 sm:px-6">Nenhuma ligação registrada.</p>
                @endif

                <details class="p-5 sm:px-6">
                    <summary class="cursor-pointer text-sm font-bold text-grena-ink">Tirar da fila (matriculou, desistiu ou remover)</summary>
                    <form method="POST" action="{{ route('waitlist.entries.close', $entry) }}" class="mt-4 grid grid-cols-1 items-end gap-3 sm:grid-cols-[minmax(0,14rem)_1fr_auto]">
                        @csrf
                        <div>
                            <x-input-label for="status-{{ $entry->id }}" value="Situação" class="mb-1.5" />
                            <select name="status" id="status-{{ $entry->id }}" required class="{{ $selectClass }}">
                                <option value="enrolled">Matriculado</option>
                                <option value="withdrawn">Desistiu</option>
                                <option value="removed">Removido (diga o motivo)</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="reason-{{ $entry->id }}" value="Motivo / observação" class="mb-1.5" />
                            <x-text-input type="text" name="close_reason" id="reason-{{ $entry->id }}" maxlength="255" />
                        </div>
                        <x-danger-button class="justify-center" onclick="return confirm('Tirar {{ addslashes($person->name) }} da fila desta turma?');">
                            <x-icon name="check" /> Confirmar
                        </x-danger-button>
                    </form>
                    <p class="mt-2 text-xs text-ink-3">Dá para reativar depois: a pessoa volta para a mesma posição.</p>
                </details>
            </section>
        @empty
            <x-empty-state icon="class">Não está aguardando em nenhuma turma.</x-empty-state>
        @endforelse

        {{-- Incluir em outras turmas --}}
        <form method="POST" action="{{ route('waitlist.people.enroll', $person) }}" class="rounded-card bg-surface p-5 shadow-card sm:p-6">
            @csrf
            <h3 class="font-display text-base font-semibold text-ink">Incluir em outras turmas</h3>
            <p class="mb-4 mt-1 text-sm text-ink-2">A pessoa entra no fim da fila de cada turma marcada, com a data de hoje.</p>
            @include('waitlist.people.partials.classes', ['classes' => $availableClasses, 'selected' => old('classes', [])])
            @if ($availableClasses->isNotEmpty())
                <div class="mt-5 flex justify-end">
                    <x-primary-button><x-icon name="plus" /> Incluir na fila</x-primary-button>
                </div>
            @endif
        </form>

        {{-- Filas de que já saiu --}}
        @if ($closed->isNotEmpty())
            <section class="overflow-hidden rounded-card bg-surface shadow-card">
                <h3 class="border-b border-line p-5 font-display text-base font-semibold text-ink sm:px-6">Saiu da fila</h3>
                <ul class="divide-y divide-line">
                    @foreach ($closed as $entry)
                        <li id="fila-{{ $entry->id }}" class="flex scroll-mt-24 flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                            <div class="text-sm">
                                <span class="font-semibold text-ink">{{ $entry->waitlistClass->label() }}</span>
                                <x-pill :kind="$entry->statusKind()" class="ml-1">{{ $entry->statusLabel() }}</x-pill>
                                <p class="mt-1 text-xs text-ink-3">
                                    Entrou {{ $entry->joined_at->format('d/m/Y') }} · saiu {{ $entry->closed_at?->format('d/m/Y') }}
                                    @if ($entry->closer) por {{ $entry->closer->name }} @endif
                                    @if ($entry->close_reason) · {{ $entry->close_reason }} @endif
                                    · {{ $entry->contacts->count() }} {{ $entry->contacts->count() === 1 ? 'ligação' : 'ligações' }}
                                </p>
                            </div>
                            <form method="POST" action="{{ route('waitlist.entries.reopen', $entry) }}" onsubmit="return confirm('Devolver a pessoa à fila desta turma, na posição original?');">
                                @csrf
                                <x-secondary-button type="submit"><x-icon name="history" /> Reativar</x-secondary-button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Cadastro --}}
        <details class="rounded-card bg-surface p-5 shadow-card sm:p-6" @if ($errors->hasAny(['cpf', 'name', 'birth_date', 'phone', 'phone_alt', 'email', 'guardian_name', 'guardian_phone', 'notes'])) open @endif>
            <summary class="cursor-pointer font-display text-base font-semibold text-ink">Editar cadastro</summary>
            <form method="POST" action="{{ route('waitlist.people.update', $person) }}" class="mt-5">
                @csrf
                @method('PUT')
                @include('waitlist.people.partials.form', ['person' => $person])
                <div class="mt-6 flex justify-end border-t border-line pt-5">
                    <x-primary-button><x-icon name="check" /> Salvar</x-primary-button>
                </div>
            </form>
        </details>

        <p class="text-xs text-ink-3">
            Cadastro criado em {{ $person->created_at?->format('d/m/Y') }}@if ($person->creator) por {{ $person->creator->name }}@endif.
        </p>
    </x-page>
</x-app-layout>
