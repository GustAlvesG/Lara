@props([
    'area',
    'title',
    'icon' => 'grid',
    'eyebrow' => 'Módulo',
    'tabs' => [],
])

{{--
    Capa do módulo (refeita em 11/11/2026, junto com a barra "ilha"): cartão
    arredondado na cor da área, alinhado à largura da página, com o símbolo,
    o nome, as ações (slot) e as páginas do módulo como abas numa barra de
    pílulas. Entra uma vez ao abrir (auth-enter, partials/entrada-estilos).

    tabs: [['label' => 'Avisos', 'href' => route(...), 'active' => true, 'badge' => 3], ...]
--}}
<div class="mx-auto w-full max-w-[1200px] px-4 pt-4 sm:px-6 lg:px-8">
    <section class="relative isolate overflow-hidden rounded-[28px] shadow-card" style="{{ \App\View\AreaColor::style($area) }}">
        {{-- Marca d'água: o símbolo do módulo, grande, no canto. --}}
        <x-icon :name="$icon" class="pointer-events-none absolute -right-10 -top-14 -z-10 h-64 w-64 opacity-[.10]" />
        <span class="pointer-events-none absolute -bottom-24 -left-16 -z-10 h-64 w-64 rounded-full opacity-[.08]" style="background-color: rgb(var(--ci))" aria-hidden="true"></span>

        {{-- A entrada (auth-enter) vai no conteúdo, e não na seção: ela anima a
             opacidade dos filhos diretos até 1 e apagaria a transparência das
             marcas d'água, que cobririam o título. --}}
        <div class="auth-enter flex flex-col gap-5 p-5 sm:p-7">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex min-w-0 items-center gap-4">
                    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-[18px] bg-surface shadow-card" style="color: rgb(var(--ci))">
                        <x-icon :name="$icon" class="h-7 w-7" />
                    </span>
                    <div class="min-w-0">
                        <small class="block text-xs font-bold uppercase tracking-[0.14em] opacity-75">{{ $eyebrow }}</small>
                        <h1 class="truncate font-display text-[24px] font-semibold leading-tight tracking-tight sm:text-[32px]">{{ $title }}</h1>
                    </div>
                </div>
                @if (trim($slot) !== '')
                    <div class="flex flex-wrap items-center gap-2.5">{{ $slot }}</div>
                @endif
            </div>

            @if ($tabs)
                <nav class="-mx-1 flex w-fit max-w-full gap-1 overflow-x-auto rounded-full p-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    style="background-color: rgb(var(--surface) / .5)" aria-label="Páginas de {{ $title }}">
                    @foreach ($tabs as $tab)
                        <a href="{{ $tab['href'] }}" @if (! empty($tab['active'])) aria-current="page" @endif
                            class="inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-4 text-sm font-bold no-underline transition focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint {{ ! empty($tab['active']) ? 'bg-surface text-ink shadow-card' : 'hover:bg-surface/60' }}"
                            @if (empty($tab['active'])) style="color: rgb(var(--ci))" @endif>
                            {{ $tab['label'] }}
                            @if (! empty($tab['badge']))
                                <span class="rounded-full px-1.5 py-px font-mono text-[10.5px] font-semibold" style="background-color: rgb(var(--ci)); color: rgb(var(--c))">{{ $tab['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>
    </section>
</div>
