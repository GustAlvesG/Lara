@props(['title', 'lead' => null])

{{--
    Cartão das telas de fora do painel (entrar, registrar, senha): logo,
    título e o formulário. O carmim fica só no logo; a ação é grená.
--}}
<div {{ $attributes->merge(['class' => 'w-full max-w-md rounded-card bg-surface p-6 shadow-pop sm:p-8']) }}>
    <div class="mb-6 flex flex-col items-center text-center">
        <span class="mb-4 grid h-16 w-16 place-items-center rounded-2xl bg-white shadow-card">
            <x-application-logo :width="'30px'" :height="'38px'" :color="'#A00001'" />
        </span>
        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
        @if ($lead)
            <p class="mt-1 text-sm text-ink-2">{{ $lead }}</p>
        @endif
    </div>

    {{ $slot }}
</div>
