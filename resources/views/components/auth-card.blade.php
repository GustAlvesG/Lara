@props(['title', 'lead' => null, 'eyebrow' => null, 'variant' => 'card'])

{{--
    Cabeçalho + formulário das telas de fora do painel (entrar, registrar,
    senha). O carmim fica só no logo; a ação é grená.

    variant="card" (padrão): cartão centrado, com o logo — telas de senha e
    de confirmação, dentro do <x-guest-layout>.
    variant="flush": sem caixa e sem logo, título à esquerda — entrar e criar
    conta, dentro do <x-auth-layout>, onde o logo já está sobre a foto.
--}}
@if ($variant === 'flush')
    <div {{ $attributes->merge(['class' => 'auth-enter w-full']) }}>
        <div class="mb-7">
            @if ($eyebrow)
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-grena-ink">{{ $eyebrow }}</p>
            @endif
            <h1 class="mt-2 font-display text-[28px] font-semibold leading-tight tracking-tight text-ink sm:text-[32px]">{{ $title }}</h1>
            @if ($lead)
                <p class="mt-2 text-base leading-relaxed text-ink-2">{{ $lead }}</p>
            @endif
        </div>

        {{ $slot }}
    </div>
@else
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
@endif
