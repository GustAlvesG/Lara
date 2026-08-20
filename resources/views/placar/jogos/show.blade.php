<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Jogo') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="mb-2 flex items-center gap-4">
            <a href="{{ route('placar.jogos.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 border border-gray-100 dark:border-gray-700 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">
                    {{ $jogo->timeCasa->nomeExibicaoResolvido() }}
                    @if(!is_null($jogo->placar_casa)) <span class="text-emerald-600">{{ $jogo->placar_casa }} x {{ $jogo->placar_fora }}</span> @else x @endif
                    {{ $jogo->timeFora->nomeExibicaoResolvido() }}
                    @if($jogo->criado_em_campo)
                        <span class="ml-2 align-middle inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Criado em campo</span>
                    @endif
                </h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">
                    {{ $jogo->modalidade->nome }} · {{ $jogo->data_hora->format('d/m/Y H:i') }}
                    @if($jogo->competicao) · {{ $jogo->competicao->nome }} @endif
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('placar.jogos.escalacao.edit', $jogo) }}" class="inline-flex items-center px-5 py-2.5 bg-gray-800 text-white rounded-xl font-bold shadow hover:bg-gray-900 transition text-sm">
                Escalação
            </a>
            @can('view-placar-scout')
            <a href="{{ route('placar.scout.sumula', $jogo) }}" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white rounded-xl font-bold shadow hover:bg-indigo-700 transition text-sm">
                Súmula
            </a>
            @endcan
        </div>

        <form action="{{ route('placar.jogos.update', $jogo) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">Dados</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Data e hora <span class="text-red-500">*</span></label>
                        <input type="datetime-local" name="data_hora" value="{{ old('data_hora', $jogo->data_hora->format('Y-m-d\TH:i')) }}" required
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                        @error('data_hora')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Status <span class="text-red-500">*</span></label>
                        <select name="status" required class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            @foreach(\App\Models\Placar\Jogo::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', $jogo->status) === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Normalmente o Node quem muda isto (iniciar/encerrar) — ajuste manual só em exceção.</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Local</label>
                        <input type="text" name="local" value="{{ old('local', $jogo->local) }}"
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                    </div>
                    @if($jogo->observacoes)
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Observações</label>
                        <pre class="text-xs text-gray-500 dark:text-gray-400 whitespace-pre-wrap p-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg">{{ $jogo->observacoes }}</pre>
                    </div>
                    @endif
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold shadow-lg hover:bg-emerald-700 transition">Salvar Alterações</button>
            </div>
        </form>

        <div class="flex justify-end">
            <form method="POST" action="{{ route('placar.jogos.destroy', $jogo) }}"
                  onsubmit="return confirm('Excluir este jogo?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition">
                    Excluir Jogo
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
