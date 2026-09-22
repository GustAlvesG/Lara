@php
    /**
     * Formulário do modelo de documento — o mesmo na criação e na revisão.
     *
     * É o mesmo porque revisar não altera a linha em uso: grava a versão
     * seguinte com estes mesmos campos.
     *
     * As variáveis são geridas em JavaScript puro (linhas que se acrescentam e
     * removem), no padrão das demais telas do projeto — sem componente de
     * build, sem Alpine.
     */
    $template = $template ?? null;
    $variaveis = old('variables', $template?->variables ?? []);
@endphp

<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 space-y-6">

    <div>
        <label for="name" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome do modelo</label>
        <input type="text" name="name" id="name" required maxlength="150"
               value="{{ old('name', $template?->name) }}"
               class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white shadow-sm focus:border-[#A00001] focus:ring-[#A00001]">
        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Descrição</label>
        <input type="text" name="description" id="description" maxlength="2000"
               value="{{ old('description', $template?->description) }}"
               placeholder="Para que serve este documento e quando usá-lo"
               class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white shadow-sm focus:border-[#A00001] focus:ring-[#A00001]">
    </div>

    <div>
        <label for="body_html" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Texto do documento</label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
            HTML simples: parágrafos, títulos, listas e tabelas. Use <code class="font-mono">[[nome_da_variavel]]</code>
            onde entra um dado preenchido pelo atendente, e <code class="font-mono">[[assinatura]]</code> onde ficam os
            campos de assinatura. Sem o marcador de assinatura, os campos vão para o fim do documento.
        </p>
        <textarea name="body_html" id="body_html" rows="18" required
                  class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white shadow-sm font-mono text-xs focus:border-[#A00001] focus:ring-[#A00001]">{{ old('body_html', $template?->body_html) }}</textarea>
        @error('body_html')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300">Variáveis</label>
            <button type="button" id="addVariable"
                    class="text-xs font-bold text-[#A00001] hover:underline">+ Acrescentar variável</button>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
            Cada variável vira um campo no formulário do atendente. A chave é o que vai entre colchetes no texto.
        </p>

        <div id="variables" class="space-y-2">
            @foreach($variaveis as $i => $variavel)
                <div class="grid grid-cols-12 gap-2 items-center" data-variable-row>
                    <input type="text" name="variables[{{ $i }}][key]" value="{{ $variavel['key'] ?? '' }}"
                           placeholder="nome_do_espaco"
                           class="col-span-4 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-xs font-mono">
                    <input type="text" name="variables[{{ $i }}][label]" value="{{ $variavel['label'] ?? '' }}"
                           placeholder="Rótulo mostrado ao atendente"
                           class="col-span-5 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-xs">
                    <label class="col-span-2 flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                        <input type="hidden" name="variables[{{ $i }}][required]" value="0">
                        <input type="checkbox" name="variables[{{ $i }}][required]" value="1"
                               @checked($variavel['required'] ?? false)
                               class="rounded border-gray-300 text-[#A00001] focus:ring-[#A00001]">
                        Obrigatória
                    </label>
                    <button type="button" class="col-span-1 text-xs text-red-600 hover:underline" data-remove-variable>Remover</button>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 pt-2 border-t border-gray-100 dark:border-gray-700">
        <div>
            <label for="identity_check" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">
                Conferência de identidade
            </label>
            <select name="identity_check" id="identity_check"
                    class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white shadow-sm focus:border-[#A00001] focus:ring-[#A00001]">
                @foreach(\App\Models\SignatureTemplate::IDENTITY_CHECKS as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected(old('identity_check', $template?->identity_check) === $valor)>
                        {{ $rotulo }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Conferida no servidor. O CPF cadastrado nunca vai para a tela do tablet.
            </p>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Foto do signatário</label>
            <label class="flex items-center gap-2 mt-3 text-sm text-gray-700 dark:text-gray-300">
                <input type="hidden" name="requires_photo" value="0">
                <input type="checkbox" name="requires_photo" value="1"
                       @checked(old('requires_photo', $template?->requires_photo ?? true))
                       class="rounded border-gray-300 text-[#A00001] focus:ring-[#A00001]">
                Capturar foto na confirmação
            </label>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                O tablet avisa a pessoa antes de fotografar (LGPD).
            </p>
        </div>

        <div>
            <label for="retention_months" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">
                Prazo de guarda (meses)
            </label>
            <input type="number" name="retention_months" id="retention_months" min="1" max="1200"
                   value="{{ old('retention_months', $template?->retention_months) }}"
                   placeholder="{{ config('signature.retention_months') }}"
                   class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white shadow-sm focus:border-[#A00001] focus:ring-[#A00001]">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Registrado para a política de retenção. A exclusão automática ainda não está implementada.
            </p>
        </div>
    </div>

    <input type="hidden" name="signature_placeholder"
           value="{{ old('signature_placeholder', $template?->signature_placeholder ?? '[[assinatura]]') }}">
</div>

{{--
    Script inline, e não em `@push`: o layout deste projeto não tem `@stack`,
    e uma pilha sem stack é descartada em silêncio. É o padrão das demais
    telas com JavaScript próprio (ex.: freelancers/partials/photo).
--}}
<script>
(function () {
    var container = document.getElementById('variables');
    var addButton = document.getElementById('addVariable');

    if (!container || !addButton) {
        return;
    }

    // Índice do próximo campo. Conta as linhas existentes para que uma
    // revisão com variáveis já cadastradas não sobrescreva a primeira.
    var index = container.querySelectorAll('[data-variable-row]').length;

    function linha(i) {
        var div = document.createElement('div');
        div.className = 'grid grid-cols-12 gap-2 items-center';
        div.setAttribute('data-variable-row', '');
        div.innerHTML =
            '<input type="text" name="variables[' + i + '][key]" placeholder="nome_do_espaco" ' +
            'class="col-span-4 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-xs font-mono">' +
            '<input type="text" name="variables[' + i + '][label]" placeholder="Rótulo mostrado ao atendente" ' +
            'class="col-span-5 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-xs">' +
            '<label class="col-span-2 flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">' +
            '<input type="hidden" name="variables[' + i + '][required]" value="0">' +
            '<input type="checkbox" name="variables[' + i + '][required]" value="1" ' +
            'class="rounded border-gray-300 text-[#A00001] focus:ring-[#A00001]">Obrigatória</label>' +
            '<button type="button" class="col-span-1 text-xs text-red-600 hover:underline" data-remove-variable>Remover</button>';

        return div;
    }

    addButton.addEventListener('click', function () {
        container.appendChild(linha(index++));
    });

    container.addEventListener('click', function (event) {
        if (event.target.matches('[data-remove-variable]')) {
            event.target.closest('[data-variable-row]').remove();
        }
    });
})();
</script>
