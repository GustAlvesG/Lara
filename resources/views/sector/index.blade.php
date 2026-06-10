<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Setores') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('roles-permission.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Setores</h1>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">Gerencie os setores e os acessos ao Banco de Horas.</p>
                </div>
            </div>

            <a href="{{ route('sectors.create') }}" class="inline-flex items-center px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Novo Setor
            </a>
        </div>

        @include('partials.alerts')

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 mb-8 border border-gray-100 dark:border-gray-700">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" id="sector-search-input" placeholder="Filtrar por nome do setor..."
                       class="w-full pl-10 pr-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 outline-none shadow-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white dark:placeholder-gray-500"
                       onkeyup="filterSectors()">
            </div>
        </div>

        @if($sectors->isEmpty())
            <div class="bg-white dark:bg-gray-800 p-12 text-center rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <p class="text-gray-500 dark:text-gray-400 text-lg font-medium">Nenhum setor cadastrado.</p>
                <a href="{{ route('sectors.create') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-[#A00001] text-white rounded-lg font-bold hover:bg-[#800000] transition">
                    Criar primeiro setor
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="sectors-container">
                @foreach($sectors as $sector)
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 flex flex-col sector-card overflow-hidden transition-all duration-300 hover:shadow-indigo-100 dark:hover:shadow-indigo-900/20">

                    <div class="p-6 border-b border-gray-50 dark:border-gray-700 flex items-center justify-between bg-gray-50/50 dark:bg-gray-700/50">
                        <div class="flex items-center gap-3">
                            <div class="p-3 bg-[#A00001] flex items-center justify-center rounded-lg shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-extrabold text-gray-900 dark:text-white uppercase tracking-tight sector-name">{{ $sector->name }}</h2>
                                @if($sector->description)
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $sector->description }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <a href="{{ route('sectors.show', $sector->id) }}" class="p-2 text-indigo-600 dark:text-indigo-400 hover:bg-white dark:hover:bg-gray-600 rounded-lg transition border border-transparent hover:border-indigo-100 dark:hover:border-indigo-900 hover:shadow-sm" title="Editar Setor">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </a>
                            <form method="POST" action="{{ route('sectors.destroy', $sector->id) }}"
                                  onsubmit="return confirm('Excluir o setor \'{{ $sector->name }}\' permanentemente?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-2 text-red-700 dark:text-red-400 hover:bg-white dark:hover:bg-gray-600 rounded-lg transition border border-transparent hover:border-red-100 dark:hover:border-red-900 hover:shadow-sm" title="Excluir Setor">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-500">
                            ID: #{{ $sector->id }}
                        </span>
                        <span class="text-xs font-medium text-gray-400 dark:text-gray-600">
                            {{ $sector->users_count }} membro(s)
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<script>
    function filterSectors() {
        const input = document.getElementById('sector-search-input');
        const filter = input.value.toUpperCase();
        const cards = document.querySelectorAll('.sector-card');

        cards.forEach(card => {
            const name = card.querySelector('.sector-name').textContent.toUpperCase();
            card.style.display = name.includes(filter) ? "" : "none";
        });
    }
</script>

</x-app-layout>
