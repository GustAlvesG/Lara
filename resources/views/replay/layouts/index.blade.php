<x-app-layout :bootstrap-grid="false">

<div>
    <div class="mx-auto w-full max-w-[1200px] px-4 pb-14 pt-6 sm:px-6 sm:pt-8 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="font-display text-[22px] font-semibold leading-tight tracking-tight text-ink sm:text-[26px]">Layouts de Logomarca</h2>
                <p class="mt-1.5 max-w-3xl text-[15px] leading-relaxed text-ink-2">
                    O que é composto aqui vira um PNG transparente do tamanho do vídeo, aplicado pela câmera no canto (0,0).
                </p>
            </div>

            <x-primary-button-a href="{{ route('replay.layouts.create') }}"><x-icon name="plus" /> Novo layout</x-primary-button-a>
        </div>

        @include('partials.alerts')


        @unless($ffmpegAvailable)
            <div class="mb-6 px-6 py-4 rounded-2xl bg-warn-soft border border-warn/40 text-warn text-sm">
                <strong>ffmpeg não encontrado neste servidor.</strong>
                Logomarcas em GIF animado vão sair <em>paradas</em> (primeiro quadro) — o PNG continua sendo gerado normalmente.
            </div>
        @endunless

        <div class="bg-surface rounded-card shadow-card overflow-hidden">
            @if($layouts->isEmpty())
                <div class="p-12 text-center text-ink-2">
                    Nenhum layout criado. Sem layout, o vídeo sai limpo — o que também é uma escolha válida.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-ink-3 uppercase tracking-wider bg-subtle">
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
                        <tbody class="divide-y divide-line">
                            @foreach($layouts as $layout)
                                <tr class="hover:bg-subtle transition">
                                    <td class="px-6 py-4 font-semibold text-ink">{{ $layout->name }}</td>
                                    <td class="px-6 py-4 text-ink-2">
                                        {{ $layout->ownerLabel() }}:
                                        <span class="font-medium">{{ $layout->place?->name ?? $layout->group?->name ?? '—' }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-ink-2">
                                        {{ \App\Support\Replay\Orientation::label($layout->orientation) }}
                                    </td>
                                    <td class="px-6 py-4 text-ink-2">{{ $layout->items->count() }}</td>
                                    <td class="px-6 py-4">
                                        @if($layout->overlay_path)
                                            <a href="{{ $layout->overlayUrl() }}" target="_blank"
                                               class="text-ok hover:underline text-xs font-medium">PNG</a>
                                            @if($layout->overlay_animated_path)
                                                <a href="{{ $layout->animatedOverlayUrl() }}" target="_blank"
                                                   class="ml-2 text-grena-ink hover:underline text-xs font-medium">WebM</a>
                                            @endif
                                            <div class="text-xs text-ink-3">{{ $layout->overlay_rendered_at?->format('d/m/Y H:i') }}</div>
                                        @else
                                            <span class="text-xs text-ink-3">sem logomarca</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($layout->active)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-ok-soft text-ok">Ativo</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-subtle text-ink-2">Inativo</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap space-x-3">
                                        <a href="{{ route('replay.layouts.edit', $layout) }}"
                                           class="text-ok hover:underline font-medium text-xs">Editar</a>

                                        <form action="{{ route('replay.layouts.destroy', $layout) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Remover o layout e todas as suas logomarcas?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-danger hover:underline font-medium text-xs">Excluir</button>
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
