{{--
    Uma variável do contrato. $k = chave do formulário; $v = {key, label, type, value}.
    O marcador ([[chave]]) é o que se escreve no texto — ou se escolhe em "Inserir variável".
    Nome e valor lado a lado e o tipo na linha de baixo: os rótulos dos tipos
    são longos ("Valor em reais, com extenso — R$ 8.900,00 …") e cortavam.
--}}
@php
    $f = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
    $l = 'mb-1 block text-xs font-bold text-ink-2';
    $n = fn (string $campo) => 'variables[' . $k . '][' . $campo . ']';
@endphp
<div class="ct-row grid grid-cols-1 gap-3 rounded-xl border border-line p-3 sm:grid-cols-2" data-row="variable">
    <input type="hidden" name="{{ $n('key') }}" value="{{ $v['key'] ?? '' }}">
    <div>
        <label class="{{ $l }}">Nome
            @if(filled($v['key'] ?? null))
                <code class="ml-1 font-mono text-[11px] font-normal text-ink-3">[[{{ $v['key'] }}]]</code>
            @endif
        </label>
        <input type="text" name="{{ $n('label') }}" value="{{ $v['label'] ?? '' }}" class="{{ $f }}" maxlength="120" placeholder="Ex.: Valor global">
    </div>
    <div>
        <div class="flex items-center justify-between gap-2">
            <label class="{{ $l }}">Valor</label>
            <button type="button" data-remove class="-mt-1 rounded-md px-2 py-1 text-xs font-bold text-danger hover:bg-danger-soft" title="Remover a variável">Remover</button>
        </div>
        <input type="text" name="{{ $n('value') }}" value="{{ $v['value'] ?? '' }}" class="{{ $f }}" maxlength="500" placeholder="Ex.: 8.900,00">
    </div>
    <div class="sm:col-span-2">
        <label class="{{ $l }}">Tipo (como sai no texto)</label>
        <select name="{{ $n('type') }}" class="{{ $f }} pr-10">
            @foreach($variableTypes as $valor => $rotulo)
                <option value="{{ $valor }}" @selected(($v['type'] ?? 'text') === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>
</div>
