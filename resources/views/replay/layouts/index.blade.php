<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Replay — Layouts de Logomarca') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Layouts de Logomarca</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">
                    O que é composto aqui vira um PNG transparente do tamanho do vídeo, aplicado pela câmera no canto (0,0).
                </p>
            </div>

            <a href="{{ route('replay.layouts.create') }}"
               class="inline-flex items-center px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold shadow-lg hover:bg-emerald-700 transition duration-150">
                Novo Layout
            </a>
        </div>

        @include('partials.alerts')

        @include('replay.partials.tabs', ['current' => 'layouts'])

        @unless($ffmpegAvailable)
            <div class="mb-6 px-6 py-4 rounded-2xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 text-sm">
                <strong>ffmpeg não encontrado neste servidor.</strong>
                Logomarcas em GIF animado vão sair <em>paradas</em> (primeiro quadro) — o PNG continua sendo gerado normalmente.
            </div>
        @endunless

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($layouts->isEmpty())
                <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                    Nenhum layout criado. Sem layout, o vídeo sai limpo — o que também é uma escolha válida.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">Layout</th>
                                <th class="px-6 py-3">Aplica-se a</th>
                                <th class="px-6 py-3">Orientação</th>
                                <th class="px-6 py-3">Logos</th>
                                <th class="px-6 py-3">Overlay</th>
                                <th class="px-6 py-3">Situação</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($layouts as $layout)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">{{ $layout->name }}</td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                        {{ $layout->ownerLabel() }}:
                                        <span class="font-medium">{{ $layout->place?->name ?? $layout->group?->name ?? '—' }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                        {{ \App\Support\Replay\Orientation::label($layout->orientation) }}
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $layout->items->count() }}</td>
                                    <td class="px-6 py-4">
                                        @if($layout->overlay_path)
                                            <a href="{{ $layout->overlayUrl() }}" target="_blank"
                                               class="text-emerald-600 dark:text-emerald-400 hover:underline text-xs font-medium">PNG</a>
                                            @if($layout->overlay_animated_path)
                                                <a href="{{ $layout->animatedOverlayUrl() }}" target="_blank"
                                                   class="ml-2 text-blue-600 dark:text-blue-400 hover:underline text-xs font-medium">WebM</a>
                                            @endif
                                            <div class="text-xs text-gray-400">{{ $layout->overlay_rendered_at?->format('d/m/Y H:i') }}</div>
                                        @else
                                            <span class="text-xs text-gray-400">sem logomarca</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($layout->active)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">Ativo</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Inativo</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap space-x-3">
                                        <a href="{{ route('replay.layouts.edit', $layout) }}"
                                           class="text-emerald-600 dark:text-emerald-400 hover:underline font-medium text-xs">Editar</a>

                                        <form action="{{ route('replay.layouts.destroy', $layout) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Remover o layout e todas as suas logomarcas?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 dark:text-red-400 hover:underline font-medium text-xs">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
