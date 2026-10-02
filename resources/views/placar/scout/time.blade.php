<x-app-layout>
<div class="py-6">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

        <div class="flex items-center gap-4">
            @if($painel['time']['logo_url'])
                <img src="{{ $painel['time']['logo_url'] }}" class="w-16 h-16 rounded-full object-cover border border-line">
            @else
                <div class="w-16 h-16 rounded-full bg-subtle"></div>
            @endif
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">{{ $painel['time']['nome_exibicao'] }}</h1>
                <a href="{{ route('placar.times.show', $time) }}" class="text-sm font-bold text-grena-ink hover:underline">Ver cadastro →</a>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="bg-surface rounded-2xl shadow-pop border border-line p-6 text-center">
                <p class="text-3xl font-extrabold text-ok">{{ $painel['retrospecto']['vitorias'] }}</p>
                <p class="text-xs font-bold text-ink-3 uppercase tracking-wider mt-1">Vitórias</p>
            </div>
            <div class="bg-surface rounded-2xl shadow-pop border border-line p-6 text-center">
                <p class="text-3xl font-extrabold text-ink-2">{{ $painel['retrospecto']['empates'] }}</p>
                <p class="text-xs font-bold text-ink-3 uppercase tracking-wider mt-1">Empates</p>
            </div>
            <div class="bg-surface rounded-2xl shadow-pop border border-line p-6 text-center">
                <p class="text-3xl font-extrabold text-danger">{{ $painel['retrospecto']['derrotas'] }}</p>
                <p class="text-xs font-bold text-ink-3 uppercase tracking-wider mt-1">Derrotas</p>
            </div>
        </div>

        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            <div class="p-6 border-b border-line">
                <h2 class="text-lg font-bold text-ink">Jogos</h2>
            </div>
            @if(empty($painel['jogos']))
                <div class="p-6 text-sm text-ink-2">Nenhum jogo registrado.</div>
            @else
                <ul class="divide-y divide-line">
                    @foreach($painel['jogos'] as $jogo)
                    <li class="p-4 flex items-center justify-between text-sm">
                        <a href="{{ route('placar.jogos.show', $jogo['jogo_id']) }}" class="text-ink hover:underline">
                            {{ $jogo['mandante'] ? 'vs' : '@' }} {{ $jogo['adversario']['nome_exibicao'] }}
                        </a>
                        <span class="flex items-center gap-2">
                            @if($jogo['resultado'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                                    @class([
                                        'bg-ok-soft text-ok' => $jogo['resultado'] === 'V',
                                        'bg-danger-soft text-grena-ink' => $jogo['resultado'] === 'D',
                                        'bg-subtle text-ink-2' => $jogo['resultado'] === 'E',
                                    ])">{{ $jogo['resultado'] }}</span>
                                <span class="text-xs text-ink-3">{{ $jogo['placar_time'] }}-{{ $jogo['placar_adversario'] }}</span>
                            @else
                                <span class="text-xs text-ink-3">{{ $jogo['status'] }}</span>
                            @endif
                        </span>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
