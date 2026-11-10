@props([
    'id',
    'name' => null,
    'autocomplete' => 'current-password',
    'invalid' => false,
    'describedby' => null,
])

{{--
    Campo de senha com o botão de mostrar/ocultar e o aviso de Caps Lock.
    O comportamento vem do script em <x-auth-layout> (data-password-toggle e
    data-caps-warning); sem JS, é um campo de senha comum.
--}}
@php
    $described = trim($id . '-caps ' . ($describedby ?? ''));
@endphp
<div class="relative">
    <input type="password" id="{{ $id }}" name="{{ $name ?? $id }}" required autocomplete="{{ $autocomplete }}"
           aria-describedby="{{ $described }}" @if ($invalid) aria-invalid="true" @endif
           {{ $attributes->merge(['class' => 'h-12 w-full rounded-xl border bg-surface pl-4 pr-14 text-base text-ink placeholder:text-ink-3 transition focus:border-grena focus:ring-4 focus:ring-grena-tint ' . ($invalid ? 'border-danger' : 'border-line-strong')]) }}>
    <button type="button" data-password-toggle aria-controls="{{ $id }}" aria-pressed="false" aria-label="Mostrar senha"
            class="absolute right-0.5 top-0.5 grid h-11 w-11 place-items-center rounded-[10px] text-ink-3 transition hover:bg-subtle hover:text-ink focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
        <span data-eye="show"><x-icon name="eye" class="h-5 w-5" /></span>
        <span data-eye="hide" hidden><x-icon name="eye-off" class="h-5 w-5" /></span>
    </button>
</div>
<p id="{{ $id }}-caps" data-caps-warning hidden class="mt-1.5 text-sm font-medium text-warn">Caps Lock ligado.</p>
