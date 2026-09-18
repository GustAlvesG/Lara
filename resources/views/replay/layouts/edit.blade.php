<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Replay — Editor de Layout') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $layout->name }}</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">
                    {{ $layout->ownerLabel() }}: {{ $layout->place?->name ?? $layout->group?->name ?? '—' }}
                    · {{ \App\Support\Replay\Orientation::label($layout->orientation) }}
                    · tela de {{ $dimensions['width'] }}×{{ $dimensions['height'] }}
                </p>
            </div>

            <a href="{{ route('replay.layouts.index') }}"
               class="text-sm font-bold text-gray-600 dark:text-gray-300 hover:underline">Voltar aos layouts</a>
        </div>

        @include('partials.alerts')

        @unless($ffmpegAvailable)
            <div class="mb-6 px-6 py-4 rounded-2xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 text-sm">
                <strong>ffmpeg não encontrado neste servidor.</strong>
                GIF animado aqui vai sair parado no vídeo (só o primeiro quadro).
            </div>
        @endunless

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Tela de composição --}}
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Composição</h2>
                        <div class="flex items-center gap-3">
                            <span id="replay-status" class="text-xs text-gray-400"></span>
                            <button type="button" id="replay-save"
                                    class="px-5 py-2 bg-emerald-600 text-white rounded-xl font-bold text-sm shadow hover:bg-emerald-700 transition">
                                Salvar posições
                            </button>
                        </div>
                    </div>

                    {{--
                        O xadrez cinza é convenção de editor de imagem para
                        "aqui é transparente": sem ele, quem monta o layout não
                        distingue fundo branco de fundo vazado, e só descobriria
                        no vídeo.
                    --}}
                    <div id="replay-canvas"
                         class="relative w-full select-none rounded-xl overflow-hidden border border-gray-300 dark:border-gray-600"
                         style="aspect-ratio: {{ $dimensions['width'] }} / {{ $dimensions['height'] }};
                                background-color: #6b7280;
                                background-image:
                                    linear-gradient(45deg, rgba(0,0,0,.18) 25%, transparent 25%),
                                    linear-gradient(-45deg, rgba(0,0,0,.18) 25%, transparent 25%),
                                    linear-gradient(45deg, transparent 75%, rgba(0,0,0,.18) 75%),
                                    linear-gradient(-45deg, transparent 75%, rgba(0,0,0,.18) 75%);
                                background-size: 24px 24px;
                                background-position: 0 0, 0 12px, 12px -12px, -12px 0;">

                        @foreach($layout->items as $item)
                            <div class="replay-item absolute cursor-move"
                                 data-id="{{ $item->id }}"
                                 style="left: {{ $item->x }}%; top: {{ $item->y }}%;
                                        width: {{ $item->width }}%; height: {{ $item->height }}%;
                                        opacity: {{ $item->opacity / 100 }};
                                        z-index: {{ $item->z_index }};">
                                <img src="{{ $item->imageUrl() }}" alt=""
                                     class="w-full h-full object-fill pointer-events-none" draggable="false">
                                <span class="replay-handle absolute -right-1 -bottom-1 w-4 h-4 rounded-sm bg-emerald-500 border-2 border-white cursor-se-resize"></span>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Arraste para posicionar, use o quadrado verde para redimensionar. As medidas são salvas em
                        porcentagem da tela — o layout continua correto se a câmera mudar de resolução.
                    </p>
                </div>
            </div>

            {{-- Painel lateral --}}
            <div class="space-y-6">

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Nova logomarca</h2>

                    <form action="{{ route('replay.layouts.logos.store', $layout) }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <input type="file" name="logo" accept="image/png,image/gif" required
                               class="w-full text-sm text-gray-700 dark:text-gray-300">
                        <button type="submit"
                                class="w-full px-4 py-2 bg-gray-800 text-white rounded-xl font-bold text-sm hover:bg-gray-900 transition">
                            Enviar
                        </button>
                        <p class="text-xs text-gray-500 dark:text-gray-400">PNG ou GIF, até 8MB. GIF animado gera também a versão animada do overlay.</p>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Peça selecionada</h2>

                    <div id="replay-empty" class="text-sm text-gray-500 dark:text-gray-400">
                        Clique em uma logomarca na composição para ajustar opacidade e ordem.
                    </div>

                    <div id="replay-controls" class="space-y-4 hidden">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                Opacidade (<span id="replay-opacity-value">100</span>%)
                            </label>
                            <input type="range" id="replay-opacity" min="5" max="100" value="100" class="w-full">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Ordem (frente/trás)</label>
                            <input type="number" id="replay-z" min="0" max="999"
                                   class="w-24 px-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-xs text-gray-500 dark:text-gray-400">
                            <div>X: <span id="replay-x">0</span>%</div>
                            <div>Y: <span id="replay-y">0</span>%</div>
                            <div>Largura: <span id="replay-w">0</span>%</div>
                            <div>Altura: <span id="replay-h">0</span>%</div>
                        </div>

                        <form id="replay-delete-form" method="POST" action="" onsubmit="return confirm('Remover esta logomarca do layout?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:underline font-medium">
                                Remover logomarca
                            </button>
                        </form>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Overlay publicado</h2>

                    <div id="replay-overlay-info" class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
                        @if($layout->overlay_path)
                            <p>Gerado em <span id="replay-rendered">{{ $layout->overlay_rendered_at?->format('d/m/Y H:i') }}</span></p>
                            <a id="replay-png-link" href="{{ $layout->overlayUrl() }}" target="_blank"
                               class="block text-emerald-600 dark:text-emerald-400 hover:underline text-xs font-medium">Abrir PNG</a>
                            @if($layout->overlay_animated_path)
                                <a id="replay-webm-link" href="{{ $layout->animatedOverlayUrl() }}" target="_blank"
                                   class="block text-blue-600 dark:text-blue-400 hover:underline text-xs font-medium">Abrir WebM (animado)</a>
                            @endif
                        @else
                            <p class="text-gray-500 dark:text-gray-400">Ainda sem logomarca — o vídeo sai limpo.</p>
                        @endif
                    </div>

                    <form action="{{ route('replay.layouts.rerender', $layout) }}" method="POST" class="mt-4">
                        @csrf
                        <button type="submit" class="text-xs text-gray-600 dark:text-gray-300 hover:underline font-medium">
                            Gerar overlay novamente
                        </button>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Dados do layout</h2>

                    <form action="{{ route('replay.layouts.update', $layout) }}" method="POST" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" value="{{ $layout->name }}" required maxlength="255"
                               class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="active" value="1" @checked($layout->active)
                                   class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            Ativo (layout inativo é ignorado pelas câmeras)
                        </label>
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-xl font-bold text-sm hover:bg-gray-900 transition">
                            Salvar dados
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/*
 | Editor de posicionamento das logomarcas.
 |
 | JavaScript sem biblioteca de propósito: o que a tela faz é arrastar,
 | redimensionar e mandar um JSON — e uma dependência de arrastar-e-soltar
 | custaria mais para manter do que estas linhas.
 |
 | Toda medida trafega em PORCENTAGEM da tela de composição, nunca em pixels:
 | é o que o banco guarda, e é o que mantém o layout correto quando a câmera
 | grava em outra resolução. Pixel só existe dentro do cálculo do arrasto.
 */
(function () {
    const canvas = document.getElementById('replay-canvas');
    if (!canvas) return;

    const statusEl = document.getElementById('replay-status');
    const controls = document.getElementById('replay-controls');
    const emptyEl = document.getElementById('replay-empty');
    const opacityInput = document.getElementById('replay-opacity');
    const opacityValue = document.getElementById('replay-opacity-value');
    const zInput = document.getElementById('replay-z');
    const deleteForm = document.getElementById('replay-delete-form');
    const deleteUrlTemplate = @json(route('replay.layouts.items.destroy', ['layout' => $layout->id, 'item' => '__ID__']));
    const saveUrl = @json(route('replay.layouts.items.update', $layout));
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    let selected = null;
    let drag = null;

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function readItem(el) {
        return {
            id: parseInt(el.dataset.id, 10),
            x: parseFloat(el.style.left),
            y: parseFloat(el.style.top),
            width: parseFloat(el.style.width),
            height: parseFloat(el.style.height),
            opacity: Math.round(parseFloat(el.style.opacity || '1') * 100),
            z_index: parseInt(el.style.zIndex || '0', 10),
        };
    }

    function refreshPanel() {
        if (!selected) {
            controls.classList.add('hidden');
            emptyEl.classList.remove('hidden');
            return;
        }

        const item = readItem(selected);
        controls.classList.remove('hidden');
        emptyEl.classList.add('hidden');

        opacityInput.value = item.opacity;
        opacityValue.textContent = item.opacity;
        zInput.value = item.z_index;
        document.getElementById('replay-x').textContent = item.x.toFixed(1);
        document.getElementById('replay-y').textContent = item.y.toFixed(1);
        document.getElementById('replay-w').textContent = item.width.toFixed(1);
        document.getElementById('replay-h').textContent = item.height.toFixed(1);
        deleteForm.action = deleteUrlTemplate.replace('__ID__', item.id);
    }

    function select(el) {
        canvas.querySelectorAll('.replay-item').forEach(function (node) {
            node.style.outline = '';
        });

        selected = el;

        if (el) {
            el.style.outline = '2px solid #10b981';
        }

        refreshPanel();
    }

    canvas.addEventListener('pointerdown', function (event) {
        const item = event.target.closest('.replay-item');

        if (!item) {
            select(null);
            return;
        }

        select(item);

        const rect = canvas.getBoundingClientRect();
        const resizing = event.target.classList.contains('replay-handle');

        drag = {
            el: item,
            resizing: resizing,
            startX: event.clientX,
            startY: event.clientY,
            rect: rect,
            origin: readItem(item),
        };

        // O ponteiro fica preso ao elemento: sem isso, arrastar rápido
        // "solta" a logo quando o cursor ultrapassa a borda da tela.
        item.setPointerCapture(event.pointerId);
        event.preventDefault();
    });

    canvas.addEventListener('pointermove', function (event) {
        if (!drag) return;

        const deltaX = (event.clientX - drag.startX) / drag.rect.width * 100;
        const deltaY = (event.clientY - drag.startY) / drag.rect.height * 100;

        if (drag.resizing) {
            // Mínimo de 1%: abaixo disso a peça vira um ponto impossível de
            // pegar de novo com o mouse.
            const width = clamp(drag.origin.width + deltaX, 1, 200);
            const height = clamp(drag.origin.height + deltaY, 1, 200);
            drag.el.style.width = width.toFixed(3) + '%';
            drag.el.style.height = height.toFixed(3) + '%';
        } else {
            // Deixa passar um pouco da borda (-50 a 150): logo sangrando para
            // fora do quadro é recurso de layout, não erro.
            const x = clamp(drag.origin.x + deltaX, -50, 150);
            const y = clamp(drag.origin.y + deltaY, -50, 150);
            drag.el.style.left = x.toFixed(3) + '%';
            drag.el.style.top = y.toFixed(3) + '%';
        }

        refreshPanel();
    });

    ['pointerup', 'pointercancel'].forEach(function (type) {
        canvas.addEventListener(type, function () {
            drag = null;
        });
    });

    opacityInput.addEventListener('input', function () {
        if (!selected) return;
        selected.style.opacity = (parseInt(this.value, 10) / 100).toString();
        opacityValue.textContent = this.value;
    });

    zInput.addEventListener('input', function () {
        if (!selected) return;
        selected.style.zIndex = (parseInt(this.value, 10) || 0).toString();
    });

    document.getElementById('replay-save').addEventListener('click', function () {
        const items = Array.from(canvas.querySelectorAll('.replay-item')).map(readItem);

        statusEl.textContent = 'Salvando e gerando o overlay...';

        fetch(saveUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ items: items }),
        })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function (data) {
            statusEl.textContent = 'Overlay gerado ' + (data.rendered_at || '') + '.';

            // A URL muda a cada composição (o hash entra no nome), então o
            // link precisa ser reescrito — apontar para o arquivo antigo
            // mostraria o layout anterior.
            const png = document.getElementById('replay-png-link');
            if (png && data.overlay_url) png.href = data.overlay_url;

            const webm = document.getElementById('replay-webm-link');
            if (webm && data.animated_url) webm.href = data.animated_url;
        })
        .catch(function (error) {
            statusEl.textContent = 'Falha ao salvar: ' + error.message;
        });
    });
})();
</script>
</x-app-layout>
