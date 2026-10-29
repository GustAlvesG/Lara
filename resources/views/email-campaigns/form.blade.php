{{--
    Criar e editar campanha: dados do envio e o editor do HTML com prévia ao
    lado. A prévia é um iframe sem script (sandbox sem allow-scripts) e troca
    as variáveis pelos dados do primeiro destinatário da lista.

    Nada de chaves duplas literais neste arquivo fora das diretivas: o Blade
    as compilaria. O JS monta as chaves com fromCharCode/\x7B e o HTML com
    a entidade &#123;.
--}}
@php
    $editing = $campaign->exists;
    $hint = 'mt-1.5 text-xs text-ink-3';
    $tool = 'inline-flex h-8 items-center gap-1.5 rounded-full border border-line-strong bg-surface px-3 text-[13px] font-bold text-ink transition hover:border-ink-3 disabled:opacity-50';
    $starter = file_get_contents(resource_path('email-campaigns/modelo-inicial.html'));
    $variables = $campaign->variables();
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page wide>
        <x-page-title :title="$editing ? 'Editar campanha' : 'Nova campanha'" :back="$editing ? route('email-campaigns.show', $campaign) : route('email-campaigns.index')">
            @if ($editing)
                {{ $campaign->name }} · {{ $campaign->statusLabel() }}
            @else
                Primeiro os dados e o conteúdo; a lista de destinatários vem no passo seguinte.
            @endif
        </x-page-title>

        @include('partials.alerts')

        <form method="POST" action="{{ $editing ? route('email-campaigns.update', $campaign) : route('email-campaigns.store') }}"
              x-data="emailEditor({{ Js::from([
                  'html' => old('html', $campaign->html ?? ''),
                  'subject' => old('subject', $campaign->subject ?? ''),
                  'sample' => $sample,
                  'variables' => array_keys($variables),
                  'starter' => $starter,
                  'uploadUrl' => route('email-campaigns.images.store'),
              ]) }})"
              class="flex flex-col gap-4">
            @csrf
            @if ($editing) @method('PUT') @endif

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
                        <x-text-input type="text" name="from_name" id="from_name" maxlength="100" value="{{ old('from_name', $campaign->from_name) }}" placeholder="Ex.: Clube dos Funcionários" />
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
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-bold text-ink">Conteúdo (HTML)</h3>
                    <div class="flex flex-wrap items-center gap-1.5" role="toolbar" aria-label="Inserir no conteúdo">
                        <button type="button" class="{{ $tool }}" @click="useStarter()"><x-icon name="doc" class="h-3.5 w-3.5" /> Modelo inicial</button>
                        <button type="button" class="{{ $tool }}" @click="insert(snippets.title)">Título</button>
                        <button type="button" class="{{ $tool }}" @click="insert(snippets.paragraph)">Parágrafo</button>
                        <button type="button" class="{{ $tool }}" @click="insert(snippets.button)">Botão</button>
                        <button type="button" class="{{ $tool }}" @click="insert(snippets.divider)">Divisória</button>
                        <button type="button" class="{{ $tool }}" @click="$refs.image.click()" :disabled="uploading">
                            <x-icon name="image" class="h-3.5 w-3.5" /> <span x-text="uploading ? 'Enviando…' : 'Imagem'">Imagem</span>
                        </button>
                        <button type="button" class="{{ $tool }}" @click="$refs.htmlFile.click()"><x-icon name="download" class="h-3.5 w-3.5 rotate-180" /> Abrir arquivo .html</button>
                        <input type="file" x-ref="image" class="hidden" accept="image/png,image/jpeg,image/gif,image/webp" @change="uploadImage($event)">
                        <input type="file" x-ref="htmlFile" class="hidden" accept=".html,.htm,text/html" @change="openHtml($event)">
                    </div>
                </div>

                <div class="mb-3 flex flex-wrap items-center gap-1.5">
                    <span class="text-xs font-bold text-ink-3">Variáveis (clique para inserir):</span>
                    @foreach ($variables as $key => $label)
                        <button type="button" class="rounded-lg bg-subtle px-2 py-1 font-mono text-xs text-ink transition hover:bg-grena-tint hover:text-grena-ink"
                                title="{{ $label }}" @click="insertVariable(@js($key))">&#123;&#123;{{ $key }}&#125;&#125;</button>
                    @endforeach
                </div>

                <p x-show="error" x-cloak x-text="error" class="mb-3 rounded-xl bg-danger-soft px-4 py-2 text-sm text-danger"></p>
                <p x-show="unknown.length" x-cloak class="mb-3 rounded-xl bg-warn-soft px-4 py-2 text-sm text-warn">
                    O texto usa variáveis que a lista não tem: <span class="font-mono" x-text="unknown.map(k => open + k + close).join(', ')"></span>. Elas sairiam vazias.
                </p>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <div class="flex min-w-0 flex-col">
                        <textarea name="html" x-ref="code" x-model="html" spellcheck="false" aria-label="Código HTML do e-mail"
                                  @keydown.tab.prevent="insert('  ')"
                                  class="h-[620px] w-full resize-y rounded-xl border border-line-strong bg-subtle p-3 font-mono text-[12.5px] leading-relaxed text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint"
                                  placeholder="Cole aqui o HTML do e-mail ou clique em Modelo inicial."></textarea>
                        <x-input-error :messages="$errors->get('html')" class="mt-1" />
                        <p class="{{ $hint }}">Use tabelas e estilo dentro das tags (style="…"): é o que os programas de e-mail respeitam. Script não é enviado.</p>
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

    <script>
        function emailEditor(config) {
            const open = String.fromCharCode(123, 123);
            const close = String.fromCharCode(125, 125);
            const variablePattern = new RegExp('\\x7B\\x7B\\s*([A-Za-z0-9_]+)\\s*\\x7D\\x7D', 'g');

            const escapeHtml = (value) => String(value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

            return {
                html: config.html || '',
                subject: config.subject || '',
                sample: config.sample || {},
                known: new Set(['email', 'nome', 'primeiro_nome', ...(config.variables || [])]),
                width: 'desktop',
                expanded: false,
                uploading: false,
                error: '',
                open, close,
                timer: null,
                snippets: {
                    title: '<h2 style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:22px;line-height:1.3;color:#2b2523;">Título</h2>\n',
                    paragraph: '<p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#2b2523;">Texto do parágrafo.</p>\n',
                    button: '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;"><tr><td style="background-color:#a00001;border-radius:999px;">'
                        + '<a href="https://www.exemplo.com.br" style="display:inline-block;padding:14px 28px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">Texto do botão</a>'
                        + '</td></tr></table>\n',
                    divider: '<hr style="border:0;border-top:1px solid #ece6e1;margin:24px 0;">\n',
                },

                init() {
                    this.$watch('html', () => this.schedule());
                    this.$watch('subject', () => this.schedule());
                    this.render();
                },

                get unknown() {
                    const used = new Set();
                    for (const match of (this.subject + ' ' + this.html).matchAll(variablePattern)) {
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
                    this.timer = setTimeout(() => this.render(), 250);
                },

                render() {
                    let doc = this.fill(this.html.replace(/<script\b[^>]*>[\s\S]*?<\/script\s*>/gi, ''));

                    if (!/<html[\s>]/i.test(doc)) {
                        doc = '<!DOCTYPE html><html><head><meta charset="utf-8"><base target="_blank"></head><body style="margin:0;">' + doc + '</body></html>';
                    }

                    this.$refs.preview.srcdoc = doc;
                },

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

                insertVariable(key) {
                    this.insert(open + key + close);
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
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            this.error = data.errors?.image?.[0] || data.message || 'Não foi possível enviar a imagem.';
                            return;
                        }

                        this.insert('<img src="' + data.url + '" alt="" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;">\n');
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
