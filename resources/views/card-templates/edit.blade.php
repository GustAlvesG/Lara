<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Editar Modelo de Carteirinha') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight mb-1">Editar Modelo de Carteirinha</h1>
        <p class="text-gray-500 dark:text-gray-400 font-medium mb-8">{{ $template->name }}</p>

        @include('partials.alerts')

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 border border-gray-100 dark:border-gray-700">
            @include('card-templates.partials.form')
        </div>
    </div>
</div>
</x-app-layout>
