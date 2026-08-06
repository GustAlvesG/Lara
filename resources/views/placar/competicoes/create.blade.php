<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Nova Competição') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <form action="{{ route('placar.competicoes.store') }}" method="POST">
            @csrf
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">Dados da Competição</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome <span class="text-red-500">*</span></label>
                        <input type="text" name="nome" value="{{ old('nome') }}" required
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                        @error('nome')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Modalidade <span class="text-red-500">*</span></label>
                        <select name="modalidade_id" required class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            <option value="">Selecione</option>
                            @foreach($modalidades as $modalidade)
                                <option value="{{ $modalidade->id }}" @selected((string) old('modalidade_id') === (string) $modalidade->id)>{{ $modalidade->nome }}</option>
                            @endforeach
                        </select>
                        @error('modalidade_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Temporada <span class="text-red-500">*</span></label>
                        <input type="number" name="temporada" value="{{ old('temporada', now()->year) }}" required
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                        @error('temporada')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('placar.competicoes.index') }}" class="px-6 py-3 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold shadow-lg hover:bg-emerald-700 transition">Cadastrar</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
