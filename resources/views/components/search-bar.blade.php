@props([
    'mode' => 'server',
    'name' => 'q',
    'placeholder' => 'Buscar',
    'label' => 'Buscar',
    'target' => null,
    'action' => null,
    'id' => null,
    'filters' => [],
])

{{--
    Busca de toda tela que lista vários registros. Dois modos:

    server — formulário GET (`?q=`), para telas paginadas: a busca precisa
             ir ao banco. Os outros parâmetros da URL (filtros, ordenação)
             viajam junto; a página volta para a 1.
             <x-search-bar placeholder="Buscar placa ou nome" />

    client — filtra na própria página, sem recarregar, para listas que já
             vêm inteiras. `target` aponta o contêiner; cada item buscável
             leva `data-search="..."` (o texto visível também conta).
             <x-search-bar mode="client" target="#veiculos" />

    Filtros extras no mesmo formulário (só no server): os campos vão no slot
    `controls` e os nomes deles em `filters`, para não virarem hidden
    duplicado e para o "Limpar" zerar tudo.
             <x-search-bar name="busca" :filters="['status']">
                 <x-slot:controls><x-select-input name="status">…</x-select-input></x-slot:controls>
             </x-search-bar>
--}}
@php
    $inputId = $id ?? 'busca-' . $name;
    $inputClass = 'h-11 w-full rounded-full border border-line-strong bg-surface pl-10 pr-4 text-ink placeholder:text-ink-3 shadow-none transition focus:border-grena focus:ring-4 focus:ring-grena-tint';
@endphp

@if ($mode === 'client')
    @once
        <script>
            // Fica no corpo da página, antes do Alpine iniciar (ele vem como
            // módulo, que roda depois do HTML), para o x-data já encontrar.
            window.laraSearch = window.laraSearch || function (target) {
                var DIACRITICS = new RegExp('[' + String.fromCharCode(0x300) + '-' + String.fromCharCode(0x36f) + ']', 'g');
                function norm(text) {
                    return String(text || '').normalize('NFD').replace(DIACRITICS, '').toLowerCase();
                }
                return {
                    q: '',
                    count: null,
                    filter: function () {
                        var root = document.querySelector(target);
                        if (!root) {
                            return;
                        }
                        var needle = norm(this.q.trim());
                        var shown = 0;
                        root.querySelectorAll('[data-search]').forEach(function (el) {
                            var match = !needle || norm(el.getAttribute('data-search') + ' ' + el.textContent).indexOf(needle) !== -1;
                            el.style.display = match ? '' : 'none';
                            // Com Cartões/Lista, o mesmo registro existe nas
                            // duas vistas: conta só a que está na tela.
                            var pane = el.closest('[data-view-pane]');
                            if (match && (!pane || pane.style.display !== 'none')) {
                                shown++;
                            }
                        });
                        this.count = needle ? shown : null;
                    },
                };
            };
        </script>
    @endonce

    <div {{ $attributes->merge(['class' => 'flex w-full max-w-xl flex-col gap-1.5']) }} x-data="laraSearch(@js($target))" role="search">
        <label for="{{ $inputId }}" class="sr-only">{{ $label }}</label>
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-ink-3" />
            <input type="search" id="{{ $inputId }}" x-model="q" x-on:input="filter()" placeholder="{{ $placeholder }}" autocomplete="off" class="{{ $inputClass }}">
        </div>
        <p class="px-1 text-[13px] text-ink-3" x-show="count !== null" x-cloak aria-live="polite">
            <span x-show="count > 0" x-text="count + (count === 1 ? ' resultado' : ' resultados')"></span>
            <span x-show="count === 0">Nada encontrado. Confira a grafia ou busque por outra parte do nome.</span>
        </p>
    </div>
@else
    @php
        $value = request()->query($name);
        $keep = collect(request()->query())->except(array_merge([$name, 'page'], $filters));
        $clearUrl = url()->current() . ($keep->isNotEmpty() ? '?' . http_build_query($keep->all()) : '');
        $filtered = filled($value) || collect($filters)->contains(fn ($filter) => filled(request()->query($filter)));
    @endphp

    <form method="GET" action="{{ $action ?? url()->current() }}" role="search" {{ $attributes->merge(['class' => 'flex w-full flex-wrap items-center gap-2 ' . (isset($controls) ? '' : 'max-w-xl')]) }}>
        @foreach ($keep as $key => $kept)
            @foreach ((array) $kept as $nested => $item)
                @if (is_scalar($item))
                    <input type="hidden" name="{{ is_array($kept) ? $key . '[' . $nested . ']' : $key }}" value="{{ $item }}">
                @endif
            @endforeach
        @endforeach

        <label for="{{ $inputId }}" class="sr-only">{{ $label }}</label>
        <div class="relative min-w-[14rem] flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-ink-3" />
            <input type="search" id="{{ $inputId }}" name="{{ $name }}" value="{{ is_scalar($value) ? $value : '' }}" placeholder="{{ $placeholder }}" autocomplete="off" class="{{ $inputClass }}">
        </div>
        @isset($controls)
            {{ $controls }}
        @endisset
        <x-primary-button>Buscar</x-primary-button>
        @if ($filtered)
            <a href="{{ $clearUrl }}" class="whitespace-nowrap px-1 text-sm font-bold text-grena-ink hover:underline">Limpar</a>
        @endif
    </form>
@endif
