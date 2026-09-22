<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Novo Modelo de Documento') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <form action="{{ route('signature-templates.store') }}" method="POST">
            @csrf
            @include('signature.templates.partials.form', ['template' => null])

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('signature-templates.index') }}" class="px-6 py-3 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition">Criar modelo</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
