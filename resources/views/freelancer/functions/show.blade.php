<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Função') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <form action="{{ route('freelancer-functions.update', $function) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <a href="{{ route('freelancer-functions.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </a>
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $function->name }}</h1>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">Edite os dados da função.</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                            @if($function->createdBy)Cadastrado por {{ $function->createdBy->name }}@endif
                            @if($function->updatedBy && $function->updated_by !== $function->created_by) · Atualizado por {{ $function->updatedBy->name }}@endif
                        </p>
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                    Salvar Alterações
                </button>
            </div>

            @include('freelancer.functions.partials.form')
        </form>

        <div class="flex justify-end">
            <form method="POST" action="{{ route('freelancer-functions.destroy', $function) }}"
                  onsubmit="return confirm('Excluir a função \'{{ $function->name }}\'?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition">
                    Excluir Função
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
