@props([
    'href' => '#',
    'label' => '',
    'glyph' => 'grid',
    'area' => 'inicio',
])

{{-- Atalho do painel: símbolo na cor da área e o nome da tela. --}}
<a href="{{ $href }}" style="{{ \App\View\AreaColor::style($area, paint: false) }}"
   class="group flex flex-col items-center justify-center gap-2 rounded-card bg-surface p-5 text-center shadow-card transition hover:shadow-pop focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
    <span class="grid h-11 w-11 place-items-center rounded-2xl transition group-hover:scale-105" style="background-color: rgb(var(--c)); color: rgb(var(--ci))">
        <x-icon :name="$glyph" class="h-5 w-5" />
    </span>
    <span class="text-sm font-bold text-ink">{{ __($label) }}</span>
</a>
