@php
    /*
     | Abas do módulo Replay. As quatro telas são passos do mesmo trabalho
     | (configurar formato -> desenhar layout -> ligar a câmera -> conferir os
     | vídeos), e quem opera troca entre elas o tempo todo.
     |
     | `Route::has` em cada item porque este partial é incluído em toda tela
     | do módulo: um nome de rota que não resolve (cache de rotas velho, tela
     | ainda não mesclada) derrubaria a página inteira com 500 em vez de
     | esconder uma aba.
     */
    $tabs = [
        'settings' => ['route' => 'replay.settings.index', 'label' => 'Configuração de Vídeo'],
        'layouts' => ['route' => 'replay.layouts.index', 'label' => 'Layouts de Logomarca'],
        'cameras' => ['route' => 'replay.cameras.index', 'label' => 'Câmeras'],
        'videos' => ['route' => 'replay.videos.index', 'label' => 'Vídeos'],
    ];
@endphp

<div class="mb-8 flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700 pb-3">
    @foreach($tabs as $key => $tab)
        @continue(! \Illuminate\Support\Facades\Route::has($tab['route']))
        <a href="{{ route($tab['route']) }}"
           class="px-4 py-2 rounded-xl text-sm font-bold transition
           {{ ($current ?? null) === $key
                ? 'bg-emerald-600 text-white shadow'
                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
