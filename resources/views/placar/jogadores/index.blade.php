<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Placar Clube — Jogadores') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Jogadores</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Cada jogador é de uma equipe e uma modalidade; pode estar em vários times dessa equipe.</p>
            </div>

            <a href="{{ route('placar.jogadores.create') }}" class="inline-flex items-center px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold shadow-lg hover:bg-emerald-700 transition duration-150 transform hover:scale-[1.02]">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Novo Jogador
            </a>
        </div>

        @include('partials.alerts')

        <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
            <input type="text" name="busca" value="{{ request('busca') }}" placeholder="Buscar por nome ou documento..."
                class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
            <select name="equipe_id" class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <option value="">Todas as equipes</option>
                @foreach($equipes as $equipeFiltro)
                    <option value="{{ $equipeFiltro->id }}" @selected((string) request('equipe_id') === (string) $equipeFiltro->id)>{{ $equipeFiltro->nome }}</option>
                @endforeach
            </select>
            <select name="modalidade" class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <option value="">Todas as modalidades</option>
                @foreach($modalidades as $modalidadeFiltro)
                    <option value="{{ $modalidadeFiltro->slug }}" @selected(request('modalidade') === $modalidadeFiltro->slug)>{{ $modalidadeFiltro->nome }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input type="checkbox" name="criado_em_campo" value="1" @checked(request('criado_em_campo'))
                    class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                Só criados em campo
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input type="checkbox" name="pendentes" value="1" @checked(request('pendentes'))
                    class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                Só pendentes de revisão
            </label>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-bold hover:bg-gray-900 transition">Filtrar</button>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($jogadores->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Nenhum jogador cadastrado.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3"></th>
                                <th class="px-6 py-3">Nome</th>
                                <th class="px-6 py-3">Equipe</th>
                                <th class="px-6 py-3">Modalidade</th>
                                <th class="px-6 py-3">Documento</th>
                                <th class="px-6 py-3">Situação</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($jogadores as $jogador)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-6 py-4">
                                    @if($jogador->fotoUrl())
                                        <img src="{{ $jogador->fotoUrl() }}" class="w-10 h-10 rounded-full object-cover border border-gray-200 dark:border-gray-600">
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700"></div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                    {{ $jogador->nomeExibicaoResolvido() }}
                                    @if($jogador->criado_em_campo)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Criado em campo</span>
                                    @endif
                                    @if($jogador->precisaDeRevisao())
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">Sem equipe/modalidade</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $jogador->equipe?->nome ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $jogador->modalidade?->nome ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $jogador->documento ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    @if($jogador->ativo)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">Ativo</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Inativo</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('placar.jogadores.show', $jogador) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline font-medium text-xs">Editar</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $jogadores->links() }}</div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
