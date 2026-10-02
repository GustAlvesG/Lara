{{--
    Itens do menu da conta.

    Um só para os quatro dropdowns (Módulos, lateral expandida, lateral
    recolhida e barra superior) não saírem de sincronia.

    Depende de estar dentro de um x-data com `userOpen` (os quatro têm) e do
    escopo do laraShell, de onde vem `organizerOpen`.
--}}
@php
    $itemClasses = 'flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left text-sm text-ink no-underline transition hover:bg-subtle';
    $glyphClasses = 'h-4 w-4 text-ink-3';
    $links = array_filter(
        \App\View\Navigation::accountLinks(),
        fn ($link) => ! ($link['permission'] ?? null) || auth()->user()?->can($link['permission'])
    );
@endphp

<div class="flex flex-col p-1">
    @foreach ($links as $link)
        <a href="{{ route($link['route']) }}" class="{{ $itemClasses }}">
            <x-icon :name="$link['glyph']" class="{{ $glyphClasses }}" />{{ $link['label'] }}
        </a>
    @endforeach

    <button type="button" @click="organizerOpen = true; userOpen = false" class="{{ $itemClasses }}">
        <x-icon name="sliders" class="{{ $glyphClasses }}" />Organizar menu
    </button>
</div>

<div class="border-t border-line">
    <x-theme-toggle />
    <x-nav-mode-toggle />
</div>

<form method="POST" action="{{ route('logout') }}" class="border-t border-line p-1">
    @csrf
    <button type="submit" class="{{ $itemClasses }} text-danger hover:bg-danger-soft">
        <x-icon name="logout" class="h-4 w-4" />Sair
    </button>
</form>
