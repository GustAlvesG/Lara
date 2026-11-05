{{--
    Uma variável do contrato. $k = chave do formulário; $v = {key, label, type, value}.
    O marcador ([[chave]]) é o que se escreve no texto — ou se escolhe em "Inserir variável".
    Nome e preenchimento lado a lado e o tipo na linha de baixo: os rótulos dos tipos
    são longos ("Valor em reais, com extenso — R$ 8.900,00 …") e cortavam.

    05/11/2026: "Preenchimento" no lugar de "Valor" (os usuários liam "valor" como dinheiro);
    o tipo sai do nome enquanto a pessoa não escolhe (`data-var-type`, ContractVariables::GUESS);
    embaixo, onde a variável é usada (`data-var-usage`, da prévia). $variableUsage vem do editor.
--}}
@php
    $f = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
    $l = 'mb-1 block text-xs font-bold text-ink-2';
    $n = fn (string $campo) => 'variables[' . $k . '][' . $campo . ']';
    $chaveVar = $v['key'] ?? '';
    $usos = $chaveVar !== '' ? (($variableUsage ?? [])[$chaveVar] ?? []) : [];
@endphp
<div class="ct-row grid grid-cols-1 gap-3 rounded-xl border border-line p-3 sm:grid-cols-2" data-row="variable">
    <input type="hidden" name="{{ $n('key') }}" value="{{ $chaveVar }}" data-var-key>
    <div>
        <label class="{{ $l }}">Nome
            <code @class(['ml-1 font-mono text-[11px] font-normal text-ink-3', 'hidden' => $chaveVar === '']) data-var-token>[[{{ $chaveVar }}]]</code>
        </label>
        <input type="text" name="{{ $n('label') }}" value="{{ $v['label'] ?? '' }}" class="{{ $f }}" maxlength="120" placeholder="Ex.: Prazo de vigência" data-var-label>
    </div>
    <div>
        <div class="flex items-center justify-between gap-2">
            <label class="{{ $l }}">Preenchimento</label>
            <button type="button" data-remove class="-mt-1 rounded-md px-2 py-1 text-xs font-bold text-danger hover:bg-danger-soft" title="Remover a variável">Remover</button>
        </div>
        <input type="text" name="{{ $n('value') }}" value="{{ $v['value'] ?? '' }}" class="{{ $f }}" maxlength="500" placeholder="O que entra no texto. Ex.: 12, 10/11/2026, 8.900,00" data-var-value>
    </div>
    <div class="sm:col-span-2">
        <label class="{{ $l }}">Tipo (como sai no texto)</label>
        {{-- Já escolhido (variável salva): o palpite pelo nome não mexe mais. --}}
        <select name="{{ $n('type') }}" class="{{ $f }} pr-10" data-var-type @if($chaveVar !== '') data-touched="1" @endif>
            @foreach($variableTypes as $valor => $rotulo)
                <option value="{{ $valor }}" @selected(($v['type'] ?? 'text') === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>
    <p class="text-xs sm:col-span-2 {{ $chaveVar !== '' && $usos === [] ? 'text-warn' : 'text-ink-3' }}" data-var-usage>
        @if($chaveVar !== '')
            {{ $usos === [] ? 'Ainda não usada no texto.' : 'Usada em: ' . implode(', ', $usos) . '.' }}
        @endif
    </p>
</div>
