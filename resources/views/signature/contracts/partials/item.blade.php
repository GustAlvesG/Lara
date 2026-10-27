{{--
    Um item de cláusula. $c = chave da cláusula; $k = chave do item; $i = {uid, level, html}.
    Nível 2 é o subitem (1.1.1), recuado.
--}}
@php
    $n = fn (string $campo) => 'clauses[' . $c . '][items][' . $k . '][' . $campo . ']';
    $nivel = (int) ($i['level'] ?? 1) === 2 ? 2 : 1;
@endphp
<div class="ct-row flex items-start gap-2 {{ $nivel === 2 ? 'ml-8' : '' }}" data-row="item">
    <input type="hidden" name="{{ $n('uid') }}" value="{{ $i['uid'] ?? '' }}">
    <input type="hidden" name="{{ $n('level') }}" value="{{ $nivel }}" data-level>
    <div class="min-w-0 flex-1">
        @include('signature.contracts.partials.rich', ['name' => $n('html'), 'value' => $i['html'] ?? '', 'placeholder' => 'Texto do item…'])
    </div>
    <div class="flex flex-col gap-1">
        <button type="button" data-indent class="rounded-md px-2 py-1 text-[11px] font-bold text-ink-2 hover:bg-subtle" title="Subitem (1.1.1) ou item (1.1)">{{ $nivel === 2 ? '← Item' : 'Subitem →' }}</button>
        <button type="button" data-move="up" class="rounded-md px-2 py-1 text-xs text-ink-2 hover:bg-subtle" title="Subir">↑</button>
        <button type="button" data-move="down" class="rounded-md px-2 py-1 text-xs text-ink-2 hover:bg-subtle" title="Descer">↓</button>
        <button type="button" data-remove class="rounded-md px-2 py-1 text-xs font-bold text-danger hover:bg-danger-soft" title="Remover item">✕</button>
    </div>
</div>
