<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Nova Função') }}
        </h2>
    </x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <form action="{{ route('freelancer-functions.store') }}" method="POST">
            @csrf
            @include('freelancer.functions.partials.form')

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('freelancer-functions.index') }}" class="px-6 py-3 rounded-xl font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition">Cadastrar</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
