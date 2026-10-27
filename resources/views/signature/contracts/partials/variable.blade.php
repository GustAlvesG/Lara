{{--
    Uma variável do contrato. $k = chave do formulário; $v = {key, label, type, value}.
    O marcador ([[chave]]) é o que se escreve no texto — ou se escolhe em "Inserir variável".
--}}
@php
    $f = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
    $n = fn (string $campo) => 'variables[' . $k . '][' . $campo . ']';
@endphp
<div class="ct-row grid grid-cols-1 items-start gap-2 rounded-xl border border-line p-3 sm:grid-cols-12" data-row="variable">
    <input type="hidden" name="{{ $n('key') }}" value="{{ $v['key'] ?? '' }}">
    <div class="sm:col-span-4">
        <input type="text" name="{{ $n('label') }}" value="{{ $v['label'] ?? '' }}" class="{{ $f }}" maxlength="120" placeholder="Nome (ex.: Valor global)" aria-label="Nome da variável">
        @if(filled($v['key'] ?? null))
            <code class="mt-1 block text-[11px] text-ink-3">[[{{ $v['key'] }}]]</code>
        @endif
    </div>
    <div class="sm:col-span-4">
        <select name="{{ $n('type') }}" class="{{ $f }}" aria-label="Tipo">
            @foreach($variableTypes as $valor => $rotulo)
                <option value="{{ $valor }}" @selected(($v['type'] ?? 'text') === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>
    <div class="sm:col-span-3">
        <input type="text" name="{{ $n('value') }}" value="{{ $v['value'] ?? '' }}" class="{{ $f }}" maxlength="500" placeholder="Valor" aria-label="Valor">
    </div>
    <div class="sm:col-span-1 text-right">
        <button type="button" data-remove class="rounded-md px-2 py-2 text-xs font-bold text-danger hover:bg-danger-soft" title="Remover">✕</button>
    </div>
</div>
