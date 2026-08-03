<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Lara — Assistente do Clube 
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8"
             x-data="laraChat(@js($mensagens->map(fn ($m) => ['role' => $m->role, 'conteudo' => $m->conteudo, 'horario' => $m->created_at?->format('H:i')])->values()), {{ Js::from($disponivel) }})"
             x-init="scrollToEnd()">

            @unless($disponivel)
                <div class="mb-4 p-4 bg-amber-100 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-300 rounded-lg text-sm">
                    @if($configurado)
                        A Lara não está respondendo agora — o servidor dela pode estar reiniciando.
                        Recarregue a página em alguns instantes; se continuar assim, avise o time de TI.
                    @else
                        A Lara está desativada no momento. Fale com o time de TI se precisar dela agora.
                    @endif
                </div>
            @endunless

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-xl flex flex-col" style="height: 70vh;">

                {{-- ══════════════ Conversa ══════════════ --}}
                <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4" x-ref="conversa">

                    <template x-if="mensagens.length === 0">
                        <div class="h-full flex flex-col items-center justify-center text-center text-gray-500 dark:text-gray-400 px-6">
                            <svg class="w-12 h-12 mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z"/>
                            </svg>
                            <p class="text-sm">Pergunte alguma coisa sobre o estatuto do clube.</p>
                            <p class="text-xs mt-1">Ex.: “qual o horário da academia?”</p>
                        </div>
                    </template>

                    <template x-for="(m, i) in mensagens" :key="i">
                        <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                            <div class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap break-words"
                                 :class="m.role === 'user'
                                    ? 'bg-indigo-600 text-white rounded-br-sm'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded-bl-sm'">
                                {{-- x-text, nunca x-html: a resposta vem de um modelo de linguagem
                                     e não pode virar HTML executável na tela. --}}
                                <span x-text="m.conteudo"></span>
                                <span class="block mt-1 text-[10px] opacity-60" x-text="m.horario"></span>
                            </div>
                        </div>
                    </template>

                    {{-- A espera pode passar de 20s: sem este indicador a tela parece travada. --}}
                    <template x-if="pensando">
                        <div class="flex justify-start">
                            <div class="bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300 rounded-2xl rounded-bl-sm px-4 py-2.5 text-sm flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>Lara está pensando… <span x-text="segundos + 's'"></span></span>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- ══════════════ Erro de transporte ══════════════ --}}
                <template x-if="erro">
                    <div class="px-4 sm:px-6 pb-2">
                        <div class="p-3 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-300 rounded-lg text-sm"
                             x-text="erro"></div>
                    </div>
                </template>

                {{-- ══════════════ Envio ══════════════ --}}
                <div class="border-t border-gray-200 dark:border-gray-700 p-3 sm:p-4">
                    <form @submit.prevent="enviar()" class="flex items-end gap-2">
                        <textarea x-model="pergunta"
                                  x-ref="entrada"
                                  rows="1"
                                  maxlength="{{ (int) config('services.lara.max_input_chars', 1000) }}"
                                  :disabled="pensando || !disponivel"
                                  @keydown.enter.prevent="if (!$event.shiftKey) enviar()"
                                  @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 140) + 'px'"
                                  placeholder="Escreva sua pergunta…"
                                  class="flex-1 resize-none rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60"></textarea>

                        <button type="submit"
                                :disabled="pensando || !disponivel || pergunta.trim().length < 2"
                                class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-medium rounded-xl transition">
                            Enviar
                        </button>
                    </form>

                    <div class="flex justify-between items-center mt-2">
                        <span class="text-xs text-gray-400 dark:text-gray-500">Enter envia · Shift+Enter quebra linha</span>
                        <button type="button"
                                @click="novaConversa()"
                                :disabled="pensando"
                                class="text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 disabled:opacity-40 transition">
                            Nova conversa
                        </button>
                    </div>
                </div>
            </div>

            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500 text-center">
                A Lara responde com base no estatuto do clube. Em caso de dúvida, confirme com o setor responsável. Essa é "A Lara".
            </p>
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

        // A cadeia de timeouts é escalonada: a IA desiste em 22s, o PHP em 25s,
        // e o navegador só em 35s. Abortar aqui antes do PHP jogaria fora uma
        // resposta que estava a caminho e deixaria o funcionário sem explicação.
        TIMEOUT_MS: 35000,

        async enviar() {
            const texto = this.pergunta.trim();
            if (this.pensando || !this.disponivel || texto.length < 2) return;

            this.erro = null;
            this.pergunta = '';
            this.$refs.entrada.style.height = 'auto';
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
