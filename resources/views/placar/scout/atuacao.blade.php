@php
    $j = $atuacao['jogo'];
    $p = $atuacao['jogador'];
@endphp
<x-app-layout>
<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="flex items-center gap-4">
            <a href="{{ route('placar.scout.sumula', $jogo) }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            @if($p['foto_url'])
                <img src="{{ $p['foto_url'] }}" class="w-16 h-16 rounded-full object-cover border border-line">
            @else
                <div class="w-16 h-16 rounded-full bg-subtle"></div>
            @endif
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">
                    @if($p['numero'])<span class="text-ink-3 font-mono">#{{ $p['numero'] }}</span>@endif
                    {{ $p['nome_exibicao'] }}
                </h1>
                <p class="text-ink-2 font-medium text-sm">
                    {{ $j['time_casa']['nome_exibicao'] }} {{ $j['placar_casa'] ?? 0 }} x {{ $j['placar_fora'] ?? 0 }} {{ $j['time_fora']['nome_exibicao'] }}
                    · {{ \Illuminate\Support\Carbon::parse($j['data_hora'])->format('d/m/Y H:i') }}
                    @if($j['competicao']) · {{ $j['competicao']['nome'] }} @endif
                </p>
            </div>
        </div>

        @php
            $vocab = $atuacao['vocabulario'];
            $nomeDoPeriodo = ucfirst($vocab['periodo']);
            // Vôlei não tem falta: o quadro sairia sempre zerado.
            $quadros = $vocab['tem_falta']
                ? ['pontos' => ucfirst($vocab['pontos']), 'faltas' => 'Faltas', 'lances' => 'Lances']
                : ['pontos' => ucfirst($vocab['pontos']), 'lances' => 'Lances'];
        @endphp

        {{-- Classe literal: o Tailwind varre o fonte e não veria
             "grid-cols-{n}" montado em tempo de execução. --}}
        <div class="grid {{ count($quadros) === 3 ? 'grid-cols-3' : 'grid-cols-2' }} gap-4">
            @foreach($quadros as $campo => $label)
            <div class="bg-surface rounded-2xl shadow-pop border border-line p-6 text-center">
                <p class="text-3xl font-extrabold text-ink">{{ $atuacao['totais'][$campo] }}</p>
                <p class="text-xs font-bold text-ink-3 uppercase tracking-wider mt-1">{{ $label }}</p>
            </div>
            @endforeach
        </div>

        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            <div class="p-6 border-b border-line">
                <h2 class="text-lg font-bold text-ink">Lances minutados</h2>
                <p class="text-xs text-ink-3 mt-1">Minuto do cronômetro da partida, não do relógio.</p>
            </div>

            @if(empty($atuacao['lances']))
                <div class="p-12 text-center">
                    <p class="text-ink-2">Este jogador não teve nenhum lance registrado nesta partida.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-ink-3 uppercase tracking-wider bg-subtle">
                            <tr>
                                <th class="px-6 py-3">Minuto</th>
                                <th class="px-6 py-3">{{ $nomeDoPeriodo }}</th>
                                <th class="px-6 py-3">Lance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($atuacao['lances'] as $lance)
                            <tr class="@if($lance['estornado']) opacity-40 @endif">
                                <td class="px-6 py-3 font-mono font-bold text-ink">{{ $lance['minuto'] ?? '—' }}</td>
                                <td class="px-6 py-3 text-ink-2">{{ $lance['periodo'] ? $nomeDoPeriodo . ' ' . $lance['periodo'] : '—' }}</td>
                                <td class="px-6 py-3">
                                    {{-- "Cesta de 3" já carrega o valor: por
                                         isso não há coluna Valor. --}}
                                    <span class="text-xs font-bold text-ink-2">{{ $lance['rotulo'] }}</span>
                                    @if($lance['estornado'])
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-danger-soft text-grena-ink">estornado</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="text-center">
            <a href="{{ route('placar.scout.jogador', $jogador) }}" class="text-sm font-bold text-grena-ink hover:underline">
                Ver todas as partidas deste jogador →
            </a>
        </div>
    </div>
</div>
</x-app-layout>
