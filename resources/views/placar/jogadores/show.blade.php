<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Jogador') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="mb-2 flex items-center gap-4">
            <a href="{{ route('placar.jogadores.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 border border-gray-100 dark:border-gray-700 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">
                    {{ $jogador->nomeExibicaoResolvido() }}
                    @if($jogador->criado_em_campo)
                        <span class="ml-2 align-middle inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Criado em campo</span>
                    @endif
                </h1>
                @can('view-placar-scout')
                    <a href="{{ route('placar.scout.jogador', $jogador) }}" class="text-sm font-bold text-emerald-600 dark:text-emerald-400 hover:underline">Ver perfil no scout →</a>
                @endcan
            </div>
        </div>

        {{-- Foto --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Foto</h2>
            <div class="flex items-center gap-6">
                @if($jogador->fotoUrl())
                    <img src="{{ $jogador->fotoUrl() }}" class="w-24 h-24 rounded-xl object-cover border border-gray-200 dark:border-gray-600">
                @else
                    <div class="w-24 h-24 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-xs text-gray-400">sem foto</div>
                @endif
                <div class="flex flex-col gap-2">
                    <form action="{{ route('placar.jogadores.foto.store', $jogador) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input type="file" name="arquivo" accept="image/jpeg,image/png,image/webp" required class="text-sm">
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700 transition">Enviar</button>
                    </form>
                    @if($jogador->foto_path)
                    <form action="{{ route('placar.jogadores.foto.destroy', $jogador) }}" method="POST" onsubmit="return confirm('Remover a foto?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-bold text-red-600 dark:text-red-400 hover:underline">Remover foto</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Vídeo de apresentação --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="text-lg font-bold text-gray-800 dark:text-white mb-1">Vídeo de apresentação</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">
                Usado pelo telão na entrada em quadra (a foto é usada na escalação e na súmula).
                mp4 ou webm, até 28MB.
            </p>
            <div class="flex flex-col sm:flex-row sm:items-start gap-6">
                @if($jogador->videoUrl())
                    <video src="{{ $jogador->videoUrl() }}" controls preload="metadata"
                           class="w-64 rounded-xl border border-gray-200 dark:border-gray-600 bg-black"></video>
                @else
                    <div class="w-64 h-36 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-xs text-gray-400">sem vídeo</div>
                @endif
                <div class="flex flex-col gap-2">
                    <form action="{{ route('placar.jogadores.video.store', $jogador) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input type="file" name="video" accept="video/mp4,video/webm" required class="text-sm">
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700 transition">Enviar</button>
                    </form>
                    @if($jogador->video_path)
                    <form action="{{ route('placar.jogadores.video.destroy', $jogador) }}" method="POST" onsubmit="return confirm('Remover o vídeo?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-bold text-red-600 dark:text-red-400 hover:underline">Remover vídeo</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        <form action="{{ route('placar.jogadores.update', $jogador) }}" method="POST">
            @csrf
            @method('PUT')
            @include('placar.jogadores.partials.form')

            <div class="mt-6 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold shadow-lg hover:bg-emerald-700 transition">Salvar Alterações</button>
            </div>
        </form>

        {{-- Elencos --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-50 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Times vinculados</h2>
            </div>
            @if($jogador->elencos->isEmpty())
                <div class="p-6 text-sm text-gray-500 dark:text-gray-400">Nenhum vínculo de elenco ainda.</div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($jogador->elencos as $vinculo)
                    <li class="p-4 flex items-center justify-between text-sm">
                        <a href="{{ route('placar.times.show', $vinculo->time) }}" class="text-gray-800 dark:text-gray-200 hover:underline">
                            {{ $vinculo->time->nomeExibicaoResolvido() }} <span class="text-xs text-gray-400">({{ $vinculo->time->equipe->nome }})</span>
                        </a>
                        <span class="text-xs text-gray-400">{{ $vinculo->temporada }} · nº {{ $vinculo->numero ?? '—' }} @if(!$vinculo->ativo) · inativo @endif</span>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="flex justify-end">
            <form method="POST" action="{{ route('placar.jogadores.destroy', $jogador) }}"
                  onsubmit="return confirm('Excluir este jogador?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition">
                    Excluir Jogador
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
