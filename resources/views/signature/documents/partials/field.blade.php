@php
    /**
     * Um campo do modelo no formulário do ATENDENTE, com o controle do tipo
     * dele. Recebe `$campo` (de `attendantFields()`) e `$valor` — o que o
     * atendente digitou num envio recusado, ou o valor guardado, já na forma
     * de formulário (`SignatureFieldTypes::inputValue`).
     *
     * A conferência é do servidor (StoreSignatureDocumentRequest); os
     * atributos daqui só ajudam a digitar.
     */
    use App\Services\Signature\SignatureFieldTypes as T;

    $nome = 'data[' . $campo['key'] . ']';
    $id = 'data_' . $campo['key'];
    $classe = 'w-full rounded-xl border-line-strong shadow-card focus:border-grena focus:ring-grena-tint';

    // [type do input, inputmode, placeholder]
    [$tipoInput, $modo, $exemplo] = match ($campo['type']) {
        T::NUMBER => ['text', 'decimal', ''],
        T::MONEY => ['text', 'decimal', '1.500,00'],
        T::CPF => ['text', 'numeric', '000.000.000-00'],
        T::CNPJ => ['text', 'text', '00.000.000/0000-00'],
        T::EMAIL => ['email', 'email', 'nome@exemplo.com.br'],
        T::PHONE => ['text', 'tel', '(24) 99999-9999'],
        T::CEP => ['text', 'numeric', '00000-000'],
        T::DATE, T::DATE_LONG => ['date', null, ''],
        T::TIME => ['time', null, ''],
        default => ['text', null, ''],
    };

    $opcoes = $campo['type'] === T::YES_NO ? ['sim' => 'Sim', 'nao' => 'Não'] : array_combine($campo['options'], $campo['options']);

    // Que dados de um signatário cabem neste campo ("Usar dados do
    // signatário"). Campo de CPF só aceita CPF; texto livre aceita qualquer um.
    $fontes = match ($campo['type']) {
        T::TEXT, T::TEXTAREA => ['name', 'cpf', 'email', 'phone'],
        T::CPF => ['cpf'],
        T::EMAIL => ['email'],
        T::PHONE => ['phone'],
        default => [],
    };
@endphp

<div>
    <label for="{{ $id }}" class="block text-sm font-bold text-ink mb-1">
        {{ $campo['label'] }}
        @if($campo['required'])<span class="text-grena-ink">*</span>@endif
    </label>

    @if($campo['type'] === T::TEXTAREA)
        <textarea name="{{ $nome }}" id="{{ $id }}" rows="4" class="{{ $classe }}">{{ is_string($valor) ? $valor : '' }}</textarea>
    @elseif($campo['type'] === T::CHECKBOX)
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            @foreach($opcoes as $opcao)
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="{{ $nome }}[]" value="{{ $opcao }}"
                           @checked(in_array($opcao, (array) $valor, true))
                           class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                    {{ $opcao }}
                </label>
            @endforeach
        </div>
    @elseif(in_array($campo['type'], [T::RADIO, T::YES_NO], true))
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            @foreach($opcoes as $valorOpcao => $rotuloOpcao)
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="radio" name="{{ $nome }}" value="{{ $valorOpcao }}"
                           @checked($valor === (string) $valorOpcao)
                           class="border-line-strong text-grena-ink focus:ring-grena-tint">
                    {{ $rotuloOpcao }}
                </label>
            @endforeach
        </div>
    @else
        <input type="{{ $tipoInput }}" name="{{ $nome }}" id="{{ $id }}"
               value="{{ is_string($valor) ? $valor : '' }}"
               @if($modo) inputmode="{{ $modo }}" @endif
               @if($exemplo) placeholder="{{ $exemplo }}" @endif
               class="{{ $classe }}">
    @endif

    @if($fontes)
        {{-- As opções são montadas pelo script do formulário, com o que estiver digitado nos signatários naquele momento. --}}
        <select data-signer-fill="{{ $id }}" data-fill-kinds="{{ implode(',', $fontes) }}" data-fill-label="{{ $campo['label'] }}"
                aria-label="Usar dados do signatário em {{ $campo['label'] }}"
                class="mt-1 rounded-lg border-line-strong text-xs text-ink-2" style="max-width: 100%;">
            <option value="">Usar dados do signatário…</option>
        </select>
    @endif

    @if($campo['type'] === T::MONEY)
        <p class="mt-1 text-xs text-ink-2">Sai no documento como R$ 1.500,00 — não escreva “R$” no texto do modelo.</p>
    @elseif($campo['type'] === T::DATE_LONG)
        <p class="mt-1 text-xs text-ink-2">Sai no documento por extenso: 3 de outubro de 2026.</p>
    @endif

    @error('data.' . $campo['key'])<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
</div>
