@props([
    'title' => '',
    'glyph' => 'grid',
    'href' => null,
    'linkLabel' => 'Abrir',
    'area' => 'inicio',
])

{{--
    Painel de um módulo no Dashboard (10/11/2026): faixa na cor da área com o
    símbolo, o nome e o atalho; o conteúdo embaixo, na superfície. Os números
    vão em x-dashboard.metric (sem cartão dentro de cartão).
--}}
<section {{ $attributes->merge(['class' => 'flex min-w-0 flex-col overflow-hidden rounded-card bg-surface shadow-card']) }}
         style="{{ \App\View\AreaColor::style($area, paint: false) }}">
    <header class="flex items-center justify-between gap-3 px-5 py-4" style="background-color: rgb(var(--c)); color: rgb(var(--ci))">
        <div class="flex min-w-0 items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-surface">
                <x-icon :name="$glyph" class="h-[18px] w-[18px]" />
            </span>
            <h2 class="truncate font-display text-base font-semibold tracking-tight">{{ __($title) }}</h2>
        </div>
        @if ($href)
            <a href="{{ $href }}" class="group inline-flex min-h-9 items-center gap-1 whitespace-nowrap rounded-full px-3 text-sm font-bold no-underline transition hover:bg-surface/60 focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                {{ __($linkLabel) }} <x-icon name="arrow-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
            </a>
        @endif
    </header>

    <div class="flex flex-1 flex-col gap-5 p-5">
        {{ $slot }}
    </div>
</section>
