@props(['href' => null])

{{--
    Cartão de lista: imagem (slot `media`, normalmente um x-media), corpo e
    rodapé (slot `footer`: status à esquerda, ação à direita).

    Para a busca na própria página (x-search-bar mode="client"), passe
    `data-search="texto que a busca deve achar"`.
--}}
<article {{ $attributes->merge(['class' => 'flex min-w-0 flex-col overflow-hidden rounded-card bg-surface shadow-card']) }}>
    @isset($media)
        @if ($href)
            <a href="{{ $href }}" class="block focus:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-grena-tint" tabindex="-1">{{ $media }}</a>
        @else
            {{ $media }}
        @endif
    @endisset

    <div class="flex flex-1 flex-col gap-1.5 px-4 pb-4 pt-3.5">
        {{ $slot }}

        @isset($footer)
            <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-2">
                {{ $footer }}
            </div>
        @endisset
    </div>
</article>
