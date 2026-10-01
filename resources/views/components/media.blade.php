@props([
    'src' => null,
    'alt' => '',
    'area' => null,
    'icon' => 'image',
    'initials' => null,
    'logo' => false,
    'ratio' => 'wide',
])

{{--
    Imagem de cartão. Sempre desenha um substituto na cor da área (ícone ou
    iniciais); se houver `src`, a foto cobre o substituto, e se ela falhar ao
    carregar (arquivo sumido), o <img> se remove e o substituto aparece — sem
    depender de serviço de placeholder externo.

    `initials` desenha um círculo (pessoa); com `logo`, um retângulo
    (empresa). Sobreposições (data, setor) vão no slot, com x-media-tag.
--}}
@php
    $ratios = [
        'wide' => 'aspect-[16/10]',
        'video' => 'aspect-video',
        'short' => 'aspect-[21/9]',
        'sq' => 'aspect-[4/3]',
    ];
    $aspect = $ratios[$ratio] ?? $ratios['wide'];
@endphp

<div {{ $attributes->merge(['class' => "relative grid place-items-center overflow-hidden {$aspect}"]) }}
    style="{{ \App\View\AreaColor::style($area) }} background-image: linear-gradient(140deg, rgb(var(--c)) 0%, color-mix(in srgb, rgb(var(--c)) 82%, rgb(var(--ci))) 100%);">
    <span class="pointer-events-none absolute inset-0" aria-hidden="true"
        style="background-image: repeating-linear-gradient(135deg, transparent 0 14px, rgb(var(--ci) / .06) 14px 15px);"></span>

    @if ($initials && $logo)
        <span class="relative grid h-[72px] w-24 place-items-center rounded-2xl bg-surface font-display text-2xl font-bold tracking-tight shadow-card" style="color: rgb(var(--ci))" aria-hidden="true">{{ $initials }}</span>
    @elseif ($initials)
        <span class="relative grid aspect-square w-[46%] max-w-[120px] place-items-center rounded-full bg-surface font-display text-3xl font-semibold tracking-tight shadow-card sm:text-4xl" style="color: rgb(var(--ci))" aria-hidden="true">{{ $initials }}</span>
    @else
        <x-icon :name="$icon" class="relative w-[30%] max-w-[96px] h-auto opacity-55" />
    @endif

    @if ($src)
        <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
    @endif

    {{ $slot }}
</div>
