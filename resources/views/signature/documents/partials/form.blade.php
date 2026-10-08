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
    $partes = $template->declaredParties();

    // Documento novo de um modelo com partes já nasce com uma linha por parte:
    // é o que o contrato pede, e o atendente não precisa lembrar de quantas são.
    $linhasIniciais = $partes
        ? array_map(fn(array $parte) => ['role' => \App\Models\SignatureSigner::ROLE_SIGNER, 'party' => $parte['key']], $partes)
        : [['role' => \App\Models\SignatureSigner::ROLE_SIGNER]];

    $signers = old('signers', $document?->signers->map(fn($s) => [
        'name' => $s->name,
        'cpf' => $s->cpf,
        'member_id' => $s->member_id,
        'email' => $s->email,
        'phone' => $s->phone,
        'role' => $s->role,
        'party' => $s->party,
    ])->all() ?? $linhasIniciais);

    // Depois de um envio recusado vale o que o atendente digitou, cru; fora
    // disso, o valor guardado, na forma de formulário do tipo de cada campo.
    $reenvio = old('signers') !== null || old('data') !== null;

    $valorDe = fn(array $campo) => $reenvio
        ? old('data.' . $campo['key'])
        : \App\Services\Signature\SignatureFieldTypes::inputValue($campo, $document?->data[$campo['key']] ?? null);

    // Quem responde cada campo é decidido aqui, no documento: marcado em
    // "Perguntar ao signatário", vai ao tablet; senão, o atendente preenche.
    $perguntados = $reenvio
        ? array_map('strval', (array) old('ask_signer', []))
        : ($document?->signerFieldKeys() ?? []);

    $manuais = $template->manualFields();
    $automaticos = $template->automaticFields();
@endphp

<div class="space-y-6">

    {{-- Cada cartão é um passo do formulário (partials/steps): um por vez na tela. --}}
    <div class="bg-surface rounded-card shadow-card p-6 space-y-5" data-step="Documento" data-step-keys="title">
        <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider">Documento</h3>

        <div>
            <label for="title" class="block text-sm font-bold text-ink mb-1">Título</label>
            <input type="text" name="title" id="title" maxlength="200"
                   value="{{ old('title', $document?->title ?? $template->name) }}"
                   class="w-full rounded-xl border-line-strong shadow-card focus:border-grena focus:ring-grena-tint">
            @if(!$document && $template->name !== '')
                <p class="mt-1 text-xs text-ink-2">
                    Se você não mudar o título, o nome do primeiro signatário é acrescentado a ele:
                    “{{ $template->name }} - Nome da pessoa”.
                </p>
            @endif
        </div>
    </div>

    {{-- Os signatários vêm ANTES dos dados: quase sempre o nome, o CPF e o contato que o texto pede são os de quem assina, e os campos abaixo os aproveitam. --}}
    <div class="bg-surface rounded-card shadow-card p-6" data-step="Signatários" data-step-keys="signers">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider">Signatários</h3>
            <button type="button" id="addSigner" class="text-xs font-bold text-grena-ink hover:underline">+ Acrescentar signatário</button>
        </div>
        <p class="text-xs text-ink-2 mb-4">
            Vários signatários assinam um de cada vez, em qualquer ordem — um QR Code por pessoa.
            @if($partes)
                Este modelo tem partes ({{ collect($partes)->pluck('label')->join(', ') }}): cada uma precisa de
                ao menos um signatário para o documento ser congelado.
            @endif
        </p>

        <div id="signers" class="space-y-4">
            @foreach($signers as $i => $signer)
                @include('signature.documents.partials.signer-row', ['i' => $i, 'n' => $loop->iteration, 'signer' => $signer, 'partes' => $partes])
            @endforeach
        </div>
        @error('signers')<p class="mt-2 text-xs text-danger">{{ $message }}</p>@enderror
    </div>

    @if($template->declaredVariables())
        <div class="bg-surface rounded-card shadow-card p-6 space-y-4" data-step="Dados do documento" data-step-keys="data">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider">Dados do documento</h3>
                @if($manuais)
                    <button type="button" id="fillFromSigners" class="text-xs font-bold text-grena-ink hover:underline">
                        Preencher com os dados dos signatários
                    </button>
                @endif
            </div>
            <p id="fillFromSignersStatus" class="text-xs text-ink-2" hidden></p>

            <script>
                // "Perguntar ao signatário": o campo some do formulário — quem responde é quem assina.
                document.addEventListener('change', function (e) {
                    if (!e.target.matches('[data-ask-toggle]')) {
                        return;
                    }

                    var bloco = e.target.closest('[data-ask-field]');
                    bloco.querySelector('[data-ask-answer]').hidden = e.target.checked;
                    bloco.querySelector('[data-ask-note]').hidden = !e.target.checked;
                });
            </script>

            @foreach($manuais as $variavel)
                @include('signature.documents.partials.field', [
                    'campo' => $variavel,
                    'valor' => $valorDe($variavel),
                    'perguntar' => in_array($variavel['key'], $perguntados, true),
                ])
            @endforeach

            @if($manuais)
                <p class="text-xs text-ink-2">
                    Os campos obrigatórios podem ficar em branco no rascunho — são conferidos no congelamento.
                    Marcando <b>Perguntar ao signatário</b>, quem assina responde no tablet, antes de ler o
                    documento (e a obrigatoriedade é conferida lá). Documento com pergunta ao signatário só pode
                    ser assinado no tablet: para o gov.br, preencha tudo aqui.
                </p>
            @endif

            @if($automaticos)
                {{-- O que NÃO é do atendente aparece aqui para ele não procurar onde digitar. --}}
                <div class="rounded-xl bg-subtle p-4 text-xs text-ink-2 space-y-1">
                    @if($automaticos)
                        <div>
                            <span class="font-bold">O sistema preenche na assinatura:</span>
                            {{ collect($automaticos)->pluck('label')->join(', ') }}.
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- Anexos: os do modelo aparecem para conferência; os deste documento são editados aqui. O arquivo em si é enviado na tela do documento. --}}
    <div class="bg-surface rounded-card shadow-card p-6 space-y-3" data-step="Anexos" data-step-keys="attachments">
        <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider">Anexos</h3>

        @if($template->declaredAttachments())
            <div class="rounded-xl bg-subtle p-4 text-xs text-ink-2">
                <span class="font-bold">O modelo pede:</span>
                {{ collect($template->declaredAttachments())->map(fn($a) => $a['label'] . ($a['required'] ? ' (obrigatório)' : ''))->join('; ') }}.
            </div>
        @endif

        <p class="text-xs text-ink-2">
            Precisa de mais algum arquivo só neste documento? Peça aqui. Os arquivos são enviados depois, na tela do
            documento — antes ou depois da assinatura; sem os obrigatórios, o documento não conclui.
        </p>

        @include('signature.partials.attachment-requirements', [
            'id' => 'documentAttachments',
            'items' => old('attachments', $document?->attachment_requirements ?? []),
        ])
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
    var partes = @json($partes);

    // O seletor "Assina como" da linha nova. Montado por DOM: o nome da parte
    // vem do modelo e não é interpretado como HTML.
    function seletorDeParte(i) {
        var caixa = document.createElement('div');
        caixa.className = 'grid grid-cols-1 md:grid-cols-12 gap-3';
        caixa.innerHTML =
            '<div class="md:col-span-6"><label class="block text-xs font-bold text-ink-2 mb-1">Assina como</label>' +
            '<select name="signers[' + i + '][party]" class="w-full rounded-lg border-line-strong text-sm">' +
            '<option value="">— sem parte (testemunha, por exemplo) —</option></select></div>';

        var select = caixa.querySelector('select');

        partes.forEach(function (parte) {
            var opcao = document.createElement('option');
            opcao.value = parte.key;
            opcao.textContent = parte.label;
            select.appendChild(opcao);
        });

        return caixa;
    }
    var buscaUrl = @json(route('signature-documents.members'));
    var csrf = @json(csrf_token());

    function opcoesDePapel(selecionado) {
        return Object.keys(papeis).map(function (valor) {
            return '<option value="' + valor + '"' + (valor === selecionado ? ' selected' : '') + '>' + papeis[valor] + '</option>';
        }).join('');
    }

    function linha(i) {
        var div = document.createElement('div');
        div.className = 'rounded-xl border border-line p-4 space-y-3';
        div.setAttribute('data-signer-row', '');
        div.innerHTML =
            '<h4 class="text-sm font-bold text-ink" data-signer-title></h4>' +
            '<div class="grid grid-cols-1 md:grid-cols-12 gap-3">' +
            '<div class="md:col-span-5"><label class="block text-xs font-bold text-ink-2 mb-1">Nome</label>' +
            '<input type="text" name="signers[' + i + '][name]" required maxlength="150" data-signer-name ' +
            'class="w-full rounded-lg border-line-strong text-sm"></div>' +
            '<div class="md:col-span-3"><label class="block text-xs font-bold text-ink-2 mb-1">CPF</label>' +
            '<input type="text" name="signers[' + i + '][cpf]" required inputmode="numeric" data-signer-cpf ' +
            'class="w-full rounded-lg border-line-strong text-sm font-mono"></div>' +
            '<div class="md:col-span-3"><label class="block text-xs font-bold text-ink-2 mb-1">Papel</label>' +
            '<select name="signers[' + i + '][role]" class="w-full rounded-lg border-line-strong text-sm">' +
            opcoesDePapel('signer') + '</select></div>' +
            '<div class="md:col-span-1 flex items-end"><button type="button" class="text-xs text-danger hover:underline pb-2" data-remove-signer>Remover</button></div>' +
            '</div>' +
            '<div class="grid grid-cols-1 md:grid-cols-12 gap-3">' +
            '<div class="md:col-span-6"><label class="block text-xs font-bold text-ink-2 mb-1">E-mail (para receber a via)</label>' +
            '<input type="email" name="signers[' + i + '][email]" maxlength="150" data-signer-email ' +
            'class="w-full rounded-lg border-line-strong text-sm"></div>' +
            '<div class="md:col-span-6"><label class="block text-xs font-bold text-ink-2 mb-1">Telefone</label>' +
            '<input type="text" name="signers[' + i + '][phone]" maxlength="30" data-signer-phone ' +
            'class="w-full rounded-lg border-line-strong text-sm"></div>' +
            '</div>' +
            '<input type="hidden" name="signers[' + i + '][member_id]" data-signer-member>' +
            '<div class="flex items-center gap-2">' +
            '<input type="text" placeholder="Buscar associado por nome, título ou CPF" data-member-search ' +
            'class="flex-1 rounded-lg border-line-strong text-xs">' +
            '<span class="text-xs text-ink-3" data-member-hint></span></div>' +
            '<div class="hidden rounded-lg border border-line divide-y divide-line" data-member-results></div>';

        // "Assina como" vem logo depois do título do cartão: é ele que dá nome ao cartão.
        if (partes.length) {
            div.insertBefore(seletorDeParte(i), div.children[1]);
        }

        return div;
    }

    if (addButton) {
        addButton.addEventListener('click', function () {
            container.appendChild(linha(index++));
            atualizaFontes();
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
            atualizaFontes();
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
                        item.className = 'w-full text-left px-3 py-2 text-xs hover:bg-subtle';
                        item.textContent = associado.name + ' — ' + associado.cpf_masked
                            + (associado.title ? ' (título ' + associado.title + ')' : '')
                            + (associado.kind ? ' · ' + associado.kind : '');

                        item.addEventListener('click', function () {
                            linha.querySelector('[data-signer-name]').value = associado.name || '';
                            linha.querySelector('[data-signer-cpf]').value = associado.cpf || '';
                            linha.querySelector('[data-signer-member]').value = associado.id || '';

                            if (associado.email) { linha.querySelector('[data-signer-email]').value = associado.email; }
                            if (associado.phone) { linha.querySelector('[data-signer-phone]').value = associado.phone; }

                            resultados.classList.add('hidden');
                            campo.value = '';

                            // Preenchido por script: não dispara `input`, então avisa as listas dos campos.
                            atualizaFontes();
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

    /*
     * "Usar dados do signatário": o nome, o CPF e o contato que o texto pede
     * quase sempre são os de quem assina, já digitados logo acima. Cada campo
     * compatível ganha uma lista com esses dados; o botão do topo preenche de
     * uma vez os campos VAZIOS cujo rótulo deixa claro de quem é o dado.
     *
     * Nada disso vai ao servidor: só copia para o campo, que o atendente
     * confere e pode corrigir.
     */
    var seletoresDeDado = document.querySelectorAll('[data-signer-fill]');
    var botaoPreencher = document.getElementById('fillFromSigners');
    var avisoPreencher = document.getElementById('fillFromSignersStatus');

    var rotulosDeDado = { name: 'Nome', cpf: 'CPF', email: 'E-mail', phone: 'Telefone' };

    // Como cada dado costuma aparecer no rótulo de um campo.
    var padroesDeDado = [
        ['cpf', /\bcpf\b/],
        ['email', /\be ?mail\b/],
        ['phone', /\b(telefone|celular|whatsapp|fone)\b/],
        ['name', /\bnome( completo)?\b/],
    ];

    // Palavras que dizem "é de quem assina" sem apontar uma parte.
    var quemAssina = ['signatario', 'assinante', 'associado', 'socio', 'titular', 'cliente', 'requerente', 'solicitante'];

    function cpfFormatado(valor) {
        var digitos = (valor || '').replace(/\D/g, '');

        return digitos.length === 11
            ? digitos.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4')
            : (valor || '').trim();
    }

    function simplificado(texto) {
        return (texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ').trim();
    }

    function signatarios() {
        return Array.prototype.map.call(container.querySelectorAll('[data-signer-row]'), function (linha, n) {
            var parte = linha.querySelector('select[name$="[party]"]');
            var valor = function (seletor) {
                var campo = linha.querySelector(seletor);

                return campo ? campo.value.trim() : '';
            };

            return {
                ordem: n + 1,
                parte: parte && parte.value ? parte.options[parte.selectedIndex].textContent.trim() : '',
                dados: {
                    name: valor('[data-signer-name]'),
                    cpf: cpfFormatado(valor('[data-signer-cpf]')),
                    email: valor('[data-signer-email]'),
                    phone: valor('[data-signer-phone]'),
                },
            };
        });
    }

    /*
     * O dado que o botão do topo põe num campo, ou null se o rótulo não deixa
     * claro. "Nome do contratante" é do signatário daquela parte; "Nome" ou
     * "CPF do associado" é do primeiro; "Nome do evento" ou "CPF do cônjuge"
     * não é de ninguém da lista e fica para o atendente.
     */
    function sugestao(rotulo, tipos, pessoas) {
        var texto = simplificado(rotulo);
        var tipo = null;

        padroesDeDado.some(function (padrao) {
            if (tipos.indexOf(padrao[0]) === -1 || !padrao[1].test(texto)) {
                return false;
            }

            tipo = padrao[0];
            texto = texto.replace(padrao[1], ' ');

            return true;
        });

        if (!tipo) {
            if (tipos.length !== 1) {
                return null;
            }

            tipo = tipos[0];
        }

        var resto = texto.replace(/\b(do|da|de|dos|das|o|a|n|no|numero)\b/g, ' ').replace(/\s+/g, ' ').trim();

        var pessoa = pessoas.filter(function (p) { return p.parte && simplificado(p.parte) === resto; })[0];

        if (!pessoa && (resto === '' || quemAssina.indexOf(resto) !== -1)) {
            pessoa = pessoas[0];
        }

        return pessoa && pessoa.dados[tipo] ? pessoa.dados[tipo] : null;
    }

    /*
     * O título de cada cartão: "Signatário 2 - Contratado". O número é a
     * posição na lista — a ordem de atendimento —, então é refeito quando uma
     * linha sai; a parte acompanha o "Assina como".
     */
    function atualizaTitulos() {
        Array.prototype.forEach.call(container.querySelectorAll('[data-signer-row]'), function (linha, n) {
            var titulo = linha.querySelector('[data-signer-title]');
            var parte = linha.querySelector('select[name$="[party]"]');

            if (titulo) {
                titulo.textContent = 'Signatário ' + (n + 1)
                    + (parte && parte.value ? ' - ' + parte.options[parte.selectedIndex].textContent.trim() : '');
            }
        });
    }

    // Chamada a cada mudança na lista de signatários: refaz os títulos dos
    // cartões e as listas "Usar dados do signatário" dos campos.
    function atualizaFontes() {
        atualizaTitulos();

        var pessoas = signatarios();

        Array.prototype.forEach.call(seletoresDeDado, function (select) {
            var tipos = select.getAttribute('data-fill-kinds').split(',');

            select.innerHTML = '';
            select.appendChild(new Option('Usar dados do signatário…', ''));

            pessoas.forEach(function (pessoa) {
                var grupo = document.createElement('optgroup');
                grupo.label = (pessoa.dados.name || 'Signatário ' + pessoa.ordem) + (pessoa.parte ? ' — ' + pessoa.parte : '');

                tipos.forEach(function (tipo) {
                    if (pessoa.dados[tipo]) {
                        grupo.appendChild(new Option(rotulosDeDado[tipo] + ': ' + pessoa.dados[tipo], pessoa.dados[tipo]));
                    }
                });

                if (grupo.children.length) {
                    select.appendChild(grupo);
                }
            });

            if (select.options.length === 1) {
                var vazio = new Option('Preencha os signatários acima primeiro', '');
                vazio.disabled = true;
                select.appendChild(vazio);
            }
        });
    }

    function destaca(alvo) {
        alvo.style.backgroundColor = 'rgba(234, 179, 8, .15)';
        alvo.addEventListener('input', function () { alvo.style.backgroundColor = ''; }, { once: true });
    }

    Array.prototype.forEach.call(seletoresDeDado, function (select) {
        select.addEventListener('focus', atualizaFontes);

        select.addEventListener('change', function () {
            var alvo = document.getElementById(select.getAttribute('data-signer-fill'));

            if (alvo && select.value) {
                // Num texto longo o dado entra no fim do que já foi escrito; nos demais, substitui.
                alvo.value = alvo.tagName === 'TEXTAREA' && alvo.value.trim()
                    ? alvo.value.replace(/\s+$/, '') + ' ' + select.value
                    : select.value;
                alvo.style.backgroundColor = '';
                alvo.focus();
            }

            select.selectedIndex = 0;
        });
    });

    container.addEventListener('input', atualizaFontes);
    container.addEventListener('change', atualizaFontes);

    if (botaoPreencher) {
        botaoPreencher.addEventListener('click', function () {
            var pessoas = signatarios();
            var preenchidos = 0;

            Array.prototype.forEach.call(seletoresDeDado, function (select) {
                var alvo = document.getElementById(select.getAttribute('data-signer-fill'));

                if (!alvo || alvo.value.trim()) {
                    return;
                }

                var dado = sugestao(
                    select.getAttribute('data-fill-label'),
                    select.getAttribute('data-fill-kinds').split(','),
                    pessoas,
                );

                if (dado) {
                    alvo.value = dado;
                    destaca(alvo);
                    preenchidos++;
                }
            });

            avisoPreencher.hidden = false;
            avisoPreencher.textContent = preenchidos
                ? (preenchidos === 1 ? '1 campo preenchido' : preenchidos + ' campos preenchidos')
                    + ' com os dados dos signatários — confira os destacados. Nos demais, use a lista embaixo de cada campo.'
                : 'Nenhum campo vazio pôde ser preenchido sozinho. Use a lista "Usar dados do signatário" embaixo de cada campo.';
        });
    }

    atualizaFontes();
})();
</script>

@include('signature.documents.partials.steps')
