{{--
    HAL (IA) — balão no canto inferior direito que abre o chat num painel
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

    // O robô do HAL (public/images/lara, gerado do GIF original — ver
    // docs/funcionalidades/lara-ia.md). WebP animado com transparência; quem
    // pede menos movimento no sistema vê a imagem parada. O balão é o robô
    // dentro do anel grená (GIF próprio); o resto usa o robô de corpo inteiro.
    $laraBalao = asset('images/lara/lara-balao.webp');
    $laraBalaoParado = asset('images/lara/lara-balao.png');
    $laraRoboGrande = asset('images/lara/lara-robo-320.webp');
    $laraRoboParado = asset('images/lara/lara-robo.png');
    $laraCabeca = asset('images/lara/lara-robo-cabeca.png');

    // Onde a pessoa está, para o HAL entender "como faço isso aqui?". Só
    // nomes de rota: módulo e página o servidor tira do menu
    // (AskLaraRequest::contexto), sem confiar em texto vindo do navegador.
    $laraContexto = [
        'rota' => request()->route()?->getName(),
        'aba' => $navCurrent['key'] ?? null,
    ];

    // O mesmo lugar, legível, no cabeçalho do painel ("Nesta tela: SIV ›
    // Busca"): a pessoa vê que o HAL sabe de onde ela pergunta.
    $laraOnde = null;
    if (isset($navCurrent['group']['label'], $navCurrent['label'])) {
        $laraGrupo = __($navCurrent['group']['label']);
        $laraOnde = $laraGrupo === $navCurrent['label'] ? $laraGrupo : $laraGrupo . ' › ' . $navCurrent['label'];
    }

    $laraNome = \Illuminate\Support\Str::before(trim((string) (auth()->user()?->name ?? '')), ' ');

    // Atalhos do início da conversa. O primeiro só faz sentido dentro de um módulo.
    $laraSugestoes = array_values(array_filter([
        $laraOnde ? 'Como faço o que preciso nesta tela?' : null,
        'O que significa o status que aparece aqui?',
        'Qual o horário de funcionamento da academia?',
        'O que diz o estatuto sobre dependentes?',
    ]));

    // Erro que esta tela está mostrando, para o "Verificar com o HAL". A
    // página de erro (errors/pagina) passa o dela; o resto vem do retorno da
    // ação. O servidor filtra o texto ao receber (App\Services\Lara\ScreenError).
    $laraErroTela = $laraErroPagina ?? null;
    if ($laraErroTela === null) {
        if (filled(session('error')) && is_string(session('error'))) {
            $laraErroTela = ['status' => null, 'texto' => session('error')];
        } elseif (isset($errors) && $errors->any()) {
            $laraErroTela = ['status' => 422, 'texto' => implode(' ', array_slice($errors->all(), 0, 5))];
        } elseif (request()->has('error')) {
            $laraErroTela = ['status' => null, 'texto' => null];
        }
    }
@endphp

<script>
    // Ação por fetch que falhou (tela que não recarrega): avisa o balão. Só
    // observa uma cópia da resposta — a tela segue tratando o erro como sempre.
    // GET fica de fora: consulta em segundo plano não é ação da pessoa.
    (function () {
        var fetchOriginal = window.fetch;
        var prefixoLara = @js(url('/lara')) + '/';

        function avisar(res) {
            res.json().catch(() => ({})).then(function (data) {
                var campos = Object.values(data.errors || {}).flat().slice(0, 5);
                var texto = campos.length ? campos.join(' ') : data.message;
                window.dispatchEvent(new CustomEvent('lara-erro', {
                    detail: { status: res.status, texto: typeof texto === 'string' ? texto : null },
                }));
            });
        }

        window.fetch = function (entrada, init) {
            var promessa = fetchOriginal.apply(this, arguments);

            try {
                var pedido = entrada instanceof Request ? entrada : null;
                var metodo = String((init && init.method) || (pedido && pedido.method) || 'GET').toUpperCase();
                var url = new URL(pedido ? pedido.url : String(entrada), location.href);

                if (metodo !== 'GET' && url.origin === location.origin && !url.href.startsWith(prefixoLara)) {
                    promessa.then(function (res) {
                        if (res.status >= 400) avisar(res.clone());
                    }).catch(function () {});
                }
            } catch (e) {}

            return promessa;
        };
    })();

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
            // Erro da tela à espera do "Verificar com o HAL".
            erroTela: opcoes.erroTela || null,
            // Na página de erro o botão já está na própria página.
            mostrarAtalhoErro: !opcoes.paginaDeErro,
            verificarAoCarregar: false,

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

                    if (this.verificarAoCarregar) {
                        this.verificarAoCarregar = false;
                        if (this.carregado) this.verificarErro();
                    }
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

                this.pergunta = '';
                this.ajustarAltura();
                await this.perguntar(texto, { mensagem: texto, contexto: opcoes.contexto });
            },

            // "Verificar com o HAL": abre o painel e pergunta sobre o erro. A
            // pergunta é montada no servidor e substitui o rótulo da bolha.
            async verificarErro() {
                if (!this.erroTela) return;

                if (!this.aberto || !this.carregado) {
                    this.verificarAoCarregar = !this.carregado;
                    this.abrir();
                    if (!this.carregado) return;
                }

                // Fora do ar ou com outra resposta a caminho: o aviso fica no
                // painel, para tentar de novo.
                if (this.pensando || !this.disponivel) return;

                var erro = this.erroTela;
                this.erroTela = null;
                await this.perguntar('Verificar o erro desta tela', { contexto: opcoes.contexto, erro: erro });
            },

            async perguntar(rotulo, corpo) {
                this.erro = null;
                var indice = this.mensagens.push({ role: 'user', conteudo: rotulo, horario: this.agora() }) - 1;
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
                        body: JSON.stringify(corpo),
                        signal: abort.signal,
                    });

                    var data = await res.json().catch(() => ({}));

                    if (!res.ok) {
                        // 422 (validação) e 429 (pergunta anterior em curso)
                        // já vêm com um texto pronto para o funcionário.
                        this.erro = data.message || 'Não foi possível enviar a pergunta. Tente de novo.';
                        return;
                    }

                    if (data.pergunta) this.mensagens[indice].conteudo = data.pergunta;
                    this.mensagens.push({ role: 'assistant', conteudo: data.resposta, horario: data.horario || this.agora() });
                    if (!this.aberto) this.novidade = true;
                } catch (e) {
                    this.erro = e.name === 'AbortError'
                        ? 'O HAL demorou demais para responder. Tente de novo em instantes.'
                        : 'Falha de conexão com o portal. Tente de novo.';
                } finally {
                    clearTimeout(timer);
                    this.pararEspera();
                    this.scrollToEnd();
                    this.focar();
                }
            },

            // Sugestão do início: pergunta direto, como se tivesse digitado.
            async sugerir(texto) {
                if (this.pensando || !this.disponivel) return;
                await this.perguntar(texto, { mensagem: texto, contexto: opcoes.contexto });
            },

            // Copiar uma resposta (para colar num e-mail, num aviso…).
            copiado: null,
            async copiar(i) {
                try {
                    await navigator.clipboard.writeText(this.mensagens[i].conteudo);
                    this.copiado = i;
                    setTimeout(() => { if (this.copiado === i) this.copiado = null; }, 1600);
                } catch (e) {}
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
        'erroTela' => $laraErroTela,
        'paginaDeErro' => isset($laraErroPagina),
        'urls' => [
            'conversa' => url('/lara/conversa'),
            'perguntar' => url('/lara/perguntar'),
            'reiniciar' => url('/lara/reiniciar'),
        ],
    ]))"
    @keydown.escape.window="if (aberto) fechar()"
    @lara-abrir.window="abrir()"
    @lara-erro.window="erroTela = $event.detail"
    @lara-verificar-erro.window="verificarErro()">

    {{-- Atalho sobre o balão quando a tela mostra um erro. --}}
    <div x-show="erroTela && !aberto && mostrarAtalhoErro" x-cloak role="status"
        class="fixed right-3 z-[55] flex max-w-[calc(100vw-1.5rem)] items-center gap-2 rounded-2xl border border-line bg-surface py-1.5 pl-3 pr-1.5 text-sm text-ink-2 shadow-pop sm:right-5"
        style="bottom: calc(var(--lara-balao-bottom) + 80px)">
        <span>Deu erro?</span>
        <button type="button" @click="verificarErro()"
            class="rounded-full bg-grena px-3 py-1.5 text-xs font-bold text-white transition hover:bg-grena-hover">
            Verificar com o HAL
        </button>
        <button type="button" @click="erroTela = null" aria-label="Dispensar"
            class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-ink-3 transition hover:bg-subtle hover:text-ink">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Balão: o robô do HAL no anel grená, acenando. No celular fica acima da barra
         de baixo. A sombra segue o contorno (drop-shadow), não uma caixa. --}}
    <button type="button" x-show="!aberto" @click="abrir()" aria-label="Abrir o HAL (assistente de IA)" title="HAL (IA)"
        class="fixed right-3 z-[55] block rounded-2xl transition hover:-translate-y-1 focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint sm:right-5"
        style="bottom: var(--lara-balao-bottom)">
        <picture>
            <source srcset="{{ $laraBalaoParado }}" media="(prefers-reduced-motion: reduce)">
            <img src="{{ $laraBalao }}" alt="" width="96" height="72"
                class="h-[72px] w-auto drop-shadow-[0_6px_10px_rgba(0,0,0,0.28)]">
        </picture>
        <span x-show="novidade || pensando" x-cloak
            class="absolute right-0 top-0 h-3.5 w-3.5 rounded-full border-2 border-white bg-grena"
            :class="pensando && !novidade ? 'animate-pulse' : ''"></span>
    </button>

    {{-- Painel lateral. Sem fundo escurecido: a página continua à vista para
         quem pergunta sobre o que está vendo. No celular ocupa a tela.
         Visual de 11/11/2026: cabeçalho escuro do HAL (hal-band), conversa em
         fundo pontilhado, respostas em cartão e sugestões no início. --}}
    <aside x-show="aberto" x-cloak role="dialog" aria-label="HAL, assistente de IA"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full opacity-0"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="translate-x-full opacity-0"
        class="fixed inset-0 z-[60] flex flex-col bg-surface text-ink shadow-pop sm:inset-y-0 sm:left-auto sm:right-0 sm:w-[440px] sm:border-l sm:border-line"
        style="padding-bottom: env(safe-area-inset-bottom, 0px)">

        <header class="hal-band shrink-0 px-4 pb-3.5" style="padding-top: calc(env(safe-area-inset-top, 0px) + 14px)">
            <div class="flex items-center gap-3">
                <span class="hal-ring grid h-11 w-11 shrink-0 place-items-center" :data-off="carregado && !disponivel ? '' : null">
                    <img src="{{ $laraCabeca }}" alt="" width="40" height="40" class="h-10 w-10 rounded-full">
                </span>

                <div class="min-w-0 flex-1 leading-tight">
                    <b class="block font-display text-[17px] font-semibold tracking-tight">HAL</b>
                    <span class="mt-0.5 flex items-center gap-1.5 text-xs text-white/70">
                        <span class="h-1.5 w-1.5 rounded-full"
                            :class="!carregado ? 'bg-white/40' : (disponivel ? 'bg-emerald-400' : (configurado ? 'bg-amber-300' : 'bg-white/40'))"></span>
                        <span x-text="!carregado ? 'Conectando…' : (disponivel ? 'No ar · assistente do clube' : (configurado ? 'Fora do ar agora' : 'Desativado'))">Assistente do clube</span>
                    </span>
                </div>

                <button type="button" @click="novaConversa()" :disabled="pensando || !mensagens.length" title="Começar uma conversa nova"
                    class="flex items-center gap-1.5 rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold text-white/85 transition hover:border-white/35 hover:bg-white/10 hover:text-white active:scale-[.97] disabled:pointer-events-none disabled:opacity-35">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                    Nova conversa
                </button>
                <button type="button" @click="fechar()" aria-label="Fechar o HAL"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-white/75 transition hover:bg-white/10 hover:text-white active:scale-95">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            @if ($laraOnde)
                {{-- O HAL recebe a rota (contexto); aqui a pessoa vê qual. --}}
                <p class="mt-3 flex items-center gap-2 truncate rounded-lg bg-white/[.07] px-2.5 py-1.5 text-xs text-white/75">
                    <svg class="h-3.5 w-3.5 shrink-0 text-white/50" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                    </svg>
                    <span class="truncate">Nesta tela: <b class="font-semibold text-white">{{ $laraOnde }}</b></span>
                </p>
            @endif
        </header>

        <template x-if="carregado && !disponivel">
            <div class="flex shrink-0 gap-2 border-b border-line bg-warn-soft px-4 py-2.5 text-xs leading-relaxed text-warn"
                x-text="configurado
                    ? 'O HAL não está respondendo agora — o servidor dele pode estar reiniciando. Tente em instantes; se continuar, avise a TI.'
                    : 'O HAL está desativado no momento. Fale com a TI se precisar dele agora.'"></div>
        </template>

        {{-- Conversa --}}
        <div class="hal-thread min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-5" x-ref="conversa" aria-live="polite">
            <template x-if="!carregado">
                <div class="space-y-4 pt-2" aria-label="Carregando a conversa">
                    <div class="ml-auto h-9 w-2/5 animate-pulse rounded-2xl bg-line"></div>
                    <div class="h-20 w-4/5 animate-pulse rounded-2xl bg-line"></div>
                    <div class="ml-auto h-9 w-1/2 animate-pulse rounded-2xl bg-line"></div>
                </div>
            </template>

            <template x-if="carregado && mensagens.length === 0 && !pensando">
                <div class="flex min-h-full flex-col justify-center pb-2">
                    <div class="flex items-end gap-4">
                        {{-- Só baixa quando a conversa está vazia (x-if). --}}
                        <picture class="shrink-0">
                            <source srcset="{{ $laraRoboParado }}" media="(prefers-reduced-motion: reduce)">
                            <img src="{{ $laraRoboGrande }}" alt="" width="85" height="120" class="h-[120px] w-auto">
                        </picture>
                        <div class="pb-2">
                            <p class="font-display text-[22px] font-semibold leading-tight tracking-tight text-ink">
                                Olá{{ $laraNome !== '' ? ', ' . $laraNome : '' }}.<br>Eu sou o HAL.
                            </p>
                            <p class="mt-1.5 text-sm leading-relaxed text-ink-2">Pergunto e respondo sobre o estatuto, as atividades, os avisos do clube e o uso do Lara.</p>
                        </div>
                    </div>

                    <p class="mb-2 mt-7 text-[11px] font-semibold uppercase tracking-[.08em] text-ink-3">Comece por aqui</p>
                    <ul class="grid gap-2">
                        @foreach ($laraSugestoes as $sugestao)
                            <li>
                                <button type="button" @click="sugerir(@js($sugestao))" :disabled="pensando || !disponivel"
                                    class="hal-chip flex w-full items-center justify-between gap-3 rounded-xl border border-line bg-surface px-3.5 py-2.5 text-left text-sm text-ink shadow-card disabled:cursor-not-allowed disabled:opacity-50">
                                    <span>{{ $sugestao }}</span>
                                    <svg class="h-4 w-4 shrink-0 text-grena" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                    </svg>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </template>

            <template x-for="(m, i) in mensagens" :key="i">
                <div class="group">
                    <template x-if="m.role === 'user'">
                        <div class="flex justify-end pl-10">
                            {{-- x-text, nunca x-html: o conteúdo vem de um modelo de
                                 linguagem e não pode virar HTML executável na tela. --}}
                            <div class="whitespace-pre-wrap break-words rounded-2xl rounded-br-md bg-grena px-3.5 py-2 text-sm leading-relaxed text-white shadow-card"
                                x-text="m.conteudo"></div>
                        </div>
                    </template>

                    <template x-if="m.role !== 'user'">
                        <div class="flex gap-2.5 pr-2">
                            <img src="{{ $laraCabeca }}" alt="" width="28" height="28" class="mt-0.5 h-7 w-7 shrink-0 rounded-full bg-grena-tint">
                            <div class="hal-answer min-w-0 flex-1 px-3.5 py-2.5">
                                <div class="whitespace-pre-wrap break-words text-sm leading-relaxed text-ink" x-text="m.conteudo"></div>
                            </div>
                        </div>
                    </template>

                    <div class="mt-1 flex items-center gap-2 text-[11px] text-ink-3"
                        :class="m.role === 'user' ? 'justify-end' : 'pl-[2.375rem]'">
                        <span x-text="m.horario"></span>
                        <template x-if="m.role !== 'user'">
                            <button type="button" @click="copiar(i)"
                                class="rounded px-1 font-semibold text-ink-3 opacity-0 transition hover:text-grena focus-visible:opacity-100 group-hover:opacity-100"
                                :class="copiado === i ? 'opacity-100 text-ok' : ''"
                                x-text="copiado === i ? 'Copiado' : 'Copiar'"></button>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="pensando">
                <div class="flex items-center gap-2.5">
                    <img src="{{ $laraCabeca }}" alt="" width="28" height="28" class="h-7 w-7 shrink-0 rounded-full bg-grena-tint">
                    <span class="hal-answer flex items-center gap-2.5 px-3.5 py-2.5">
                        <span class="hal-typing flex gap-1" aria-hidden="true">
                            <span class="h-1.5 w-1.5 rounded-full bg-grena"></span>
                            <span class="h-1.5 w-1.5 rounded-full bg-grena"></span>
                            <span class="h-1.5 w-1.5 rounded-full bg-grena"></span>
                        </span>
                        <span class="text-xs text-ink-3">Pensando</span>
                        {{-- Só depois de alguns segundos: numa resposta rápida
                             viraria cronômetro; numa demorada, mostra que não travou. --}}
                        <span x-show="segundos >= 5" x-cloak class="text-xs tabular-nums text-ink-3" x-text="segundos + 's'"></span>
                    </span>
                </div>
            </template>
        </div>

        {{-- Envio --}}
        <div class="shrink-0 border-t border-line bg-surface px-3 pb-2.5 pt-3">
            <template x-if="erro">
                <div class="mb-2 rounded-xl bg-danger-soft px-3 py-1.5 text-xs text-danger" role="alert" x-text="erro"></div>
            </template>

            <template x-if="erroTela">
                <div class="mb-2 flex items-center justify-between gap-2 rounded-xl bg-danger-soft px-3 py-1.5 text-xs text-danger">
                    <span>Apareceu um erro nesta tela.</span>
                    <button type="button" @click="verificarErro()" :disabled="pensando || !disponivel"
                        class="shrink-0 rounded-full bg-grena px-2.5 py-1 font-bold text-white transition hover:bg-grena-hover disabled:opacity-40">
                        Verificar com o HAL
                    </button>
                </div>
            </template>

            <form @submit.prevent="enviar()"
                class="hal-compose flex items-end gap-2 rounded-2xl border border-line bg-canvas p-1.5 pl-3 transition">
                <label for="hal-pergunta" class="sr-only">Sua pergunta para o HAL</label>
                <textarea id="hal-pergunta" x-model="pergunta" x-ref="entrada" rows="1" maxlength="{{ $laraMaxChars }}"
                    :disabled="pensando || !disponivel"
                    @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); enviar(); }"
                    @input="ajustarAltura()"
                    :placeholder="pensando ? 'O HAL está respondendo…' : 'Pergunte ao HAL…'"
                    class="max-h-28 flex-1 resize-none border-0 bg-transparent px-0 py-1.5 text-sm text-ink placeholder:text-ink-3 focus:ring-0 disabled:opacity-60"></textarea>

                <button type="submit" :disabled="pensando || !disponivel || pergunta.trim().length < 2" title="Enviar" aria-label="Enviar"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-grena text-white transition hover:bg-grena-hover active:scale-95 disabled:cursor-not-allowed disabled:opacity-35">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 12 3.27 4.36a.5.5 0 0 1 .68-.62l16.5 7.8a.5.5 0 0 1 0 .92l-16.5 7.8a.5.5 0 0 1-.68-.62L6 12Zm0 0h6"/>
                    </svg>
                </button>
            </form>

            <div class="mt-1.5 flex items-center justify-between gap-3 px-1 text-[11px] text-ink-3">
                <span>Respostas de IA: na dúvida, confirme com o setor.</span>
                <span class="hidden shrink-0 sm:inline" x-show="pergunta.length < {{ (int) ($laraMaxChars * 0.8) }}">Enter envia · Shift+Enter quebra linha</span>
                <span class="shrink-0 tabular-nums" x-show="pergunta.length >= {{ (int) ($laraMaxChars * 0.8) }}" x-cloak
                    :class="pergunta.length >= {{ $laraMaxChars }} ? 'text-danger' : ''"
                    x-text="pergunta.length + '/' + {{ $laraMaxChars }}"></span>
            </div>
        </div>
    </aside>
</div>
