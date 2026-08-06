<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Escalação') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="mb-2 flex items-center gap-4">
            <a href="{{ route('placar.jogos.show', $jogo) }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 border border-gray-100 dark:border-gray-700 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
                    {{ $jogo->timeCasa->nomeExibicaoResolvido() }} x {{ $jogo->timeFora->nomeExibicaoResolvido() }}
                </h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">{{ $jogo->data_hora->format('d/m/Y H:i') }} — marque quem está relacionado, os titulares e o capitão de cada time.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            @foreach([['casa', $jogo->time_casa_id], ['fora', $jogo->time_fora_id]] as [$lado, $timeId])
            @php $painel = $times[$lado]; @endphp
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">{{ $painel['time']->nomeExibicaoResolvido() }}</h2>
                </div>

                @if($painel['jogadores']->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        Este time não tem elenco cadastrado na temporada.
                        <a href="{{ route('placar.times.show', $painel['time']) }}" class="font-bold text-emerald-600 dark:text-emerald-400 hover:underline">Cadastrar elenco</a>.
                    </div>
                @else
                <form action="{{ route('placar.jogos.escalacao.update', $jogo) }}" method="POST">
                    @csrf
                    <input type="hidden" name="time_id" value="{{ $timeId }}">

                    <div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-[28rem] overflow-y-auto">
                        @foreach($painel['jogadores'] as $item)
                        @php $jogadorId = $item['jogador']->id; @endphp
                        <div class="p-3 flex items-center gap-3 text-sm">
                            <input type="checkbox" name="jogadores[{{ $jogadorId }}][relacionado]" value="1" @checked($item['relacionado'])
                                class="w-5 h-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="flex-1 text-gray-800 dark:text-gray-200">{{ $item['jogador']->nomeExibicaoResolvido() }}</span>
                            <input type="text" name="jogadores[{{ $jogadorId }}][numero]" value="{{ $item['numero'] }}" placeholder="nº"
                                class="w-14 px-2 py-1 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs">
                            <label class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <input type="checkbox" name="jogadores[{{ $jogadorId }}][titular]" value="1" @checked($item['titular'])
                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                Titular
                            </label>
                            <label class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <input type="checkbox" name="jogadores[{{ $jogadorId }}][capitao]" value="1" @checked($item['capitao'])
                                    class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                Capitão
                            </label>
                        </div>
                        @endforeach
                    </div>

                    <div class="p-4 border-t border-gray-100 dark:border-gray-700 text-right">
                        <button type="submit" class="px-5 py-2 bg-emerald-600 text-white rounded-xl font-bold shadow hover:bg-emerald-700 transition text-sm">
                            Salvar escalação
                        </button>
                    </div>
                </form>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>
</x-app-layout>
