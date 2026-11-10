{{--
    InfoClube › Fila de Espera — as turmas, com quantos não sócios esperam em
    cada uma e há quanto tempo o primeiro espera. A fila dos sócios é a do
    MultiClubes e não aparece aqui.
--}}
@php
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
            {{-- Uma turma por cartão (10/11/2026): quem espera em destaque; o cartão
                 abre a fila, o lápis edita a turma. --}}
            <ul class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($classes as $class)
                    <li class="group relative flex min-w-0 flex-col overflow-hidden rounded-card bg-surface shadow-card transition duration-200 hover:-translate-y-0.5 hover:shadow-pop focus-within:ring-4 focus-within:ring-grena-tint motion-reduce:hover:translate-y-0 {{ $class->active ? '' : 'opacity-70' }}">
                        <div class="flex items-start justify-between gap-3 bg-area-info px-5 pb-4 pt-5 text-area-info-ink">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-[0.1em] opacity-80">{{ $class->modality }}</p>
                                <h3 class="mt-1 font-display text-lg font-semibold leading-snug tracking-tight">
                                    <a href="{{ route('waitlist.classes.show', $class) }}" aria-label="Ver a fila de {{ $class->label() }}"
                                       class="no-underline after:absolute after:inset-0 after:content-[''] focus:outline-none">{{ $class->name }}</a>
                                </h3>
                            </div>
                            @if ($class->active)
                                <x-pill kind="ok">Ativa</x-pill>
                            @else
                                <x-pill kind="off">Inativa</x-pill>
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col gap-4 p-5">
                            <div class="flex items-end gap-3">
                                <p class="font-mono text-[40px] font-semibold leading-none tracking-tight text-ink">{{ $class->waiting_count }}</p>
                                <p class="pb-1 text-sm font-semibold text-ink-2">aguardando<br>
                                    <span class="font-normal text-ink-3">{{ (int) $class->waiting_count === 1 ? 'pessoa na fila' : 'pessoas na fila' }}</span>
                                </p>
                            </div>

                            @if ($class->notes)
                                <p class="line-clamp-2 text-sm text-ink-2">{{ $class->notes }}</p>
                            @endif

                            <div class="mt-auto flex items-center justify-between gap-3 border-t border-line pt-3">
                                <p class="text-xs text-ink-3">
                                    @if ($class->oldest_joined_at)
                                        Primeiro da fila desde
                                        <span class="font-mono font-semibold text-ink-2">{{ \Illuminate\Support\Carbon::parse($class->oldest_joined_at)->format('d/m/Y') }}</span>
                                    @else
                                        Ninguém aguardando.
                                    @endif
                                </p>
                                <a href="{{ route('waitlist.classes.edit', $class) }}" title="Editar a turma" aria-label="Editar {{ $class->label() }}"
                                   class="relative z-10 grid h-10 w-10 shrink-0 place-items-center rounded-full text-ink-3 transition hover:bg-subtle hover:text-ink focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                                    <x-icon name="pencil" />
                                </a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            {{ $classes->links() }}
        @endif
    </x-page>
</x-app-layout>
