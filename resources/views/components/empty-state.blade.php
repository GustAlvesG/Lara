@props(['icon' => 'search'])

{{--
    Lista vazia ou busca sem resultado: diz o que aconteceu e o que fazer.
    Refeito em 11/11/2026: centrado, com o símbolo num círculo grená claro.
--}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 rounded-[22px] border-[1.5px] border-dashed border-line-strong bg-surface/70 px-6 py-10 text-center text-[15px] text-ink-2']) }}>
    <span class="grid h-14 w-14 place-items-center rounded-full bg-grena-tint text-grena-ink">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>
    <div class="min-w-0 max-w-md">{{ $slot }}</div>
</div>
