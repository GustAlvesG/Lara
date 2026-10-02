@props(['plate', 'size' => 'md'])

{{--
    Placa no padrão Mercosul, com a faixa em grená. Serve para o padrão novo
    (ABC1D23) e para o antigo (ABC1234): só normaliza para maiúsculas, sem
    hífen nem espaço.
--}}
@php
    $text = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $plate));
    $sizes = [
        'sm' => ['min-w-[72px] rounded border', 'text-[5px] py-px', 'text-[11px] px-1.5 pt-0.5 pb-1'],
        'md' => ['min-w-[84px] rounded-md border-[1.5px]', 'text-[5.5px] py-0.5', 'text-xs px-2 pt-[3px] pb-1'],
        'lg' => ['min-w-[120px] rounded-lg border-2', 'text-[7.5px] py-[3px]', 'text-lg px-2.5 pt-1 pb-1.5'],
    ];
    [$box, $band, $digits] = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex flex-col overflow-hidden border-[#1a1a1a] bg-white text-center leading-none text-[#111] align-middle {$box}"]) }} title="Placa {{ $text }}">
    <span class="block bg-plate-band font-sans font-bold tracking-[0.22em] text-white {{ $band }}" aria-hidden="true">BRASIL</span>
    <span class="block font-mono font-semibold tracking-[0.08em] {{ $digits }}">{{ $text }}</span>
</span>
