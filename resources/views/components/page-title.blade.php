@props(['title', 'back' => null])

{{--
    Título da tela repaginada, dentro do x-page: nome, linha de apoio (slot) e
    ações (slot `actions`, à direita). Existe além da capa da área porque a
    capa só aparece na navegação por Módulos; nos menus de antes, é este o
    título da tela.

    `back` desenha a seta de voltar (telas de detalhe).
--}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-3']) }}>
    <div class="flex min-w-0 items-center gap-3">
        @if ($back)
            <a href="{{ $back }}" aria-label="Voltar"
                class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-line-strong bg-surface text-ink-2 transition hover:text-ink">
                <x-icon name="arrow-right" class="h-4 w-4 rotate-180" />
            </a>
        @endif
        <div class="min-w-0">
            <h2 class="font-display text-xl font-semibold leading-tight tracking-tight text-ink sm:text-[22px]">{{ $title }}</h2>
            @if (trim($slot) !== '')
                <div class="mt-1 text-sm text-ink-2">{{ $slot }}</div>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
