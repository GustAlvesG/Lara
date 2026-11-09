{{--
    Uma variável do contrato. $k = chave do formulário; $v = {key, label, type, value}.
    O marcador ([[chave]]) é o que se escreve no texto — ou se escolhe em "Inserir variável".

    05/11/2026: "Preenchimento" no lugar de "Valor" (os usuários liam "valor" como dinheiro);
    o tipo sai do nome enquanto a pessoa não escolhe (`data-var-type`, ContractVariables::GUESS);
    embaixo, onde a variável é usada (`data-var-usage`, da prévia). $variableUsage vem do editor.

    09/11/2026 (decisão do usuário): as pessoas ainda escreviam o preenchimento no NOME. No
    contrato, a linha mostra um campo só — o do preenchimento, com o nome da variável como
    rótulo — e o nome, o tipo e o "Remover" ficam em "Nome e tipo", recolhido (<details>).
    Variável nova (sem nome) já abre com ele aberto. O campo muda com o tipo
    (ContractVariables::INPUTS): calendário para data, "R$" ou "%" ao lado, teclado numérico.
    O editor-js troca o campo quando o tipo muda (`aplicaTipo`).

    $modelo (edição do modelo, 06/11/2026): sem "Preenchimento" — o modelo guarda a
    variável vazia; quem preenche é o contrato. Ali o nome é o que se edita, e fica à vista.
    O campo do preenchimento fica escondido e vazio (`data-var-value`), porque o editor-js o procura.
--}}
@php
    use App\Services\Signature\Contracts\ContractVariables;

    $modelo = $modelo ?? false;
    $f = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
    $l = 'mb-1 block text-xs font-bold text-ink-2';
    $n = fn (string $campo) => 'variables[' . $k . '][' . $campo . ']';
    $chaveVar = $v['key'] ?? '';
    $rotulo = $v['label'] ?? '';
    $tipoVar = ContractVariables::exists($v['type'] ?? null) ? $v['type'] : ContractVariables::TEXT;
    $campo = ContractVariables::input($tipoVar);
    $usos = $chaveVar !== '' ? (($variableUsage ?? [])[$chaveVar] ?? []) : [];
    $idValor = 'var-' . $k . '-value';
@endphp
@if($modelo)
<div class="ct-row grid grid-cols-1 gap-3 rounded-xl border border-line p-3 sm:grid-cols-2" data-row="variable">
    <input type="hidden" name="{{ $n('key') }}" value="{{ $chaveVar }}" data-var-key>
    <div>
        <label class="{{ $l }}">Nome
            <code @class(['ml-1 font-mono text-[11px] font-normal text-ink-3', 'hidden' => $chaveVar === '']) data-var-token>[[{{ $chaveVar }}]]</code>
        </label>
        <input type="text" name="{{ $n('label') }}" value="{{ $rotulo }}" class="{{ $f }}" maxlength="120" placeholder="Ex.: Prazo de vigência" data-var-label>
    </div>
    <div class="flex items-end justify-end">
        <input type="hidden" name="{{ $n('value') }}" value="" data-var-value>
        <button type="button" data-remove class="rounded-md px-2 py-1 text-xs font-bold text-danger hover:bg-danger-soft" title="Remover a variável">Remover</button>
    </div>
    <div class="sm:col-span-2">
        <label class="{{ $l }}">Tipo (como sai no texto)</label>
        {{-- Já escolhido (variável salva): o palpite pelo nome não mexe mais. --}}
        <select name="{{ $n('type') }}" class="{{ $f }} pr-10" data-var-type @if($chaveVar !== '') data-touched="1" @endif>
            @foreach($variableTypes as $valor => $rotuloTipo)
                <option value="{{ $valor }}" @selected($tipoVar === $valor)>{{ $rotuloTipo }}</option>
            @endforeach
        </select>
    </div>
    <p class="text-xs sm:col-span-2 {{ $chaveVar !== '' && $usos === [] ? 'text-warn' : 'text-ink-3' }}" data-var-usage>
        @if($chaveVar !== '')
            {{ $usos === [] ? 'Ainda não usada no texto.' : 'Usada em: ' . implode(', ', $usos) . '.' }}
        @endif
    </p>
</div>
@else
<div class="ct-row space-y-2 rounded-xl border border-line p-3" data-row="variable">
    <input type="hidden" name="{{ $n('key') }}" value="{{ $chaveVar }}" data-var-key>

    {{-- O nome da variável é o rótulo do único campo à vista. --}}
    <label for="{{ $idValor }}" class="block text-sm font-bold text-ink">
        <span data-var-title>{{ $rotulo !== '' ? $rotulo : 'Nova variável' }}</span>
    </label>
    <div class="flex items-stretch">
        <span @class(['inline-flex items-center rounded-l-lg border border-r-0 border-line-strong bg-subtle px-3 text-sm font-bold text-ink-2', 'hidden' => $campo['prefix'] === '']) data-var-prefix>{{ $campo['prefix'] }}</span>
        <input type="{{ $campo['input'] }}" id="{{ $idValor }}" name="{{ $n('value') }}"
               value="{{ ContractVariables::inputValue($tipoVar, $v['value'] ?? '') }}"
               inputmode="{{ $campo['inputmode'] }}" placeholder="{{ $campo['placeholder'] }}" maxlength="500"
               @class([$f, 'min-w-0 flex-1', 'rounded-l-none' => $campo['prefix'] !== '', 'rounded-r-none' => $campo['suffix'] !== ''])
               data-var-value>
        <span @class(['inline-flex items-center rounded-r-lg border border-l-0 border-line-strong bg-subtle px-3 text-sm font-bold text-ink-2', 'hidden' => $campo['suffix'] === '']) data-var-suffix>{{ $campo['suffix'] }}</span>
    </div>
    <p class="text-xs text-ink-3" data-var-hint>{{ $campo['hint'] }}</p>
    <p class="text-xs {{ $chaveVar !== '' && $usos === [] ? 'text-warn' : 'text-ink-3' }}" data-var-usage>
        @if($chaveVar !== '')
            {{ $usos === [] ? 'Ainda não usada no texto.' : 'Usada em: ' . implode(', ', $usos) . '.' }}
        @endif
    </p>

    {{-- Nome, tipo e Remover: recolhidos. Variável nova (sem nome) já abre aberta. --}}
    <details class="group rounded-lg bg-subtle px-3 py-2" data-var-settings @if($rotulo === '' && $chaveVar === '') open @endif>
        <summary class="cursor-pointer select-none text-xs font-bold text-ink-2 hover:text-ink">
            Nome e tipo da variável
            <code @class(['ml-1 font-mono text-[11px] font-normal text-ink-3', 'hidden' => $chaveVar === '']) data-var-token>[[{{ $chaveVar }}]]</code>
        </summary>
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <label class="{{ $l }}" for="var-{{ $k }}-label">Nome (não é o preenchimento)</label>
                <input type="text" id="var-{{ $k }}-label" name="{{ $n('label') }}" value="{{ $rotulo }}" class="{{ $f }}" maxlength="120" placeholder="Ex.: Prazo de vigência" data-var-label>
            </div>
            <div>
                <label class="{{ $l }}" for="var-{{ $k }}-type">Tipo (como sai no texto)</label>
                {{-- Já escolhido (variável salva): o palpite pelo nome não mexe mais. --}}
                <select id="var-{{ $k }}-type" name="{{ $n('type') }}" class="{{ $f }} pr-10" data-var-type @if($chaveVar !== '') data-touched="1" @endif>
                    @foreach($variableTypes as $valor => $rotuloTipo)
                        <option value="{{ $valor }}" @selected($tipoVar === $valor)>{{ $rotuloTipo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2 flex justify-end">
                <button type="button" data-remove class="rounded-md px-2 py-1 text-xs font-bold text-danger hover:bg-danger-soft" title="Remover a variável">Remover variável</button>
            </div>
        </div>
    </details>
</div>
@endif
