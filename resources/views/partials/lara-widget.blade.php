{{--
    Lara (IA) — balão no canto inferior direito que abre o chat num painel
    lateral. Fica em toda tela do layout, para quem tem a permissão `lara`
    (o layout pergunta por @can — Gate, sem consulta de model).

    Nada da conversa é carregado na renderização: o layout roda em toda tela e
    o health check bate na VM da IA. O estado vem de GET /lara/conversa quando
    o painel abre pela primeira vez na página.

    O painel acompanha a navegação: aberto/fechado fica no sessionStorage da
    aba, e uma pergunta feita numa tela continua sendo respondida na seguinte
    (o servidor devolve `pendente` e o balão consulta até a resposta chegar).

    As URLs são fixas, e não route(): o layout não pode cair por um cache de
    rotas desatualizado.
--}}
@php
    $laraTimeoutMs = ((int) config('services.lara.timeout', 30) + 10) * 1000;
    $laraMaxChars = (int) config('services.lara.max_input_chars', 1000);
    $laraAbrir = (bool) session(\App\Http\Controllers\LaraChatController::OPEN_FLASH);

    // O robô da Lara (public/images/lara, gerado do GIF original — ver
    // docs/funcionalidades/lara-ia.md). WebP animado com transparência; quem
    // pede menos movimento no sistema vê a imagem parada.
    $laraRobo = asset('images/lara/lara-robo-128.webp');
    $laraRoboGrande = asset('images/lara/lara-robo-320.webp');
    $laraRoboParado = asset('images/lara/lara-robo.png');
    $laraCabeca = asset('images/lara/lara-robo-cabeca.png');

    // Onde a pessoa está, para a Lara entender "como faço isso aqui?". Só
    // nomes de rota: módulo e página o servidor tira do menu
    // (AskLaraRequest::contexto), sem confiar em texto vindo do navegador.
    $laraContexto = [
        'rota' => request()->route()?->getName(),
        'aba' => $navCurrent['key'] ?? null,
    ];
@endphp

<script>
    window.laraWidget = function (opcoes) {
        var CHAVE = 'laraWidgetAberto';

        function lerAberto() {
            try {
                return sessionStorage.getItem(CHAVE) === '1';
            } catch (e) {
                return false;
            }
        }

        function gravarAberto(valor) {
            try {
                sessionStorage.setItem(CHAVE, valor ? '1' : '0');
            } catch (e) {}
        }

        function cabecalhos(json) {
            var h = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            };
            if (json) {
                h['Content-Type'] = 'application/json';
            }
            return h;
        }

        return {
            aberto: opcoes.abrir || lerAberto(),
            carregado: false,
            carregando: false,
            mensagens: [],
            configurado: true,
            disponivel: false,
            pergunta: '',
            pensando: false,
            erro: null,
            segundos: 0,
            cronometro: null,
            consulta: null,
            // Resposta que chegou com o painel fechado: ponto no balão.
            novidade: false,

            init() {
                if (this.aberto) {
                    gravarAberto(true);
                    this.carregar();
                }
            },

            abrir() {
                this.aberto = true;
                this.novidade = false;
                gravarAberto(true);

                if (!this.carregado) {
                    this.carregar();
                }

                this.scrollToEnd();
                this.focar();
            },

            fechar() {
                this.aberto = false;
                gravarAberto(false);
            },

            async carregar() {
                if (this.carregando) return;
                this.carregando = true;

                try {
                    var res = await fetch(opcoes.urls.conversa, { headers: cabecalhos(false), credentials: 'same-origin' });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    var data = await res.json();

                    this.mensagens = data.mensagens || [];
                    this.configurado = !!data.configurado;
                    this.disponivel = !!data.disponivel;
                    this.carregado = true;

                    if (data.pendente) {
                        this.aguardarRespostaDeOutraTela();
                    } else if (this.consulta) {
                        this.pararEspera();
                        if (!this.aberto) this.novidade = true;
                    }
                } catch (e) {
                    this.erro = 'Não foi possível carregar a conversa. Feche e abra de novo.';
                } finally {
                    this.carregando = false;
                    this.scrollToEnd();
                    this.focar();
                }
            },

            // A pergunta foi feita numa tela anterior e o servidor ainda está
            // esperando a IA: mostra "pensando" e consulta até a resposta ser
            // gravada.
            aguardarRespostaDeOutraTela() {
                if (!this.pensando) this.iniciarEspera();

                clearTimeout(this.consulta);
                this.consulta = setTimeout(() => this.carregar(), 3000);
            },

            async enviar() {
                var texto = this.pergunta.trim();
                if (this.pensando || !this.disponivel || texto.length < 2) return;

                this.erro = null;
                this.pergunta = '';
                this.ajustarAltura();
                this.mensagens.push({ role: 'user', conteudo: texto, horario: this.agora() });
                this.scrollToEnd();
                this.iniciarEspera();

                // Derivado de LARA_TIMEOUT com folga: abortar aqui antes do PHP
                // jogaria fora uma resposta a caminho.
                var abort = new AbortController();
                var timer = setTimeout(() => abort.abort(), opcoes.timeoutMs);

                try {
                    var res = await fetch(opcoes.urls.perguntar, {
                        method: 'POST',
                        headers: cabecalhos(true),
                        credentials: 'same-origin',
                        body: JSON.stringify({ mensagem: texto, contexto: opcoes.contexto }),
                        signal: abort.signal,
                    });

                    var data = await res.json().catch(() => ({}));

                    if (!res.ok) {
                        // 422 (validação) e 429 (pergunta anterior em curso)
                        // já vêm com um texto pronto para o funcionário.
                        this.erro = data.message || 'Não foi possível enviar a pergunta. Tente de novo.';
                        return;
                    }

                    this.mensagens.push({ role: 'assistant', conteudo: data.resposta, horario: data.horario || this.agora() });
                    if (!this.aberto) this.novidade = true;
                } catch (e) {
                    this.erro = e.name === 'AbortError'
                        ? 'A Lara demorou demais para responder. Tente de novo em instantes.'
                        : 'Falha de conexão com o portal. Tente de novo.';
                } finally {
                    clearTimeout(timer);
                    this.pararEspera();
                    this.scrollToEnd();
                    this.focar();
                }
            },

            async novaConversa() {
                if (this.pensando) return;
                this.erro = null;

                try {
                    await fetch(opcoes.urls.reiniciar, { method: 'POST', headers: cabecalhos(false), credentials: 'same-origin' });
                } catch (e) {
                    // A limpeza do lado da IA é best-effort — o painel zera de todo jeito.
                }

                this.mensagens = [];
                this.focar();
            },

            ajustarAltura() {
                var el = this.$refs.entrada;
                if (!el) return;
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 112) + 'px';
            },

            iniciarEspera() {
                this.pensando = true;
                this.segundos = 0;
                clearInterval(this.cronometro);
                this.cronometro = setInterval(() => { this.segundos++; this.scrollToEnd(); }, 1000);
            },

            pararEspera() {
                this.pensando = false;
                clearInterval(this.cronometro);
                clearTimeout(this.consulta);
                this.consulta = null;
            },

            focar() {
                this.$nextTick(() => {
                    if (this.aberto && this.$refs.entrada && !this.$refs.entrada.disabled) {
                        this.$refs.entrada.focus();
                    }
                });
            },

            scrollToEnd() {
                this.$nextTick(() => {
                    var el = this.$refs.conversa;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },

            agora() {
                return new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            },
        };
    };
</script>

<div x-data="laraWidget(@js([
        'abrir' => $laraAbrir,
        'timeoutMs' => $laraTimeoutMs,
        'contexto' => $laraContexto,
        'urls' => [
            'conversa' => url('/lara/conversa'),
            'perguntar' => url('/lara/perguntar'),
            'reiniciar' => url('/lara/reiniciar'),
        ],
    ]))"
    @keydown.escape.window="if (aberto) fechar()"
    @lara-abrir.window="abrir()">

    {{-- Balão: o robô da Lara, de pé no canto. No celular fica acima da barra
         de baixo. A sombra segue o contorno (drop-shadow), não uma caixa. --}}
    <button type="button" x-show="!aberto" @click="abrir()" aria-label="Abrir a Lara (assistente de IA)" title="Lara (IA)"
        class="fixed right-3 z-[55] block rounded-2xl transition hover:-translate-y-1 focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint sm:right-5"
        style="bottom: var(--lara-balao-bottom)">
        <picture>
            <source srcset="{{ $laraRoboParado }}" media="(prefers-reduced-motion: reduce)">
            <img src="{{ $laraRobo }}" alt="" width="52" height="74"
                class="h-[74px] w-auto drop-shadow-[0_6px_10px_rgba(0,0,0,0.28)]">
        </picture>
        <span x-show="novidade || pensando" x-cloak
            class="absolute right-0 top-0 h-3.5 w-3.5 rounded-full border-2 border-white bg-grena"
            :class="pensando && !novidade ? 'animate-pulse' : ''"></span>
    </button>

    {{-- Painel lateral. Sem fundo escurecido: a página continua à vista para
         quem pergunta sobre o que está vendo. No celular ocupa a tela. --}}
    <aside x-show="aberto" x-cloak role="dialog" aria-label="Lara, assistente de IA"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full opacity-0"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="translate-x-full opacity-0"
        class="fixed inset-0 z-[60] flex flex-col bg-surface text-ink shadow-pop sm:inset-y-0 sm:left-auto sm:right-0 sm:w-[420px] sm:border-l sm:border-line"
        style="padding-top: env(safe-area-inset-top, 0px); padding-bottom: env(safe-area-inset-bottom, 0px)">

        <header class="flex h-[62px] shrink-0 items-center gap-2.5 border-b border-line px-4">
            <img src="{{ $laraCabeca }}" alt="" width="36" height="36" class="h-9 w-9 shrink-0 rounded-full bg-grena-tint">

            <div class="min-w-0 flex-1 leading-tight">
                <b class="block text-[15px] font-bold">Lara</b>
                <span class="flex items-center gap-1.5 text-xs text-ink-3">
                    <template x-if="carregado && disponivel">
                        <span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-ok"></span>Disponível</span>
                    </template>
                    <template x-if="!carregado || !disponivel">
                        <span>Assistente do clube</span>
                    </template>
                </span>
            </div>
            <button type="button" @click="novaConversa()" :disabled="pensando || !mensagens.length"
                class="rounded-full px-3 py-1.5 text-xs font-bold text-ink-2 transition hover:bg-subtle hover:text-ink disabled:opacity-40">
                Nova conversa
            </button>
            <button type="button" @click="fechar()" aria-label="Fechar a Lara"
                class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-ink-2 transition hover:bg-subtle hover:text-ink">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </header>

        <template x-if="carregado && !disponivel">
            <div class="mx-4 mt-3 rounded-xl bg-warn-soft px-3 py-2 text-xs leading-relaxed text-warn"
                x-text="configurado
                    ? 'A Lara não está respondendo agora — o servidor dela pode estar reiniciando. Tente em instantes; se continuar, avise a TI.'
                    : 'A Lara está desativada no momento. Fale com a TI se precisar dela agora.'"></div>
        </template>

        {{-- Conversa --}}
        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-4" x-ref="conversa">
            <template x-if="!carregado">
                <p class="py-10 text-center text-sm text-ink-3">Carregando…</p>
            </template>

            <template x-if="carregado && mensagens.length === 0 && !pensando">
                <div class="flex h-full flex-col items-center justify-center px-6 text-center">
                    {{-- Só baixa quando a conversa está vazia (x-if). --}}
                    <picture>
                        <source srcset="{{ $laraRoboParado }}" media="(prefers-reduced-motion: reduce)">
                        <img src="{{ $laraRoboGrande }}" alt="" width="113" height="160" class="mb-3 h-40 w-auto">
                    </picture>
                    <p class="font-display text-base font-bold text-ink">Olá! Eu sou a Lara.</p>
                    <p class="mt-1 text-sm text-ink-2">Pergunte sobre o estatuto, as atividades, os avisos do clube ou como usar O Lara.</p>
                    <p class="mt-0.5 text-xs text-ink-3">Ex.: “qual o horário da academia?” ou “como faço isso nesta tela?”</p>
                </div>
            </template>

            {{-- Balão só para quem pergunta; a resposta da Lara é longa e lê
                 melhor como texto corrido. --}}
            <template x-for="(m, i) in mensagens" :key="i">
                <div class="group">
                    <template x-if="m.role === 'user'">
                        <div class="flex justify-end">
                            {{-- x-text, nunca x-html: o conteúdo vem de um modelo de
                                 linguagem e não pode virar HTML executável na tela. --}}
                            <div class="max-w-[85%] whitespace-pre-wrap break-words rounded-2xl rounded-br-md bg-grena px-3.5 py-2 text-sm leading-relaxed text-white"
                                x-text="m.conteudo"></div>
                        </div>
                    </template>

                    <template x-if="m.role !== 'user'">
                        <div class="flex gap-2.5">
                            <img src="{{ $laraCabeca }}" alt="" width="24" height="24" class="mt-0.5 h-6 w-6 shrink-0 rounded-full bg-grena-tint">
                            <div class="min-w-0 flex-1 whitespace-pre-wrap break-words text-sm leading-relaxed text-ink"
                                x-text="m.conteudo"></div>
                        </div>
                    </template>

                    <div class="mt-1 text-[10px] text-ink-3 opacity-0 transition-opacity group-hover:opacity-100"
                        :class="m.role === 'user' ? 'text-right' : 'pl-[2.125rem]'"
                        x-text="m.horario"></div>
                </div>
            </template>

            <template x-if="pensando">
                <div class="flex items-center gap-2.5">
                    <img src="{{ $laraCabeca }}" alt="" width="24" height="24" class="h-6 w-6 shrink-0 rounded-full bg-grena-tint">
                    <span class="flex items-center gap-1.5">
                        <span class="flex gap-1">
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-ink-3" style="animation-delay:0ms"></span>
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-ink-3" style="animation-delay:150ms"></span>
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-ink-3" style="animation-delay:300ms"></span>
                        </span>
                        {{-- Só depois de alguns segundos: numa resposta rápida
                             viraria cronômetro; numa demorada, mostra que não travou. --}}
                        <span x-show="segundos >= 5" x-cloak class="text-xs tabular-nums text-ink-3" x-text="segundos + 's'"></span>
                    </span>
                </div>
            </template>
        </div>

        {{-- Envio --}}
        <div class="shrink-0 border-t border-line px-3 py-2.5">
            <template x-if="erro">
                <div class="mb-2 rounded-xl bg-danger-soft px-3 py-1.5 text-xs text-danger" x-text="erro"></div>
            </template>

            <form @submit.prevent="enviar()" class="flex items-end gap-2">
                <textarea x-model="pergunta" x-ref="entrada" rows="1" maxlength="{{ $laraMaxChars }}"
                    :disabled="pensando || !disponivel"
                    @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); enviar(); }"
                    @input="ajustarAltura()"
                    placeholder="Escreva sua pergunta…"
                    class="max-h-28 flex-1 resize-none rounded-xl border-line bg-canvas py-2 text-sm text-ink placeholder:text-ink-3 focus:border-grena focus:ring-grena disabled:opacity-60"></textarea>

                <button type="submit" :disabled="pensando || !disponivel || pergunta.trim().length < 2" title="Enviar" aria-label="Enviar"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-grena text-white transition hover:bg-grena-hover disabled:cursor-not-allowed disabled:opacity-40">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 12 3.27 4.36a.5.5 0 0 1 .68-.62l16.5 7.8a.5.5 0 0 1 0 .92l-16.5 7.8a.5.5 0 0 1-.68-.62L6 12Zm0 0h6"/>
                    </svg>
                </button>
            </form>

            <p class="mt-1.5 px-0.5 text-[11px] text-ink-3">Respostas geradas por IA — em caso de dúvida, confirme com o setor.</p>
        </div>
    </aside>
</div>
