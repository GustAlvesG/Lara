{{-- Editor do mapa de lugares: dados do mapa (nome, tamanho, imagem de
     fundo) e o desenho — lugares (clicar, arrastar, grade) e áreas de
     orientação. O desenho vai inteiro, em JSON, no "Salvar desenho"
     (public/js/tickets/seat-map.js). Lugar que já teve ingresso fica com
     cadeado: rótulo e categoria travados, não apaga (desative). --}}
@php
    use App\Models\Ticket\TicketMap;
    use App\Models\Ticket\TicketSeat;

    $select = 'mt-1 h-10 w-full rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink';
    $input = 'mt-1 h-10 w-full rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink';
    $toolButton = 'inline-flex h-10 items-center gap-2 rounded-xl border border-line-strong bg-surface px-3 text-sm font-semibold text-ink-2 aria-pressed:border-grena aria-pressed:bg-grena-tint aria-pressed:text-grena';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="$map->name . ' — ' . $event->title" :back="route('tickets.maps.index', $event)">
            @if ($map->types->isEmpty())
                Nenhum tipo de ingresso vende este mapa ainda: depois de desenhar, crie o tipo na tela do evento e escolha este mapa.
            @else
                Vendido por {{ $map->types->map(fn ($t) => $t->fullName() . ($t->seat_category ? " (categoria {$t->seat_category})" : ' (todos os lugares)'))->implode('; ') }}.
            @endif
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('tickets.maps.captives.index', [$event, $map]) }}"><x-icon name="star" /> Mesas cativas</x-secondary-button-a>
                @can(\App\Authorization\Permissions::INGRESSOS_VENDAS)
                    <x-secondary-button-a href="{{ route('tickets.maps.occupancy', [$event, $map]) }}"><x-icon name="eye" /> Ocupação</x-secondary-button-a>
                @endcan
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <details class="rounded-card bg-surface p-5 shadow-card">
            <summary class="cursor-pointer text-sm font-black uppercase tracking-widest text-ink-3">Dados do mapa (nome, tamanho, imagem de fundo)</summary>
            <form method="POST" action="{{ route('tickets.maps.update', [$event, $map]) }}" enctype="multipart/form-data" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end" id="map-data-form">
                @csrf @method('PUT')
                <div class="sm:col-span-2"><x-input-label for="map-name" value="Nome" /><x-text-input id="map-name" name="name" class="mt-1" :value="old('name', $map->name)" required maxlength="100" /></div>
                <div><x-input-label for="map-width" value="Largura (unidades)" /><x-text-input type="number" id="map-width" name="width" class="mt-1" :value="old('width', $map->width)" min="{{ TicketMap::MIN_SIZE }}" max="{{ TicketMap::MAX_SIZE }}" /></div>
                <div><x-input-label for="map-height" value="Altura (unidades)" /><x-text-input type="number" id="map-height" name="height" class="mt-1" :value="old('height', $map->height)" min="{{ TicketMap::MIN_SIZE }}" max="{{ TicketMap::MAX_SIZE }}" /></div>
                <div><x-input-label for="map-bg" value="Nova imagem de fundo" /><input type="file" id="map-bg" name="background" accept="image/*" class="mt-1 block w-full text-sm text-ink-2"></div>
                <div><x-input-label for="map-bg-url" value="Ou link da imagem" /><x-text-input id="map-bg-url" name="background_url" class="mt-1" placeholder="https://i.imgur.com/…" /></div>
                @if ($map->background)
                    <label class="flex items-center gap-2 text-sm text-ink-2"><input type="checkbox" name="remove_background" value="1"> Tirar a imagem de fundo</label>
                    <div><x-secondary-button type="button" id="fit-image">Ajustar o tamanho à imagem</x-secondary-button></div>
                @endif
                <p class="text-xs text-ink-3 sm:col-span-4">As posições usam as unidades do mapa: mudar o tamanho não move os lugares (diminuir com lugar fora é recusado). Com imagem de fundo, use "Ajustar o tamanho à imagem" para a proporção bater e o desenho não ficar torto.</p>
                <div class="sm:col-span-4 sm:text-right"><x-primary-button>Salvar dados</x-primary-button></div>
            </form>
        </details>

        <section class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-card" id="map-editor">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="{{ $toolButton }}" data-tool="select" aria-pressed="true"><x-icon name="arrow-right" /> Selecionar e mover</button>
                <button type="button" class="{{ $toolButton }}" data-tool="seat" aria-pressed="false"><x-icon name="plus" /> Lugar</button>
                <button type="button" class="{{ $toolButton }}" data-tool="area" aria-pressed="false"><x-icon name="grid" /> Área de orientação</button>
                <details class="relative">
                    <summary class="{{ $toolButton }} cursor-pointer list-none"><x-icon name="list" /> Grade de lugares</summary>
                    <div class="absolute z-20 mt-2 grid w-72 grid-cols-2 gap-3 rounded-2xl border border-line bg-surface p-4 shadow-pop">
                        <div><x-input-label value="Fileiras" /><input type="number" min="1" max="50" value="3" data-grid="rows" class="{{ $input }}"></div>
                        <div><x-input-label value="Colunas" /><input type="number" min="1" max="50" value="5" data-grid="cols" class="{{ $input }}"></div>
                        <div><x-input-label value="Espaço lateral" /><input type="number" min="0" max="500" value="20" data-grid="gap-x" class="{{ $input }}"></div>
                        <div><x-input-label value="Espaço entre fileiras" /><input type="number" min="0" max="500" value="20" data-grid="gap-y" class="{{ $input }}"></div>
                        <p class="col-span-2 text-xs text-ink-3">Usa o prefixo, a categoria, a forma e o tamanho de "Novo lugar". Depois de clicar no botão, clique no mapa onde fica o primeiro lugar.</p>
                        <button type="button" class="{{ $toolButton }} col-span-2 justify-center" data-tool="grid" aria-pressed="false">Colocar grade no mapa</button>
                    </div>
                </details>
                <span class="mx-1 h-6 w-px bg-line"></span>
                <x-secondary-button type="button" data-zoom="out" aria-label="Diminuir">−</x-secondary-button>
                <x-secondary-button type="button" data-zoom="reset">100%</x-secondary-button>
                <x-secondary-button type="button" data-zoom="in" aria-label="Aumentar">+</x-secondary-button>
                <form method="POST" action="{{ route('tickets.maps.layout', [$event, $map]) }}" class="ml-auto" data-layout-form>
                    @csrf @method('PUT')
                    <input type="hidden" name="layout" value="">
                    <x-primary-button>Salvar desenho</x-primary-button>
                </form>
            </div>
            <p class="text-xs text-ink-3" data-tool-help></p>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_18rem]">
                <div class="overflow-auto rounded-xl border border-line bg-subtle p-2" style="max-height: 75vh;">
                    <svg data-map xmlns="http://www.w3.org/2000/svg" class="block select-none" style="touch-action: none; min-width: 320px;" aria-label="Desenho do mapa {{ $map->name }}"></svg>
                </div>

                <aside class="flex flex-col gap-4" data-props>
                    <div class="flex flex-col gap-3 rounded-xl border border-line p-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-ink-3">Novo lugar</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div><x-input-label value="Prefixo do rótulo" /><input type="text" maxlength="10" value="M" data-new="prefix" class="{{ $input }}"></div>
                            <div><x-input-label value="Lugares (pessoas)" /><input type="number" min="1" max="255" value="4" data-new="places" class="{{ $input }}"></div>
                            <div class="col-span-2"><x-input-label value="Categoria (opcional)" /><input type="text" maxlength="60" data-new="category" list="seat-categories" class="{{ $input }}" placeholder="Ex.: Vip"></div>
                            <div class="col-span-2">
                                <x-input-label value="Forma" />
                                <select data-new="shape" class="{{ $select }}">
                                    @foreach (TicketSeat::SHAPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div><x-input-label value="Largura" /><input type="number" min="10" max="1000" value="40" data-new="w" class="{{ $input }}"></div>
                            <div><x-input-label value="Altura" /><input type="number" min="10" max="1000" value="40" data-new="h" class="{{ $input }}"></div>
                        </div>
                        <p class="text-xs text-ink-3">A categoria separa lugares de preço diferente no mesmo mapa (ex.: Vip na frente do palco): cada tipo de ingresso vende uma categoria. Sem categoria, o tipo vende todos.</p>
                    </div>

                    <div class="rounded-xl border border-line p-4 text-sm text-ink-3" data-for="none">Nada selecionado. Clique num lugar ou numa área para editar.</div>

                    <div class="flex flex-col gap-3 rounded-xl border border-line p-4" data-for="seat" style="display: none;">
                        <h3 class="text-xs font-black uppercase tracking-widest text-ink-3" data-seat-count></h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div><x-input-label value="Rótulo" /><input type="text" maxlength="20" data-prop="seat-label" class="{{ $input }}"></div>
                            <div><x-input-label value="Lugares" /><input type="number" min="1" max="255" data-prop="seat-places" class="{{ $input }}"></div>
                            <div class="col-span-2"><x-input-label value="Categoria" /><input type="text" maxlength="60" data-prop="seat-category" list="seat-categories" class="{{ $input }}"></div>
                            <div class="col-span-2">
                                <x-input-label value="Forma" />
                                <select data-prop="seat-shape" class="{{ $select }}">
                                    <option value="">—</option>
                                    @foreach (TicketSeat::SHAPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div><x-input-label value="Largura" /><input type="number" min="10" max="1000" data-prop="seat-w" class="{{ $input }}"></div>
                            <div><x-input-label value="Altura" /><input type="number" min="10" max="1000" data-prop="seat-h" class="{{ $input }}"></div>
                            <label class="col-span-2 flex items-center gap-2 text-sm text-ink-2"><input type="checkbox" data-prop="seat-active"> Ativo (à venda)</label>
                        </div>
                        <p class="text-xs text-warn" data-locked-note style="display: none;">Lugar com ingresso (cadeado): rótulo e categoria travados; não apaga — desative.</p>
                        <div class="flex flex-wrap gap-2">
                            <x-secondary-button type="button" size="sm" data-align="row">Alinhar na fileira</x-secondary-button>
                            <x-secondary-button type="button" size="sm" data-align="column">Alinhar na coluna</x-secondary-button>
                            <x-secondary-button type="button" size="sm" data-distribute="row">Espaçar na fileira</x-secondary-button>
                            <x-secondary-button type="button" size="sm" data-distribute="column">Espaçar na coluna</x-secondary-button>
                        </div>
                        <x-danger-button type="button" size="sm" data-remove>Apagar selecionado(s)</x-danger-button>
                    </div>

                    <div class="flex flex-col gap-3 rounded-xl border border-line p-4" data-for="area" style="display: none;">
                        <h3 class="text-xs font-black uppercase tracking-widest text-ink-3">Área de orientação</h3>
                        <div><x-input-label value="Nome" /><input type="text" maxlength="60" data-prop="area-label" class="{{ $input }}" placeholder="Ex.: Palco"></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label value="Forma" />
                                <select data-prop="area-shape" class="{{ $select }}">
                                    @foreach (TicketMap::AREA_SHAPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Cor" />
                                <select data-prop="area-color" class="{{ $select }}">
                                    @foreach (TicketMap::AREA_COLORS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div><x-input-label value="Largura" /><input type="number" min="10" data-prop="area-w" class="{{ $input }}"></div>
                            <div><x-input-label value="Altura" /><input type="number" min="10" data-prop="area-h" class="{{ $input }}"></div>
                        </div>
                        <p class="text-xs text-ink-3">Só orientação: não é clicável nem vendida.</p>
                        <x-danger-button type="button" size="sm" data-remove>Apagar área</x-danger-button>
                    </div>
                </aside>
            </div>
            <p class="text-sm text-ink-2" data-editor-status></p>
            <datalist id="seat-categories">
                @foreach ($map->categories() as $category)<option value="{{ $category }}">@endforeach
                @foreach ($map->types->pluck('seat_category')->filter()->unique() as $category)<option value="{{ $category }}">@endforeach
            </datalist>
        </section>
    </x-page>

    <script src="{{ asset('js/tickets/seat-map.js') }}?v={{ filemtime(public_path('js/tickets/seat-map.js')) }}"></script>
    <script>
        (function () {
            const root = document.getElementById('map-editor');
            TicketSeatMap.editor(root, @json($layout));

            const fit = document.getElementById('fit-image');
            if (fit) {
                fit.addEventListener('click', () => {
                    const img = new Image();
                    img.onload = () => {
                        // Mantém a proporção da imagem com o lado maior em até 1600 unidades.
                        const scale = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight));
                        document.getElementById('map-width').value = Math.max({{ TicketMap::MIN_SIZE }}, Math.round(img.naturalWidth * scale));
                        document.getElementById('map-height').value = Math.max({{ TicketMap::MIN_SIZE }}, Math.round(img.naturalHeight * scale));
                    };
                    img.src = @json($map->backgroundUrl());
                });
            }
        })();
    </script>
</x-app-layout>
