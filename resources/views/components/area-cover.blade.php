@props([
    'area',
    'title',
    'icon' => 'grid',
    'eyebrow' => 'Módulo',
    'tabs' => [],
])

{{--
    Capa da área: faixa na cor da área, com símbolo, nome, ações (slot) e as
    páginas da área como abas.

    tabs: [['label' => 'Avisos', 'href' => route(...), 'active' => true, 'badge' => 3], ...]
--}}
<section class="relative overflow-hidden" style="{{ \App\View\AreaColor::style($area) }}">
    <x-icon :name="$icon" class="pointer-events-none absolute -right-8 -top-10 h-56 w-56 opacity-[.12]" />

    <div class="relative mx-auto flex max-w-[1200px] flex-col gap-4 px-4 pt-6 sm:px-6 lg:px-8 {{ $tabs ? '' : 'pb-6' }}">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <span class="grid h-[54px] w-[54px] shrink-0 place-items-center rounded-2xl bg-surface" style="color: rgb(var(--ci))">
                    <x-icon :name="$icon" class="h-7 w-7" />
                </span>
                <div>
                    <small class="block text-xs font-bold uppercase tracking-[0.1em] opacity-80">{{ $eyebrow }}</small>
                    <h1 class="font-display text-[22px] font-semibold leading-tight tracking-tight sm:text-[28px]">{{ $title }}</h1>
                </div>
            </div>
            @if (trim($slot) !== '')
                <div class="flex flex-wrap items-center gap-2.5">{{ $slot }}</div>
            @endif
        </div>

        @if ($tabs)
            <nav class="-mx-1.5 flex gap-0.5 overflow-x-auto px-1.5 [scrollbar-width:none]" aria-label="Páginas de {{ $title }}">
                @foreach ($tabs as $tab)
                    <a href="{{ $tab['href'] }}" @if (! empty($tab['active'])) aria-current="page" @endif
                        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-t-xl px-4 pb-3 pt-2.5 font-semibold no-underline transition {{ ! empty($tab['active']) ? 'bg-canvas text-ink' : 'opacity-80 hover:opacity-100' }}"
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
