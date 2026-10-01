@props(['side' => 'left'])

{{-- Etiqueta sobre a imagem do cartão (horário, setor, quantidade). --}}
<span {{ $attributes->merge(['class' => 'absolute top-2.5 z-[1] rounded-full bg-surface px-2.5 py-1 text-xs font-bold text-ink shadow-card ' . ($side === 'right' ? 'right-2.5' : 'left-2.5')]) }}>
    {{ $slot }}
</span>
