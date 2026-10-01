@props(['icon' => 'search'])

{{-- Lista vazia ou busca sem resultado: diz o que aconteceu e o que fazer. --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-3 rounded-2xl border-[1.5px] border-dashed border-line-strong bg-surface p-5 text-ink-2']) }}>
    <x-icon :name="$icon" class="h-6 w-6 text-ink-3" />
    <div class="min-w-0">{{ $slot }}</div>
</div>
