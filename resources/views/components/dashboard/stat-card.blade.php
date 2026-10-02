@props([
    'glyph' => 'grid',
    'label' => '',
    'value' => '',
    'sub' => null,
    'tone' => null,
    'href' => null,
])

@php
    /*
     | Número do painel. O símbolo herda a cor da área do bloco em volta
     | (--c/--ci do x-dashboard.section); `tone` só entra quando o número é
     | um estado: ok (permitido), danger (negado), warn (atenção).
     */
    $tones = [
        'ok' => 'bg-ok-soft text-ok',
        'danger' => 'bg-danger-soft text-danger',
        'warn' => 'bg-warn-soft text-warn',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'flex items-center gap-4 rounded-card bg-surface p-4 shadow-card' . ($href ? ' transition hover:shadow-pop focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint' : '')]) }}>
    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl {{ $tones[$tone] ?? '' }}"
        @unless(isset($tones[$tone])) style="background-color: rgb(var(--c)); color: rgb(var(--ci))" @endunless>
        <x-icon :name="$glyph" class="h-5 w-5" />
    </span>
    <div class="min-w-0">
        <p class="truncate font-mono text-2xl font-semibold leading-tight text-ink">{{ $value }}</p>
        <p class="text-xs font-bold text-ink-2">{{ __($label) }}</p>
        @if($sub)
            <p class="mt-0.5 text-[11px] font-bold text-ink-3">{{ $sub }}</p>
        @endif
    </div>
</{{ $tag }}>
