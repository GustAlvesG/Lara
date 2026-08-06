<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Competição') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="mb-2 flex items-center gap-4">
            <a href="{{ route('placar.competicoes.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 border border-gray-100 dark:border-gray-700 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $competicao->nome }}</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">{{ $competicao->modalidade->nome }} · {{ $competicao->temporada }}</p>
            </div>
        </div>

        <form action="{{ route('placar.competicoes.update', $competicao) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">Dados</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome <span class="text-red-500">*</span></label>
                        <input type="text" name="nome" value="{{ old('nome', $competicao->nome) }}" required
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                        @error('nome')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Temporada <span class="text-red-500">*</span></label>
                        <input type="number" name="temporada" value="{{ old('temporada', $competicao->temporada) }}" required
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                        @error('temporada')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-600 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                            <input type="hidden" name="ativo" value="0">
                            <input type="checkbox" name="ativo" value="1" @checked(old('ativo', $competicao->ativo))
                                class="w-5 h-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300">Ativa</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold shadow-lg hover:bg-emerald-700 transition">Salvar Alterações</button>
            </div>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-50 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Jogos</h2>
                <a href="{{ route('placar.jogos.create') }}" class="text-sm font-bold text-emerald-600 dark:text-emerald-400 hover:underline">+ Novo jogo</a>
            </div>
            @if($jogos->isEmpty())
                <div class="p-6 text-sm text-gray-500 dark:text-gray-400">Nenhum jogo vinculado a esta competição.</div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($jogos as $jogo)
                    <li class="p-4 flex items-center justify-between text-sm">
                        <a href="{{ route('placar.jogos.show', $jogo) }}" class="text-gray-800 dark:text-gray-200 hover:underline">
                            {{ $jogo->timeCasa->nomeExibicaoResolvido() }} x {{ $jogo->timeFora->nomeExibicaoResolvido() }}
                        </a>
                        <span class="text-xs text-gray-400">{{ $jogo->data_hora->format('d/m/Y H:i') }} · {{ $jogo->status }}</span>
                    </li>
                    @endforeach
                </ul>
                <div class="p-4">{{ $jogos->links() }}</div>
            @endif
        </div>

        <div class="flex justify-end">
            <form method="POST" action="{{ route('placar.competicoes.destroy', $competicao) }}"
                  onsubmit="return confirm('Excluir esta competição?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition">
                    Excluir Competição
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
