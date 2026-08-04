<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">L</span>
                <div class="leading-tight">
                    <h2 class="font-semibold text-gray-800 dark:text-gray-200">Lara</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Assistente do estatuto</p>
                </div>
            </div>

            @if($disponivel)
                <span class="hidden sm:inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Disponível
                </span>
            @endif
        </div>
    </x-slot>

    <div class="py-5">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8"
             x-data="laraChat(@js($mensagens->map(fn ($m) => ['role' => $m->role, 'conteudo' => $m->conteudo, 'horario' => $m->created_at?->format('H:i')])->values()), {{ Js::from($disponivel) }})"
             x-init="scrollToEnd()">

            @unless($disponivel)
                <div class="mb-3 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800 dark:border-amber-700 dark:bg-amber-900/25 dark:text-amber-300">
                    <svg class="mt-px h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3.75m0 3.75h.01M10.34 3.94l-8.02 13.9A1.5 1.5 0 0 0 3.62 20h16.76a1.5 1.5 0 0 0 1.3-2.16l-8.02-13.9a1.5 1.5 0 0 0-2.6 0Z"/>
                    </svg>
                    <span>
                        @if($configurado)
                            A Lara não está respondendo agora — o servidor dela pode estar reiniciando.
                            Recarregue a página em instantes; se continuar, avise a TI.
                        @else
                            A Lara está desativada no momento. Fale com a TI se precisar dela agora.
                        @endif
                    </span>
                </div>
            @endunless

            <div class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
                 style="height: min(65vh, 600px)">

                {{-- ══════════════ Conversa ══════════════ --}}
                <div class="flex-1 space-y-5 overflow-y-auto px-4 py-4 sm:px-5" x-ref="conversa">

                    <template x-if="mensagens.length === 0">
                        <div class="flex h-full flex-col items-center justify-center px-6 text-center">
                            <svg class="mb-2 h-7 w-7 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z"/>
                            </svg>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Pergunte algo sobre o estatuto do clube.</p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Ex.: “qual o horário da academia?”</p>
                        </div>
                    </template>

                    {{-- Balão só para quem pergunta. A resposta da Lara é longa por
                         natureza — embrulhá-la numa caixa cinza transformaria cada
                         resposta num bloco pesado; como texto corrido ela lê melhor
                         e a conversa fica mais leve. --}}
                    <template x-for="(m, i) in mensagens" :key="i">
                        <div class="group">
                            <template x-if="m.role === 'user'">
                                <div class="flex justify-end">
                                    {{-- x-text, nunca x-html: o conteúdo vem de um modelo de
                                         linguagem e não pode virar HTML executável na tela. --}}
                                    <div class="max-w-[80%] whitespace-pre-wrap break-words rounded-2xl rounded-br-md bg-indigo-600 px-3.5 py-2 text-sm leading-relaxed text-white"
                                         x-text="m.conteudo"></div>
                                </div>
                            </template>

                            <template x-if="m.role !== 'user'">
                                <div class="flex gap-2.5">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">L</span>
                                    <div class="min-w-0 flex-1 whitespace-pre-wrap break-words text-sm leading-relaxed text-gray-800 dark:text-gray-100"
                                         x-text="m.conteudo"></div>
                                </div>
                            </template>

                            {{-- O horário raramente importa numa conversa que acabou de
                                 acontecer: fica no hover para não somar uma linha de ruído
                                 embaixo de cada mensagem. --}}
                            <div class="mt-1 text-[10px] text-gray-400 opacity-0 transition-opacity group-hover:opacity-100 dark:text-gray-500"
                                 :class="m.role === 'user' ? 'text-right' : 'pl-[2.125rem]'"
                                 x-text="m.horario"></div>
                        </div>
                    </template>

                    <template x-if="pensando">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">L</span>
                            <span class="flex items-center gap-1.5">
                                <span class="flex gap-1">
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay:0ms"></span>
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay:150ms"></span>
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay:300ms"></span>
                                </span>
                                {{-- O contador só aparece depois de alguns segundos: numa
                                     resposta rápida ele viraria cronômetro; numa demorada,
                                     é o que mostra que a tela não travou. --}}
                                <span x-show="segundos >= 5" x-cloak
                                      class="text-xs tabular-nums text-gray-400 dark:text-gray-500"
                                      x-text="segundos + 's'"></span>
                            </span>
                        </div>
                    </template>
                </div>

                {{-- ══════════════ Envio ══════════════ --}}
                <div class="border-t border-gray-200 px-3 py-2.5 dark:border-gray-700">
                    <template x-if="erro">
                        <div class="mb-2 rounded-lg bg-red-50 px-3 py-1.5 text-xs text-red-700 dark:bg-red-900/25 dark:text-red-300"
                             x-text="erro"></div>
                    </template>

                    <form @submit.prevent="enviar()" class="flex items-end gap-2">
                        <textarea x-model="pergunta"
                                  x-ref="entrada"
                                  rows="1"
                                  maxlength="{{ (int) config('services.lara.max_input_chars', 1000) }}"
                                  :disabled="pensando || !disponivel"
                                  @keydown.enter.prevent="if (!$event.shiftKey) enviar()"
                                  @input="ajustarAltura()"
                                  placeholder="Escreva sua pergunta…"
                                  class="max-h-28 flex-1 resize-none rounded-lg border-gray-300 py-2 text-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"></textarea>

                        <button type="submit"
                                :disabled="pensando || !disponivel || pergunta.trim().length < 2"
                                title="Enviar"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 12 3.27 4.36a.5.5 0 0 1 .68-.62l16.5 7.8a.5.5 0 0 1 0 .92l-16.5 7.8a.5.5 0 0 1-.68-.62L6 12Zm0 0h6"/>
                            </svg>
                        </button>
                    </form>

                    <div class="mt-1.5 flex items-center justify-between gap-3 px-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                        <span class="truncate">Respostas baseadas no estatuto — em caso de dúvida, confirme com o setor.</span>
                        <button type="button"
                                @click="novaConversa()"
                                :disabled="pensando"
                                class="shrink-0 transition hover:text-indigo-600 disabled:opacity-40 dark:hover:text-indigo-400">
                            Nova conversa
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
function laraChat(historico, disponivel) {
    return {
        mensagens: historico,
        disponivel: disponivel,
        pergunta: '',
        pensando: false,
        erro: null,
        segundos: 0,
        cronometro: null,

        // Derivado de LARA_TIMEOUT com folga: a cadeia é IA -> PHP -> navegador, e
        // abortar aqui antes do PHP jogaria fora uma resposta a caminho, deixando
        // o funcionário com um erro no lugar do texto que já tinha chegado.
        TIMEOUT_MS: {{ ((int) config('services.lara.timeout', 25) + 10) * 1000 }},

        async enviar() {
            const texto = this.pergunta.trim();
            if (this.pensando || !this.disponivel || texto.length < 2) return;

            this.erro = null;
            this.pergunta = '';
            this.ajustarAltura();
            this.mensagens.push({ role: 'user', conteudo: texto, horario: this.agora() });
            this.scrollToEnd();
            this.iniciarEspera();

            const abort = new AbortController();
            const timer = setTimeout(() => abort.abort(), this.TIMEOUT_MS);

            try {
                const res = await fetch('{{ route('lara.ask') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ mensagem: texto }),
                    signal: abort.signal,
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    // 422 (validação) e 429 (pergunta anterior em curso) já vêm
                    // com um texto pronto para o funcionário.
                    this.erro = data.message || 'Não foi possível enviar a pergunta. Tente de novo.';
                    return;
                }

                this.mensagens.push({
                    role: 'assistant',
                    conteudo: data.resposta,
                    horario: data.horario || this.agora(),
                });
            } catch (e) {
                this.erro = e.name === 'AbortError'
                    ? 'A Lara demorou demais para responder. Tente de novo em instantes.'
                    : 'Falha de conexão com o portal. Tente de novo.';
            } finally {
                clearTimeout(timer);
                this.pararEspera();
                this.scrollToEnd();
            }
        },

        async novaConversa() {
            if (this.pensando) return;

            this.erro = null;

            try {
                await fetch('{{ route('lara.reset') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
            } catch (e) {
                // A limpeza do lado da IA é best-effort — a tela zera de todo jeito.
            }

            this.mensagens = [];
            this.$refs.entrada.focus();
        },

        ajustarAltura() {
            const el = this.$refs.entrada;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 112) + 'px';
        },

        iniciarEspera() {
            this.pensando = true;
            this.segundos = 0;
            this.cronometro = setInterval(() => { this.segundos++; this.scrollToEnd(); }, 1000);
        },

        pararEspera() {
            this.pensando = false;
            clearInterval(this.cronometro);
        },

        scrollToEnd() {
            this.$nextTick(() => {
                const el = this.$refs.conversa;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        agora() {
            return new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        },
    };
}
</script>
