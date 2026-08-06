<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Placar Clube — Competições') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Competições</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Campeonatos e torneios — cada jogo pode (ou não) pertencer a uma.</p>
            </div>

            <a href="{{ route('placar.competicoes.create') }}" class="inline-flex items-center px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold shadow-lg hover:bg-emerald-700 transition duration-150 transform hover:scale-[1.02]">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Nova Competição
            </a>
        </div>

        @include('partials.alerts')

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($competicoes->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Nenhuma competição cadastrada.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">Nome</th>
                                <th class="px-6 py-3">Modalidade</th>
                                <th class="px-6 py-3">Temporada</th>
                                <th class="px-6 py-3">Jogos</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($competicoes as $competicao)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">{{ $competicao->nome }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $competicao->modalidade->nome }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $competicao->temporada }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $competicao->jogos_count }}</td>
                                <td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('placar.competicoes.show', $competicao) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline font-medium text-xs">Ver / Editar</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $competicoes->links() }}</div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
