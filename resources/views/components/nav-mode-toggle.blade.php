{{-- Modo de navegação: Módulos (nova) ou os menus de antes, Lateral e Superior.
     Estado no laraShell (navMode / setNav). --}}
@php
    $modes = ['areas' => 'Módulos', 'side' => 'Lateral', 'top' => 'Superior'];
@endphp

<div class="px-3.5 py-2.5">
    <p class="mb-1.5 text-[11px] font-bold uppercase tracking-[0.06em] text-ink-3">Navegação</p>
    <div class="flex gap-1 rounded-full bg-subtle p-1" role="group" aria-label="Modo de navegação">
        @foreach ($modes as $mode => $label)
            <button type="button" @click="setNav('{{ $mode }}')" :aria-pressed="(navMode === '{{ $mode }}').toString()"
                class="flex-1 rounded-full px-2 py-1 text-xs font-bold text-ink-2 transition hover:text-ink aria-pressed:bg-surface aria-pressed:text-ink aria-pressed:shadow-card">
                {{ $label }}
            </button>
        @endforeach
    </div>
</div>
