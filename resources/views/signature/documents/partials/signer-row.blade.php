@php
    /**
     * Uma linha de signatário. O equivalente em Blade da linha que o
     * JavaScript do formulário monta — as duas precisam gerar os MESMOS
     * campos, senão um documento reaberto com erro de validação perderia o
     * que foi digitado.
     */
@endphp
<div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3" data-signer-row>
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <div class="md:col-span-5">
            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">Nome</label>
            <input type="text" name="signers[{{ $i }}][name]" required maxlength="150" data-signer-name
                   value="{{ $signer['name'] ?? '' }}"
                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
        </div>
        <div class="md:col-span-3">
            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">CPF</label>
            <input type="text" name="signers[{{ $i }}][cpf]" required inputmode="numeric" data-signer-cpf
                   value="{{ $signer['cpf'] ?? '' }}"
                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm font-mono">
        </div>
        <div class="md:col-span-3">
            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">Papel</label>
            <select name="signers[{{ $i }}][role]"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                @foreach(\App\Models\SignatureSigner::ROLE_LABELS as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected(($signer['role'] ?? 'signer') === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-1 flex items-end">
            <button type="button" class="text-xs text-red-600 hover:underline pb-2" data-remove-signer>Remover</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <div class="md:col-span-6">
            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">E-mail (para receber a via)</label>
            <input type="email" name="signers[{{ $i }}][email]" maxlength="150" data-signer-email
                   value="{{ $signer['email'] ?? '' }}"
                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
        </div>
        <div class="md:col-span-6">
            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">Telefone</label>
            <input type="text" name="signers[{{ $i }}][phone]" maxlength="30" data-signer-phone
                   value="{{ $signer['phone'] ?? '' }}"
                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
        </div>
    </div>

    <input type="hidden" name="signers[{{ $i }}][member_id]" data-signer-member value="{{ $signer['member_id'] ?? '' }}">

    <div class="flex items-center gap-2">
        <input type="text" placeholder="Buscar associado por nome, título ou CPF" data-member-search
               class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-xs">
        <span class="text-xs text-gray-400" data-member-hint></span>
    </div>

    <div class="hidden rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700" data-member-results></div>
</div>
