<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title title="Novo funcionário" :back="route('employees.index')">
            Um por vez no formulário, ou vários de uma vez pela planilha.
        </x-page-title>

        @include('partials.alerts')

        {{-- Mesmo bloco de importação dos freelancers e do Placar. --}}
        @include('freelancer.partials.import-card', [
            'action' => route('employees.import'),
            'templateRoute' => route('employees.import.template'),
            'columns' => $importColumns,
            'hint' => 'A matrícula é a chave: matrícula nova cadastra, matrícula que já existe atualiza o funcionário (célula opcional em branco não apaga nada). O setor é escrito pelo nome e precisa já existir na tela de Setores. Rescisão, férias e afastamento são registrados na ficha de cada um.',
        ])

        <form method="POST" action="{{ route('employees.store') }}" class="rounded-card bg-surface p-5 shadow-card sm:p-6">
            @csrf

            @include('employees.partials.form')

            <div class="mt-6 flex flex-wrap justify-end gap-2.5 border-t border-line pt-5">
                <x-secondary-button-a href="{{ route('employees.index') }}">Cancelar</x-secondary-button-a>
                <x-primary-button><x-icon name="check" /> Cadastrar</x-primary-button>
            </div>
        </form>
    </x-page>
</x-app-layout>
