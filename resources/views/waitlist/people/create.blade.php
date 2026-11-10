{{--
    Incluir na fila de espera. Começa pelo CPF: quem já tem cadastro vai para
    a ficha (o controller redireciona); quem não tem ganha o formulário, com
    as turmas em que já entra.
--}}
<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title title="Incluir pessoa na fila" :back="route('waitlist.index')">
            Só não sócios. Sócio entra na fila pelo MultiClubes.
        </x-page-title>

        @include('partials.alerts')

        @if ($cpf === null)
            <form method="GET" action="{{ route('waitlist.people.create') }}" class="rounded-card bg-surface p-5 shadow-card sm:p-6">
                <x-input-label for="cpf" class="mb-1.5">CPF da pessoa <span class="text-danger">*</span></x-input-label>
                <div class="flex flex-col gap-2.5 sm:flex-row">
                    <x-text-input type="text" name="cpf" id="cpf" required inputmode="numeric" maxlength="14" class="font-mono sm:max-w-xs"
                        placeholder="000.000.000-00" value="{{ old('cpf') }}" autofocus />
                    <x-primary-button><x-icon name="arrow-right" /> Continuar</x-primary-button>
                </div>
                <p class="mt-1.5 text-xs text-ink-3">Se a pessoa já estiver cadastrada, o Lara abre a ficha dela para incluir em outras turmas.</p>
                <x-input-error :messages="$errors->get('cpf')" class="mt-1" />
            </form>
        @else
            <form method="POST" action="{{ route('waitlist.people.store') }}" class="flex flex-col gap-4">
                @csrf

                <section class="rounded-card bg-surface p-5 shadow-card sm:p-6">
                    <h3 class="mb-4 font-display text-base font-semibold text-ink">Dados da pessoa</h3>
                    @include('waitlist.people.partials.form', ['person' => null, 'cpf' => $cpf])
                </section>

                <section class="rounded-card bg-surface p-5 shadow-card sm:p-6">
                    <h3 class="font-display text-base font-semibold text-ink">Turmas</h3>
                    <p class="mb-4 mt-1 text-sm text-ink-2">Marque as turmas em que a pessoa entra na fila. Ela entra no fim de cada fila, com a data de hoje.</p>
                    @include('waitlist.people.partials.classes', ['classes' => $classes, 'selected' => old('classes', [])])
                </section>

                <div class="flex flex-wrap justify-end gap-2.5">
                    <x-secondary-button-a href="{{ route('waitlist.people.create') }}">Outro CPF</x-secondary-button-a>
                    <x-primary-button><x-icon name="check" /> Incluir na fila</x-primary-button>
                </div>
            </form>
        @endif
    </x-page>
</x-app-layout>
