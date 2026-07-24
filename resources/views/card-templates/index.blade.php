<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Modelos de Carteirinha') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Modelos de Carteirinha</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Modelos usados para emitir carteirinhas em cartão PVC.</p>
            </div>

            <a href="{{ route('card-templates.create') }}" class="inline-flex items-center px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Novo Modelo
            </a>
        </div>

        @include('partials.alerts')

        @if($templates->isEmpty())
            <div class="bg-white dark:bg-gray-800 p-12 text-center rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
                <p class="text-gray-500 dark:text-gray-400 text-lg font-medium">Nenhum modelo cadastrado.</p>
                <a href="{{ route('card-templates.create') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-[#A00001] text-white rounded-lg font-bold hover:bg-[#800000] transition">
                    Cadastrar primeiro modelo
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($templates as $template)
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="grid grid-cols-2 gap-px bg-gray-200 dark:bg-gray-700">
                        <div>
                            <div class="aspect-[54/85.6] max-h-72 bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                <img src="{{ $template->frontImageUrl() }}" alt="Frente de {{ $template->name }}" class="w-full h-full object-cover">
                            </div>
                            <p class="py-1 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800">Frente</p>
                        </div>
                        <div>
                            <div class="aspect-[54/85.6] max-h-72 bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                <img src="{{ $template->backImageUrl() }}" alt="Verso de {{ $template->name }}" class="w-full h-full object-cover">
                            </div>
                            <p class="py-1 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800">Verso</p>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-2">
                            <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $template->name }}</h2>
                            @if($template->is_active)
                                <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-700">Ativo</span>
                            @else
                                <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-600">Inativo</span>
                            @endif
                        </div>

                        <div class="mt-4 flex gap-2">
                            <a href="{{ route('card-templates.edit', $template) }}" class="flex-1 text-center px-3 py-2 text-indigo-700 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 rounded-lg font-bold text-sm transition">
                                Editar
                            </a>
                            <form method="POST" action="{{ route('card-templates.destroy', $template) }}"
                                  onsubmit="return confirm('Excluir o modelo \'{{ $template->name }}\' permanentemente?')" class="flex-1">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full px-3 py-2 text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 rounded-lg font-bold text-sm transition">
                                    Excluir
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
</x-app-layout>
