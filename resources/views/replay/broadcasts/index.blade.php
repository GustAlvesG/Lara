{{--
    RePET › Transmissões: um cartão por quadra, com o estado vindo do servidor
    do rePET (GET /status, a cada 3 s). Ligar/desligar e placar respondem JSON
    (BroadcastController) e a tela não recarrega no meio do jogo.

    Ponto, set e volume NÃO são idempotentes no servidor: enquanto uma ação
    está em curso os botões da quadra ficam travados, e depois de um timeout a
    tela pede para conferir o placar antes de repetir.
--}}
<x-app-layout :bootstrap-grid="false">

    <x-slot name="css">
        <style>[x-cloak]{ display: none !important; }</style>
    </x-slot>

    @php
        $config = [
            'enabled' => $enabled,
            'base' => route('replay.broadcasts.index'),
            'statusUrl' => route('replay.broadcasts.status'),
        ];
    @endphp

    <script>
        window.repetBroadcasts = function (config) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

            return {
                enabled: config.enabled,
                quadras: [],
                emAr: 0,
                limite: 2,
                loaded: false,
                error: null,
                busy: {},
                notice: {},
                forms: {},
                timer: null,

                init() {
                    if (! this.enabled) return;
                    this.refresh();
                    this.timer = setInterval(() => { if (! document.hidden) this.refresh(); }, 3000);
                },

                async refresh() {
                    try {
                        const r = await fetch(config.statusUrl, { headers: { Accept: 'application/json' } });
                        const body = await r.json();
                        if (! body.ok) { this.error = body.message; return; }
                        this.error = null;
                        this.apply(body.state);
                    } catch (e) {
                        this.error = 'Sem resposta do Lara ao buscar o estado. A tela tenta de novo sozinha.';
                    } finally {
                        this.loaded = true;
                    }
                },

                apply(state) {
                    this.emAr = state.transmissoes_com_placar?.no_ar ?? 0;
                    this.limite = state.transmissoes_com_placar?.limite ?? 2;
                    // Ordena pelo nome que aparece na quadra (o id não bate com o nome).
                    const quadras = (state.quadras || []).slice().sort((a, b) => (a.nome || '').localeCompare(b.nome || '', 'pt-BR', { numeric: true }));
                    quadras.forEach(q => {
                        // Uma ação em curso manda no cartão: o estado dela chega na resposta.
                        if (this.busy[q.quadra_id]) {
                            const atual = this.quadras.find(x => x.quadra_id === q.quadra_id);
                            if (atual) Object.assign(q, atual);
                        }
                        if (! this.forms[q.quadra_id]) {
                            this.forms[q.quadra_id] = {
                                nome_a: '', nome_b: '', reset: true, ajustes: false,
                                pontos_a: '', pontos_b: '', sets_a: '', sets_b: '',
                                volume: q.placar?.volume ?? 100,
                            };
                        }
                    });
                    this.quadras = quadras;
                },

                url(q, path) { return config.base + '/' + encodeURIComponent(q.quadra_id) + '/' + path; },

                async act(q, method, path, data = {}, confirmText = null) {
                    if (confirmText && ! confirm(confirmText)) return;
                    if (this.busy[q.quadra_id]) return;
                    this.busy[q.quadra_id] = true;
                    this.notice[q.quadra_id] = null;
                    try {
                        const r = await fetch(this.url(q, path), {
                            method,
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify(data),
                        });
                        const body = await r.json().catch(() => ({}));
                        if (r.status === 422 && body.errors) {
                            this.notice[q.quadra_id] = { ok: false, text: Object.values(body.errors).flat().join(' ') };
                        } else if (! body.ok) {
                            this.notice[q.quadra_id] = { ok: false, text: body.message || 'Não foi possível concluir.' };
                        } else {
                            // As rotas do placar devolvem o objeto de estado: vale na hora.
                            if (body.state && body.state.nomes) {
                                q.placar = Object.assign({}, q.placar || {}, body.state);
                            }
                            if (body.message) this.notice[q.quadra_id] = { ok: true, text: body.message };
                        }
                    } catch (e) {
                        this.notice[q.quadra_id] = { ok: false, text: 'Sem resposta. Confira o placar antes de repetir.' };
                    } finally {
                        this.busy[q.quadra_id] = false;
                        this.refresh();
                    }
                },

                ligar(q, modo) {
                    const f = this.forms[q.quadra_id];
                    const texto = modo === 'direta'
                        ? 'Ligar a transmissão SEM placar de ' + q.nome + '? Ela vai ao ar no YouTube.'
                        : 'Ligar a transmissão COM placar de ' + q.nome + '? Ela vai ao ar no YouTube.';
                    this.act(q, 'POST', 'ligar', { modo, nome_a: f.nome_a || null, nome_b: f.nome_b || null, reset: f.reset }, texto);
                },

                desligar(q) {
                    const modo = q.modo === 'direta' ? 'direta' : 'placar';
                    this.act(q, 'POST', 'desligar', { modo }, 'Encerrar a transmissão de ' + q.nome + '?');
                },

                corrigir(q) {
                    const f = this.forms[q.quadra_id];
                    const data = {};
                    ['pontos_a', 'pontos_b', 'sets_a', 'sets_b'].forEach(k => { if (f[k] !== '' && f[k] !== null) data[k] = parseInt(f[k], 10); });
                    if (! Object.keys(data).length) { this.notice[q.quadra_id] = { ok: false, text: 'Preencha ao menos um campo do placar.' }; return; }
                    this.act(q, 'PUT', 'placar', data).then(() => { ['pontos_a', 'pontos_b', 'sets_a', 'sets_b'].forEach(k => f[k] = ''); });
                },

                nomes(q) {
                    const f = this.forms[q.quadra_id];
                    const data = {};
                    if (f.nome_a) data.nome_a = f.nome_a;
                    if (f.nome_b) data.nome_b = f.nome_b;
                    if (! Object.keys(data).length) { this.notice[q.quadra_id] = { ok: false, text: 'Escreva ao menos um nome.' }; return; }
                    this.act(q, 'PUT', 'nomes', data);
                },

                tempo(s) {
                    if (s === null || s === undefined) return '';
                    const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60);
                    return h ? h + ' h ' + String(m).padStart(2, '0') + ' min' : m + ' min';
                },

                modoLabel(q) {
                    return ({ placar: 'No ar com placar', direta: 'No ar sem placar', nenhum: 'Fora do ar', conflito: 'Conflito: dois modos ligados', desconhecido: 'Câmera sem resposta' })[q.modo] || q.modo;
                },

                modoClass(q) {
                    if (q.modo === 'placar' || q.modo === 'direta') return 'bg-ok-soft text-ok';
                    if (q.modo === 'conflito' || q.modo === 'desconhecido') return 'bg-warn-soft text-warn';
                    return 'bg-subtle text-ink-2';
                },

                bloqueioDeChave(q) {
                    return q.chave_em_uso_por && q.chave_em_uso_por !== q.quadra_id ? q.chave_em_uso_por : null;
                },

                nomeDe(id) { return this.quadras.find(x => x.quadra_id === id)?.nome || id; },
            };
        };
    </script>

    <div class="py-6" x-data="repetBroadcasts({{ \Illuminate\Support\Js::from($config) }})">
        <div class="mx-auto flex max-w-[1200px] flex-col gap-6 px-4 sm:px-6 lg:px-8">

            <x-page-title title="Transmissões">
                Live das quadras no YouTube: ligue, desligue e opere o placar que aparece no vídeo.
            </x-page-title>

            @unless ($enabled)
                <div class="rounded-xl border border-warn/40 bg-warn-soft p-4 text-sm text-ink">
                    A integração com o servidor do rePET está desligada neste ambiente. A TI precisa preencher
                    <code>REPET_API_URL</code> e <code>REPET_API_TOKEN</code> no <code>.env</code>.
                </div>
            @endunless

            <template x-if="enabled">
                <div class="flex flex-col gap-6">
                    <div class="flex flex-wrap items-center gap-3 text-sm text-ink-2">
                        <span>Transmissões com placar no ar: <strong class="text-ink" x-text="emAr + ' de ' + limite"></strong></span>
                        <span class="text-ink-3">·</span>
                        <span>Atualiza sozinho a cada 3 segundos.</span>
                    </div>

                    <div x-show="error" x-cloak class="rounded-xl border border-danger/40 bg-danger-soft p-4 text-sm text-grena-ink" x-text="error"></div>

                    <div x-show="! loaded" class="rounded-card bg-surface p-8 text-center text-sm text-ink-2 shadow-card">Consultando o servidor do rePET…</div>

                    <div x-show="loaded && ! quadras.length && ! error" x-cloak class="rounded-card bg-surface p-8 text-center text-sm text-ink-2 shadow-card">
                        O servidor do rePET não devolveu nenhuma quadra.
                    </div>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <template x-for="q in quadras" :key="q.quadra_id">
                            <section class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-card sm:p-6" :aria-busy="busy[q.quadra_id] ? 'true' : 'false'">

                                {{-- Cabeçalho --}}
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="font-display text-lg font-semibold tracking-tight text-ink" x-text="q.nome"></h3>
                                        <p class="text-xs text-ink-3"><span x-text="q.esporte"></span> · <span class="font-mono" x-text="q.quadra_id"></span></p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold" :class="modoClass(q)">
                                        <span class="h-2 w-2 rounded-full bg-current" :class="(q.modo === 'placar' || q.modo === 'direta') && 'animate-pulse'"></span>
                                        <span x-text="modoLabel(q)"></span>
                                    </span>
                                </div>

                                <template x-if="q.modo === 'placar' && q.placar">
                                    <p class="text-xs text-ink-2">
                                        <span x-show="q.placar.transmitindo">Transmitindo</span>
                                        <span x-show="! q.placar.transmitindo" class="font-semibold text-warn">Tentando conectar (câmera ou YouTube)…</span>
                                        <span x-show="q.placar.no_ar_ha_segundos !== null"> · no ar há <span x-text="tempo(q.placar.no_ar_ha_segundos)"></span></span>
                                        <span x-show="q.placar.encerra_em_segundos !== null"> · desliga sozinho em <span x-text="tempo(q.placar.encerra_em_segundos)"></span></span>
                                    </p>
                                </template>

                                <template x-if="! q.tem_stream_key">
                                    <p class="rounded-lg bg-subtle p-3 text-xs text-ink-2">Esta quadra não tem chave do YouTube no servidor do rePET e não pode transmitir. O placar pode ser preparado mesmo assim.</p>
                                </template>
                                <template x-if="bloqueioDeChave(q)">
                                    <p class="rounded-lg bg-warn-soft p-3 text-xs text-ink">Usa o mesmo canal do YouTube que <strong x-text="nomeDe(bloqueioDeChave(q))"></strong>, que está no ar. Encerre aquela antes de ligar esta.</p>
                                </template>
                                <template x-if="q.modo === 'conflito'">
                                    <p class="rounded-lg bg-warn-soft p-3 text-xs text-ink">Os dois modos estão ligados ao mesmo tempo, o que não deveria acontecer. Use <strong>Desligar</strong> e ligue de novo no modo certo.</p>
                                </template>
                                <template x-if="q.placar && q.placar.audio_da_camera === false && q.modo === 'placar'">
                                    <p class="rounded-lg bg-subtle p-3 text-xs text-ink-2">A câmera desta quadra não tem áudio: a live sai muda e o volume não muda nada.</p>
                                </template>

                                {{-- Placar --}}
                                <template x-if="q.placar">
                                    <div class="grid grid-cols-2 gap-3">
                                        <template x-for="lado in ['a', 'b']" :key="lado">
                                            <div class="flex flex-col items-center gap-2 rounded-xl border border-line p-3">
                                                <p class="w-full truncate text-center text-sm font-bold text-ink" x-text="q.placar.nomes?.[lado]"></p>
                                                <div class="flex items-end gap-3">
                                                    <div class="text-center">
                                                        <p class="text-[11px] font-bold uppercase tracking-wider text-ink-3">Sets</p>
                                                        <p class="font-display text-2xl font-semibold text-ink-2" x-text="q.placar.placar?.[lado]?.sets ?? 0"></p>
                                                    </div>
                                                    <div class="text-center">
                                                        <p class="text-[11px] font-bold uppercase tracking-wider text-ink-3">Pontos</p>
                                                        <p class="font-display text-4xl font-semibold text-ink" x-text="q.placar.placar?.[lado]?.pontos ?? 0"></p>
                                                    </div>
                                                </div>
                                                <div class="grid w-full grid-cols-2 gap-2">
                                                    <button type="button" @click="act(q, 'POST', 'ponto', { lado, delta: 1 })" :disabled="busy[q.quadra_id]"
                                                        class="h-12 rounded-xl bg-grena text-base font-bold text-white transition hover:bg-grena-hover disabled:cursor-not-allowed disabled:opacity-50">+1 ponto</button>
                                                    <button type="button" @click="act(q, 'POST', 'ponto', { lado, delta: -1 })" :disabled="busy[q.quadra_id]"
                                                        class="h-12 rounded-xl border border-line text-sm font-semibold text-ink-2 transition hover:bg-subtle disabled:cursor-not-allowed disabled:opacity-50">−1 ponto</button>
                                                    <button type="button" @click="act(q, 'POST', 'set', { lado, delta: 1 }, 'Fechar o set para ' + (q.placar.nomes?.[lado] || 'esta dupla') + '? Os pontos dos dois lados voltam a zero.')" :disabled="busy[q.quadra_id]"
                                                        class="h-10 rounded-xl border border-line text-sm font-semibold text-ink transition hover:bg-subtle disabled:cursor-not-allowed disabled:opacity-50">+1 set</button>
                                                    <button type="button" @click="act(q, 'POST', 'set', { lado, delta: -1 })" :disabled="busy[q.quadra_id]"
                                                        class="h-10 rounded-xl border border-line text-sm font-semibold text-ink-2 transition hover:bg-subtle disabled:cursor-not-allowed disabled:opacity-50">−1 set</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Aviso da última ação --}}
                                <p x-show="notice[q.quadra_id]" x-cloak class="rounded-lg p-3 text-sm"
                                   :class="notice[q.quadra_id]?.ok ? 'bg-ok-soft text-ok' : 'bg-danger-soft text-grena-ink'"
                                   x-text="notice[q.quadra_id]?.text"></p>

                                {{-- Ligar --}}
                                <template x-if="q.modo === 'nenhum' || q.modo === 'desconhecido'">
                                    <div class="flex flex-col gap-3 border-t border-line pt-4">
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <label class="text-xs font-bold uppercase tracking-wider text-ink-3">Dupla A
                                                <input type="text" maxlength="40" x-model="forms[q.quadra_id].nome_a" :placeholder="q.placar?.nomes?.a || 'DUPLA A'"
                                                    class="mt-1 block w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm font-normal normal-case tracking-normal text-ink">
                                            </label>
                                            <label class="text-xs font-bold uppercase tracking-wider text-ink-3">Dupla B
                                                <input type="text" maxlength="40" x-model="forms[q.quadra_id].nome_b" :placeholder="q.placar?.nomes?.b || 'DUPLA B'"
                                                    class="mt-1 block w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm font-normal normal-case tracking-normal text-ink">
                                            </label>
                                        </div>
                                        <label class="inline-flex items-center gap-2 text-sm text-ink-2">
                                            <input type="checkbox" x-model="forms[q.quadra_id].reset" class="rounded border-line">
                                            Jogo novo (placar 0 × 0 e volume 100%)
                                        </label>
                                        <p class="text-xs text-ink-3">No vídeo os nomes saem em maiúsculas, com até 14 letras.</p>
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" @click="ligar(q, 'placar')" :disabled="busy[q.quadra_id] || ! q.tem_stream_key || bloqueioDeChave(q)"
                                                class="inline-flex h-10 items-center gap-2 rounded-full bg-grena px-4 text-sm font-bold text-white transition hover:bg-grena-hover disabled:cursor-not-allowed disabled:opacity-50">
                                                <span x-show="! busy[q.quadra_id]">Ligar com placar</span>
                                                <span x-show="busy[q.quadra_id]">Ligando… (até 30 s)</span>
                                            </button>
                                            <button type="button" @click="ligar(q, 'direta')" :disabled="busy[q.quadra_id] || ! q.tem_stream_key || bloqueioDeChave(q)"
                                                class="inline-flex h-10 items-center gap-2 rounded-full border border-line px-4 text-sm font-semibold text-ink transition hover:bg-subtle disabled:cursor-not-allowed disabled:opacity-50">
                                                Ligar sem placar
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                {{-- Desligar --}}
                                <template x-if="q.modo === 'placar' || q.modo === 'direta' || q.modo === 'conflito'">
                                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line pt-4">
                                        <button type="button" @click="forms[q.quadra_id].ajustes = ! forms[q.quadra_id].ajustes" x-show="q.modo !== 'direta'"
                                            class="text-sm font-medium text-grena-ink hover:underline" :aria-expanded="forms[q.quadra_id].ajustes">
                                            Nomes, correção do placar e volume
                                        </button>
                                        <button type="button" @click="desligar(q)" :disabled="busy[q.quadra_id]"
                                            class="ml-auto inline-flex h-10 items-center rounded-full border border-danger/40 px-4 text-sm font-bold text-grena-ink transition hover:bg-danger-soft disabled:cursor-not-allowed disabled:opacity-50">
                                            Desligar
                                        </button>
                                    </div>
                                </template>

                                <template x-if="q.modo === 'nenhum' || q.modo === 'desconhecido'">
                                    <button type="button" @click="forms[q.quadra_id].ajustes = ! forms[q.quadra_id].ajustes"
                                        class="self-start text-sm font-medium text-grena-ink hover:underline" :aria-expanded="forms[q.quadra_id].ajustes">
                                        Preparar placar e volume antes de ligar
                                    </button>
                                </template>

                                {{-- Ajustes --}}
                                <div x-show="forms[q.quadra_id]?.ajustes && q.modo !== 'direta'" x-cloak class="flex flex-col gap-4 rounded-xl bg-subtle p-4">
                                    <div>
                                        <p class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-3">Nomes das duplas</p>
                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_1fr_auto]">
                                            <input type="text" maxlength="40" x-model="forms[q.quadra_id].nome_a" :placeholder="q.placar?.nomes?.a || 'DUPLA A'" aria-label="Dupla A"
                                                class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink">
                                            <input type="text" maxlength="40" x-model="forms[q.quadra_id].nome_b" :placeholder="q.placar?.nomes?.b || 'DUPLA B'" aria-label="Dupla B"
                                                class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink">
                                            <button type="button" @click="nomes(q)" :disabled="busy[q.quadra_id]"
                                                class="h-10 rounded-full border border-line bg-surface px-4 text-sm font-semibold text-ink hover:bg-canvas disabled:opacity-50">Trocar nomes</button>
                                        </div>
                                    </div>

                                    <div>
                                        <p class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-3">Corrigir placar (campo vazio não muda)</p>
                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                            <input type="number" min="0" x-model="forms[q.quadra_id].pontos_a" placeholder="Pontos A" aria-label="Pontos A" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink">
                                            <input type="number" min="0" x-model="forms[q.quadra_id].sets_a" placeholder="Sets A" aria-label="Sets A" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink">
                                            <input type="number" min="0" x-model="forms[q.quadra_id].pontos_b" placeholder="Pontos B" aria-label="Pontos B" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink">
                                            <input type="number" min="0" x-model="forms[q.quadra_id].sets_b" placeholder="Sets B" aria-label="Sets B" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink">
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <button type="button" @click="corrigir(q)" :disabled="busy[q.quadra_id]"
                                                class="h-10 rounded-full border border-line bg-surface px-4 text-sm font-semibold text-ink hover:bg-canvas disabled:opacity-50">Gravar placar</button>
                                            <button type="button" @click="act(q, 'POST', 'zerar', {}, 'Zerar pontos e sets dos dois lados?')" :disabled="busy[q.quadra_id]"
                                                class="h-10 rounded-full border border-line bg-surface px-4 text-sm font-semibold text-ink-2 hover:bg-canvas disabled:opacity-50">Zerar placar</button>
                                        </div>
                                    </div>

                                    <div>
                                        <p class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-3">
                                            Volume da câmera: <span class="text-ink" x-text="forms[q.quadra_id].volume + '%'"></span>
                                            <span class="font-normal normal-case tracking-normal">(atual: <span x-text="(q.placar?.volume ?? 100) + '%'"></span>)</span>
                                        </p>
                                        <div class="flex items-center gap-3">
                                            <input type="range" min="0" max="400" step="10" x-model.number="forms[q.quadra_id].volume" class="flex-1" aria-label="Volume">
                                            <button type="button" @click="act(q, 'PUT', 'volume', { nivel: forms[q.quadra_id].volume })" :disabled="busy[q.quadra_id]"
                                                class="h-10 rounded-full border border-line bg-surface px-4 text-sm font-semibold text-ink hover:bg-canvas disabled:opacity-50">Aplicar</button>
                                        </div>
                                        <p class="mt-1 text-xs text-ink-3">100% é o som original e 0% é mudo. O microfone já chega alto: acima de 150% o som costuma estourar.</p>
                                    </div>
                                </div>
                            </section>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-app-layout>
