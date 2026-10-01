<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Novo Freelancer') }}
        </h2>
    </x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        @include('freelancer.partials.import-card', [
            'action' => route('freelancers.import'),
            'templateRoute' => route('freelancers.import.template'),
            'columns' => $importColumns,
            'hint' => 'O CPF identifica o freelancer no sistema e não pode se repetir — nem dentro da planilha, nem entre já cadastrados.',
        ])

        <form action="{{ route('freelancers.store') }}" method="POST">
            @csrf
            @include('freelancer.freelancers.partials.form')

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('freelancers.index') }}" class="px-6 py-3 rounded-xl font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition">Cadastrar</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
