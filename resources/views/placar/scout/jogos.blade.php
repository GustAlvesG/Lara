<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Placar Clube — Súmulas') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Súmulas</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Escolha uma partida para ver a súmula e a atuação minutada de cada jogador.</p>
        </div>

        @include('partials.alerts')

        <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
            <select name="modalidade" class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <option value="">Todas as modalidades</option>
                @foreach($modalidades as $modalidade)
                    <option value="{{ $modalidade->slug }}" @selected(request('modalidade') === $modalidade->slug)>{{ $modalidade->nome }}</option>
                @endforeach
            </select>
            <select name="status" class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <option value="">Todos os status</option>
                @foreach(\App\Models\Placar\Jogo::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', $status) }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-bold hover:bg-gray-900 transition">Filtrar</button>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($jogos->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Nenhuma partida encontrada com estes filtros.</p>
                </div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($jogos as $jogo)
                    <li class="p-4 flex items-center justify-between gap-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <div class="min-w-0">
                            <a href="{{ route('placar.scout.sumula', $jogo) }}" class="font-semibold text-gray-900 dark:text-white hover:underline">
                                {{ $jogo->timeCasa->nomeExibicaoResolvido() }} <span class="text-gray-400">x</span> {{ $jogo->timeFora->nomeExibicaoResolvido() }}
                            </a>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $jogo->data_hora?->format('d/m/Y H:i') }} · {{ $jogo->modalidade->nome }}
                                @if($jogo->competicao) · {{ $jogo->competicao->nome }} @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="font-extrabold text-gray-900 dark:text-white">{{ $jogo->placar_casa ?? 0 }} x {{ $jogo->placar_fora ?? 0 }}</span>
                            @if($jogo->status === \App\Models\Placar\Jogo::STATUS_AO_VIVO)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-600 animate-pulse"></span> ao vivo
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">{{ str_replace('_', ' ', $jogo->status) }}</span>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ul>
                <div class="p-4">{{ $jogos->links() }}</div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
