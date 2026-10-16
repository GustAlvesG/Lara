<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Nova Equipe') }}
        </h2>
    </x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <form action="{{ route('placar.equipes.store') }}" method="POST">
            @csrf
            @include('placar.equipes.partials.form')

            @include('placar.partials.botoes-cadastro', ['voltar' => route('placar.equipes.index')])
        </form>
    </div>
</div>
</x-app-layout>
