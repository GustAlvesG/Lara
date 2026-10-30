{{--
    Criar e editar campanha: dados do envio e o conteúdo, com prévia ao lado.

    Dois jeitos de escrever:
      - Editor visual (padrão): texto formatado, como num editor de texto. O
        Lara monta o e-mail no layout do clube (EmailComposer), e a prévia vem
        do mesmo compose() do servidor — a prévia é o e-mail que vai sair.
      - HTML (avançado): o código à mão, para quem tem um e-mail pronto.
    "Passar para HTML" leva o e-mail montado para o código; o caminho de volta
    não converte o código, só retoma o texto do editor visual.

    A prévia é um iframe sem script (sandbox sem allow-scripts) e troca as
    variáveis pelos dados do primeiro destinatário da lista.

    Nada de chaves duplas literais neste arquivo fora das diretivas: o Blade
    as compilaria. O JS monta as chaves com fromCharCode/\x7B e o HTML com
    a entidade &#123;.
--}}
@php
    use App\Models\EmailCampaign\EmailCampaign;

    $editing = $campaign->exists;
    $hint = 'mt-1.5 text-xs text-ink-3';
    $tool = 'inline-flex h-8 items-center gap-1.5 rounded-full border border-line-strong bg-surface px-3 text-[13px] font-bold text-ink transition hover:border-ink-3 disabled:opacity-50';
    $square = 'inline-grid h-8 min-w-8 place-items-center rounded-lg px-2 text-[13px] font-bold text-ink transition hover:bg-subtle';
    $starter = file_get_contents(resource_path('email-campaigns/modelo-inicial.html'));
    $variables = $campaign->variables();
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page wide>
        <x-page-title :title="$editing ? 'Editar campanha' : 'Nova campanha'" :back="$editing ? route('email-campaigns.show', $campaign) : route('email-campaigns.index')">
            @if ($editing)
                {{ $campaign->name }} · {{ $campaign->statusLabel() }}
            @else
                Primeiro os dados e o texto; a lista de destinatários vem no passo seguinte.
            @endif
        </x-page-title>

        @include('partials.alerts')

        <form method="POST" action="{{ $editing ? route('email-campaigns.update', $campaign) : route('email-campaigns.store') }}"
              x-data="emailEditor({{ Js::from([
                  'editor' => old('editor', $campaign->editor ?? EmailCampaign::EDITOR_VISUAL),
                  'body' => old('body', $campaign->body ?? ''),
                  'html' => old('html', $campaign->html ?? ''),
                  'subject' => old('subject', $campaign->subject ?? ''),
                  'fromName' => old('from_name', $campaign->from_name ?? ''),
                  'sample' => $sample,
                  'variables' => array_keys($variables),
                  'starter' => $starter,
                  'uploadUrl' => route('email-campaigns.images.store'),
                  'composeUrl' => route('email-campaigns.compose'),
              ]) }})"
              class="flex flex-col gap-4">
            @csrf
            @if ($editing) @method('PUT') @endif
            <input type="hidden" name="editor" :value="editor">
            <input type="hidden" name="body" :value="body">

            <section class="rounded-card bg-surface p-5 shadow-card sm:p-6">
                <h3 class="mb-4 text-base font-bold text-ink">Dados do envio</h3>
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <x-input-label for="name" class="mb-1.5">Nome da campanha <span class="text-danger">*</span></x-input-label>
                        <x-text-input type="text" name="name" id="name" required maxlength="120" value="{{ old('name', $campaign->name) }}" placeholder="Ex.: Convite Festa Junina 2026" />
                        <p class="{{ $hint }}">Só para a equipe; o destinatário não vê.</p>
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="subject" class="mb-1.5">Assunto <span class="text-danger">*</span></x-input-label>
                        <x-text-input type="text" name="subject" id="subject" required maxlength="200" value="{{ old('subject', $campaign->subject) }}" x-model="subject" />
                        <p class="{{ $hint }}">Aceita variáveis, como no texto (ex.: &#123;&#123;primeiro_nome&#125;&#125;, venha para a festa!).</p>
                        <x-input-error :messages="$errors->get('subject')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="from_address" class="mb-1.5">E-mail do remetente <span class="text-danger">*</span></x-input-label>
                        <x-select-input name="from_address" id="from_address" required>
                            @foreach ($fromAddresses as $address)
                                <option value="{{ $address }}" @selected(old('from_address', $campaign->from_address) === $address)>{{ $address }}</option>
                            @endforeach
                        </x-select-input>
                        <p class="{{ $hint }}">Só os endereços liberados pela TI no servidor de e-mail; outro endereço cairia no spam.</p>
                        <x-input-error :messages="$errors->get('from_address')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="from_name" value="Nome do remetente" class="mb-1.5" />
                        <x-text-input type="text" name="from_name" id="from_name" maxlength="100" value="{{ old('from_name', $campaign->from_name) }}" x-model="fromName" placeholder="Ex.: Clube dos Funcionários" />
                        <p class="{{ $hint }}">No editor visual, aparece também na faixa do alto do e-mail.</p>
                        <x-input-error :messages="$errors->get('from_name')" class="mt-1" />
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="reply_to" value="Responder para" class="mb-1.5" />
                        <x-text-input type="email" name="reply_to" id="reply_to" maxlength="255" value="{{ old('reply_to', $campaign->reply_to) }}" placeholder="Ex.: secretaria@clubedosfuncionarios.com.br" />
                        <p class="{{ $hint }}">Para onde vão as respostas e os pedidos de descadastro ("cancelar inscrição" do Gmail e do Outlook). Em branco, vão para o remetente.</p>
                        <x-input-error :messages="$errors->get('reply_to')" class="mt-1" />
                    </div>
                </div>
            </section>

            <section class="rounded-card bg-surface p-5 shadow-card sm:p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-bold text-ink">Texto do e-mail</h3>
                    <div class="inline-flex rounded-full border border-line-strong bg-subtle p-1" role="tablist" aria-label="Como escrever">
                        <button type="button" role="tab" :aria-selected="(editor === 'visual').toString()" @click="toVisual()"
                                class="h-8 rounded-full px-4 text-[13px] font-bold transition" :class="editor === 'visual' ? 'bg-surface text-ink shadow-card' : 'text-ink-3 hover:text-ink'">
                            <x-icon name="pencil" class="inline h-3.5 w-3.5" /> Editor visual
                        </button>
                        <button type="button" role="tab" :aria-selected="(editor === 'html').toString()" @click="toHtml()"
                                class="h-8 rounded-full px-4 text-[13px] font-bold transition" :class="editor === 'html' ? 'bg-surface text-ink shadow-card' : 'text-ink-3 hover:text-ink'">
                            HTML (avançado)
                        </button>
                    </div>
                </div>

                {{-- Barra do editor visual: @mousedown.prevent mantém o texto selecionado. --}}
                <div x-show="editor === 'visual'" class="mb-2 flex flex-wrap items-center gap-0.5 rounded-xl border border-line-strong bg-subtle p-1.5" role="toolbar" aria-label="Formatação do texto">
                    <button type="button" class="{{ $square }}" title="Texto normal" @mousedown.prevent="command('formatBlock', '<p>')">Texto</button>
                    <button type="button" class="{{ $square }} text-base" title="Título" @mousedown.prevent="command('formatBlock', '<h2>')">Título</button>
                    <button type="button" class="{{ $square }}" title="Subtítulo" @mousedown.prevent="command('formatBlock', '<h3>')">Subtítulo</button>
                    <span class="mx-1 h-5 w-px bg-line-strong"></span>
                    <button type="button" class="{{ $square }}" title="Negrito" @mousedown.prevent="command('bold')"><span class="font-black">N</span></button>
                    <button type="button" class="{{ $square }}" title="Itálico" @mousedown.prevent="command('italic')"><span class="font-serif italic">I</span></button>
                    <button type="button" class="{{ $square }}" title="Sublinhado" @mousedown.prevent="command('underline')"><span class="underline">S</span></button>
                    <span class="mx-1 h-5 w-px bg-line-strong"></span>
                    <button type="button" class="{{ $square }}" title="Lista com marcadores" @mousedown.prevent="command('insertUnorderedList')">• Lista</button>
                    <button type="button" class="{{ $square }}" title="Lista numerada" @mousedown.prevent="command('insertOrderedList')">1. Lista</button>
                    <button type="button" class="{{ $square }}" title="Alinhar à esquerda" @mousedown.prevent="command('justifyLeft')">Esquerda</button>
                    <button type="button" class="{{ $square }}" title="Centralizar" @mousedown.prevent="command('justifyCenter')">Centro</button>
                    <span class="mx-1 h-5 w-px bg-line-strong"></span>
                    <button type="button" class="{{ $square }}" title="Transformar o texto selecionado em link" @mousedown.prevent="insertLink()">Link</button>
                    <button type="button" class="{{ $square }}" title="Botão grená com link" @mousedown.prevent="insertButton()">Botão</button>
                    <button type="button" class="{{ $square }}" title="Imagem" @mousedown.prevent="pickImage()" :disabled="uploading">
                        <x-icon name="image" class="h-3.5 w-3.5" /> <span class="ml-1" x-text="uploading ? 'Enviando…' : 'Imagem'">Imagem</span>
                    </button>
                    <button type="button" class="{{ $square }}" title="Linha divisória" @mousedown.prevent="command('insertHorizontalRule')">Divisória</button>
                    <button type="button" class="{{ $square }} text-ink-3" title="Tirar negrito, itálico e link do trecho selecionado" @mousedown.prevent="command('removeFormat'); command('unlink')">Limpar formatação</button>
                </div>

                {{-- Ferramentas do modo HTML. --}}
                <div x-show="editor === 'html'" x-cloak class="mb-2 flex flex-wrap items-center gap-1.5" role="toolbar" aria-label="Inserir no código">
                    <button type="button" class="{{ $tool }}" @click="useStarter()"><x-icon name="doc" class="h-3.5 w-3.5" /> Modelo inicial</button>
                    <button type="button" class="{{ $tool }}" @click="insert(snippets.title)">Título</button>
                    <button type="button" class="{{ $tool }}" @click="insert(snippets.paragraph)">Parágrafo</button>
                    <button type="button" class="{{ $tool }}" @click="insert(snippets.button)">Botão</button>
                    <button type="button" class="{{ $tool }}" @click="insert(snippets.divider)">Divisória</button>
                    <button type="button" class="{{ $tool }}" @click="pickImage()" :disabled="uploading">
                        <x-icon name="image" class="h-3.5 w-3.5" /> <span x-text="uploading ? 'Enviando…' : 'Imagem'">Imagem</span>
                    </button>
                    <button type="button" class="{{ $tool }}" @click="$refs.htmlFile.click()"><x-icon name="download" class="h-3.5 w-3.5 rotate-180" /> Abrir arquivo .html</button>
                </div>
                <input type="file" x-ref="image" class="hidden" accept="image/png,image/jpeg,image/gif,image/webp" @change="uploadImage($event)">
                <input type="file" x-ref="htmlFile" class="hidden" accept=".html,.htm,text/html" @change="openHtml($event)">

                <div class="mb-3 flex flex-wrap items-center gap-1.5">
                    <span class="text-xs font-bold text-ink-3">Personalizar (clique para inserir):</span>
                    @foreach ($variables as $key => $label)
                        <button type="button" class="rounded-lg bg-subtle px-2 py-1 text-xs text-ink transition hover:bg-grena-tint hover:text-grena-ink"
                                title="Troca por &#123;&#123;{{ $key }}&#125;&#125; de cada destinatário" @mousedown.prevent="insertVariable(@js($key))">
                            {{ $label }} <span class="font-mono text-ink-3">&#123;&#123;{{ $key }}&#125;&#125;</span>
                        </button>
                    @endforeach
                </div>

                <p x-show="error" x-cloak x-text="error" class="mb-3 rounded-xl bg-danger-soft px-4 py-2 text-sm text-danger"></p>
                <p x-show="unknown.length" x-cloak class="mb-3 rounded-xl bg-warn-soft px-4 py-2 text-sm text-warn">
                    O texto usa variáveis que a lista não tem: <span class="font-mono" x-text="unknown.map(k => open + k + close).join(', ')"></span>. Elas sairiam vazias.
                </p>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <div class="flex min-w-0 flex-col">
                        <div x-show="editor === 'visual'">
                            <div x-ref="visual" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Texto do e-mail"
                                 @input="syncBody()" @keyup="saveRange()" @mouseup="saveRange()" @blur="saveRange()" @paste.prevent="pastePlain($event)"
                                 class="email-visual-editor h-[620px] overflow-y-auto rounded-xl border border-line-strong bg-white p-6 text-[16px] leading-relaxed text-[#2b2523] focus:border-grena focus:outline-none focus:ring-4 focus:ring-grena-tint"></div>
                            <x-input-error :messages="$errors->get('body')" class="mt-1" />
                            <p class="{{ $hint }}">Escreva como num editor de texto. Texto colado de outro lugar entra sem a formatação de lá: formate aqui. O cabeçalho e o rodapé (com o descadastro) são colocados pelo Lara.</p>
                        </div>

                        <div x-show="editor === 'html'" x-cloak>
                            <textarea name="html" x-ref="code" x-model="html" spellcheck="false" aria-label="Código HTML do e-mail"
                                      @keydown.tab.prevent="insert('  ')"
                                      class="h-[620px] w-full resize-y rounded-xl border border-line-strong bg-subtle p-3 font-mono text-[12.5px] leading-relaxed text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint"
                                      placeholder="Cole aqui o HTML do e-mail ou clique em Modelo inicial."></textarea>
                            <x-input-error :messages="$errors->get('html')" class="mt-1" />
                            <p class="{{ $hint }}">Use tabelas e estilo dentro das tags (style="…"): é o que os programas de e-mail respeitam. Script não é enviado.</p>
                        </div>
                    </div>

                    {{-- Expandida, a prévia cobre a tela inteira; Esc ou "Fechar" voltam. --}}
                    <div class="flex min-w-0 flex-col" :class="expanded && 'fixed inset-0 z-50 bg-surface p-4 sm:p-6'"
                         @keydown.escape.window="expanded = false">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <p class="min-w-0 truncate text-sm text-ink-2"><span class="font-bold text-ink">Assunto:</span> <span x-text="fill(subject, false) || '—'"></span></p>
                            <div class="flex gap-1" role="group" aria-label="Largura da prévia">
                                <button type="button" class="{{ $tool }}" :class="width === 'desktop' && '!border-grena text-grena-ink'" @click="width = 'desktop'"><x-icon name="monitor" class="h-3.5 w-3.5" /> Computador</button>
                                <button type="button" class="{{ $tool }}" :class="width === 'mobile' && '!border-grena text-grena-ink'" @click="width = 'mobile'">Celular</button>
                                <button type="button" class="{{ $tool }}" @click="expanded = !expanded" :aria-pressed="expanded.toString()">
                                    <x-icon name="eye" class="h-3.5 w-3.5" /> <span x-text="expanded ? 'Fechar' : 'Expandir'">Expandir</span>
                                </button>
                            </div>
                        </div>
                        <div class="flex justify-center overflow-hidden rounded-xl border border-line-strong bg-[#f4f1ee]" :class="expanded ? 'min-h-0 flex-1' : 'h-[620px]'">
                            <iframe x-ref="preview" title="Prévia do e-mail" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                                    class="h-full bg-white transition-all" :class="width === 'mobile' ? 'w-[375px]' : 'w-full'"></iframe>
                        </div>
                        <p class="{{ $hint }}">Prévia com os dados de <span class="font-mono" x-text="sample.email"></span>. Cada programa de e-mail desenha um pouco diferente: mande um teste antes.</p>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-2.5">
                <x-secondary-button-a href="{{ $editing ? route('email-campaigns.show', $campaign) : route('email-campaigns.index') }}">Cancelar</x-secondary-button-a>
                <x-primary-button><x-icon name="check" /> {{ $editing ? 'Salvar' : 'Salvar e importar a lista' }}</x-primary-button>
            </div>
        </form>
    </x-page>

    {{-- O editor visual imita o e-mail: mesmas margens e tamanhos do EmailComposer. --}}
    <style>
        .email-visual-editor h2 { font-size: 24px; line-height: 1.3; font-weight: 700; margin: 0 0 14px; }
        .email-visual-editor h3 { font-size: 19px; line-height: 1.35; font-weight: 700; margin: 0 0 12px; }
        .email-visual-editor p { margin: 0 0 16px; }
        .email-visual-editor ul { list-style: disc; padding-left: 24px; margin: 0 0 16px; }
        .email-visual-editor ol { list-style: decimal; padding-left: 24px; margin: 0 0 16px; }
        .email-visual-editor a { color: #a00001; text-decoration: underline; }
        .email-visual-editor a.email-button { display: inline-block; padding: 12px 26px; border-radius: 999px; background: #a00001; color: #fff; font-weight: 700; text-decoration: none; }
        .email-visual-editor img { display: block; max-width: 100%; height: auto; margin: 0 0 16px; }
        .email-visual-editor hr { border: 0; border-top: 1px solid #ece6e1; margin: 24px 0; }
        .email-visual-editor:empty::before { content: 'Escreva aqui o texto do e-mail…'; color: #9a8f8a; }
    </style>

    <script>
        function emailEditor(config) {
            const open = String.fromCharCode(123, 123);
            const close = String.fromCharCode(125, 125);
            const variablePattern = new RegExp('\\x7B\\x7B\\s*([A-Za-z0-9_]+)\\s*\\x7D\\x7D', 'g');
            const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

            const escapeHtml = (value) => String(value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

            return {
                editor: config.editor || 'visual',
                body: config.body || '',
                html: config.html || '',
                composed: '',
                subject: config.subject || '',
                fromName: config.fromName || '',
                sample: config.sample || {},
                known: new Set(['email', 'nome', 'primeiro_nome', ...(config.variables || [])]),
                width: 'desktop',
                expanded: false,
                uploading: false,
                error: '',
                range: null,
                open, close,
                timer: null,
                request: 0,
                snippets: {
                    title: '<h2 style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:22px;line-height:1.3;color:#2b2523;">Título</h2>\n',
                    paragraph: '<p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#2b2523;">Texto do parágrafo.</p>\n',
                    button: '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;"><tr><td style="background-color:#a00001;border-radius:999px;">'
                        + '<a href="https://www.exemplo.com.br" style="display:inline-block;padding:14px 28px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">Texto do botão</a>'
                        + '</td></tr></table>\n',
                    divider: '<hr style="border:0;border-top:1px solid #ece6e1;margin:24px 0;">\n',
                },

                init() {
                    this.$refs.visual.innerHTML = this.body;
                    document.execCommand('defaultParagraphSeparator', false, 'p');

                    this.$watch('html', () => this.schedule());
                    this.$watch('subject', () => this.schedule());
                    this.$watch('fromName', () => this.schedule());
                    this.render();
                },

                get unknown() {
                    const used = new Set();
                    const text = this.subject + ' ' + (this.editor === 'visual' ? this.body : this.html);
                    for (const match of text.matchAll(variablePattern)) {
                        used.add(match[1].toLowerCase());
                    }
                    return [...used].filter((key) => !this.known.has(key));
                },

                fill(text, escape = true) {
                    return String(text || '').replace(variablePattern, (match, key) => {
                        const value = this.sample[key.toLowerCase()] ?? '';
                        return escape ? escapeHtml(value) : value;
                    });
                },

                schedule() {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => this.render(), this.editor === 'visual' ? 400 : 250);
                },

                async render() {
                    let doc;

                    if (this.editor === 'visual') {
                        doc = await this.compose();
                        if (doc === null) return;
                    } else {
                        doc = this.html;
                    }

                    doc = this.fill(doc.replace(/<script\b[^>]*>[\s\S]*?<\/script\s*>/gi, ''));

                    if (!/<html[\s>]/i.test(doc)) {
                        doc = '<!DOCTYPE html><html><head><meta charset="utf-8"><base target="_blank"></head><body style="margin:0;">' + doc + '</body></html>';
                    }

                    this.$refs.preview.srcdoc = doc;
                },

                // O e-mail montado pelo servidor; null quando uma resposta mais nova já chegou.
                async compose() {
                    const ticket = ++this.request;

                    try {
                        const response = await fetch(config.composeUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                            body: JSON.stringify({ body: this.body, from_name: this.fromName }),
                        });
                        if (!response.ok) throw new Error();
                        const data = await response.json();

                        if (ticket !== this.request) return null;
                        this.composed = data.html;
                        return data.html;
                    } catch (e) {
                        this.error = 'Não foi possível atualizar a prévia. Confira a conexão; o texto continua salvo na tela.';
                        return null;
                    }
                },

                // --- editor visual ---

                syncBody() {
                    this.body = this.$refs.visual.innerHTML;
                    this.error = '';
                    this.schedule();
                },

                saveRange() {
                    const selection = window.getSelection();
                    if (selection.rangeCount && this.$refs.visual.contains(selection.anchorNode)) {
                        this.range = selection.getRangeAt(0).cloneRange();
                    }
                },

                restoreRange() {
                    this.$refs.visual.focus();
                    if (!this.range) return;
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(this.range);
                },

                command(name, value = null) {
                    this.restoreRange();
                    document.execCommand(name, false, value);
                    this.saveRange();
                    this.syncBody();
                },

                insertHtml(html) {
                    this.command('insertHTML', html);
                },

                insertLink() {
                    this.saveRange();
                    const selected = this.range ? this.range.toString() : '';
                    const url = this.askUrl('Endereço do link (ex.: https://www.clube.com.br/festa):');
                    if (!url) return;

                    if (selected.trim() !== '') {
                        this.command('createLink', url);
                    } else {
                        const label = prompt('Texto do link:', url);
                        if (label) this.insertHtml('<a href="' + escapeHtml(url) + '">' + escapeHtml(label) + '</a>');
                    }
                },

                insertButton() {
                    this.saveRange();
                    const label = prompt('Texto do botão (ex.: Garanta seu ingresso):', 'Saiba mais');
                    if (!label) return;
                    const url = this.askUrl('Endereço que o botão abre:');
                    if (!url) return;

                    this.insertHtml('<p><a class="email-button" href="' + escapeHtml(url) + '">' + escapeHtml(label) + '</a></p><p><br></p>');
                },

                askUrl(question) {
                    let url = (prompt(question, 'https://') || '').trim();
                    if (url === '' || url === 'https://') return null;
                    if (!/^(https?:|mailto:|tel:)/i.test(url) && !url.startsWith(open)) {
                        url = (url.includes('@') && !url.includes('/') ? 'mailto:' : 'https://') + url;
                    }
                    return url;
                },

                // Colar sempre como texto: a formatação do Word e de sites quebra o e-mail.
                pastePlain(event) {
                    const text = (event.clipboardData || window.clipboardData).getData('text/plain');
                    if (!text) return;

                    const html = text.replace(/\r\n?/g, '\n').split(/\n{2,}/)
                        .map((block) => '<p>' + escapeHtml(block).replace(/\n/g, '<br>') + '</p>')
                        .join('');

                    this.insertHtml(html);
                },

                // --- comum aos dois modos ---

                insertVariable(key) {
                    const token = open + key + close;
                    if (this.editor === 'visual') {
                        this.command('insertText', token);
                    } else {
                        this.insert(token);
                    }
                },

                toHtml() {
                    if (this.editor === 'html') return;
                    if (!confirm('Passar para o modo HTML? O texto vira código, para quem sabe editar HTML. Se voltar ao editor visual depois, as mudanças feitas no código se perdem.')) {
                        return;
                    }

                    this.compose().then((html) => {
                        if (html !== null) this.html = html;
                        this.editor = 'html';
                        this.render();
                    });
                },

                toVisual() {
                    if (this.editor === 'visual') return;
                    if (this.html.trim() !== '' && this.html !== this.composed
                        && !confirm('Voltar ao editor visual? O código HTML não é convertido de volta: vale o texto que está no editor visual, e as mudanças feitas no código se perdem.')) {
                        return;
                    }

                    this.editor = 'visual';
                    this.render();
                },

                // --- modo HTML ---

                insert(snippet) {
                    const area = this.$refs.code;
                    const start = area.selectionStart ?? this.html.length;
                    const end = area.selectionEnd ?? this.html.length;

                    this.html = this.html.slice(0, start) + snippet + this.html.slice(end);
                    this.$nextTick(() => {
                        area.focus();
                        area.selectionStart = area.selectionEnd = start + snippet.length;
                    });
                },

                useStarter() {
                    if (this.html.trim() !== '' && !confirm('Trocar o conteúdo atual pelo modelo inicial?')) {
                        return;
                    }
                    this.html = config.starter;
                },

                openHtml(event) {
                    const file = event.target.files[0];
                    event.target.value = '';
                    if (!file) return;

                    if (this.html.trim() !== '' && !confirm('Trocar o conteúdo atual pelo arquivo ' + file.name + '?')) {
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = () => { this.html = String(reader.result); };
                    reader.readAsText(file);
                },

                pickImage() {
                    this.saveRange();
                    this.$refs.image.click();
                },

                async uploadImage(event) {
                    const file = event.target.files[0];
                    event.target.value = '';
                    if (!file) return;

                    this.error = '';
                    this.uploading = true;

                    const body = new FormData();
                    body.append('image', file);

                    try {
                        const response = await fetch(config.uploadUrl, {
                            method: 'POST',
                            body,
                            headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            this.error = data.errors?.image?.[0] || data.message || 'Não foi possível enviar a imagem.';
                            return;
                        }

                        if (this.editor === 'visual') {
                            this.insertHtml('<img src="' + escapeHtml(data.url) + '" alt="">');
                        } else {
                            this.insert('<img src="' + data.url + '" alt="" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;">\n');
                        }
                    } catch (e) {
                        this.error = 'Não foi possível enviar a imagem. Confira a conexão e tente de novo.';
                    } finally {
                        this.uploading = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
