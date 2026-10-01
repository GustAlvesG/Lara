{{-- Tema: claro, escuro ou o do sistema (padrão). Ver partials/theme-script. --}}
@php
    $themes = ['light' => ['sun', 'Claro'], 'dark' => ['moon', 'Escuro'], 'system' => ['monitor', 'Sistema']];
@endphp

<div x-data="{ theme: window.laraTheme ? window.laraTheme.get() : 'system' }"
    @lara-theme-changed.window="theme = $event.detail"
    class="px-3.5 py-2.5">
    <p class="mb-1.5 text-[11px] font-bold uppercase tracking-[0.06em] text-ink-3">Tema</p>
    <div class="flex gap-1 rounded-full bg-subtle p-1" role="group" aria-label="Tema">
        @foreach ($themes as $value => [$glyph, $label])
            <button type="button" @click="window.laraTheme.set('{{ $value }}')" :aria-pressed="(theme === '{{ $value }}').toString()"
                class="inline-flex flex-1 items-center justify-center gap-1 rounded-full px-2 py-1 text-xs font-bold text-ink-2 transition hover:text-ink aria-pressed:bg-surface aria-pressed:text-ink aria-pressed:shadow-card">
                <x-icon :name="$glyph" class="h-3.5 w-3.5" />{{ $label }}
            </button>
        @endforeach
    </div>
</div>
