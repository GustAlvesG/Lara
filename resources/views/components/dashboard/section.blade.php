@props([
    'title' => '',
    'glyph' => 'grid',
    'href' => null,
    'linkLabel' => 'Abrir',
    'area' => 'inicio',
])

{{-- Bloco de uma área no painel: símbolo e nome na cor da área, e o atalho para ela. --}}
<section class="flex flex-col gap-3" style="{{ \App\View\AreaColor::style($area, paint: false) }}">
    <div class="flex items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl" style="background-color: rgb(var(--c)); color: rgb(var(--ci))">
                <x-icon :name="$glyph" class="h-5 w-5" />
            </span>
            <h2 class="truncate font-display text-lg font-semibold tracking-tight text-ink">{{ __($title) }}</h2>
        </div>
        @if($href)
            <a href="{{ $href }}" class="inline-flex items-center gap-1 whitespace-nowrap text-sm font-bold text-grena-ink hover:underline">
                {{ __($linkLabel) }} <x-icon name="arrow-right" class="h-3.5 w-3.5" />
            </a>
        @endif
    </div>
    {{ $slot }}
</section>
