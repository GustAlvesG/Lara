@props(['narrow' => false, 'wide' => false])

{{-- Miolo das telas repaginadas: largura máxima, margens laterais e respiro.
     `narrow` para leitura (detalhe de aviso, formulários); `wide` para
     editor com prévia ao lado (contratos). --}}
<div {{ $attributes->merge(['class' => 'mx-auto flex w-full flex-col gap-4 px-4 pb-12 pt-6 sm:px-6 lg:px-8 ' . ($narrow ? 'max-w-[860px]' : ($wide ? 'max-w-[1680px]' : 'max-w-[1200px]'))]) }}>
    {{ $slot }}
</div>
