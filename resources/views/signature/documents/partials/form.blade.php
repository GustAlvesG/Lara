@php
    /**
     * Formulário do documento — o mesmo na criação e na correção do rascunho.
     *
     * O modelo só pode ser escolhido na criação: trocá-lo depois mudaria o
     * texto embaixo dos dados já preenchidos, e o caminho para isso é criar
     * outro documento.
     *
     * A busca de associado preenche nome, CPF, e-mail e telefone do
     * signatário. Quem não é associado é digitado à mão — o balcão atende
     * visitante todo dia.
     */
    $document = $document ?? null;
    $signers = old('signers', $document?->signers->map(fn($s) => [
        'name' => $s->name,
        'cpf' => $s->cpf,
        'member_id' => $s->member_id,
        'email' => $s->email,
        'phone' => $s->phone,
        'role' => $s->role,
    ])->all() ?? [['role' => \App\Models\SignatureSigner::ROLE_SIGNER]]);

    $dados = old('data', $document?->data ?? []);
@endphp

<div class="space-y-6">

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 space-y-5">
        <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Documento</h3>

        <div>
            <label for="title" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Título</label>
            <input type="text" name="title" id="title" maxlength="200"
                   value="{{ old('title', $document?->title ?? $template->name) }}"
                   class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white shadow-sm focus:border-[#A00001] focus:ring-[#A00001]">
        </div>

        <div>
            <label for="location" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Local do atendimento</label>
            <input type="text" name="location" id="location" maxlength="120"
                   value="{{ old('location', $document?->location ?? config('signature.location')) }}"
                   class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white shadow-sm focus:border-[#A00001] focus:ring-[#A00001]">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Vai para a página de manifesto do documento assinado.</p>
        </div>

        @if($template->declaredVariables())
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700 space-y-4">
                <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dados do documento</h4>

                @foreach($template->declaredVariables() as $variavel)
                    <div>
                        <label for="data_{{ $variavel['key'] }}" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">
                            {{ $variavel['label'] }}
                            @if($variavel['required'])<span class="text-[#A00001]">*</span>@endif
                        </label>
                        <input type="text" name="data[{{ $variavel['key'] }}]" id="data_{{ $variavel['key'] }}"
                               value="{{ $dados[$variavel['key']] ?? '' }}"
                               class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white shadow-sm focus:border-[#A00001] focus:ring-[#A00001]">
                    </div>
                @endforeach

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Os campos obrigatórios podem ficar em branco no rascunho — são conferidos no congelamento.
                </p>
            </div>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Signatários</h3>
            <button type="button" id="addSigner" class="text-xs font-bold text-[#A00001] hover:underline">+ Acrescentar signatário</button>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            Vários signatários são atendidos em sequência, na ordem desta lista — um QR Code por pessoa.
        </p>

        <div id="signers" class="space-y-4">
            @foreach($signers as $i => $signer)
                @include('signature.documents.partials.signer-row', ['i' => $i, 'signer' => $signer])
            @endforeach
        </div>
        @error('signers')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<script>
(function () {
    var container = document.getElementById('signers');
    var addButton = document.getElementById('addSigner');

    if (!container) {
        return;
    }

    var index = container.querySelectorAll('[data-signer-row]').length;

    var papeis = @json(\App\Models\SignatureSigner::ROLE_LABELS);
    var buscaUrl = @json(route('signature-documents.members'));
    var csrf = @json(csrf_token());

    function opcoesDePapel(selecionado) {
        return Object.keys(papeis).map(function (valor) {
            return '<option value="' + valor + '"' + (valor === selecionado ? ' selected' : '') + '>' + papeis[valor] + '</option>';
        }).join('');
    }

    function linha(i) {
        var div = document.createElement('div');
        div.className = 'rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3';
        div.setAttribute('data-signer-row', '');
        div.innerHTML =
            '<div class="grid grid-cols-1 md:grid-cols-12 gap-3">' +
            '<div class="md:col-span-5"><label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">Nome</label>' +
            '<input type="text" name="signers[' + i + '][name]" required maxlength="150" data-signer-name ' +
            'class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm"></div>' +
            '<div class="md:col-span-3"><label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">CPF</label>' +
            '<input type="text" name="signers[' + i + '][cpf]" required inputmode="numeric" data-signer-cpf ' +
            'class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm font-mono"></div>' +
            '<div class="md:col-span-3"><label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">Papel</label>' +
            '<select name="signers[' + i + '][role]" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">' +
            opcoesDePapel('signer') + '</select></div>' +
            '<div class="md:col-span-1 flex items-end"><button type="button" class="text-xs text-red-600 hover:underline pb-2" data-remove-signer>Remover</button></div>' +
            '</div>' +
            '<div class="grid grid-cols-1 md:grid-cols-12 gap-3">' +
            '<div class="md:col-span-6"><label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">E-mail (para receber a via)</label>' +
            '<input type="email" name="signers[' + i + '][email]" maxlength="150" data-signer-email ' +
            'class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm"></div>' +
            '<div class="md:col-span-6"><label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">Telefone</label>' +
            '<input type="text" name="signers[' + i + '][phone]" maxlength="30" data-signer-phone ' +
            'class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm"></div>' +
            '</div>' +
            '<input type="hidden" name="signers[' + i + '][member_id]" data-signer-member>' +
            '<div class="flex items-center gap-2">' +
            '<input type="text" placeholder="Buscar associado por nome, título ou CPF" data-member-search ' +
            'class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-xs">' +
            '<span class="text-xs text-gray-400" data-member-hint></span></div>' +
            '<div class="hidden rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700" data-member-results></div>';

        return div;
    }

    if (addButton) {
        addButton.addEventListener('click', function () {
            container.appendChild(linha(index++));
        });
    }

    container.addEventListener('click', function (event) {
        if (event.target.matches('[data-remove-signer]')) {
            var linhas = container.querySelectorAll('[data-signer-row]');

            if (linhas.length === 1) {
                alert('O documento precisa de ao menos um signatário.');
                return;
            }

            event.target.closest('[data-signer-row]').remove();
        }
    });

    /*
     * Busca de associado: dispara depois de uma pausa na digitação, para não
     * consultar o banco a cada tecla. Preenche a linha inteira do signatário.
     */
    var timers = new WeakMap();

    container.addEventListener('input', function (event) {
        var campo = event.target;

        if (!campo.matches('[data-member-search]')) {
            return;
        }

        clearTimeout(timers.get(campo));

        var termo = campo.value.trim();
        var linha = campo.closest('[data-signer-row]');
        var resultados = linha.querySelector('[data-member-results]');
        var hint = linha.querySelector('[data-member-hint]');

        if (termo.length < 3) {
            resultados.classList.add('hidden');
            resultados.innerHTML = '';
            hint.textContent = '';
            return;
        }

        timers.set(campo, setTimeout(function () {
            hint.textContent = 'buscando…';

            fetch(buscaUrl + '?q=' + encodeURIComponent(termo), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (associados) {
                    hint.textContent = associados.length ? '' : 'nenhum associado';
                    resultados.innerHTML = '';

                    associados.forEach(function (associado) {
                        var item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'w-full text-left px-3 py-2 text-xs hover:bg-gray-50 dark:hover:bg-gray-700';
                        item.textContent = associado.name + ' — ' + associado.cpf_masked
                            + (associado.title ? ' (título ' + associado.title + ')' : '');

                        item.addEventListener('click', function () {
                            linha.querySelector('[data-signer-name]').value = associado.name || '';
                            linha.querySelector('[data-signer-cpf]').value = associado.cpf || '';
                            linha.querySelector('[data-signer-member]').value = associado.id || '';

                            if (associado.email) { linha.querySelector('[data-signer-email]').value = associado.email; }
                            if (associado.phone) { linha.querySelector('[data-signer-phone]').value = associado.phone; }

                            resultados.classList.add('hidden');
                            campo.value = '';
                        });

                        resultados.appendChild(item);
                    });

                    resultados.classList.toggle('hidden', associados.length === 0);
                })
                .catch(function () {
                    hint.textContent = 'falha na busca';
                });
        }, 350));
    });
})();
</script>
