@props(['key', 'default' => 'cards'])

{{--
    Alterna a mesma lista entre Cartões e Lista. A escolha fica salva neste
    navegador, por tela (`key`).

    <x-view-switch key="frota">
        <x-slot:toolbar><x-search-bar mode="client" target="#frota" /></x-slot:toolbar>
        <x-slot:cards>...</x-slot:cards>
        <x-slot:list>...</x-slot:list>
    </x-view-switch>
--}}
@once
    <script>
        window.laraViewSwitch = window.laraViewSwitch || function (key, fallback) {
            var storageKey = 'laraView:' + key;
            var initial = fallback;
            try {
                var saved = localStorage.getItem(storageKey);
                if (saved === 'cards' || saved === 'list') {
                    initial = saved;
                }
            } catch (e) {}
            return {
                view: initial,
                set: function (view) {
                    this.view = view;
                    try {
                        localStorage.setItem(storageKey, view);
                    } catch (e) {}
                    // A contagem da busca considera só a vista visível.
                    this.$nextTick(function () {
                        this.$root.querySelectorAll('input[type=search]').forEach(function (input) {
                            input.dispatchEvent(new Event('input'));
                        });
                    }.bind(this));
                },
            };
        };
    </script>
@endonce

<div {{ $attributes->merge(['class' => 'flex flex-col gap-4']) }} x-data="laraViewSwitch(@js($key), @js($default))">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1">{{ $toolbar ?? '' }}</div>
        <div class="inline-flex rounded-full bg-subtle p-[3px]" role="group" aria-label="Modo de exibição">
            @foreach (['cards' => ['grid', 'Cartões'], 'list' => ['list', 'Lista']] as $view => [$icon, $label])
                <button type="button" x-on:click="set('{{ $view }}')" x-bind:aria-pressed="view === '{{ $view }}'"
                    class="inline-flex h-[30px] items-center gap-1.5 rounded-full px-3 text-[13px] font-bold text-ink-2 transition aria-pressed:bg-surface aria-pressed:text-ink aria-pressed:shadow-card">
                    <x-icon :name="$icon" class="h-[15px] w-[15px]" />{{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div data-view-pane="cards" x-show="view === 'cards'" @if ($default !== 'cards') x-cloak @endif>{{ $cards }}</div>
    <div data-view-pane="list" x-show="view === 'list'" @if ($default !== 'list') x-cloak @endif>{{ $list }}</div>
</div>
