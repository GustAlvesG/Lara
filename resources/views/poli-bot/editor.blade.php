<x-app-layout>
    @php
        $campo = 'w-full px-3 py-2 border border-line-strong rounded-lg shadow-card text-sm focus:border-grena focus:ring-grena-tint bg-surface';
        $rotulo = 'block text-xs font-semibold text-ink-2 mb-1';
        $botaoSec = 'inline-flex items-center justify-center px-3 py-1.5 bg-surface border border-line-strong rounded-md text-xs font-semibold text-ink hover:bg-subtle disabled:opacity-40';
        $secao = 'text-sm font-bold text-ink';
    @endphp

    <x-slot name="css">
        <style>
            .pb-canvas { background-color: rgb(var(--canvas)); background-image: radial-gradient(circle, rgb(var(--ink-3) / .35) 1px, transparent 1px); }
            .pb-canvas.pb-panning { cursor: grabbing; }
            .pb-node { transition: box-shadow .15s; }
            .pb-port { transition: transform .1s; }
            .pb-port:hover { transform: scale(1.35); }
        </style>
    </x-slot>

    {{-- Editor de fluxos em forma de fluxograma: blocos arrastáveis, ligações puxadas das
         bolinhas de saída. A definição gravada é a mesma que o BotEngine lê; a única
         chave nova é `layout` (posição de cada bloco na tela), que o motor ignora. --}}
    <div x-data="botFlowEditor(@js([
        'flow' => $dados,
        'versions' => $versoes,
        'otherFlows' => $outrosFluxos,
        'invalidDefaults' => config('poli.bot.messages.invalid'),
        'urls' => [
            'index' => route('poli-bot.index'),
            'store' => route('poli-bot.flows.store'),
            'update' => $fluxo ? route('poli-bot.flows.update', $fluxo) : null,
            'destroy' => $fluxo ? route('poli-bot.flows.destroy', $fluxo) : null,
            'simulate' => route('poli-bot.simulate'),
            'templates' => route('poli-bot.poli.templates'),
            'teams' => route('poli-bot.poli.teams'),
        ],
    ]))" x-cloak
         class="flex flex-col bg-subtle" style="height: calc(100vh - 4rem); min-height: 640px"
         @pointermove.window="onMove($event)" @pointerup.window="onUp($event)" @keydown.window="onKey($event)">

        {{-- ============ Barra superior ============ --}}
        <div class="flex flex-wrap items-center gap-3 px-4 py-2.5 border-b border-line bg-surface">
            <a :href="urls.index" class="text-sm text-ink-2 hover:underline">&larr; Fluxos</a>
            <h1 class="font-display text-lg font-semibold tracking-tight text-ink truncate max-w-[24rem]" x-text="flow.name || 'Novo fluxo'"></h1>
            <span class="px-2 py-0.5 rounded text-xs font-bold"
                  :class="flow.active ? 'bg-ok-soft text-ok' : 'bg-line text-ink'"
                  x-text="flow.active ? 'Ativo' : 'Rascunho'"></span>
            <span x-show="dirty" class="text-xs text-warn font-semibold">● Alterações não salvas</span>
            <div class="flex-1"></div>
            <span x-show="message" x-text="message" class="text-sm text-ok"></span>
            <button type="button" x-show="flow.id" @click="removeFlow()" class="text-xs text-danger hover:underline">Apagar fluxo</button>
            <button type="button" @click="save()" :disabled="saving" title="Ctrl+S"
                    class="inline-flex h-9 items-center rounded-full bg-grena px-5 text-sm font-bold text-white transition hover:bg-grena-hover disabled:opacity-50">
                <span x-text="saving ? 'Salvando…' : 'Salvar'"></span>
            </button>
        </div>

        <div class="flex flex-1 min-h-0">

            {{-- ============ Paleta de blocos ============ --}}
            <aside class="w-52 shrink-0 border-r border-line bg-surface overflow-y-auto p-3 space-y-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-ink-3 mb-2">Conversa</p>
                    <div class="space-y-1.5">
                        <template x-for="k in ['mensagem', 'pergunta', 'menu', 'template']" :key="k">
                            <div draggable="true" @dragstart="paletteDrag($event, k)" @click="addBlockAtCenter(k)"
                                 class="flex items-start gap-2 p-2 rounded-lg border border-line cursor-grab active:cursor-grabbing hover:border-grena/40 hover:bg-grena-tint/50 select-none"
                                 :title="'Arraste para o quadro (ou clique) — ' + KINDS[k].hint">
                                <span class="w-7 h-7 shrink-0 rounded-md flex items-center justify-center text-sm text-white" :style="'background:' + KINDS[k].color" x-text="KINDS[k].icon"></span>
                                <span class="min-w-0">
                                    <span class="block text-xs font-semibold text-ink" x-text="KINDS[k].label"></span>
                                    <span class="block text-[10px] leading-tight text-ink-3" x-text="KINDS[k].hint"></span>
                                </span>
                            </div>
                        </template>
                    </div>
                </div>
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-ink-3 mb-2">Ações</p>
                    <div class="space-y-1.5">
                        <template x-for="k in ['handoff', 'uber_request', 'goto_flow', 'close']" :key="k">
                            <div draggable="true" @dragstart="paletteDrag($event, k)" @click="addBlockAtCenter(k)"
                                 class="flex items-start gap-2 p-2 rounded-lg border border-line cursor-grab active:cursor-grabbing hover:border-grena/40 hover:bg-grena-tint/50 select-none"
                                 :title="'Arraste para o quadro (ou clique) — ' + KINDS[k].hint">
                                <span class="w-7 h-7 shrink-0 rounded-md flex items-center justify-center text-sm text-white" :style="'background:' + KINDS[k].color" x-text="KINDS[k].icon"></span>
                                <span class="min-w-0">
                                    <span class="block text-xs font-semibold text-ink" x-text="KINDS[k].label"></span>
                                    <span class="block text-[10px] leading-tight text-ink-3" x-text="KINDS[k].hint"></span>
                                </span>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="rounded-lg bg-subtle p-2.5 text-[11px] leading-snug text-ink-2 space-y-1">
                    <p><strong>Ligar:</strong> puxe a bolinha à direita de um bloco até outro bloco. Soltar no vazio cria um bloco já ligado.</p>
                    <p><strong>Desligar:</strong> clique na linha e em ✕ (ou Delete).</p>
                    <p><strong>Mover o quadro:</strong> arraste o fundo. Roda do mouse dá zoom.</p>
                    <p><strong>Atalhos:</strong> Ctrl+S salva, Ctrl+D duplica, Delete apaga, Esc solta.</p>
                </div>
            </aside>

            {{-- ============ Quadro (canvas) ============ --}}
            <div class="relative flex-1 min-w-0 overflow-hidden pb-canvas select-none" x-ref="canvas"
                 :class="drag && drag.type === 'pan' && drag.moved ? 'pb-panning' : ''"
                 :style="`background-size: ${20 * view.z}px ${20 * view.z}px; background-position: ${view.x}px ${view.y}px`"
                 @pointerdown="canvasDown($event)" @wheel.prevent="onWheel($event)"
                 @dragover.prevent="$event.dataTransfer.dropEffect = 'copy'" @drop.prevent="paletteDrop($event)">

                <div class="absolute left-0 top-0" style="transform-origin: 0 0; will-change: transform"
                     :style="`transform: translate(${view.x}px, ${view.y}px) scale(${view.z})`">

                    {{-- Ligações (SVG gerado: x-for não funciona dentro de <svg>) --}}
                    <svg class="absolute left-0 top-0 overflow-visible" width="1" height="1" style="pointer-events: none">
                        <defs>
                            <marker id="pb-seta" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="#94a3b8"/></marker>
                            <marker id="pb-seta-opcao" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="#818cf8"/></marker>
                            <marker id="pb-seta-entrada" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="#1f2937"/></marker>
                            <marker id="pb-seta-sel" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="#10b981"/></marker>
                        </defs>
                        <g x-html="edgesSvg()"></g>
                        <path x-show="drag && drag.type === 'link'" :d="linkPath()" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-dasharray="6 5"/>
                    </svg>

                    {{-- Botão de desligar a ligação selecionada --}}
                    <template x-if="selectedEdgeMid()">
                        <button type="button" @pointerdown.stop @click.stop="removeSelectedEdge()"
                                class="absolute w-6 h-6 -ml-3 -mt-3 rounded-full bg-danger text-white text-xs font-bold shadow-card hover:bg-danger"
                                :style="`left:${selectedEdgeMid().x}px; top:${selectedEdgeMid().y}px`" title="Desligar (Delete)">✕</button>
                    </template>

                    {{-- Entradas: Início e Fora do horário --}}
                    <template x-for="en in entries()" :key="en.id">
                        <div class="absolute flex items-center gap-2 px-3 rounded-full text-xs font-bold text-white shadow-card cursor-move"
                             :style="`left:${en.pos.x}px; top:${en.pos.y}px; width:${ENTRY_W}px; height:${ENTRY_H}px; background:${en.color}`"
                             @pointerdown.stop="entryDown($event, en)" :title="en.hint">
                            <span x-text="en.icon"></span><span class="flex-1 truncate" x-text="en.label"></span>
                            <span class="pb-port absolute -right-[7px] w-3.5 h-3.5 rounded-full border-2 bg-surface cursor-crosshair"
                                  :style="`top:${ENTRY_H / 2 - 7}px; border-color:${en.color}`"
                                  @pointerdown.stop="portDown($event, en.id, 'next')" title="Puxe até o passo por onde começar"></span>
                        </div>
                    </template>

                    {{-- Blocos (passos) --}}
                    <template x-for="s in steps" :key="s._uid">
                        <div class="pb-node absolute rounded-xl bg-surface border shadow-card cursor-move"
                             :data-node="s._uid"
                             :class="nodeClass(s)"
                             :style="`left:${s.pos.x}px; top:${s.pos.y}px; width:${W}px; height:${nodeHeight(s)}px`"
                             @pointerdown.stop="nodeDown($event, s)" @dblclick.stop="select(s); panel = 'passo'">

                            {{-- entrada --}}
                            <span class="absolute -left-[7px] w-3.5 h-3.5 rounded-full border-2 border-line-strong bg-surface"
                                  :class="drag && drag.type === 'link' ? '!border-grena scale-125' : ''"
                                  :style="`top:${HEAD / 2 - 7}px`"></span>

                            <div class="flex items-center gap-2 px-3 rounded-t-xl text-white" :style="`height:${HEAD}px; background:${KINDS[kind(s)].color}`">
                                <span class="text-sm" x-text="KINDS[kind(s)].icon"></span>
                                <span class="font-mono font-semibold text-sm truncate flex-1" x-text="s.key"></span>
                                <span x-show="s.key === settings.start" class="px-1.5 py-0.5 rounded bg-white/25 text-[10px] font-bold">início</span>
                                <span x-show="settings.hours.enabled && s.key === settings.hours.out_of_hours" class="px-1.5 py-0.5 rounded bg-white/25 text-[10px] font-bold">fora</span>
                                <span x-show="!isReachable(s.key)" class="px-1.5 py-0.5 rounded bg-warn-soft text-warn text-[10px] font-bold" title="Nenhum passo leva até aqui">sem caminho</span>
                                <span x-show="errorKeys().has(s.key)" class="px-1.5 py-0.5 rounded bg-danger text-[10px] font-bold" title="Este passo tem problema — veja o aviso no alto">!</span>
                            </div>

                            <div class="px-3 pt-1.5 overflow-hidden" :style="`height:${BODY}px`">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-ink-3 truncate" x-text="meta(s)"></p>
                                <p class="text-xs text-ink line-clamp-2 leading-snug" :class="previewText(s) ? '' : 'italic text-ink-3'" x-text="previewText(s) || '(sem mensagem)'"></p>
                            </div>

                            <div class="border-t border-line">
                                <template x-for="(p, pi) in ports(s)" :key="p.id">
                                    <div class="relative flex items-center justify-end gap-1 pl-3 pr-4 text-[11px]"
                                         :class="p.dim ? 'text-ink-3' : 'text-ink-2'"
                                         :style="`height:${ROW}px`">
                                        <span class="truncate" x-text="p.label"></span>
                                        <span x-show="!p.to && !p.dim" class="text-[10px] text-ink-3 shrink-0">· fim</span>
                                        <span class="pb-port absolute -right-[7px] w-3.5 h-3.5 rounded-full border-2 bg-surface cursor-crosshair"
                                              :style="`top:${ROW / 2 - 7}px; border-color:${p.opt ? '#818cf8' : '#94a3b8'}`"
                                              @pointerdown.stop="portDown($event, s._uid, p.id)" title="Puxe até o próximo passo"></span>
                                    </div>
                                </template>
                                <template x-if="!ports(s).length">
                                    <div class="flex items-center px-3 text-[11px] font-semibold" :style="`height:${ROW}px; color:${KINDS[kind(s)].color}`">
                                        <span class="truncate" x-text="'■ ' + terminalLabel(s)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Criar bloco ao soltar uma ligação no vazio --}}
                <template x-if="quick">
                    <div class="absolute z-20 w-56 rounded-xl bg-surface shadow-pop border border-line p-1.5"
                         :style="`left:${quick.sx}px; top:${quick.sy}px`" @pointerdown.stop>
                        <p class="px-2 py-1 text-[11px] font-semibold text-ink-3">Criar e ligar:</p>
                        <template x-for="(k, ki) in Object.keys(KINDS)" :key="k">
                            <button type="button" @click="quickCreate(k)" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-lg text-left text-xs text-ink hover:bg-subtle">
                                <span class="w-5 h-5 rounded flex items-center justify-center text-[11px] text-white" :style="'background:' + KINDS[k].color" x-text="KINDS[k].icon"></span>
                                <span x-text="KINDS[k].label"></span>
                            </button>
                        </template>
                        <button type="button" @click="quick = null" class="w-full px-2 py-1 mt-1 text-[11px] text-ink-3 hover:underline">cancelar (Esc)</button>
                    </div>
                </template>

                {{-- Problemas ao salvar --}}
                <template x-if="errors.length">
                    <div class="absolute z-10 top-3 left-3 max-w-lg rounded-xl border border-danger/40 bg-danger-soft text-grena-ink px-4 py-3 text-sm shadow-card" @pointerdown.stop>
                        <div class="flex items-start gap-2">
                            <p class="font-semibold mb-1 flex-1">Não foi salvo. Corrija:</p>
                            <button type="button" @click="errors = []" class="text-danger hover:text-danger">✕</button>
                        </div>
                        <ul class="list-disc ml-5 space-y-0.5 max-h-48 overflow-y-auto">
                            <template x-for="e in errors">
                                <li>
                                    <template x-if="errorStep(e)"><button type="button" @click="focusKey(errorStep(e))" class="text-left hover:underline" x-text="e"></button></template>
                                    <template x-if="!errorStep(e)"><span x-text="e"></span></template>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                {{-- Zoom e arrumação --}}
                <div class="absolute z-10 bottom-3 left-3 flex items-center gap-1 rounded-lg bg-surface/95 shadow-card border border-line p-1" @pointerdown.stop>
                    <button type="button" @click="zoomBy(1 / 1.2)" class="w-7 h-7 rounded hover:bg-subtle text-ink-2" title="Diminuir">−</button>
                    <span class="w-12 text-center text-xs text-ink-2" x-text="Math.round(view.z * 100) + '%'"></span>
                    <button type="button" @click="zoomBy(1.2)" class="w-7 h-7 rounded hover:bg-subtle text-ink-2" title="Aumentar">+</button>
                    <span class="w-px h-5 bg-line mx-1"></span>
                    <button type="button" @click="fit()" class="px-2 h-7 rounded hover:bg-subtle text-xs text-ink-2" title="Mostrar o fluxo inteiro">Ajustar</button>
                    <button type="button" @click="autoLayout(); $nextTick(() => fit())" class="px-2 h-7 rounded hover:bg-subtle text-xs text-ink-2" title="Reposicionar os blocos em colunas, na ordem da conversa">Organizar</button>
                </div>

                <div x-show="!steps.length" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <p class="text-sm text-ink-3">Arraste um bloco da paleta para começar.</p>
                </div>
            </div>

            {{-- ============ Painel lateral ============ --}}
            <aside class="w-[24rem] shrink-0 border-l border-line bg-surface flex flex-col min-h-0">
                <div class="flex border-b border-line text-xs font-semibold">
                    <button type="button" @click="panel = 'fluxo'" class="flex-1 py-2.5 border-b-2" :class="panel === 'fluxo' ? 'border-grena text-grena-ink' : 'border-transparent text-ink-2'">Fluxo</button>
                    <button type="button" @click="panel = 'passo'" :disabled="!sel" class="flex-1 py-2.5 border-b-2 disabled:opacity-40" :class="panel === 'passo' ? 'border-grena text-grena-ink' : 'border-transparent text-ink-2'">
                        Passo<span x-show="sel" class="font-mono font-normal" x-text="sel ? ' · ' + sel.key : ''"></span>
                    </button>
                    <button type="button" @click="panel = 'sim'" class="flex-1 py-2.5 border-b-2" :class="panel === 'sim' ? 'border-grena text-grena-ink' : 'border-transparent text-ink-2'">Simulador</button>
                </div>

                {{-- ---------- Configuração do fluxo ---------- --}}
                <div x-show="panel === 'fluxo'" class="flex-1 overflow-y-auto p-4 space-y-5">
                    <div class="space-y-3">
                        <div>
                            <label class="{{ $rotulo }}">Nome</label>
                            <input type="text" x-model="flow.name" @input="if (!flow.id && !slugTouched) flow.slug = slugify(flow.name)" class="{{ $campo }}" placeholder="Ex.: Atendimento inicial">
                        </div>
                        <div>
                            <label class="{{ $rotulo }}">Identificador</label>
                            <input type="text" x-model="flow.slug" @input="slugTouched = true" :disabled="!!flow.id" class="{{ $campo }} font-mono disabled:opacity-60" placeholder="atendimento">
                            <p class="text-[11px] text-ink-3 mt-1" x-show="flow.id">Não muda depois de criado.</p>
                        </div>
                        <label class="flex items-start gap-2 text-sm text-ink">
                            <input type="checkbox" x-model="flow.active" class="rounded border-line-strong mt-0.5">
                            <span>Ativo — salvar publica: vale na próxima mensagem dos associados</span>
                        </label>
                    </div>

                    <div class="border-t border-line pt-4 space-y-3">
                        <h3 class="{{ $secao }}">Quando começa</h3>
                        <select x-model="triggerMode" class="{{ $campo }}">
                            <option value="any">Em qualquer primeira mensagem (boas-vindas)</option>
                            <option value="texts">Quando a mensagem for uma destas palavras</option>
                            <option value="redirect">Quando um setor transferir a conversa para O Lara</option>
                            <option value="goto">Só quando outro fluxo mandar para cá</option>
                        </select>
                        <p x-show="triggerMode === 'redirect'" class="text-[11px] text-gray-400">Vale quando um atendente, de qualquer setor, passa para O Lara uma conversa que estava com ele. Só um fluxo ativo pode começar assim; sem nenhum, a transferência abre o fluxo de boas-vindas.</p>
                        <div x-show="triggerMode === 'texts'">
                            <input type="text" x-model="settings.triggerTexts" class="{{ $campo }}" placeholder="carro de aplicativo, uber, taxi">
                            <p class="text-[11px] text-ink-3 mt-1">Separe por vírgula. Vale a mensagem inteira ou começando pela palavra; sem diferença de acento ou maiúscula.</p>
                        </div>
                        <div>
                            <label class="{{ $rotulo }}">Primeiro passo</label>
                            <select x-model="settings.start" class="{{ $campo }}">
                                <template x-for="k in stepKeys()"><option :value="k" x-text="k" :selected="k === settings.start"></option></template>
                            </select>
                            <p class="text-[11px] text-ink-3 mt-1">Ou puxe a bolinha do bloco <strong>Início</strong> no quadro.</p>
                        </div>
                    </div>

                    <div class="border-t border-line pt-4 space-y-3">
                        <h3 class="{{ $secao }}">Limites</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="{{ $rotulo }}">Encerrar parada após (min)</label>
                                <input type="number" min="1" x-model.number="settings.timeout_minutes" class="{{ $campo }}">
                            </div>
                            <div>
                                <label class="{{ $rotulo }}">Erros até passar p/ humano</label>
                                <input type="number" min="1" max="10" x-model.number="settings.max_attempts" class="{{ $campo }}">
                            </div>
                        </div>
                        <div>
                            <label class="{{ $rotulo }}">Ao esgotar as tentativas, passar para o time</label>
                            <select x-model="settings.on_max_team" class="{{ $campo }}">
                                <option value="" x-text="settings.on_max_raw ? 'Manter como está (fluxo ' + (settings.on_max_raw.flow || '?') + ')' : 'Time padrão do servidor (POLI_BOT_FALLBACK_TEAM)'"></option>
                                <template x-for="t in teams"><option :value="t.uuid" x-text="t.name" :selected="t.uuid === settings.on_max_team"></option></template>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-line pt-4 space-y-3">
                        <label class="inline-flex items-center gap-2 {{ $secao }}">
                            <input type="checkbox" x-model="settings.hours.enabled" class="rounded border-line-strong">
                            Tem horário de atendimento
                        </label>
                        <p class="text-xs text-ink-2">Fora do horário a conversa começa pelo bloco ligado a <strong>Fora do horário</strong> no quadro.</p>
                        <div x-show="settings.hours.enabled" class="space-y-2">
                            <template x-for="d in weekDays" :key="d.n">
                                <div class="flex items-center gap-2">
                                    <label class="inline-flex items-center gap-2 w-24 text-xs font-semibold text-ink">
                                        <input type="checkbox" x-model="settings.hours.days[d.n].open" class="rounded border-line-strong">
                                        <span x-text="d.label"></span>
                                    </label>
                                    <template x-if="settings.hours.days[d.n].open">
                                        <div class="flex items-center gap-1 flex-1">
                                            <input type="time" x-model="settings.hours.days[d.n].from" class="{{ $campo }} !px-1 !py-1">
                                            <span class="text-xs text-ink-3">às</span>
                                            <input type="time" x-model="settings.hours.days[d.n].to" class="{{ $campo }} !px-1 !py-1">
                                        </div>
                                    </template>
                                    <span x-show="!settings.hours.days[d.n].open" class="text-xs text-ink-3">Fechado</span>
                                </div>
                            </template>
                            <div class="flex items-center gap-2">
                                <span class="w-24 text-xs font-semibold text-ink pl-6">Feriados</span>
                                <div class="flex items-center gap-1 flex-1">
                                    <input type="time" x-model="settings.hours.holiday.from" class="{{ $campo }} !px-1 !py-1">
                                    <span class="text-xs text-ink-3">às</span>
                                    <input type="time" x-model="settings.hours.holiday.to" class="{{ $campo }} !px-1 !py-1">
                                </div>
                            </div>
                            <div>
                                <label class="{{ $rotulo }}">Datas de feriado (uma por linha, dd/mm/aaaa)</label>
                                <textarea rows="3" x-model="settings.hours.holidays" class="{{ $campo }} font-mono" placeholder="25/12/2026"></textarea>
                            </div>
                            <div>
                                <label class="{{ $rotulo }}">Fora do horário, começar pelo passo</label>
                                <select x-model="settings.hours.out_of_hours" class="{{ $campo }}">
                                    <option value="">— escolha —</option>
                                    <template x-for="k in stepKeys()"><option :value="k" x-text="k" :selected="k === settings.hours.out_of_hours"></option></template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-line pt-4" x-show="versions.length">
                        <h3 class="{{ $secao }} mb-2">Versões</h3>
                        <ul class="space-y-1 text-xs max-h-60 overflow-y-auto">
                            <template x-for="(v, vi) in versions" :key="v.id">
                                <li class="flex items-center gap-2">
                                    <span class="text-ink-2 flex-1"><span x-text="v.at"></span> · <span x-text="v.user || '—'"></span><span x-show="vi === 0" class="text-ok font-semibold"> (atual)</span></span>
                                    <button type="button" x-show="vi > 0" @click="restoreVersion(v)" class="text-grena-ink hover:underline">restaurar</button>
                                </li>
                            </template>
                        </ul>
                        <p class="text-[11px] text-ink-3 mt-2">Restaurar só carrega na tela — confira e salve.</p>
                    </div>
                </div>

                {{-- ---------- Propriedades do passo selecionado ---------- --}}
                <div x-show="panel === 'passo'" class="flex-1 overflow-y-auto">
                    <template x-if="sel">
                        <div class="p-4 space-y-5">
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label class="{{ $rotulo }}">Nome do passo</label>
                                    <input type="text" :value="sel.key" @change="renameStep(steps.indexOf(sel), $event.target.value); $event.target.value = sel.key" class="{{ $campo }} font-mono">
                                </div>
                                <button type="button" @click="focusStep(sel)" class="{{ $botaoSec }} !px-2 h-[38px]" title="Mostrar no quadro">◎</button>
                                <button type="button" @click="duplicateStep(steps.indexOf(sel))" class="{{ $botaoSec }} !px-2 h-[38px]" title="Duplicar (Ctrl+D)">⧉</button>
                                <button type="button" @click="removeStep(steps.indexOf(sel))" class="{{ $botaoSec }} !px-2 h-[38px] !text-danger" title="Apagar (Delete)">✕</button>
                            </div>
                            <button type="button" x-show="sel.key !== settings.start" @click="settings.start = sel.key" class="text-xs text-grena-ink hover:underline">Começar o fluxo por este passo</button>

                            {{-- 1. O bot diz --}}
                            <div class="space-y-3">
                                <h3 class="{{ $secao }}">1. O bot diz</h3>
                                <select x-model="sel.say.type" class="{{ $campo }}">
                                    <option value="">Nada</option>
                                    <option value="text">Uma mensagem de texto</option>
                                    <option value="menu">Um menu numerado (texto + opções)</option>
                                    <option value="template">Um template da Poli (lista ou botões)</option>
                                </select>

                                <div x-show="sel.say.type === 'text' || sel.say.type === 'menu'">
                                    <textarea rows="4" x-model="sel.say.text" class="{{ $campo }}" :placeholder="sel.say.type === 'menu' ? 'Escolha uma opção:' : 'Olá, {contato}!'"></textarea>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        <span class="text-[11px] text-ink-3 mr-1">Inserir:</span>
                                        <template x-for="v in variables()">
                                            <button type="button" @click="sel.say.text = (sel.say.text || '') + '{' + v + '}'" class="px-1.5 py-0.5 rounded bg-subtle text-[11px] font-mono text-ink-2" x-text="'{' + v + '}'"></button>
                                        </template>
                                    </div>
                                    <p class="text-[11px] text-ink-3 mt-1">*negrito* e _itálico_ funcionam como no WhatsApp.</p>
                                </div>

                                <div x-show="sel.say.type === 'template'" class="space-y-2">
                                    <div class="flex gap-2">
                                        <select x-model="sel.say.template_uuid" class="{{ $campo }}">
                                            <option value="">— escolha o template —</option>
                                            <template x-for="t in templates"><option :value="t.uuid" x-text="t.key + ' [' + t.type + ']'" :selected="t.uuid === sel.say.template_uuid"></option></template>
                                        </select>
                                        <button type="button" @click="loadPoli(true)" class="{{ $botaoSec }}" title="Recarregar da Poli">↻</button>
                                    </div>
                                    <p x-show="poliError" x-text="poliError" class="text-xs text-danger"></p>
                                    <template x-if="templateById(sel.say.template_uuid)">
                                        <div class="rounded-lg bg-subtle border border-line p-3 text-xs text-ink-2 whitespace-pre-line">
                                            <span x-text="templateById(sel.say.template_uuid).body"></span>
                                            <template x-if="templateById(sel.say.template_uuid).options.length">
                                                <div class="mt-2">
                                                    <button type="button" @click="useTemplateOptions(sel)" class="text-grena-ink font-semibold hover:underline">
                                                        Usar as <span x-text="templateById(sel.say.template_uuid).options.length"></span> opções deste template como respostas aceitas
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <div>
                                        <label class="{{ $rotulo }}">Variáveis do template, na ordem (opcional)</label>
                                        <template x-for="(p, pi) in sel.say.params">
                                            <div class="flex gap-2 mb-1">
                                                <input type="text" x-model="sel.say.params[pi]" class="{{ $campo }}" placeholder="{contato}">
                                                <button type="button" @click="sel.say.params.splice(pi, 1)" class="text-danger text-xs">✕</button>
                                            </div>
                                        </template>
                                        <button type="button" @click="sel.say.params.push('')" class="text-xs text-grena-ink hover:underline">+ variável</button>
                                    </div>
                                </div>
                            </div>

                            {{-- 2. O bot espera --}}
                            <div class="space-y-3 border-t border-line pt-4">
                                <h3 class="{{ $secao }}">2. O bot espera como resposta</h3>
                                <select x-model="sel.expect.type" class="{{ $campo }}">
                                    <option value="">Nada — segue direto</option>
                                    <option value="option">Uma das opções</option>
                                    <option value="text">Texto livre</option>
                                    <option value="plate">Placa de veículo</option>
                                    <option value="date">Data</option>
                                    <option value="number">Número</option>
                                    <option value="yes_no">Sim ou não</option>
                                    <option value="image">Imagem (foto ou print)</option>
                                    <option value="any">Qualquer coisa</option>
                                </select>

                                <div x-show="sel.expect.type === 'text'" class="grid grid-cols-2 gap-3">
                                    <div><label class="{{ $rotulo }}">Mín. caracteres</label><input type="number" min="1" x-model="sel.expect.min" class="{{ $campo }}"></div>
                                    <div><label class="{{ $rotulo }}">Máx. caracteres</label><input type="number" min="1" x-model="sel.expect.max" class="{{ $campo }}"></div>
                                    <div class="col-span-2">
                                        <label class="{{ $rotulo }}">Formato (expressão regular, opcional)</label>
                                        <input type="text" x-model="sel.expect.pattern" class="{{ $campo }} font-mono" placeholder="^[0-9]+$">
                                    </div>
                                </div>
                                <div x-show="sel.expect.type === 'number'" class="grid grid-cols-2 gap-3">
                                    <div><label class="{{ $rotulo }}">Mínimo</label><input type="number" x-model="sel.expect.min" class="{{ $campo }}"></div>
                                    <div><label class="{{ $rotulo }}">Máximo</label><input type="number" x-model="sel.expect.max" class="{{ $campo }}"></div>
                                </div>
                                <div x-show="sel.expect.type === 'date'" class="flex gap-6 text-sm text-ink">
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" x-model="sel.expect.past_only" class="rounded border-line-strong"> Só passadas</label>
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" x-model="sel.expect.future_only" class="rounded border-line-strong"> Só futuras</label>
                                </div>

                                {{-- Opções: cada uma vira uma saída do bloco no quadro --}}
                                <div x-show="hasOptions(sel)" class="space-y-2">
                                    <p class="text-xs text-ink-2">Cada opção é uma saída do bloco. Vale o número, o texto (tocado ou digitado) ou um apelido.</p>
                                    <template x-for="(op, oi) in sel.options">
                                        <div class="rounded-lg border border-line p-2.5 space-y-2">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-grena-ink w-5" x-text="(oi + 1) + '.'"></span>
                                                <input type="text" x-model="op.label" class="{{ $campo }} !py-1" placeholder="Rótulo da opção">
                                                <button type="button" @click="moveOption(sel, oi, -1)" :disabled="oi === 0" class="text-ink-3 text-xs disabled:opacity-30" title="Subir">↑</button>
                                                <button type="button" @click="sel.options.splice(oi, 1)" class="text-danger text-xs" title="Remover opção">✕</button>
                                            </div>
                                            <input type="text" x-model="op.description" class="{{ $campo }} !py-1" placeholder="Descrição (opcional)">
                                            <input type="text" x-model="op.aliases" class="{{ $campo }} !py-1" placeholder="Apelidos: uber, 99">
                                            <div class="grid grid-cols-2 gap-2">
                                                <select x-model="op.next" class="{{ $campo }} !py-1" title="Vai para">
                                                    <option value="">→ (saída "sem destino")</option>
                                                    <template x-for="k in stepKeys()"><option :value="k" x-text="'→ ' + k" :selected="k === op.next"></option></template>
                                                </select>
                                                <input type="text" x-model="op.value" class="{{ $campo }} !py-1" placeholder="Valor gravado" title="O que fica guardado em vez do rótulo (opcional)">
                                            </div>
                                        </div>
                                    </template>
                                    <button type="button" @click="sel.options.push({label: '', description: '', aliases: '', next: '', value: ''})" class="text-xs text-grena-ink hover:underline">+ opção</button>
                                </div>

                                <div x-show="sel.expect.type" class="space-y-3">
                                    <div>
                                        <label class="{{ $rotulo }}">Guardar a resposta como</label>
                                        <input type="text" x-model="sel.save_as" class="{{ $campo }} font-mono" placeholder="placa">
                                        <p class="text-[11px] text-ink-3 mt-1">Vira a variável <span class="font-mono" x-text="'{' + (sel.save_as || 'nome') + '}'"></span> nos passos seguintes.</p>
                                    </div>
                                    <div>
                                        <label class="{{ $rotulo }}">Se a resposta não servir, o bot responde</label>
                                        <textarea rows="2" x-model="sel.invalid" class="{{ $campo }}" :placeholder="invalidDefaults[sel.expect.type] || invalidDefaults.text"></textarea>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 items-start">
                                        <div>
                                            <label class="{{ $rotulo }}">Tentativas neste passo</label>
                                            <input type="number" min="1" max="10" x-model="sel.max_attempts" class="{{ $campo }}" :placeholder="'padrão: ' + settings.max_attempts" :disabled="sel.optional">
                                        </div>
                                        <label class="inline-flex items-center gap-2 text-sm text-ink mt-6">
                                            <input type="checkbox" x-model="sel.optional" class="rounded border-line-strong">
                                            Pergunta opcional
                                        </label>
                                    </div>
                                    <p x-show="sel.optional" class="text-[11px] text-ink-3">Sem resposta, a conversa fecha em silêncio no prazo do fluxo. Resposta fora das opções encerra o fluxo e recomeça pelo menu, sem "não entendi".</p>
                                </div>
                            </div>

                            {{-- 3. Depois --}}
                            <div class="space-y-3 border-t border-line pt-4">
                                <h3 class="{{ $secao }}">3. Depois</h3>
                                <div>
                                    <label class="{{ $rotulo }}">Ação</label>
                                    <select x-model="sel.action.type" class="{{ $campo }}">
                                        <option value="">Nenhuma</option>
                                        <option value="handoff">Passar para um time (atendente)</option>
                                        <option value="close">Encerrar o atendimento</option>
                                        <option value="uber_request">Registrar pedido de carro de aplicativo</option>
                                        <option value="goto_flow">Continuar em outro fluxo</option>
                                    </select>
                                </div>
                                <div x-show="sel.action.type === 'handoff'">
                                    <label class="{{ $rotulo }}">Time</label>
                                    <select x-model="sel.action.team_uuid" class="{{ $campo }}">
                                        <option value="">Time padrão do servidor</option>
                                        <template x-for="t in teams"><option :value="t.uuid" x-text="t.name" :selected="t.uuid === sel.action.team_uuid"></option></template>
                                    </select>
                                </div>
                                <div x-show="sel.action.type === 'goto_flow'">
                                    <label class="{{ $rotulo }}">Fluxo</label>
                                    <select x-model="sel.action.flow" class="{{ $campo }}">
                                        <option value="">— escolha —</option>
                                        <template x-for="f in otherFlows"><option :value="f.slug" x-text="f.name + (f.active ? '' : ' (rascunho)')" :selected="f.slug === sel.action.flow"></option></template>
                                    </select>
                                </div>
                                <p x-show="sel.action.type === 'uber_request'" class="text-xs text-warn bg-warn-soft rounded p-2">
                                    Usa as respostas guardadas como <span class="font-mono">matricula</span>, <span class="font-mono">nome</span>,
                                    <span class="font-mono">local</span>, <span class="font-mono">placa</span> e <span class="font-mono">print</span>.
                                    Só cria o pedido de verdade com o bot no ar (modo on).
                                </p>
                                <p x-show="isTerminal(sel)" class="text-xs text-ink-2">A conversa sai do bot aqui — o bloco não tem saída.</p>
                                <div x-show="!isTerminal(sel)">
                                    <label class="{{ $rotulo }}" x-text="hasOptions(sel) ? 'Opções sem destino vão para' : 'Próximo passo'"></label>
                                    <select x-model="sel.next" class="{{ $campo }}">
                                        <option value="">Fim da conversa</option>
                                        <template x-for="k in stepKeys()"><option :value="k" x-text="k" :selected="k === sel.next"></option></template>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </template>
                    <p x-show="!sel" class="p-6 text-sm text-ink-3 text-center">Clique num bloco do quadro para editar.</p>
                </div>

                {{-- ---------- Simulador ---------- --}}
                <div x-show="panel === 'sim'" class="flex-1 flex flex-col min-h-0">
                    <div class="px-4 py-2 border-b border-line flex items-center gap-2">
                        <h2 class="font-bold text-ink flex-1">Simulador</h2>
                        <button type="button" @click="simStart()" :disabled="!flow.id || sim.busy" class="{{ $botaoSec }}" title="Começar por este fluxo (versão salva)">▶ Testar este</button>
                        <button type="button" @click="simReset()" :disabled="sim.busy" class="{{ $botaoSec }}">Reiniciar</button>
                    </div>
                    <p class="px-4 pt-2 text-[11px] text-ink-3">Nada é enviado. Testa a versão <strong>salva</strong><span x-show="dirty" class="text-warn"> — salve para testar as mudanças</span>. O passo atual acende em verde no quadro.</p>
                    <div class="flex-1 overflow-y-auto px-4 py-3 space-y-2" x-ref="chat">
                        <template x-for="(m, mi) in sim.messages" :key="mi">
                            <div :class="m.from === 'me' ? 'flex justify-end' : 'flex justify-start'">
                                <div class="max-w-[85%] rounded-xl px-3 py-2 text-sm whitespace-pre-line"
                                     :class="m.from === 'me' ? 'bg-ok-soft text-ok' : (m.kind === 'ACTION' ? 'bg-warn-soft text-warn italic text-xs' : 'bg-subtle text-ink')">
                                    <span x-text="m.text"></span>
                                    <template x-if="m.options && m.options.length">
                                        <div class="mt-2 flex flex-col gap-1">
                                            <template x-for="o in m.options">
                                                <button type="button" @click="simTap(o)" class="text-left px-2 py-1 rounded bg-surface border border-line text-xs text-grena-ink hover:bg-grena-tint" x-text="o.label"></button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <p x-show="!sim.messages.length" class="text-xs text-ink-3 text-center mt-10">Mande uma mensagem como se fosse o associado.</p>
                    </div>
                    <div class="px-4 py-2 text-[11px] text-ink-2 border-t border-line" x-show="sim.session">
                        <span x-text="sessionLabel()"></span>
                    </div>
                    <form class="p-3 border-t border-line flex gap-2" @submit.prevent="simSend(sim.input)">
                        <input type="text" x-model="sim.input" class="{{ $campo }}" placeholder="Mensagem…" :disabled="sim.busy">
                        <button type="button" @click="simImage()" :disabled="sim.busy" class="{{ $botaoSec }}" title="Enviar uma imagem">📷</button>
                        <button type="submit" :disabled="sim.busy || !sim.input.trim()" class="{{ $botaoSec }}">Enviar</button>
                    </form>
                </div>
            </aside>
        </div>
    </div>

    <x-slot name="js">
        <script>
            function botFlowEditor(config) {
                let uid = 0;
                const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
                const DAYS = [
                    {n: '1', label: 'Segunda'}, {n: '2', label: 'Terça'}, {n: '3', label: 'Quarta'}, {n: '4', label: 'Quinta'},
                    {n: '5', label: 'Sexta'}, {n: '6', label: 'Sábado'}, {n: '7', label: 'Domingo'},
                ];

                // Geometria fixa do bloco: as ligações saem de posições calculadas, sem medir o DOM.
                const W = 264, HEAD = 40, BODY = 56, ROW = 26, PAD = 4;
                const ENTRY_W = 150, ENTRY_H = 38;
                const GRID = 10;

                // Aparência de cada tipo de bloco. O tipo não é gravado: sai do conteúdo do passo (kind()).
                const KINDS = {
                    mensagem: {label: 'Mensagem', icon: '💬', color: '#0ea5e9', hint: 'O bot fala e segue'},
                    pergunta: {label: 'Pergunta', icon: '✍', color: '#f59e0b', hint: 'Espera texto, placa, data, foto…'},
                    menu: {label: 'Menu', icon: '☰', color: '#6366f1', hint: 'Opções numeradas, cada uma com seu caminho'},
                    template: {label: 'Template Poli', icon: '📋', color: '#8b5cf6', hint: 'Lista ou botões da Poli'},
                    handoff: {label: 'Passar p/ time', icon: '🎧', color: '#10b981', hint: 'Transfere para um atendente'},
                    uber_request: {label: 'Pedido de carro', icon: '🚗', color: '#f97316', hint: 'Registra o carro de aplicativo'},
                    goto_flow: {label: 'Outro fluxo', icon: '↪', color: '#14b8a6', hint: 'Continua em outro fluxo'},
                    close: {label: 'Encerrar', icon: '⏹', color: '#64748b', hint: 'Encerra o atendimento'},
                };

                // O passo que cada bloco da paleta cria.
                const BLOCKS = {
                    mensagem: {base: 'mensagem', def: {say: {type: 'text', text: ''}}},
                    pergunta: {base: 'pergunta', def: {say: {type: 'text', text: ''}, expect: {type: 'text'}}},
                    menu: {base: 'menu', def: {say: {type: 'menu', text: 'Escolha uma opção:'}, expect: {type: 'option'}, options: [{label: 'Opção 1'}, {label: 'Opção 2'}]}},
                    template: {base: 'template', def: {say: {type: 'template', template_uuid: ''}, expect: {type: 'option'}}},
                    handoff: {base: 'transferir', def: {action: {type: 'handoff'}}},
                    uber_request: {base: 'pedido_carro', def: {action: {type: 'uber_request'}}},
                    goto_flow: {base: 'outro_fluxo', def: {action: {type: 'goto_flow', flow: ''}}},
                    close: {base: 'encerrar', def: {action: {type: 'close'}}},
                };

                const STEP_FIELDS = ['say', 'expect', 'options', 'save_as', 'invalid', 'optional', 'max_attempts', 'action', 'next'];
                const TERMINAL = ['handoff', 'close', 'goto_flow'];
                const ENTRY_START = ':inicio', ENTRY_HOURS = ':fora';
                const snap = v => Math.round(v / GRID) * GRID;
                const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
                const curve = (x1, y1, x2, y2) => {
                    const dx = x2 > x1 + 40 ? Math.max(50, (x2 - x1) / 2) : 140;
                    return {x1, y1, c1x: x1 + dx, c1y: y1, c2x: x2 - dx, c2y: y2, x2, y2};
                };
                const pathOf = c => `M${c.x1},${c.y1} C${c.c1x},${c.c1y} ${c.c2x},${c.c2y} ${c.x2},${c.y2}`;

                return {
                    W, HEAD, BODY, ROW, ENTRY_W, ENTRY_H, KINDS,
                    urls: config.urls,
                    invalidDefaults: config.invalidDefaults || {},
                    otherFlows: config.otherFlows || [],
                    versions: config.versions || [],
                    weekDays: DAYS,
                    flow: {id: config.flow.id, name: config.flow.name, slug: config.flow.slug, active: !!config.flow.active},
                    slugTouched: false,
                    triggerMode: 'any',
                    settings: {},
                    steps: [],
                    entryPos: {},
                    templates: [],
                    teams: [],
                    poliError: '',
                    errors: [],
                    message: '',
                    saving: false,
                    dirty: false,
                    sim: {messages: [], input: '', busy: false, session: null, visited: []},

                    // estado do quadro
                    view: {x: 40, y: 40, z: 1},
                    panel: 'fluxo',
                    selectedUid: null,
                    selectedEdge: null,
                    drag: null,
                    quick: null,

                    init() {
                        this.load(config.flow.definition);
                        this.loadPoli(false);
                        this.$nextTick(() => {
                            this.fit();
                            this.$watch(() => JSON.stringify([this.flow, this.settings, this.steps, this.triggerMode, this.entryPos]), () => { this.dirty = true; this.message = ''; });
                        });
                        window.addEventListener('beforeunload', (e) => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
                    },

                    get sel() { return this.steps.find(s => s._uid === this.selectedUid) || null; },

                    /* ---------- definição <-> tela ---------- */

                    load(def) {
                        def = def || {};
                        const t = def.triggers || {};
                        this.triggerMode = t.any ? 'any' : (t.redirect ? 'redirect' : (t.only_goto ? 'goto' : 'texts'));
                        const h = def.hours || {};
                        const days = {};
                        DAYS.forEach(d => {
                            const iv = (h.days || {})[d.n];
                            days[d.n] = {open: Array.isArray(iv), from: iv ? iv[0] : '07:00', to: iv ? iv[1] : '18:00'};
                        });
                        const maximo = def.on_max_attempts || {};
                        this.settings = {
                            start: def.start || '',
                            triggerTexts: (t.texts || []).join(', '),
                            timeout_minutes: def.timeout_minutes ?? 15,
                            max_attempts: def.max_attempts ?? 3,
                            on_max_team: maximo.type === 'handoff' ? (maximo.team_uuid || '') : '',
                            // on_max_attempts que a tela não edita (goto_flow) volta intacto se ninguém escolher um time.
                            on_max_raw: maximo.type && maximo.type !== 'handoff' ? maximo : null,
                            hours: {
                                enabled: !!h.enabled,
                                days,
                                holiday: {from: (h.holiday || ['07:00'])[0], to: (h.holiday || [null, '18:00'])[1]},
                                holidays: (h.holidays || []).map(d => d.split('-').reverse().join('/')).join('\n'),
                                out_of_hours: h.out_of_hours || '',
                            },
                        };
                        const layout = def.layout || {};
                        this.steps = Object.entries(def.steps || {}).map(([key, s]) => this.stepFromDef(key, s, layout[key]));
                        if (!this.settings.start && this.steps.length) this.settings.start = this.steps[0].key;
                        this.entryPos = {
                            [ENTRY_START]: layout[ENTRY_START] ? {...layout[ENTRY_START]} : null,
                            [ENTRY_HOURS]: layout[ENTRY_HOURS] ? {...layout[ENTRY_HOURS]} : null,
                        };
                        this.selectedUid = null;
                        this.selectedEdge = null;

                        if (!def.layout || this.steps.every(s => !s.pos)) {
                            this.autoLayout();
                        } else {
                            this.placeMissing();
                        }
                    },

                    stepFromDef(key, s, pos) {
                        const say = s.say || {}, ex = s.expect || {}, ac = s.action || {};
                        const extra = {};
                        Object.keys(s).forEach(k => { if (!STEP_FIELDS.includes(k)) extra[k] = s[k]; });
                        return {
                            _uid: ++uid, key,
                            pos: pos && Number.isFinite(pos.x) && Number.isFinite(pos.y) ? {x: pos.x, y: pos.y} : null,
                            say: {type: say.type || '', text: say.text || '', template_uuid: say.template_uuid || '', params: [...(say.params || [])]},
                            expect: {type: ex.type || '', min: ex.min ?? '', max: ex.max ?? '', pattern: ex.pattern || '', past_only: !!ex.past_only, future_only: !!ex.future_only},
                            options: (s.options || []).map(o => ({label: o.label || '', description: o.description || '', aliases: (o.aliases || []).join(', '), next: o.next || '', value: o.value || ''})),
                            save_as: s.save_as || '', invalid: s.invalid || '', max_attempts: s.max_attempts ?? '',
                            optional: !!s.optional,
                            action: {type: ac.type || '', team_uuid: ac.team_uuid || '', flow: ac.flow || ''},
                            next: s.next || '',
                            extra,
                        };
                    },

                    toDefinition() {
                        const num = v => (v === '' || v === null || v === undefined) ? undefined : Number(v);
                        const steps = {};
                        const layout = {};
                        this.steps.forEach(s => {
                            const out = {...s.extra};
                            if (s.say.type) {
                                out.say = {type: s.say.type};
                                if (s.say.type === 'template') {
                                    out.say.template_uuid = s.say.template_uuid;
                                    const params = s.say.params.filter(p => p.trim() !== '');
                                    if (params.length) out.say.params = params;
                                } else {
                                    out.say.text = s.say.text;
                                }
                            }
                            if (s.expect.type) {
                                out.expect = {type: s.expect.type};
                                if (s.expect.type === 'text') Object.assign(out.expect, {min: num(s.expect.min), max: num(s.expect.max), pattern: s.expect.pattern || undefined});
                                if (s.expect.type === 'number') Object.assign(out.expect, {min: num(s.expect.min), max: num(s.expect.max)});
                                if (s.expect.type === 'date') Object.assign(out.expect, {past_only: s.expect.past_only || undefined, future_only: s.expect.future_only || undefined});
                                if (s.save_as) out.save_as = s.save_as.trim();
                                if (s.invalid) out.invalid = s.invalid;
                                if (num(s.max_attempts)) out.max_attempts = num(s.max_attempts);
                                if (s.optional) out.optional = true;
                            }
                            if (this.hasOptions(s)) {
                                out.options = s.options.filter(o => o.label.trim() !== '').map(o => ({
                                    label: o.label.trim(),
                                    description: o.description.trim() || undefined,
                                    aliases: o.aliases.split(',').map(a => a.trim()).filter(Boolean),
                                    next: o.next || undefined,
                                    value: (o.value || '').trim() || undefined,
                                }));
                            }
                            if (s.action.type) {
                                out.action = {type: s.action.type};
                                if (s.action.type === 'handoff' && s.action.team_uuid) out.action.team_uuid = s.action.team_uuid;
                                if (s.action.type === 'goto_flow') out.action.flow = s.action.flow;
                            }
                            if (s.next && !this.isTerminal(s)) out.next = s.next;
                            steps[s.key] = out;
                            if (s.pos) layout[s.key] = {x: Math.round(s.pos.x), y: Math.round(s.pos.y)};
                        });
                        Object.entries(this.entryPos).forEach(([k, p]) => { if (p) layout[k] = {x: Math.round(p.x), y: Math.round(p.y)}; });

                        const h = this.settings.hours;
                        const def = {
                            start: this.settings.start,
                            triggers: {
                                any: this.triggerMode === 'any',
                                texts: this.triggerMode === 'texts' ? this.settings.triggerTexts.split(',').map(t => t.trim()).filter(Boolean) : [],
                                only_goto: this.triggerMode === 'goto',
                                redirect: this.triggerMode === 'redirect' || undefined,
                            },
                            timeout_minutes: num(this.settings.timeout_minutes),
                            max_attempts: num(this.settings.max_attempts),
                            steps,
                            layout,
                        };
                        if (this.settings.on_max_team) def.on_max_attempts = {type: 'handoff', team_uuid: this.settings.on_max_team};
                        else if (this.settings.on_max_raw) def.on_max_attempts = this.settings.on_max_raw;
                        if (h.enabled) {
                            const days = {};
                            DAYS.forEach(d => { if (h.days[d.n].open) days[d.n] = [h.days[d.n].from, h.days[d.n].to]; });
                            def.hours = {
                                enabled: true,
                                days,
                                holiday: [h.holiday.from, h.holiday.to],
                                holidays: h.holidays.split(/\s+/).map(x => x.trim()).filter(Boolean).map(x => {
                                    const m = x.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                                    return m ? `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}` : x;
                                }),
                                out_of_hours: h.out_of_hours,
                            };
                        }
                        return def;
                    },

                    /* ---------- leitura de um passo ---------- */

                    hasOptions(s) { return s.expect.type === 'option' || s.say.type === 'menu'; },
                    isTerminal(s) { return TERMINAL.includes(s.action.type); },

                    kind(s) {
                        if (s.action.type && KINDS[s.action.type]) return s.action.type;
                        if (s.say.type === 'template') return 'template';
                        if (this.hasOptions(s)) return 'menu';
                        if (s.expect.type) return 'pergunta';
                        return 'mensagem';
                    },

                    // Saídas do bloco, na ordem em que aparecem. `to` vazio = fim da conversa.
                    ports(s) {
                        if (this.isTerminal(s)) return [];
                        if (this.hasOptions(s)) {
                            const out = s.options.map((o, i) => ({id: 'o' + i, label: `${i + 1}. ${o.label || '(sem rótulo)'}`, to: o.next, opt: true}));
                            const todasComDestino = s.options.length && s.options.every(o => o.next);
                            out.push({id: 'next', label: s.options.length ? 'Opção sem destino' : 'Em seguida', to: s.next, dim: todasComDestino && !s.next});
                            return out;
                        }
                        return [{id: 'next', label: s.expect.type ? 'Resposta válida' : 'Em seguida', to: s.next}];
                    },

                    nodeHeight(s) { return HEAD + BODY + Math.max(1, this.ports(s).length) * ROW + PAD; },

                    meta(s) {
                        const espera = {option: 'escolher opção', text: 'texto', plate: 'placa', date: 'data', number: 'número', yes_no: 'sim/não', image: 'imagem', any: 'qualquer coisa'}[s.expect.type];
                        const partes = [];
                        if (espera) partes.push('espera ' + espera);
                        if (s.save_as) partes.push('→ {' + s.save_as + '}');
                        if (s.optional) partes.push('opcional');
                        if (!partes.length) partes.push(KINDS[this.kind(s)].label);
                        return partes.join(' · ');
                    },

                    previewText(s) {
                        if (s.say.type === 'template') {
                            const t = this.templateById(s.say.template_uuid);
                            return t ? `[${t.key}] ${t.body || ''}` : (s.say.template_uuid ? 'Template ' + s.say.template_uuid.slice(0, 8) + '…' : '');
                        }
                        if (s.say.type) return s.say.text;
                        if (s.action.type === 'uber_request') return 'Registra o pedido com matrícula, nome, local, placa e print.';
                        return '';
                    },

                    terminalLabel(s) {
                        if (s.action.type === 'handoff') {
                            const t = this.teams.find(t => t.uuid === s.action.team_uuid);
                            return 'Passa para ' + (t ? t.name : (s.action.team_uuid ? 'o time escolhido' : 'o time padrão'));
                        }
                        if (s.action.type === 'close') return 'Encerra o atendimento';
                        const f = this.otherFlows.find(f => f.slug === s.action.flow);
                        return 'Continua em ' + (f ? f.name : (s.action.flow || '(escolha o fluxo)'));
                    },

                    nodeClass(s) {
                        const c = [];
                        const atual = this.simStep();
                        if (atual === s.key) c.push('ring-4 ring-ok/40 border-ok');
                        else if (this.selectedUid === s._uid) c.push('ring-2 ring-grena-tint border-grena');
                        else if (this.errorKeys().has(s.key)) c.push('border-danger ring-2 ring-danger/30');
                        else if (this.sim.visited.includes(s.key)) c.push('border-ok ring-1 ring-ok/40');
                        else c.push('border-line');
                        if (!this.isReachable(s.key)) c.push('opacity-80');
                        return c.join(' ');
                    },

                    /* ---------- passos ---------- */

                    stepKeys() { return this.steps.map(s => s.key); },
                    byKey(key) { return this.steps.find(s => s.key === key) || null; },

                    newKey(base) {
                        let k = base, n = 2;
                        while (this.stepKeys().includes(k)) k = `${base}_${n++}`;
                        return k;
                    },

                    addBlock(tipo, pos) {
                        const b = BLOCKS[tipo];
                        if (!b) return null;
                        const s = this.stepFromDef(this.newKey(b.base), JSON.parse(JSON.stringify(b.def)), {x: snap(pos.x), y: snap(pos.y)});
                        this.steps.push(s);
                        if (!this.settings.start) this.settings.start = s.key;
                        this.select(s);
                        this.panel = 'passo';
                        return s;
                    },

                    addBlockAtCenter(tipo) {
                        const r = this.$refs.canvas.getBoundingClientRect();
                        const c = this.toWorld(r.left + r.width / 2, r.top + r.height / 2);
                        // Blocos criados em sequência não se empilham exatamente no mesmo lugar.
                        const deslocamento = (this.steps.length % 5) * 24;
                        this.addBlock(tipo, {x: c.x - W / 2 + deslocamento, y: c.y - 60 + deslocamento});
                    },

                    duplicateStep(i) {
                        if (i < 0) return;
                        const copia = JSON.parse(JSON.stringify(this.steps[i]));
                        copia._uid = ++uid;
                        copia.key = this.newKey(this.steps[i].key);
                        copia.pos = {x: (copia.pos?.x ?? 0) + 30, y: (copia.pos?.y ?? 0) + 30};
                        this.steps.splice(i + 1, 0, copia);
                        this.select(copia);
                    },

                    removeStep(i) {
                        if (i < 0) return;
                        const key = this.steps[i].key;
                        const usado = this.steps.filter(s => s.next === key || s.options.some(o => o.next === key)).map(s => s.key);
                        const aviso = usado.length ? `\n\nOs passos ${usado.join(', ')} apontam para ele e vão passar a terminar a conversa.` : '';
                        if (!confirm(`Apagar o passo "${key}"?${aviso}`)) return;
                        this.steps.splice(i, 1);
                        this.replaceRef(key, '');
                        this.selectedUid = null;
                        this.selectedEdge = null;
                        if (this.panel === 'passo') this.panel = 'fluxo';
                    },

                    renameStep(i, novo) {
                        novo = (novo || '').trim().toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
                        const antigo = this.steps[i].key;
                        if (!novo || novo === antigo) return;
                        if (this.stepKeys().includes(novo)) { alert(`Já existe um passo "${novo}".`); return; }
                        this.steps[i].key = novo;
                        this.replaceRef(antigo, novo);
                    },

                    replaceRef(antigo, novo) {
                        this.steps.forEach(s => {
                            if (s.next === antigo) s.next = novo;
                            s.options.forEach(o => { if (o.next === antigo) o.next = novo; });
                        });
                        if (this.settings.start === antigo) this.settings.start = novo || (this.steps[0]?.key ?? '');
                        if (this.settings.hours.out_of_hours === antigo) this.settings.hours.out_of_hours = novo;
                    },

                    moveOption(s, i, dir) {
                        const j = i + dir;
                        if (j < 0 || j >= s.options.length) return;
                        const [o] = s.options.splice(i, 1);
                        s.options.splice(j, 0, o);
                    },

                    variables() {
                        return ['contato', ...new Set(this.steps.map(s => s.save_as).filter(Boolean))];
                    },

                    select(s) {
                        this.selectedUid = s ? s._uid : null;
                        this.selectedEdge = null;
                    },

                    /* ---------- ligações ---------- */

                    entries() {
                        const lista = [{id: ENTRY_START, label: 'Início', icon: '▶', color: '#1f2937', to: this.settings.start, hint: 'Por onde a conversa começa'}];
                        if (this.settings.hours.enabled) {
                            lista.push({id: ENTRY_HOURS, label: 'Fora do horário', icon: '☾', color: '#7c3aed', to: this.settings.hours.out_of_hours, hint: 'Por onde começa quando está fechado'});
                        }
                        lista.forEach(en => {
                            if (!this.entryPos[en.id]) this.entryPos[en.id] = this.defaultEntryPos(en.id);
                            en.pos = this.entryPos[en.id];
                        });
                        return lista;
                    },

                    defaultEntryPos(id) {
                        const alvo = this.byKey(id === ENTRY_START ? this.settings.start : this.settings.hours.out_of_hours);
                        const minX = Math.min(...this.steps.map(s => s.pos?.x ?? 0), 200);
                        const y = alvo?.pos ? alvo.pos.y + HEAD / 2 - ENTRY_H / 2 : (id === ENTRY_START ? 40 : 120);
                        return {x: snap(minX - ENTRY_W - 90), y: snap(y) + (id === ENTRY_HOURS && !alvo ? 60 : 0)};
                    },

                    edgeList() {
                        const lista = [];
                        const ligar = (id, x1, y1, to, tipo) => {
                            const alvo = this.byKey(to);
                            if (!alvo || !alvo.pos) return;
                            lista.push({id, tipo, c: curve(x1, y1, alvo.pos.x, alvo.pos.y + HEAD / 2)});
                        };
                        this.entries().forEach(en => ligar(en.id, en.pos.x + ENTRY_W, en.pos.y + ENTRY_H / 2, en.to, 'entrada'));
                        this.steps.forEach(s => {
                            if (!s.pos) return;
                            this.ports(s).forEach((p, i) => {
                                if (p.dim) return;
                                ligar(`${s._uid}|${p.id}`, s.pos.x + W, s.pos.y + HEAD + BODY + i * ROW + ROW / 2, p.to, p.opt ? 'opcao' : 'normal');
                            });
                        });
                        return lista;
                    },

                    edgesSvg() {
                        const cor = {normal: '#94a3b8', opcao: '#818cf8', entrada: '#1f2937'};
                        const seta = {normal: 'pb-seta', opcao: 'pb-seta-opcao', entrada: 'pb-seta-entrada'};
                        return this.edgeList().map(e => {
                            const d = pathOf(e.c);
                            const sel = e.id === this.selectedEdge;
                            return `<path d="${d}" fill="none" stroke="${sel ? '#10b981' : cor[e.tipo]}" stroke-width="${sel ? 3 : 2}" marker-end="url(#${sel ? 'pb-seta-sel' : seta[e.tipo]})"/>`
                                + `<path d="${d}" fill="none" stroke="transparent" stroke-width="14" data-edge="${e.id}" style="pointer-events:stroke;cursor:pointer"/>`;
                        }).join('');
                    },

                    selectedEdgeMid() {
                        if (!this.selectedEdge || this.selectedEdge === ENTRY_START) return null;
                        const e = this.edgeList().find(e => e.id === this.selectedEdge);
                        if (!e) return null;
                        const c = e.c;
                        return {x: (c.x1 + 3 * c.c1x + 3 * c.c2x + c.x2) / 8, y: (c.y1 + 3 * c.c1y + 3 * c.c2y + c.y2) / 8};
                    },

                    // Liga a saída `porta` do dono (passo pelo _uid, ou uma entrada) ao passo `key` ('' desliga).
                    setTarget(dono, porta, key) {
                        if (dono === ENTRY_START) { if (key) this.settings.start = key; return; }
                        if (dono === ENTRY_HOURS) { this.settings.hours.out_of_hours = key; return; }
                        const s = this.steps.find(s => s._uid === dono);
                        if (!s) return;
                        if (porta === 'next') s.next = key;
                        else s.options[Number(porta.slice(1))].next = key;
                    },

                    removeSelectedEdge() {
                        const id = this.selectedEdge;
                        if (!id || id === ENTRY_START) return;
                        if (id === ENTRY_HOURS) this.setTarget(ENTRY_HOURS, 'next', '');
                        else {
                            const [dono, porta] = id.split('|');
                            this.setTarget(Number(dono), porta, '');
                        }
                        this.selectedEdge = null;
                    },

                    linkPath() {
                        if (!this.drag || this.drag.type !== 'link') return '';
                        const d = this.drag;
                        return pathOf(curve(d.x1, d.y1, d.cur.x, d.cur.y));
                    },

                    /* ---------- quadro: coordenadas, zoom, arrasto ---------- */

                    toWorld(cx, cy) {
                        const r = this.$refs.canvas.getBoundingClientRect();
                        return {x: (cx - r.left - this.view.x) / this.view.z, y: (cy - r.top - this.view.y) / this.view.z};
                    },

                    zoomAt(fator, sx, sy) {
                        const z = clamp(this.view.z * fator, 0.25, 2);
                        this.view.x = sx - (sx - this.view.x) * z / this.view.z;
                        this.view.y = sy - (sy - this.view.y) * z / this.view.z;
                        this.view.z = z;
                    },

                    zoomBy(fator) {
                        const r = this.$refs.canvas.getBoundingClientRect();
                        this.zoomAt(fator, r.width / 2, r.height / 2);
                    },

                    onWheel(e) {
                        const r = this.$refs.canvas.getBoundingClientRect();
                        this.zoomAt(e.deltaY < 0 ? 1.1 : 1 / 1.1, e.clientX - r.left, e.clientY - r.top);
                    },

                    bounds() {
                        const caixas = this.steps.filter(s => s.pos).map(s => [s.pos.x, s.pos.y, s.pos.x + W, s.pos.y + this.nodeHeight(s)]);
                        this.entries().forEach(en => caixas.push([en.pos.x, en.pos.y, en.pos.x + ENTRY_W, en.pos.y + ENTRY_H]));
                        if (!caixas.length) return null;
                        return {
                            x1: Math.min(...caixas.map(b => b[0])), y1: Math.min(...caixas.map(b => b[1])),
                            x2: Math.max(...caixas.map(b => b[2])), y2: Math.max(...caixas.map(b => b[3])),
                        };
                    },

                    fit() {
                        const r = this.$refs.canvas.getBoundingClientRect();
                        const b = this.bounds();
                        if (!b || !r.width) return;
                        const margem = 60;
                        const z = clamp(Math.min((r.width - 2 * margem) / (b.x2 - b.x1), (r.height - 2 * margem) / (b.y2 - b.y1)), 0.3, 1);
                        this.view.z = z;
                        this.view.x = (r.width - (b.x2 - b.x1) * z) / 2 - b.x1 * z;
                        this.view.y = Math.max(margem / 2, (r.height - (b.y2 - b.y1) * z) / 2) - b.y1 * z;
                    },

                    focusStep(s) {
                        if (!s || !s.pos) return;
                        const r = this.$refs.canvas.getBoundingClientRect();
                        this.view.x = r.width / 2 - (s.pos.x + W / 2) * this.view.z;
                        this.view.y = r.height / 2 - (s.pos.y + this.nodeHeight(s) / 2) * this.view.z;
                        this.select(s);
                        this.panel = 'passo';
                    },

                    focusKey(key) { this.focusStep(this.byKey(key)); },

                    canvasDown(e) {
                        if (e.button !== 0 && e.button !== 1) return;
                        this.quick = null;
                        const aresta = e.target.dataset ? e.target.dataset.edge : null;
                        if (aresta) {
                            this.selectedEdge = aresta;
                            this.selectedUid = null;
                            return;
                        }
                        this.drag = {type: 'pan', sx: e.clientX, sy: e.clientY, vx: this.view.x, vy: this.view.y, moved: false};
                    },

                    nodeDown(e, s) {
                        if (e.button !== 0) return;
                        this.quick = null;
                        this.select(s);
                        if (this.panel === 'fluxo') this.panel = 'passo';
                        this.drag = {type: 'node', obj: s.pos, sx: e.clientX, sy: e.clientY, px: s.pos.x, py: s.pos.y, moved: false};
                    },

                    entryDown(e, en) {
                        if (e.button !== 0) return;
                        this.quick = null;
                        this.drag = {type: 'node', obj: this.entryPos[en.id], sx: e.clientX, sy: e.clientY, px: en.pos.x, py: en.pos.y, moved: false};
                    },

                    portDown(e, dono, porta) {
                        if (e.button !== 0) return;
                        this.quick = null;
                        const p = this.toWorld(e.clientX, e.clientY);
                        let x1 = p.x, y1 = p.y;
                        if (dono === ENTRY_START || dono === ENTRY_HOURS) {
                            const pos = this.entryPos[dono];
                            x1 = pos.x + ENTRY_W; y1 = pos.y + ENTRY_H / 2;
                        } else {
                            const s = this.steps.find(s => s._uid === dono);
                            const i = this.ports(s).findIndex(pt => pt.id === porta);
                            x1 = s.pos.x + W; y1 = s.pos.y + HEAD + BODY + i * ROW + ROW / 2;
                        }
                        this.drag = {type: 'link', dono, porta, x1, y1, cur: p, sx: e.clientX, sy: e.clientY, moved: false};
                    },

                    onMove(e) {
                        const d = this.drag;
                        if (!d) return;
                        if (Math.abs(e.clientX - d.sx) + Math.abs(e.clientY - d.sy) > 3) d.moved = true;
                        if (d.type === 'pan') {
                            this.view.x = d.vx + (e.clientX - d.sx);
                            this.view.y = d.vy + (e.clientY - d.sy);
                        } else if (d.type === 'node' && d.moved) {
                            d.obj.x = snap(d.px + (e.clientX - d.sx) / this.view.z);
                            d.obj.y = snap(d.py + (e.clientY - d.sy) / this.view.z);
                        } else if (d.type === 'link') {
                            d.cur = this.toWorld(e.clientX, e.clientY);
                        }
                    },

                    onUp(e) {
                        const d = this.drag;
                        this.drag = null;
                        if (!d) return;
                        if (d.type === 'pan' && !d.moved) {
                            // Clique no fundo: solta a seleção.
                            this.selectedUid = null;
                            this.selectedEdge = null;
                            if (this.panel === 'passo') this.panel = 'fluxo';
                        }
                        if (d.type !== 'link') return;
                        const alvo = document.elementFromPoint(e.clientX, e.clientY)?.closest('[data-node]');
                        if (alvo) {
                            const s = this.steps.find(s => String(s._uid) === alvo.dataset.node);
                            if (s) this.setTarget(d.dono, d.porta, s.key);
                            return;
                        }
                        if (!d.moved) return;
                        if (!this.$refs.canvas.contains(e.target)) return;
                        const r = this.$refs.canvas.getBoundingClientRect();
                        this.quick = {sx: Math.min(e.clientX - r.left, r.width - 232), sy: Math.min(e.clientY - r.top, r.height - 330), world: d.cur, dono: d.dono, porta: d.porta};
                    },

                    quickCreate(tipo) {
                        const q = this.quick;
                        this.quick = null;
                        if (!q) return;
                        const s = this.addBlock(tipo, {x: q.world.x, y: q.world.y - HEAD / 2});
                        if (s) this.setTarget(q.dono, q.porta, s.key);
                    },

                    paletteDrag(e, tipo) {
                        e.dataTransfer.setData('text/plain', 'poli-bot:' + tipo);
                        e.dataTransfer.effectAllowed = 'copy';
                    },

                    paletteDrop(e) {
                        const dado = e.dataTransfer.getData('text/plain') || '';
                        if (!dado.startsWith('poli-bot:')) return;
                        const p = this.toWorld(e.clientX, e.clientY);
                        this.addBlock(dado.slice(9), {x: p.x - W / 2, y: p.y - HEAD / 2});
                    },

                    onKey(e) {
                        const k = e.key.toLowerCase();
                        if ((e.ctrlKey || e.metaKey) && k === 's') { e.preventDefault(); this.save(); return; }
                        const digitando = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable;
                        if (digitando) return;
                        if (k === 'escape') { this.quick = null; this.drag = null; this.selectedEdge = null; this.selectedUid = null; return; }
                        if (k === 'delete' || k === 'backspace') {
                            if (this.selectedEdge) { e.preventDefault(); this.removeSelectedEdge(); }
                            else if (this.sel) { e.preventDefault(); this.removeStep(this.steps.indexOf(this.sel)); }
                            return;
                        }
                        if ((e.ctrlKey || e.metaKey) && k === 'd' && this.sel) { e.preventDefault(); this.duplicateStep(this.steps.indexOf(this.sel)); }
                    },

                    /* ---------- arrumação automática ---------- */

                    // Colunas pela distância até o início (busca em largura), na ordem da conversa.
                    autoLayout() {
                        const nivel = {};
                        const ordem = [];
                        const visitar = (raizes, d0) => {
                            const fila = raizes.map(k => [k, d0]);
                            while (fila.length) {
                                const [k, d] = fila.shift();
                                const s = this.byKey(k);
                                if (!s || k in nivel) continue;
                                nivel[k] = d;
                                ordem.push(k);
                                this.ports(s).forEach(p => { if (p.to && !p.dim) fila.push([p.to, d + 1]); });
                            }
                        };
                        visitar([this.settings.start, this.settings.hours.enabled ? this.settings.hours.out_of_hours : ''].filter(Boolean), 0);
                        this.steps.forEach(s => { if (!(s.key in nivel)) visitar([s.key], 0); });

                        const colunas = {};
                        ordem.forEach(k => (colunas[nivel[k]] = colunas[nivel[k]] || []).push(k));
                        const X0 = 40 + ENTRY_W + 100;
                        Object.entries(colunas).forEach(([d, chaves]) => {
                            let y = 40;
                            chaves.forEach(k => {
                                const s = this.byKey(k);
                                s.pos = {x: X0 + Number(d) * (W + 120), y};
                                y += this.nodeHeight(s) + 40;
                            });
                        });

                        this.entryPos = {[ENTRY_START]: null, [ENTRY_HOURS]: null};
                        this.entries();
                    },

                    // Passos sem posição (criados fora desta tela) vão para baixo do que já está desenhado.
                    placeMissing() {
                        const soltos = this.steps.filter(s => !s.pos);
                        if (!soltos.length) return;
                        const comPos = this.steps.filter(s => s.pos);
                        let y = Math.max(...comPos.map(s => s.pos.y + this.nodeHeight(s))) + 60;
                        const x = Math.min(...comPos.map(s => s.pos.x));
                        soltos.forEach((s, i) => {
                            s.pos = {x: x + (i % 3) * (W + 60), y};
                            if (i % 3 === 2) y += this.nodeHeight(s) + 40;
                        });
                    },

                    /* ---------- problemas ---------- */

                    reachable() {
                        const alcancados = new Set();
                        const fila = [this.settings.start];
                        if (this.settings.hours.enabled && this.settings.hours.out_of_hours) fila.push(this.settings.hours.out_of_hours);
                        while (fila.length) {
                            const k = fila.shift();
                            const s = this.byKey(k);
                            if (!k || alcancados.has(k) || !s) continue;
                            alcancados.add(k);
                            this.ports(s).forEach(p => { if (p.to && !p.dim) fila.push(p.to); });
                        }
                        return alcancados;
                    },

                    isReachable(key) { return this.reachable().has(key); },

                    errorStep(msg) {
                        const m = /Passo "([^"]+)"/.exec(msg);
                        return m && this.byKey(m[1]) ? m[1] : null;
                    },

                    errorKeys() { return new Set(this.errors.map(e => this.errorStep(e)).filter(Boolean)); },

                    /* ---------- Poli ---------- */

                    async loadPoli(atualizar) {
                        this.poliError = '';
                        const q = atualizar ? '?atualizar=1' : '';
                        try {
                            const [t, e] = await Promise.all([
                                fetch(this.urls.templates + q, {headers: {'Accept': 'application/json'}}),
                                fetch(this.urls.teams + q, {headers: {'Accept': 'application/json'}}),
                            ]);
                            if (t.ok) this.templates = (await t.json()).data; else this.poliError = (await t.json().catch(() => ({}))).message || 'Não foi possível carregar os templates.';
                            if (e.ok) this.teams = (await e.json()).data;
                        } catch (err) {
                            this.poliError = 'Sem conexão para carregar templates e times da Poli.';
                        }
                    },

                    templateById(uuid) { return this.templates.find(t => t.uuid === uuid) || null; },

                    useTemplateOptions(step) {
                        const t = this.templateById(step.say.template_uuid);
                        if (!t) return;
                        const antigas = Object.fromEntries(step.options.map(o => [o.label, o]));
                        step.options = t.options.map(o => ({
                            label: o.label, description: o.description || '',
                            aliases: antigas[o.label]?.aliases || '', next: antigas[o.label]?.next || '', value: antigas[o.label]?.value || '',
                        }));
                        if (!step.expect.type) step.expect.type = 'option';
                    },

                    /* ---------- salvar ---------- */

                    slugify(v) {
                        return (v || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
                    },

                    async save() {
                        if (this.saving) return;
                        this.saving = true; this.errors = []; this.message = '';
                        const novo = !this.flow.id;
                        try {
                            const res = await fetch(novo ? this.urls.store : this.urls.update, {
                                method: novo ? 'POST' : 'PUT',
                                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest'},
                                body: JSON.stringify({name: this.flow.name, slug: this.flow.slug, active: this.flow.active, definition: this.toDefinition()}),
                            });
                            const dados = await res.json().catch(() => ({}));
                            if (!res.ok) {
                                this.errors = Object.values(dados.errors || {}).flat();
                                if (!this.errors.length) this.errors = [dados.message || 'Não foi possível salvar.'];
                                return;
                            }
                            if (dados.redirect) { this.dirty = false; window.location = dados.redirect; return; }
                            if (dados.version) this.versions.unshift(dados.version);
                            this.message = dados.message || 'Salvo.';
                            this.$nextTick(() => { this.dirty = false; });
                        } catch (e) {
                            this.errors = ['Falha de rede ao salvar. Nada foi perdido: tente de novo.'];
                        } finally {
                            this.saving = false;
                        }
                    },

                    async removeFlow() {
                        if (!confirm(`Apagar o fluxo "${this.flow.name}"? As versões também somem.`)) return;
                        const res = await fetch(this.urls.destroy, {method: 'DELETE', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf()}});
                        const dados = await res.json().catch(() => ({}));
                        if (!res.ok) { this.errors = Object.values(dados.errors || {}).flat(); return; }
                        this.dirty = false;
                        window.location = dados.redirect || this.urls.index;
                    },

                    restoreVersion(v) {
                        if (!confirm(`Carregar a versão de ${v.at}? O que está na tela e não foi salvo será substituído.`)) return;
                        this.flow.name = v.name;
                        this.flow.active = !!v.active;
                        this.load(v.definition);
                        this.$nextTick(() => this.fit());
                        this.message = `Versão de ${v.at} carregada — confira e salve.`;
                    },

                    /* ---------- simulador ---------- */

                    simStep() {
                        const s = this.sim.session;
                        return s && s.state === 'flow' && s.flow === this.flow.slug ? s.step : null;
                    },

                    async simCall(body, eco) {
                        if (eco) this.sim.messages.push({from: 'me', text: eco});
                        this.sim.busy = true;
                        try {
                            const res = await fetch(this.urls.simulate, {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf()},
                                body: JSON.stringify(body),
                            });
                            const dados = await res.json().catch(() => ({}));
                            if (!res.ok) { this.sim.messages.push({from: 'bot', kind: 'ACTION', text: dados.message || 'Erro no simulador.'}); return; }
                            (dados.replies || []).forEach(r => {
                                this.sim.messages.push(this.bubble(r));
                                if (r.step && !this.sim.visited.includes(r.step)) this.sim.visited.push(r.step);
                            });
                            if (!(dados.replies || []).length && body.acao === 'enviar') this.sim.messages.push({from: 'bot', kind: 'ACTION', text: '(o bot não respondeu)'});
                            this.sim.session = dados.session;
                            const atual = this.simStep();
                            if (atual && !this.sim.visited.includes(atual)) this.sim.visited.push(atual);
                        } finally {
                            this.sim.busy = false;
                            this.$nextTick(() => { this.$refs.chat.scrollTop = this.$refs.chat.scrollHeight; });
                        }
                    },

                    bubble(r) {
                        if (r.type === 'ACTION') return {from: 'bot', kind: 'ACTION', text: '⚙ ' + r.text};
                        if (r.type === 'TEMPLATE') {
                            const t = this.templateById(r.template_uuid);
                            return {from: 'bot', text: t ? t.body : r.text, options: r.options || (t ? t.options : [])};
                        }
                        return {from: 'bot', text: r.text, options: null};
                    },

                    simSend(texto) {
                        texto = (texto || '').trim();
                        if (!texto) return;
                        this.sim.input = '';
                        this.simCall({acao: 'enviar', texto}, texto);
                    },

                    simTap(o) { this.simSend(o.description ? `${o.label}\n${o.description}` : o.label); },
                    simImage() { this.simCall({acao: 'enviar', texto: '', imagem: true}, '📷 [imagem]'); },
                    simStart() { this.sim.messages = []; this.sim.visited = []; this.simCall({acao: 'comecar', fluxo: this.flow.slug}, null); },
                    async simReset() { this.sim.messages = []; this.sim.visited = []; this.sim.session = null; await this.simCall({acao: 'reiniciar'}, null); },

                    sessionLabel() {
                        const s = this.sim.session;
                        if (!s) return '';
                        const estado = {idle: 'sem conversa', flow: 'no fluxo', human: 'com atendente', ending: 'encerrando'}[s.state] || s.state;
                        const dados = Object.entries(s.data || {}).map(([k, v]) => `${k}=${v}`).join(', ');
                        return `Estado: ${estado}` + (s.flow ? ` · ${s.flow} › ${s.step}` : '') + (s.tentativas ? ` · ${s.tentativas} erro(s)` : '') + (dados ? ` · ${dados}` : '');
                    },
                };
            }
        </script>
    </x-slot>
</x-app-layout>
