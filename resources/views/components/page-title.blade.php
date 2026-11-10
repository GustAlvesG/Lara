@props(['title', 'back' => null])

{{--
    Título da tela repaginada, dentro do x-page: nome, linha de apoio (slot) e
    ações (slot `actions`, à direita). Existe além da capa da área porque a
    capa é do módulo (e algumas telas a dispensam); este é o título da tela.

    `back` desenha a seta de voltar (telas de detalhe).
--}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-3']) }}>
    <div class="flex min-w-0 items-center gap-3">
        @if ($back)
            <a href="{{ $back }}" aria-label="Voltar"
                class="group grid h-11 w-11 shrink-0 place-items-center rounded-full bg-surface text-ink-2 shadow-card ring-1 ring-line transition hover:text-ink hover:ring-line-strong focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                <x-icon name="arrow-right" class="h-[18px] w-[18px] rotate-180 transition group-hover:-translate-x-0.5 motion-reduce:transform-none" />
            </a>
        @endif
        <div class="min-w-0">
            <h2 class="font-display text-[22px] font-semibold leading-tight tracking-tight text-ink sm:text-[26px]">{{ $title }}</h2>
            @if (trim($slot) !== '')
                <div class="mt-1.5 max-w-3xl text-[15px] leading-relaxed text-ink-2">{{ $slot }}</div>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
