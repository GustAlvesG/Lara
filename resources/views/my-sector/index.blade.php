<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Meu setor') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Meus setores</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Você coordena mais de um setor. Escolha qual equipe quer ver.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($sectors as $sector)
                <a href="{{ route('my-sector.show', $sector) }}"
                   class="block bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 hover:shadow-2xl transition">
                    <h2 class="text-xl font-extrabold text-gray-900 dark:text-white">{{ $sector->name }}</h2>
                    @if($sector->description)
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $sector->description }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
</x-app-layout>
