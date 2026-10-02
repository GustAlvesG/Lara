<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Novo Jogador') }}
        </h2>
    </x-slot>

<div class="py-6">
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
                <a href="{{ route('placar.jogadores.index') }}" class="px-6 py-3 rounded-xl font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold shadow-card hover:bg-grena-hover transition">Cadastrar</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
