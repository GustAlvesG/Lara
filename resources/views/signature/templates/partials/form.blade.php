@php
    /**
     * Formulário do modelo de documento — o mesmo na criação e na revisão.
     *
     * É o mesmo porque revisar não altera a linha em uso: grava a versão
     * seguinte com estes mesmos campos.
     *
     * O caminho principal é enviar o documento do Word: o servidor converte o
     * .docx, e a resposta preenche o texto e a lista de campos desta tela. O
     * HTML continua existindo — é o que se grava —, mas fica recolhido para
     * quem quiser ajustar à mão.
     *
     * Cada campo tem um TIPO (texto, CPF, valor, data, escolha…). Quem o
     * preenche — o atendente ou quem assina, no tablet — é decidido em cada
     * documento, não aqui; os automáticos o sistema preenche sozinho.
     *
     * Os campos são geridos em JavaScript puro (cartões clonados de um
     * <template>), no padrão das demais telas do projeto — sem componente de
     * build, sem Alpine. O estado inicial vai como JSON: assim o cartão tem
     * UMA definição, e não uma em Blade e outra em string de JavaScript.
     */
    use App\Services\Signature\SignatureFieldTypes;

    $template = $template ?? null;
    $variaveis = array_values(old('variables', $template?->variables ?? []));
    $partes = array_values(old('parties', $template?->parties ?? []));
@endphp

<div class="bg-surface rounded-card shadow-card p-6 space-y-6">

    <div>
        <label for="name" class="block text-sm font-bold text-ink mb-1">Nome do modelo</label>
        <input type="text" name="name" id="name" required maxlength="150"
               value="{{ old('name', $template?->name) }}"
               class="w-full rounded-xl border-line-strong shadow-card focus:border-grena focus:ring-grena-tint">
        @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-bold text-ink mb-1">Descrição</label>
        <input type="text" name="description" id="description" maxlength="2000"
               value="{{ old('description', $template?->description) }}"
               placeholder="Para que serve este documento e quando usá-lo"
               class="w-full rounded-xl border-line-strong shadow-card focus:border-grena focus:ring-grena-tint">
    </div>

    <div>
        <label class="block text-sm font-bold text-ink mb-2">Texto do documento</label>

        <div class="rounded-2xl border border-line bg-subtle p-5">
            <p class="text-sm font-bold text-ink mb-2">Envie o documento pronto do Word</p>
            <ol class="text-xs text-ink-2 leading-relaxed space-y-1 mb-4">
                <li>
                    <strong>1.</strong> No Word, onde entra um dado que muda a cada atendimento, escreva o nome do
                    campo entre colchetes duplos — por exemplo <code class="font-mono">[[Data do evento]]</code> ou
                    <code class="font-mono">[[Valor total]]</code>.
                </li>
                <li>
                    <strong>2.</strong> Onde a pessoa assina, escreva <code class="font-mono">[[assinatura]]</code>
                    numa linha só dela. Nome e CPF de quem assina não precisam de campo: entram sozinhos ali.
                </li>
                <li>
                    <strong>3.</strong> Salve como <strong>.docx</strong> e envie aqui. O texto e a lista de campos
                    são montados automaticamente.
                </li>
            </ol>

            {{-- Sem `name`: o arquivo vai por fetch para a conversão e não acompanha o formulário. --}}
            <input type="file" id="docxFile" accept=".docx" class="hidden"
                   data-import-url="{{ route('signature-templates.import-docx') }}">
            <button type="button" id="docxButton"
                    class="px-5 py-2.5 bg-grena text-white rounded-full font-bold text-sm hover:bg-grena-hover transition">
                Escolher arquivo .docx
            </button>

            <p id="docxStatus" class="mt-3 text-xs text-ink-2" role="status">
                Entram o texto, os títulos, negrito, itálico, listas, numeração e tabelas. Cabeçalho, rodapé e
                imagens do Word ficam de fora: o cabeçalho do clube e o título são aplicados em todo documento.
                Arquivo em PDF ou .doc? Abra no Word e salve como .docx.
            </p>
        </div>

        @error('body_html')<p class="mt-2 text-xs text-danger">{{ $message }}</p>@enderror

        <div id="previewBox" class="mt-4" hidden>
            <p class="text-xs font-bold text-ink-3 uppercase tracking-wider mb-2">Como o documento vai ficar</p>
            {{--
                iframe com sandbox vazio: a prévia mostra também o que está
                sendo digitado no HTML, antes de o servidor sanear — e, sem
                `allow-scripts`, nada do que estiver ali executa. De quebra, o
                estilo da página não vaza para dentro do documento.
            --}}
            <iframe id="bodyPreview" sandbox="" title="Prévia do documento"
                    class="w-full rounded-xl border border-line-strong bg-white" style="height: 30rem;"></iframe>
            <p class="mt-1 text-xs text-ink-2">
                Destacados: em amarelo, os campos preenchidos no documento (pelo atendente ou por quem assina,
                no tablet); em cinza, o que o sistema preenche sozinho.
            </p>
        </div>

        <details class="mt-4" @if($errors->has('body_html')) open @endif>
            <summary class="cursor-pointer text-xs font-bold text-grena-ink hover:underline">
                Editar o texto em HTML (avançado)
            </summary>
            <p class="text-xs text-ink-2 mt-2 mb-2">
                HTML simples: parágrafos, títulos, listas e tabelas. Use <code class="font-mono">[[nome_da_variavel]]</code>
                onde entra um dado preenchido pelo atendente, e <code class="font-mono">[[assinatura]]</code> onde ficam os
                campos de assinatura. Sem o marcador de assinatura, os campos vão para o fim do documento.
            </p>
            {{-- Sem `required`: campo obrigatório dentro de <details> fechado trava o envio sem mostrar por quê. Quem exige é o servidor. --}}
            <textarea name="body_html" id="body_html" rows="18"
                      class="w-full rounded-xl border-line-strong shadow-card font-mono text-xs focus:border-grena focus:ring-grena-tint">{{ old('body_html', $template?->body_html) }}</textarea>
        </details>
    </div>

    <div>
        <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-bold text-ink">Campos do documento</label>
            <button type="button" id="addVariable"
                    class="text-xs font-bold text-grena-ink hover:underline">+ Acrescentar campo</button>
        </div>
        <p class="text-xs text-ink-2 mb-3">
            Cada campo é um <code class="font-mono">[[marcador]]</code> do texto. Vindo do Word, a lista já chega
            pronta. Escolha o <strong>tipo</strong> de cada um. Quem preenche é decidido em cada documento: o
            atendente preenche, ou marca <strong>Perguntar ao signatário</strong> e a pessoa responde no tablet,
            antes de ler o documento.
        </p>

        @foreach($errors->get('variables.*') as $mensagens)
            @foreach($mensagens as $mensagem)
                <p class="mb-2 text-xs text-danger">{{ $mensagem }}</p>
            @endforeach
        @endforeach

        <div id="variables" class="space-y-3"></div>

        <p id="noVariables" class="text-xs text-ink-2" @if(count($variaveis)) hidden @endif>
            Nenhum campo — o texto é fixo.
        </p>

        {{-- O cartão de um campo. O JavaScript clona, numera os `name` e preenche. --}}
        <template id="variableRowTemplate">
            <div class="rounded-xl border border-line p-4 space-y-3" data-variable-row>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-5">
                        <label class="block text-xs font-bold text-ink-2 mb-1">Nome do campo</label>
                        <input type="text" data-field="label" maxlength="120"
                               placeholder="Como o atendente vê este campo"
                               class="w-full rounded-lg border-line-strong text-sm">
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs font-bold text-ink-2 mb-1">Tipo</label>
                        <select data-field="type" class="w-full rounded-lg border-line-strong text-sm">
                            @foreach(SignatureFieldTypes::LABELS as $valor => $rotulo)
                                <option value="{{ $valor }}">{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-ink-2 mb-1">Marcador no texto</label>
                        <input type="text" data-field="key" maxlength="60" placeholder="nome_do_campo"
                               class="w-full rounded-lg border-line-strong text-xs font-mono">
                    </div>
                </div>

                <div data-options-box hidden>
                    <label class="block text-xs font-bold text-ink-2 mb-1">Opções — uma por linha</label>
                    <textarea data-field="options" rows="3" placeholder="Piscina&#10;Academia&#10;Quadra"
                              class="w-full rounded-lg border-line-strong text-sm"></textarea>
                </div>

                <p data-auto-note hidden class="text-xs text-ink-2">
                    Ninguém preenche este campo: o sistema escreve a data em que o documento for assinado.
                </p>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <label class="flex items-center gap-2 text-xs text-ink" data-manual-only>
                        <input type="hidden" data-field="required" data-off value="0">
                        <input type="checkbox" data-field="required" value="1"
                               class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                        Obrigatório
                    </label>
                    <button type="button" class="text-xs text-danger hover:underline" data-remove-variable>Remover campo</button>
                </div>

                {{-- Quem responde o campo é decidido em cada documento: o atendente pode mandá-lo ao tablet. --}}
                <div data-question-box data-manual-only>
                    <label class="block text-xs font-bold text-ink-2 mb-1">Pergunta no tablet (opcional)</label>
                    <input type="text" data-field="question" maxlength="200"
                           placeholder="Ex.: Qual é o seu telefone para contato?"
                           class="w-full rounded-lg border-line-strong text-sm">
                    <p class="mt-1 text-xs text-ink-2">
                        Usada quando o atendente marca, no documento, “Perguntar ao signatário”. Em branco, a
                        pergunta é o nome do campo.
                    </p>
                </div>
            </div>
        </template>
    </div>

    <div>
        <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-bold text-ink">Quem assina</label>
            <button type="button" id="addParty"
                    class="text-xs font-bold text-grena-ink hover:underline">+ Acrescentar parte</button>
        </div>
        <p class="text-xs text-ink-2 mb-3">
            Para documento com mais de uma parte. No Word, escreva
            <code class="font-mono">[[assinatura: Contratante]]</code> e
            <code class="font-mono">[[assinatura: Contratado]]</code> onde cada uma assina — em linhas
            seguidas ou numa tabela, elas saem lado a lado. O nome da parte é impresso sob o nome de quem
            assina. Documento de uma pessoa só não precisa disto: basta
            <code class="font-mono">[[assinatura]]</code>.
        </p>

        @foreach($errors->get('parties.*') as $mensagens)
            @foreach($mensagens as $mensagem)
                <p class="mb-2 text-xs text-danger">{{ $mensagem }}</p>
            @endforeach
        @endforeach

        <div id="parties" class="space-y-3"></div>

        <p id="noParties" class="text-xs text-ink-2" @if(count($partes)) hidden @endif>
            Nenhuma parte — todos os signatários assinam no mesmo lugar.
        </p>

        <template id="partyRowTemplate">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end" data-party-row>
                <div class="md:col-span-5">
                    <label class="block text-xs font-bold text-ink-2 mb-1">Nome da parte</label>
                    <input type="text" data-field="label" maxlength="120" placeholder="Contratante"
                           class="w-full rounded-lg border-line-strong text-sm">
                </div>
                <div class="md:col-span-4">
                    <label class="block text-xs font-bold text-ink-2 mb-1">Marcador no texto</label>
                    <input type="text" data-field="key" maxlength="60" placeholder="contratante"
                           class="w-full rounded-lg border-line-strong text-xs font-mono">
                </div>
                <div class="md:col-span-3">
                    <button type="button" class="text-xs text-danger hover:underline pb-2" data-remove-party>Remover parte</button>
                </div>
            </div>
        </template>
    </div>

    <div>
        <label class="block text-sm font-bold text-ink mb-2">Anexos pedidos</label>
        <p class="text-xs text-ink-2 mb-3">
            Arquivos que todo documento deste modelo pede — identidade, comprovante de residência. O atendente
            envia cada um na tela do documento, antes ou depois da assinatura. Sem os <strong>obrigatórios</strong>, o
            documento não conclui: fica "Assinado", à espera deles.
            Um documento pode pedir anexos a mais, só dele.
        </p>
        @include('signature.partials.attachment-requirements', [
            'id' => 'templateAttachments',
            'items' => old('attachments', $template?->attachments ?? []),
        ])
    </div>

    <div>
        <label class="block text-sm font-bold text-ink mb-2">Itens da revisão</label>
        <p class="text-xs text-ink-2 mb-3">
            O que a <strong>revisão interna</strong> confere em todo documento deste modelo, depois de assinado — os
            processos internos do atendimento ("Cadastro atualizado no sistema", "Pagamento lançado"). A revisão é
            feita por outra pessoa, a partir do dia seguinte, em <strong>Assinaturas → Revisão</strong>.
        </p>
        @include('signature.partials.review-items', [
            'items' => old('review_items', $template?->review_items ?? []),
        ])
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 pt-2 border-t border-line">
        <div>
            <label for="identity_check" class="block text-sm font-bold text-ink mb-1">
                Conferência de identidade
            </label>
            <select name="identity_check" id="identity_check"
                    class="w-full rounded-xl border-line-strong shadow-card focus:border-grena focus:ring-grena-tint">
                @foreach(\App\Models\SignatureTemplate::IDENTITY_CHECKS as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected(old('identity_check', $template?->identity_check) === $valor)>
                        {{ $rotulo }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-ink-2">
                Conferida no servidor. O CPF cadastrado nunca vai para a tela do tablet.
            </p>
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Foto do signatário</label>
            <label class="flex items-center gap-2 mt-3 text-sm text-ink">
                <input type="hidden" name="requires_photo" value="0">
                <input type="checkbox" name="requires_photo" value="1"
                       @checked(old('requires_photo', $template?->requires_photo ?? true))
                       class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                Capturar foto na confirmação
            </label>
            <p class="mt-1 text-xs text-ink-2">
                O tablet avisa a pessoa antes de fotografar (LGPD).
            </p>

            <label class="flex items-center gap-2 mt-4 text-sm text-ink">
                <input type="hidden" name="requires_initials" value="0">
                <input type="checkbox" name="requires_initials" value="1"
                       @checked(old('requires_initials', $template?->requires_initials ?? false))
                       class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                Visto em todas as páginas
            </label>
            <p class="mt-1 text-xs text-ink-2">
                Depois de assinar, a pessoa faz a rubrica no tablet, e ela é aplicada ao pé de cada página
                do documento.
            </p>
        </div>

        <div>
            <label for="retention_months" class="block text-sm font-bold text-ink mb-1">
                Prazo de guarda (meses)
            </label>
            <input type="number" name="retention_months" id="retention_months" min="1" max="1200"
                   value="{{ old('retention_months', $template?->retention_months) }}"
                   placeholder="{{ config('signature.retention_months') }}"
                   class="w-full rounded-xl border-line-strong shadow-card focus:border-grena focus:ring-grena-tint">
            <p class="mt-1 text-xs text-ink-2">
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
    var molde = document.getElementById('variableRowTemplate');
    var empty = document.getElementById('noVariables');
    var body = document.getElementById('body_html');
    var previewBox = document.getElementById('previewBox');
    var preview = document.getElementById('bodyPreview');
    var file = document.getElementById('docxFile');
    var fileButton = document.getElementById('docxButton');
    var status = document.getElementById('docxStatus');

    if (!container || !addButton || !molde || !body) {
        return;
    }

    var iniciais = @json($variaveis);
    var partesIniciais = @json($partes);
    var caixaPartes = document.getElementById('parties');
    var moldeParte = document.getElementById('partyRowTemplate');
    var semPartes = document.getElementById('noParties');
    var indiceParte = 0;

    function linhaDeParte(parte) {
        var i = indiceParte++;
        var row = moldeParte.content.firstElementChild.cloneNode(true);

        Array.prototype.forEach.call(row.querySelectorAll('[data-field]'), function (el) {
            el.name = 'parties[' + i + '][' + el.dataset.field + ']';
            el.value = (parte && parte[el.dataset.field]) || '';
        });

        return row;
    }

    function listaDePartes() {
        return Array.prototype.map.call(caixaPartes.querySelectorAll('[data-party-row]'), function (row) {
            return {
                key: row.querySelector('[data-field="key"]').value.trim(),
                label: row.querySelector('[data-field="label"]').value.trim()
            };
        });
    }

    function atualizaSemPartes() {
        semPartes.hidden = caixaPartes.querySelectorAll('[data-party-row]').length > 0;
    }

    // Como nos campos: a parte que já estava na tela mantém o nome ajustado.
    function substituiPartes(novas) {
        var atuais = {};

        listaDePartes().forEach(function (p) {
            atuais[p.key] = p;
        });

        caixaPartes.innerHTML = '';

        novas.forEach(function (p) {
            caixaPartes.appendChild(linhaDeParte(atuais[p.key] || p));
        });

        atualizaSemPartes();
    }
    var comOpcoes = @json(SignatureFieldTypes::WITH_OPTIONS);
    var automaticos = @json(SignatureFieldTypes::AUTOMATIC);

    // Índice do próximo campo — só cresce, para dois cartões nunca dividirem
    // o mesmo `name` depois de uma remoção.
    var index = 0;

    // Vindo de um envio recusado, o "sim" chega como a string "1".
    function sim(valor) {
        return valor === true || valor === 1 || valor === '1';
    }

    function campo(row, nome) {
        return row.querySelector('[data-field="' + nome + '"]:not([data-off])');
    }

    // Mostra só o que o tipo usa: opções para os de escolha, e nem a pergunta
    // nem a obrigatoriedade para o que o sistema preenche.
    function ajusta(row) {
        var tipo = campo(row, 'type').value;
        var automatico = automaticos.indexOf(tipo) !== -1;

        row.querySelector('[data-options-box]').hidden = comOpcoes.indexOf(tipo) === -1;
        row.querySelector('[data-auto-note]').hidden = !automatico;

        Array.prototype.forEach.call(row.querySelectorAll('[data-manual-only]'), function (el) {
            el.hidden = automatico;
        });

    }

    // Os valores entram por propriedade, e não por concatenação no HTML: o
    // nome do campo vem do que a pessoa escreveu no Word.
    function linha(variavel) {
        var i = index++;
        var row = molde.content.firstElementChild.cloneNode(true);

        Array.prototype.forEach.call(row.querySelectorAll('[data-field]'), function (el) {
            el.name = 'variables[' + i + '][' + el.dataset.field + ']';
        });

        variavel = variavel || {};

        campo(row, 'key').value = variavel.key || '';
        campo(row, 'label').value = variavel.label || '';
        campo(row, 'type').value = variavel.type || 'text';
        campo(row, 'required').checked = sim(variavel.required);
        campo(row, 'question').value = variavel.question || '';
        campo(row, 'options').value = Array.isArray(variavel.options)
            ? variavel.options.join('\n')
            : (variavel.options || '');

        // Tipo desconhecido (modelo antigo, valor adulterado): cai em texto.
        if (!campo(row, 'type').value) {
            campo(row, 'type').value = 'text';
        }

        ajusta(row);

        return row;
    }

    function linhas() {
        return Array.prototype.map.call(container.querySelectorAll('[data-variable-row]'), function (row) {
            return {
                key: campo(row, 'key').value.trim(),
                label: campo(row, 'label').value.trim(),
                type: campo(row, 'type').value,
                required: campo(row, 'required').checked,
                question: campo(row, 'question').value,
                options: campo(row, 'options').value
            };
        });
    }

    function atualizaVazio() {
        if (empty) {
            empty.hidden = container.querySelectorAll('[data-variable-row]').length > 0;
        }
    }

    // Troca a lista pelos campos do documento importado. Um campo que já
    // estava na tela mantém tudo o que a pessoa ajustou nele — tipo, pergunta,
    // opções.
    function substituiVariaveis(novas) {
        var atuais = {};

        linhas().forEach(function (v) {
            atuais[v.key] = v;
        });

        container.innerHTML = '';

        novas.forEach(function (v) {
            container.appendChild(linha(atuais[v.key] || v));
        });

        atualizaVazio();
    }

    function escapa(texto) {
        return String(texto).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    var estilo =
        'body{font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.65;color:#1f1819;margin:22px 26px}' +
        'h1,h2{font-size:15px;margin:16px 0 7px}h3,h4{font-size:13.5px;margin:13px 0 6px}' +
        'p{margin:0 0 9px;text-align:justify}ol,ul{margin:0 0 9px 18px;padding:0}li{margin-bottom:5px}' +
        'table{width:100%;border-collapse:collapse;margin:0 0 11px}' +
        'td,th{border:1px solid #d8cbc9;padding:5px 7px;font-size:12px;vertical-align:top}' +
        'th{background:#f2ecea;text-align:left}' +
        'mark{background:#fde9a8;border-radius:3px;padding:0 3px}' +
        'mark.auto{background:#e4e0e0}' +
        '.sig{margin:22px 0 6px;border:1px dashed #A00001;color:#A00001;border-radius:6px;' +
        'padding:14px;text-align:center;font-size:12px}';

    var assinatura = '<div class="sig">Aqui entram os campos de assinatura (nome, CPF e traço de cada pessoa)</div>';

    function atualizaPrevia() {
        if (!preview || !previewBox) {
            return;
        }

        var html = body.value;

        previewBox.hidden = html.trim() === '';

        if (previewBox.hidden) {
            return;
        }

        var campos = {};

        linhas().forEach(function (v) {
            campos[v.key] = v;
        });

        var temAssinatura = false;
        var nomesDasPartes = {};

        listaDePartes().forEach(function (p) {
            nomesDasPartes[p.key] = p.label;
        });

        html = html.replace(/\[\[assinatura:([a-z][a-z0-9_]*)\]\]/g, function (marcador, chave) {
            temAssinatura = true;

            return '<div class="sig">Assinatura — ' + escapa(nomesDasPartes[chave] || chave) + '</div>';
        });

        html = html.replace(/\[\[([a-z][a-z0-9_]*)\]\]/g, function (marcador, chave) {
            if (chave === 'assinatura') {
                temAssinatura = true;

                return assinatura;
            }

            var v = campos[chave];
            var classe = '';

            if (v && automaticos.indexOf(v.type) !== -1) {
                classe = 'auto';
            }

            return '<mark class="' + classe + '">' + escapa((v && v.label) || chave) + '</mark>';
        });

        // Sem o marcador, a área de assinatura vai para o fim — como no documento de verdade.
        preview.srcdoc = '<!DOCTYPE html><meta charset="utf-8"><style>' + estilo + '</style>' +
            html + (temAssinatura ? '' : assinatura);
    }

    var espera = null;

    function agendaPrevia() {
        clearTimeout(espera);
        espera = setTimeout(atualizaPrevia, 300);
    }

    addButton.addEventListener('click', function () {
        container.appendChild(linha());
        atualizaVazio();
    });

    container.addEventListener('click', function (event) {
        if (event.target.matches('[data-remove-variable]')) {
            event.target.closest('[data-variable-row]').remove();
            atualizaVazio();
            agendaPrevia();
        }
    });

    container.addEventListener('change', function (event) {
        var row = event.target.closest('[data-variable-row]');

        if (row) {
            ajusta(row);
            agendaPrevia();
        }
    });

    container.addEventListener('input', agendaPrevia);
    body.addEventListener('input', agendaPrevia);

    document.getElementById('addParty').addEventListener('click', function () {
        caixaPartes.appendChild(linhaDeParte());
        atualizaSemPartes();
    });

    caixaPartes.addEventListener('click', function (event) {
        if (event.target.matches('[data-remove-party]')) {
            event.target.closest('[data-party-row]').remove();
            atualizaSemPartes();
            agendaPrevia();
        }
    });

    caixaPartes.addEventListener('input', agendaPrevia);

    function avisa(texto, erro) {
        status.textContent = texto;
        status.className = 'mt-3 text-xs ' + (erro ? 'text-danger font-bold' : 'text-ink');
    }

    if (file && fileButton && status) {
        fileButton.addEventListener('click', function () {
            file.click();
        });

        file.addEventListener('change', function () {
            if (!file.files.length) {
                return;
            }

            var nome = file.files[0].name;
            var dados = new FormData();
            dados.append('arquivo', file.files[0]);

            // Limpa a seleção: escolher o MESMO arquivo de novo (depois de
            // corrigi-lo no Word) precisa disparar outro envio.
            file.value = '';

            fileButton.disabled = true;
            avisa('Lendo "' + nome + '"…', false);

            fetch(file.dataset.importUrl, {
                method: 'POST',
                body: dados,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': body.form.querySelector('input[name="_token"]').value
                }
            })
                .then(function (resposta) {
                    return resposta.json().then(function (json) {
                        return { ok: resposta.ok, json: json };
                    });
                })
                .then(function (resultado) {
                    if (!resultado.ok) {
                        avisa(resultado.json.message || 'Não foi possível ler o arquivo.', true);

                        return;
                    }

                    var r = resultado.json;

                    body.value = r.html;
                    substituiVariaveis(r.variables);
                    substituiPartes(r.parties || []);
                    atualizaPrevia();

                    var campos = r.variables.length;
                    var partes = [
                        '"' + nome + '" importado: ' +
                        (campos === 0 ? 'nenhum campo encontrado (texto fixo).'
                            : campos === 1 ? '1 campo encontrado.'
                            : campos + ' campos encontrados.')
                    ];

                    if (campos > 0) {
                        partes.push('O tipo de cada campo foi sugerido pelo nome — confira na lista.');
                    }

                    if ((r.parties || []).length) {
                        partes.push('Partes que assinam: ' +
                            r.parties.map(function (p) { return p.label; }).join(', ') + '.');
                    }

                    if (!r.has_signature) {
                        partes.push('O documento não tem [[assinatura]]: os campos de assinatura vão para o fim.');
                    }

                    avisa(partes.concat(r.warnings).join(' ') + ' Confira a prévia abaixo antes de salvar.', false);
                })
                .catch(function () {
                    avisa('Não foi possível enviar o arquivo. Confira a conexão e tente de novo.', true);
                })
                .then(function () {
                    fileButton.disabled = false;
                });
        });
    }

    iniciais.forEach(function (v) {
        container.appendChild(linha(v));
    });

    partesIniciais.forEach(function (p) {
        caixaPartes.appendChild(linhaDeParte(p));
    });

    atualizaSemPartes();

    atualizaVazio();
    atualizaPrevia();
})();
</script>
