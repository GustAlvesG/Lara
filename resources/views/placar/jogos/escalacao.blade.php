<x-app-layout>
<div class="py-6">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="mb-2 flex items-center gap-4">
            <a href="{{ route('placar.jogos.show', $jogo) }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-ok border border-line transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">
                    {{ $jogo->timeCasa->nomeExibicaoResolvido() }} x {{ $jogo->timeFora->nomeExibicaoResolvido() }}
                </h1>
                <p class="text-ink-2 font-medium">{{ $jogo->data_hora->format('d/m/Y H:i') }} — marque quem está relacionado, os titulares e o capitão de cada time.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            @foreach([['casa', $jogo->time_casa_id], ['fora', $jogo->time_fora_id]] as [$lado, $timeId])
            @php $painel = $times[$lado]; @endphp
            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                <div class="p-6 border-b border-line bg-subtle">
                    <h2 class="text-lg font-bold text-ink">{{ $painel['time']->nomeExibicaoResolvido() }}</h2>
                </div>

                @if($painel['jogadores']->isEmpty())
                    <div class="p-6 text-sm text-ink-2">
                        Este time não tem elenco cadastrado na temporada.
                        <a href="{{ route('placar.times.show', $painel['time']) }}" class="font-bold text-grena-ink hover:underline">Cadastrar elenco</a>.
                    </div>
                @else
                <form action="{{ route('placar.jogos.escalacao.update', $jogo) }}" method="POST">
                    @csrf
                    <input type="hidden" name="time_id" value="{{ $timeId }}">

                    <div class="divide-y divide-line max-h-[28rem] overflow-y-auto">
                        @foreach($painel['jogadores'] as $item)
                        @php $jogadorId = $item['jogador']->id; @endphp
                        <div class="p-3 flex items-center gap-3 text-sm">
                            <input type="checkbox" name="jogadores[{{ $jogadorId }}][relacionado]" value="1" @checked($item['relacionado'])
                                class="w-5 h-5 rounded border-line-strong text-ok focus:ring-ok-soft">
                            <span class="flex-1 text-ink">{{ $item['jogador']->nomeExibicaoResolvido() }}</span>
                            <input type="text" name="jogadores[{{ $jogadorId }}][numero]" value="{{ $item['numero'] }}" placeholder="nº"
                                class="w-14 px-2 py-1 border border-line rounded-lg bg-surface text-ink text-xs">
                            <label class="flex items-center gap-1 text-xs text-ink-2">
                                <input type="checkbox" name="jogadores[{{ $jogadorId }}][titular]" value="1" @checked($item['titular'])
                                    class="rounded border-line-strong text-ok focus:ring-ok-soft">
                                Titular
                            </label>
                            <label class="flex items-center gap-1 text-xs text-ink-2">
                                <input type="checkbox" name="jogadores[{{ $jogadorId }}][capitao]" value="1" @checked($item['capitao'])
                                    class="rounded border-line-strong text-warn focus:ring-warn-soft">
                                Capitão
                            </label>
                        </div>
                        @endforeach
                    </div>

                    <div class="p-4 border-t border-line text-right">
                        <button type="submit" class="px-5 py-2 bg-grena text-white rounded-full font-bold shadow-card hover:bg-grena-hover transition text-sm">
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
