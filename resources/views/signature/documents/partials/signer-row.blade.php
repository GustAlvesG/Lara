@php
    /**
     * Uma linha de signatário. O equivalente em Blade da linha que o
     * JavaScript do formulário monta — as duas precisam gerar os MESMOS
     * campos, senão um documento reaberto com erro de validação perderia o
     * que foi digitado.
     *
     * O título do cartão ("Signatário 2 - Contratado") é mantido pelo script
     * do formulário quando a parte muda ou uma linha sai; aqui ele só nasce
     * certo.
     */
    $parteAtual = collect($partes ?? [])->firstWhere('key', $signer['party'] ?? '');
@endphp
<div class="rounded-xl border border-line p-4 space-y-3" data-signer-row>
    <h4 class="text-sm font-bold text-ink" data-signer-title>Signatário {{ $n ?? $i + 1 }}{{ $parteAtual ? ' - ' . $parteAtual['label'] : '' }}</h4>

    @if($partes ?? [])
        {{-- O modelo declara partes: cada signatário assina por uma delas, no lugar dela no texto. Vem primeiro porque é o que dá nome ao cartão. --}}
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-6">
                <label class="block text-xs font-bold text-ink-2 mb-1">Assina como</label>
                <select name="signers[{{ $i }}][party]" class="w-full rounded-lg border-line-strong text-sm">
                    <option value="">— sem parte (testemunha, por exemplo) —</option>
                    @foreach($partes as $parte)
                        <option value="{{ $parte['key'] }}" @selected(($signer['party'] ?? '') === $parte['key'])>{{ $parte['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    @if($diretoria ?? [])
        {{-- Assinatura Diretoria: o diretor escolhido aqui assina no tablet com matrícula e PIN. Os dados vêm do cadastro do Lara (o servidor regrava). --}}
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-6">
                <label class="block text-xs font-bold text-ink-2 mb-1">Diretor do clube</label>
                <select name="signers[{{ $i }}][director]" data-signer-director class="w-full rounded-lg border-line-strong text-sm">
                    <option value="">— não é diretor —</option>
                    @foreach($diretoria as $area)
                        <optgroup label="{{ $area['area'] }}">
                            @foreach($area['holders'] as $h)
                                @php $valor = $area['area_id'] . ':' . $h['user_id']; @endphp
                                <option value="{{ $valor }}" @selected(($signer['director'] ?? '') === $valor) @disabled($h['blocked'])
                                        data-name="{{ $h['name'] }}" data-cpf="{{ $h['cpf_masked'] }}"
                                        data-email="{{ $h['email'] }}" data-phone="{{ $h['phone'] }}">
                                    {{ $h['name'] }} — {{ $h['role_label'] }}{{ $h['blocked'] ? ' (' . $h['blocked'] . ')' : '' }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('signers.' . $i . '.director')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <div class="md:col-span-5">
            <label class="block text-xs font-bold text-ink-2 mb-1">Nome</label>
            <input type="text" name="signers[{{ $i }}][name]" required maxlength="150" data-signer-name
                   value="{{ $signer['name'] ?? '' }}"
                   class="w-full rounded-lg border-line-strong text-sm">
        </div>
        <div class="md:col-span-3">
            <label class="block text-xs font-bold text-ink-2 mb-1">CPF</label>
            <input type="text" name="signers[{{ $i }}][cpf]" required inputmode="numeric" data-signer-cpf
                   value="{{ $signer['cpf'] ?? '' }}"
                   class="w-full rounded-lg border-line-strong text-sm font-mono">
        </div>
        <div class="md:col-span-3">
            <label class="block text-xs font-bold text-ink-2 mb-1">Papel</label>
            <select name="signers[{{ $i }}][role]"
                    class="w-full rounded-lg border-line-strong text-sm">
                @foreach(\App\Models\SignatureSigner::ROLE_LABELS as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected(($signer['role'] ?? 'signer') === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-1 flex items-end">
            <button type="button" class="text-xs text-danger hover:underline pb-2" data-remove-signer>Remover</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <div class="md:col-span-6">
            <label class="block text-xs font-bold text-ink-2 mb-1">E-mail (para receber a via)</label>
            <input type="email" name="signers[{{ $i }}][email]" maxlength="150" data-signer-email
                   value="{{ $signer['email'] ?? '' }}"
                   class="w-full rounded-lg border-line-strong text-sm">
        </div>
        <div class="md:col-span-6">
            <label class="block text-xs font-bold text-ink-2 mb-1">Telefone</label>
            <input type="text" name="signers[{{ $i }}][phone]" maxlength="30" data-signer-phone
                   value="{{ $signer['phone'] ?? '' }}"
                   class="w-full rounded-lg border-line-strong text-sm">
        </div>
    </div>

    <input type="hidden" name="signers[{{ $i }}][member_id]" data-signer-member value="{{ $signer['member_id'] ?? '' }}">

    <div class="flex items-center gap-2">
        <input type="text" placeholder="Buscar associado por nome, título ou CPF" data-member-search
               class="flex-1 rounded-lg border-line-strong text-xs">
        <span class="text-xs text-ink-3" data-member-hint></span>
    </div>

    <div class="hidden rounded-lg border border-line divide-y divide-line" data-member-results></div>
</div>
