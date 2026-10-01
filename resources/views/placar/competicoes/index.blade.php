<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Competições">
            Campeonatos e torneios — cada jogo pode (ou não) pertencer a uma.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('placar.competicoes.create') }}"><x-icon name="plus" /> Nova competição</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar name="busca" placeholder="Buscar competição, temporada ou modalidade" />

        <div class="overflow-hidden rounded-card bg-surface shadow-card">
            @if($competicoes->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-ink-2">Nenhuma competição cadastrada.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="border-b border-line text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">
                            <tr>
                                <th class="px-6 py-3">Nome</th>
                                <th class="px-6 py-3">Modalidade</th>
                                <th class="px-6 py-3">Temporada</th>
                                <th class="px-6 py-3">Jogos</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($competicoes as $competicao)
                            <tr class="hover:bg-subtle transition">
                                <td class="px-6 py-4 font-semibold text-ink">{{ $competicao->nome }}</td>
                                <td class="px-6 py-4 text-ink">{{ $competicao->modalidade->nome }}</td>
                                <td class="px-6 py-4 text-ink">{{ $competicao->temporada }}</td>
                                <td class="px-6 py-4 text-ink">{{ $competicao->jogos_count }}</td>
                                <td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('placar.competicoes.show', $competicao) }}" class="text-grena-ink hover:underline font-medium text-xs">Ver / Editar</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $competicoes->links() }}</div>
            @endif
        </div>
    </x-page>
</x-app-layout>
