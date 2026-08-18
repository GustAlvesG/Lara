<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Novo Jogador') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        {{-- Bloco genérico de importação, o mesmo usado pelo cadastro de
             freelancers — não vale duplicar 90 linhas de markup. --}}
        @include('freelancer.partials.import-card', [
            'action' => route('placar.jogadores.import'),
            'templateRoute' => route('placar.jogadores.import.template'),
            'columns' => $importColumns,
            'hint' => 'A equipe precisa já estar cadastrada, e é escrita pelo nome (não pelo id). Preenchendo "Categoria do time", o jogador já entra no elenco daquele time — que é criado se ainda não existir.',
        ])

        <form action="{{ route('placar.jogadores.store') }}" method="POST">
            @csrf
            @include('placar.jogadores.partials.form')

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('placar.jogadores.index') }}" class="px-6 py-3 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold shadow-lg hover:bg-emerald-700 transition">Cadastrar</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
