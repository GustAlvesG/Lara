<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Bot WhatsApp') }}</h2>
    </x-slot>

    @php
        $campo = 'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white dark:bg-gray-900 dark:text-gray-100';
        $rotulo = 'block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1';
        $cartao = 'bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-100 dark:border-gray-700';
        $botaoSec = 'inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50';
    @endphp

    <div class="py-8 bg-gray-50 dark:bg-gray-900 min-h-screen" x-data="botFlowEditor(@js([
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
    ]))" x-cloak>
        <div class="max-w-[100rem] mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Cabeçalho fixo: nome, situação e salvar --}}
            <div class="sticky top-0 z-20 -mx-4 px-4 py-3 mb-6 bg-gray-50/95 dark:bg-gray-900/95 backdrop-blur border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-3">
                <a :href="urls.index" class="text-sm text-gray-500 hover:underline">&larr; Fluxos</a>
                <h1 class="text-xl font-extrabold text-gray-900 dark:text-white" x-text="flow.name || 'Novo fluxo'"></h1>
                <span class="px-2 py-0.5 rounded text-xs font-bold"
                      :class="flow.active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700'"
                      x-text="flow.active ? 'Ativo' : 'Rascunho'"></span>
                <span x-show="dirty" class="text-xs text-amber-600 font-semibold">Alterações não salvas</span>
                <div class="flex-1"></div>
                <span x-show="message" x-text="message" class="text-sm text-green-700"></span>
                <button type="button" x-show="flow.id" @click="removeFlow()" class="text-xs text-red-600 hover:underline">Apagar fluxo</button>
                <button type="button" @click="save()" :disabled="saving"
                        class="inline-flex items-center px-5 py-2 bg-gray-800 dark:bg-gray-200 rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 disabled:opacity-50">
                    <span x-text="saving ? 'Salvando…' : 'Salvar'"></span>
                </button>
            </div>

            <template x-if="errors.length">
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 text-red-800 px-4 py-3 text-sm">
                    <p class="font-semibold mb-1">Não foi salvo. Corrija:</p>
                    <ul class="list-disc ml-5 space-y-0.5"><template x-for="e in errors"><li x-text="e"></li></template></ul>
                </div>
            </template>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                {{-- ============ Coluna do editor ============ --}}
                <div class="xl:col-span-2 space-y-6">

                    {{-- Configuração geral --}}
                    <section class="{{ $cartao }} p-5 space-y-5">
                        <h2 class="font-bold text-gray-900 dark:text-white">Configuração do fluxo</h2>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="{{ $rotulo }}">Nome</label>
                                <input type="text" x-model="flow.name" @input="if (!flow.id && !slugTouched) flow.slug = slugify(flow.name)" class="{{ $campo }}" placeholder="Ex.: Atendimento inicial">
                            </div>
                            <div>
                                <label class="{{ $rotulo }}">Identificador</label>
                                <input type="text" x-model="flow.slug" @input="slugTouched = true" :disabled="!!flow.id" class="{{ $campo }} font-mono disabled:opacity-60" placeholder="atendimento">
                                <p class="text-[11px] text-gray-400 mt-1" x-show="flow.id">Não muda depois de criado.</p>
                            </div>
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" x-model="flow.active" class="rounded border-gray-300">
                            Ativo — salvar publica: vale na próxima mensagem dos associados
                        </label>

                        <div>
                            <label class="{{ $rotulo }}">Quando este fluxo começa</label>
                            <select x-model="triggerMode" class="{{ $campo }} md:w-2/3">
                                <option value="any">Em qualquer primeira mensagem (fluxo de boas-vindas)</option>
                                <option value="texts">Quando a mensagem for uma destas palavras</option>
                                <option value="goto">Só quando outro fluxo mandar para cá</option>
                            </select>
                            <div x-show="triggerMode === 'texts'" class="mt-2">
                                <input type="text" x-model="settings.triggerTexts" class="{{ $campo }}" placeholder="carro de aplicativo, uber, taxi">
                                <p class="text-[11px] text-gray-400 mt-1">Separe por vírgula. Vale a mensagem inteira ou começando pela palavra; sem diferença de acento ou maiúscula.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="{{ $rotulo }}">Primeiro passo</label>
                                <select x-model="settings.start" class="{{ $campo }}">
                                    <template x-for="k in stepKeys()"><option :value="k" x-text="k" :selected="k === settings.start"></option></template>
                                </select>
                            </div>
                            <div>
                                <label class="{{ $rotulo }}">Encerrar conversa parada após (min)</label>
                                <input type="number" min="1" x-model.number="settings.timeout_minutes" class="{{ $campo }}">
                            </div>
                            <div>
                                <label class="{{ $rotulo }}">Respostas erradas até passar para humano</label>
                                <input type="number" min="1" max="10" x-model.number="settings.max_attempts" class="{{ $campo }}">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="{{ $rotulo }}">Ao esgotar as tentativas, passar para o time</label>
                                <select x-model="settings.on_max_team" class="{{ $campo }}">
                                    <option value="">Time padrão do servidor (POLI_BOT_FALLBACK_TEAM)</option>
                                    <template x-for="t in teams"><option :value="t.uuid" x-text="t.name" :selected="t.uuid === settings.on_max_team"></option></template>
                                </select>
                            </div>
                        </div>

                        {{-- Horário de atendimento --}}
                        <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-gray-100">
                                <input type="checkbox" x-model="settings.hours.enabled" class="rounded border-gray-300">
                                Tem horário de atendimento
                            </label>
                            <p class="text-xs text-gray-500 mt-1">Fora do horário a conversa começa por outro passo (ex.: um menu só com o que funciona 24h).</p>

                            <div x-show="settings.hours.enabled" class="mt-4 space-y-3">
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                    <template x-for="d in weekDays" :key="d.n">
                                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-200">
                                                <input type="checkbox" x-model="settings.hours.days[d.n].open" class="rounded border-gray-300">
                                                <span x-text="d.label"></span>
                                            </label>
                                            <div class="flex items-center gap-1 mt-2" x-show="settings.hours.days[d.n].open">
                                                <input type="time" x-model="settings.hours.days[d.n].from" class="{{ $campo }} !px-1 !py-1">
                                                <span class="text-xs text-gray-400">às</span>
                                                <input type="time" x-model="settings.hours.days[d.n].to" class="{{ $campo }} !px-1 !py-1">
                                            </div>
                                            <p class="text-xs text-gray-400 mt-2" x-show="!settings.hours.days[d.n].open">Fechado</p>
                                        </div>
                                    </template>
                                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Feriados</p>
                                        <div class="flex items-center gap-1 mt-2">
                                            <input type="time" x-model="settings.hours.holiday.from" class="{{ $campo }} !px-1 !py-1">
                                            <span class="text-xs text-gray-400">às</span>
                                            <input type="time" x-model="settings.hours.holiday.to" class="{{ $campo }} !px-1 !py-1">
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                        </div>
                    </section>

                    {{-- Passos --}}
                    <div class="flex items-center justify-between">
                        <h2 class="font-bold text-gray-900 dark:text-white">Passos <span class="text-gray-400 font-normal" x-text="'(' + steps.length + ')'"></span></h2>
                        <div class="flex gap-2">
                            <button type="button" @click="steps.forEach(s => s._open = false)" class="{{ $botaoSec }}">Recolher todos</button>
                            <button type="button" @click="addStep(steps.length - 1)" class="{{ $botaoSec }}">+ Passo</button>
                        </div>
                    </div>

                    <template x-for="(step, i) in steps" :key="step._uid">
                        <section class="{{ $cartao }}" :id="'passo-' + step.key">
                            {{-- Cabeçalho do passo --}}
                            <div class="flex flex-wrap items-center gap-3 px-5 py-3 cursor-pointer select-none" @click="step._open = !step._open">
                                <span class="text-xs font-bold text-gray-400" x-text="'#' + (i + 1)"></span>
                                <span class="font-mono font-semibold text-gray-900 dark:text-white" x-text="step.key"></span>
                                <span x-show="step.key === settings.start" class="px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-100 text-indigo-800">início</span>
                                <span x-show="settings.hours.enabled && step.key === settings.hours.out_of_hours" class="px-2 py-0.5 rounded text-[11px] font-bold bg-purple-100 text-purple-800">fora do horário</span>
                                <span x-show="!reachable().has(step.key)" class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800" title="Nenhum passo leva até aqui">sem caminho</span>
                                <span class="text-xs text-gray-500 truncate flex-1 min-w-[10rem]" x-text="summary(step)"></span>
                                <div class="flex gap-1" @click.stop>
                                    <button type="button" @click="moveStep(i, -1)" :disabled="i === 0" class="px-2 text-gray-500 disabled:opacity-30" title="Subir">↑</button>
                                    <button type="button" @click="moveStep(i, 1)" :disabled="i === steps.length - 1" class="px-2 text-gray-500 disabled:opacity-30" title="Descer">↓</button>
                                    <button type="button" @click="duplicateStep(i)" class="px-2 text-gray-500 text-xs" title="Duplicar">⧉</button>
                                    <button type="button" @click="removeStep(i)" class="px-2 text-red-500 text-xs" title="Apagar">✕</button>
                                </div>
                                <span class="text-gray-400" x-text="step._open ? '▾' : '▸'"></span>
                            </div>

                            <div x-show="step._open" class="border-t border-gray-100 dark:border-gray-700 px-5 py-5 space-y-6">
                                <div class="md:w-1/3">
                                    <label class="{{ $rotulo }}">Nome do passo</label>
                                    <input type="text" :value="step.key" @change="renameStep(i, $event.target.value)" class="{{ $campo }} font-mono">
                                </div>

                                {{-- 1. O bot diz --}}
                                <div class="space-y-3">
                                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">1. O bot diz</h3>
                                    <select x-model="step.say.type" class="{{ $campo }} md:w-1/2">
                                        <option value="">Nada</option>
                                        <option value="text">Uma mensagem de texto</option>
                                        <option value="menu">Um menu numerado (texto + opções)</option>
                                        <option value="template">Um template da Poli (lista ou botões)</option>
                                    </select>

                                    <div x-show="step.say.type === 'text' || step.say.type === 'menu'">
                                        <textarea rows="3" x-model="step.say.text" class="{{ $campo }}" :placeholder="step.say.type === 'menu' ? 'Escolha uma opção:' : 'Olá, {contato}!'"></textarea>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <span class="text-[11px] text-gray-400 mr-1">Inserir:</span>
                                            <template x-for="v in variables()">
                                                <button type="button" @click="step.say.text = (step.say.text || '') + '{' + v + '}'" class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-[11px] font-mono text-gray-600 dark:text-gray-300" x-text="'{' + v + '}'"></button>
                                            </template>
                                        </div>
                                        <p class="text-[11px] text-gray-400 mt-1">*negrito* e _itálico_ funcionam como no WhatsApp.</p>
                                    </div>

                                    <div x-show="step.say.type === 'template'" class="space-y-2">
                                        <div class="flex gap-2">
                                            <select x-model="step.say.template_uuid" class="{{ $campo }}">
                                                <option value="">— escolha o template —</option>
                                                <template x-for="t in templates"><option :value="t.uuid" x-text="t.key + ' [' + t.type + ']'" :selected="t.uuid === step.say.template_uuid"></option></template>
                                            </select>
                                            <button type="button" @click="loadPoli(true)" class="{{ $botaoSec }}" title="Recarregar da Poli">↻</button>
                                        </div>
                                        <p x-show="poliError" x-text="poliError" class="text-xs text-red-600"></p>
                                        <template x-if="templateById(step.say.template_uuid)">
                                            <div class="rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-3 text-xs text-gray-600 dark:text-gray-300 whitespace-pre-line">
                                                <span x-text="templateById(step.say.template_uuid).body"></span>
                                                <template x-if="templateById(step.say.template_uuid).options.length">
                                                    <div class="mt-2">
                                                        <button type="button" @click="useTemplateOptions(step)" class="text-indigo-600 font-semibold hover:underline">
                                                            Usar as <span x-text="templateById(step.say.template_uuid).options.length"></span> opções deste template como respostas aceitas
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <div>
                                            <label class="{{ $rotulo }}">Variáveis do template, na ordem (opcional)</label>
                                            <template x-for="(p, pi) in step.say.params">
                                                <div class="flex gap-2 mb-1">
                                                    <input type="text" x-model="step.say.params[pi]" class="{{ $campo }}" placeholder="{contato}">
                                                    <button type="button" @click="step.say.params.splice(pi, 1)" class="text-red-500 text-xs">✕</button>
                                                </div>
                                            </template>
                                            <button type="button" @click="step.say.params.push('')" class="text-xs text-indigo-600 hover:underline">+ variável</button>
                                        </div>
                                    </div>
                                </div>

                                {{-- 2. O bot espera --}}
                                <div class="space-y-3">
                                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">2. O bot espera como resposta</h3>
                                    <select x-model="step.expect.type" class="{{ $campo }} md:w-1/2">
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

                                    <div x-show="step.expect.type === 'text'" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                        <div><label class="{{ $rotulo }}">Mín. caracteres</label><input type="number" min="1" x-model="step.expect.min" class="{{ $campo }}"></div>
                                        <div><label class="{{ $rotulo }}">Máx. caracteres</label><input type="number" min="1" x-model="step.expect.max" class="{{ $campo }}"></div>
                                        <div class="md:col-span-2">
                                            <label class="{{ $rotulo }}">Formato (expressão regular, opcional)</label>
                                            <input type="text" x-model="step.expect.pattern" class="{{ $campo }} font-mono" placeholder="^[0-9]+$">
                                        </div>
                                    </div>
                                    <div x-show="step.expect.type === 'number'" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        <div><label class="{{ $rotulo }}">Mínimo</label><input type="number" x-model="step.expect.min" class="{{ $campo }}"></div>
                                        <div><label class="{{ $rotulo }}">Máximo</label><input type="number" x-model="step.expect.max" class="{{ $campo }}"></div>
                                    </div>
                                    <div x-show="step.expect.type === 'date'" class="flex gap-6 text-sm text-gray-700 dark:text-gray-200">
                                        <label class="inline-flex items-center gap-2"><input type="checkbox" x-model="step.expect.past_only" class="rounded border-gray-300"> Só datas passadas</label>
                                        <label class="inline-flex items-center gap-2"><input type="checkbox" x-model="step.expect.future_only" class="rounded border-gray-300"> Só datas futuras</label>
                                    </div>

                                    {{-- Opções --}}
                                    <div x-show="step.expect.type === 'option' || step.say.type === 'menu'" class="space-y-2">
                                        <p class="text-xs text-gray-500">A resposta vale se for o número da opção, o texto dela (tocado na lista ou digitado) ou um apelido.</p>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-sm">
                                                <thead class="text-[11px] text-left text-gray-500"><tr>
                                                    <th class="py-1 pr-2 w-8">#</th><th class="py-1 pr-2">Opção</th><th class="py-1 pr-2">Descrição</th>
                                                    <th class="py-1 pr-2">Apelidos</th><th class="py-1 pr-2">Vai para</th><th></th>
                                                </tr></thead>
                                                <tbody>
                                                    <template x-for="(op, oi) in step.options">
                                                        <tr>
                                                            <td class="py-1 pr-2 text-xs text-gray-400" x-text="oi + 1"></td>
                                                            <td class="py-1 pr-2"><input type="text" x-model="op.label" class="{{ $campo }} !py-1"></td>
                                                            <td class="py-1 pr-2"><input type="text" x-model="op.description" class="{{ $campo }} !py-1"></td>
                                                            <td class="py-1 pr-2"><input type="text" x-model="op.aliases" class="{{ $campo }} !py-1" placeholder="uber, 99"></td>
                                                            <td class="py-1 pr-2">
                                                                <select x-model="op.next" class="{{ $campo }} !py-1">
                                                                    <option value="">(próximo do passo)</option>
                                                                    <template x-for="k in stepKeys()"><option :value="k" x-text="k" :selected="k === op.next"></option></template>
                                                                </select>
                                                            </td>
                                                            <td class="py-1"><button type="button" @click="step.options.splice(oi, 1)" class="text-red-500 text-xs">✕</button></td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                        <button type="button" @click="step.options.push({label: '', description: '', aliases: '', next: '', value: ''})" class="text-xs text-indigo-600 hover:underline">+ opção</button>
                                    </div>

                                    <div x-show="step.expect.type" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div>
                                            <label class="{{ $rotulo }}">Guardar a resposta como</label>
                                            <input type="text" x-model="step.save_as" class="{{ $campo }} font-mono" placeholder="placa">
                                            <p class="text-[11px] text-gray-400 mt-1">Vira a variável <span class="font-mono" x-text="'{' + (step.save_as || 'nome') + '}'"></span> nos passos seguintes.</p>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="{{ $rotulo }}">Se a resposta não servir, o bot responde</label>
                                            <textarea rows="2" x-model="step.invalid" class="{{ $campo }}" :placeholder="invalidDefaults[step.expect.type] || invalidDefaults.text"></textarea>
                                        </div>
                                        <div>
                                            <label class="{{ $rotulo }}">Tentativas neste passo</label>
                                            <input type="number" min="1" max="10" x-model="step.max_attempts" class="{{ $campo }}" :placeholder="'padrão: ' + settings.max_attempts" :disabled="step.optional">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                                <input type="checkbox" x-model="step.optional" class="rounded border-gray-300">
                                                Pergunta opcional
                                            </label>
                                            <p class="text-[11px] text-gray-400 mt-1">Sem resposta, a conversa fecha em silêncio no prazo do fluxo. Resposta fora das opções encerra o fluxo e recomeça pelo menu, sem "não entendi".</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- 3. Depois --}}
                                <div class="space-y-3">
                                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">3. Depois</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="{{ $rotulo }}">Ação</label>
                                            <select x-model="step.action.type" class="{{ $campo }}">
                                                <option value="">Nenhuma</option>
                                                <option value="handoff">Passar para um time (atendente)</option>
                                                <option value="close">Encerrar o atendimento</option>
                                                <option value="uber_request">Registrar pedido de carro de aplicativo</option>
                                                <option value="goto_flow">Continuar em outro fluxo</option>
                                            </select>
                                        </div>
                                        <div x-show="step.action.type === 'handoff'">
                                            <label class="{{ $rotulo }}">Time</label>
                                            <select x-model="step.action.team_uuid" class="{{ $campo }}">
                                                <option value="">Time padrão do servidor</option>
                                                <template x-for="t in teams"><option :value="t.uuid" x-text="t.name" :selected="t.uuid === step.action.team_uuid"></option></template>
                                            </select>
                                        </div>
                                        <div x-show="step.action.type === 'goto_flow'">
                                            <label class="{{ $rotulo }}">Fluxo</label>
                                            <select x-model="step.action.flow" class="{{ $campo }}">
                                                <option value="">— escolha —</option>
                                                <template x-for="f in otherFlows"><option :value="f.slug" x-text="f.name + (f.active ? '' : ' (rascunho)')" :selected="f.slug === step.action.flow"></option></template>
                                            </select>
                                        </div>
                                    </div>
                                    <p x-show="step.action.type === 'uber_request'" class="text-xs text-amber-700 bg-amber-50 rounded p-2">
                                        Usa as respostas guardadas como <span class="font-mono">matricula</span>, <span class="font-mono">nome</span>,
                                        <span class="font-mono">local</span>, <span class="font-mono">placa</span> e <span class="font-mono">print</span>.
                                        Só cria o pedido de verdade com o bot no ar (modo on).
                                    </p>
                                    <p x-show="step.action.type === 'handoff' || step.action.type === 'close'" class="text-xs text-gray-500">
                                        A conversa sai do bot aqui — o próximo passo não é usado.
                                    </p>
                                    <div x-show="step.action.type !== 'handoff' && step.action.type !== 'close' && step.action.type !== 'goto_flow'" class="md:w-1/2">
                                        <label class="{{ $rotulo }}">Próximo passo</label>
                                        <select x-model="step.next" class="{{ $campo }}">
                                            <option value="">Fim da conversa</option>
                                            <template x-for="k in stepKeys()"><option :value="k" x-text="k" :selected="k === step.next"></option></template>
                                        </select>
                                        <p class="text-[11px] text-gray-400 mt-1" x-show="step.expect.type === 'option'">Opções com "vai para" próprio ignoram este.</p>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button type="button" @click="addStep(i)" class="{{ $botaoSec }}">+ Passo abaixo deste</button>
                                </div>
                            </div>
                        </section>
                    </template>
                </div>

                {{-- ============ Coluna lateral: mapa, simulador, versões ============ --}}
                <aside class="space-y-6 xl:sticky xl:top-20 self-start">

                    {{-- Simulador --}}
                    <section class="{{ $cartao }} flex flex-col h-[32rem]">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
                            <h2 class="font-bold text-gray-900 dark:text-white flex-1">Simulador</h2>
                            <button type="button" @click="simStart()" :disabled="!flow.id || sim.busy" class="{{ $botaoSec }}" title="Começar por este fluxo (versão salva)">▶ Testar este</button>
                            <button type="button" @click="simReset()" :disabled="sim.busy" class="{{ $botaoSec }}">Reiniciar</button>
                        </div>
                        <p class="px-4 pt-2 text-[11px] text-gray-400">Nada é enviado. Testa a versão <strong>salva</strong><span x-show="dirty" class="text-amber-600"> — salve para testar as mudanças</span>.</p>
                        <div class="flex-1 overflow-y-auto px-4 py-3 space-y-2" x-ref="chat">
                            <template x-for="(m, mi) in sim.messages" :key="mi">
                                <div :class="m.from === 'me' ? 'flex justify-end' : 'flex justify-start'">
                                    <div class="max-w-[85%] rounded-xl px-3 py-2 text-sm whitespace-pre-line"
                                         :class="m.from === 'me' ? 'bg-green-100 text-green-900' : (m.kind === 'ACTION' ? 'bg-amber-50 text-amber-800 italic text-xs' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100')">
                                        <span x-text="m.text"></span>
                                        <template x-if="m.options && m.options.length">
                                            <div class="mt-2 flex flex-col gap-1">
                                                <template x-for="o in m.options">
                                                    <button type="button" @click="simTap(o)" class="text-left px-2 py-1 rounded bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-xs text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50" x-text="o.label"></button>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <p x-show="!sim.messages.length" class="text-xs text-gray-400 text-center mt-10">Mande uma mensagem como se fosse o associado.</p>
                        </div>
                        <div class="px-4 py-2 text-[11px] text-gray-500 border-t border-gray-100 dark:border-gray-700" x-show="sim.session">
                            <span x-text="sessionLabel()"></span>
                        </div>
                        <form class="p-3 border-t border-gray-100 dark:border-gray-700 flex gap-2" @submit.prevent="simSend(sim.input)">
                            <input type="text" x-model="sim.input" class="{{ $campo }}" placeholder="Mensagem…" :disabled="sim.busy">
                            <button type="button" @click="simImage()" :disabled="sim.busy" class="{{ $botaoSec }}" title="Enviar uma imagem">📷</button>
                            <button type="submit" :disabled="sim.busy || !sim.input.trim()" class="{{ $botaoSec }}">Enviar</button>
                        </form>
                    </section>

                    {{-- Mapa --}}
                    <section class="{{ $cartao }} p-4">
                        <h2 class="font-bold text-gray-900 dark:text-white mb-3">Mapa do fluxo</h2>
                        <ol class="space-y-2 text-xs">
                            <template x-for="s in steps" :key="s._uid">
                                <li>
                                    <a :href="'#passo-' + s.key" @click="s._open = true" class="font-mono font-semibold hover:underline"
                                       :class="reachable().has(s.key) ? 'text-gray-900 dark:text-gray-100' : 'text-amber-600'" x-text="s.key"></a>
                                    <ul class="ml-4 mt-0.5 text-gray-500 space-y-0.5">
                                        <template x-for="e in edges(s)"><li><span x-text="e.label"></span> → <span class="font-mono" :class="e.to ? '' : 'text-gray-400'" x-text="e.to || 'fim'"></span></li></template>
                                    </ul>
                                </li>
                            </template>
                        </ol>
                    </section>

                    {{-- Versões --}}
                    <section class="{{ $cartao }} p-4" x-show="versions.length">
                        <h2 class="font-bold text-gray-900 dark:text-white mb-3">Versões</h2>
                        <ul class="space-y-1 text-xs max-h-60 overflow-y-auto">
                            <template x-for="(v, vi) in versions" :key="v.id">
                                <li class="flex items-center gap-2">
                                    <span class="text-gray-500 flex-1"><span x-text="v.at"></span> · <span x-text="v.user || '—'"></span><span x-show="vi === 0" class="text-green-700 font-semibold"> (atual)</span></span>
                                    <button type="button" x-show="vi > 0" @click="restoreVersion(v)" class="text-indigo-600 hover:underline">restaurar</button>
                                </li>
                            </template>
                        </ul>
                        <p class="text-[11px] text-gray-400 mt-2">Restaurar só carrega na tela — confira e salve.</p>
                    </section>
                </aside>
            </div>
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

                return {
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
                    templates: [],
                    teams: [],
                    poliError: '',
                    errors: [],
                    message: '',
                    saving: false,
                    dirty: false,
                    sim: {messages: [], input: '', busy: false, session: null},

                    init() {
                        this.load(config.flow.definition);
                        this.loadPoli(false);
                        this.$nextTick(() => {
                            this.$watch(() => JSON.stringify([this.flow, this.settings, this.steps, this.triggerMode]), () => { this.dirty = true; this.message = ''; });
                        });
                        window.addEventListener('beforeunload', (e) => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
                    },

                    /* ---------- definição <-> tela ---------- */

                    load(def) {
                        def = def || {};
                        const t = def.triggers || {};
                        this.triggerMode = t.any ? 'any' : (t.only_goto ? 'goto' : 'texts');
                        const h = def.hours || {};
                        const days = {};
                        DAYS.forEach(d => {
                            const iv = (h.days || {})[d.n];
                            days[d.n] = {open: Array.isArray(iv), from: iv ? iv[0] : '07:00', to: iv ? iv[1] : '18:00'};
                        });
                        this.settings = {
                            start: def.start || '',
                            triggerTexts: (t.texts || []).join(', '),
                            timeout_minutes: def.timeout_minutes ?? 15,
                            max_attempts: def.max_attempts ?? 3,
                            on_max_team: (def.on_max_attempts || {}).team_uuid || '',
                            hours: {
                                enabled: !!h.enabled,
                                days,
                                holiday: {from: (h.holiday || ['07:00'])[0], to: (h.holiday || [null, '18:00'])[1]},
                                holidays: (h.holidays || []).map(d => d.split('-').reverse().join('/')).join('\n'),
                                out_of_hours: h.out_of_hours || '',
                            },
                        };
                        this.steps = Object.entries(def.steps || {}).map(([key, s]) => this.stepFromDef(key, s));
                        if (!this.settings.start && this.steps.length) this.settings.start = this.steps[0].key;
                    },

                    stepFromDef(key, s) {
                        const say = s.say || {}, ex = s.expect || {}, ac = s.action || {};
                        return {
                            _uid: ++uid, _open: false, key,
                            say: {type: say.type || '', text: say.text || '', template_uuid: say.template_uuid || '', params: [...(say.params || [])]},
                            expect: {type: ex.type || '', min: ex.min ?? '', max: ex.max ?? '', pattern: ex.pattern || '', past_only: !!ex.past_only, future_only: !!ex.future_only},
                            options: (s.options || []).map(o => ({label: o.label || '', description: o.description || '', aliases: (o.aliases || []).join(', '), next: o.next || '', value: o.value || ''})),
                            save_as: s.save_as || '', invalid: s.invalid || '', max_attempts: s.max_attempts ?? '',
                            optional: !!s.optional,
                            action: {type: ac.type || '', team_uuid: ac.team_uuid || '', flow: ac.flow || ''},
                            next: s.next || '',
                        };
                    },

                    toDefinition() {
                        const num = v => (v === '' || v === null || v === undefined) ? undefined : Number(v);
                        const steps = {};
                        this.steps.forEach(s => {
                            const out = {};
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
                            if (s.expect.type === 'option' || s.say.type === 'menu') {
                                out.options = s.options.filter(o => o.label.trim() !== '').map(o => ({
                                    label: o.label.trim(),
                                    description: o.description.trim() || undefined,
                                    aliases: o.aliases.split(',').map(a => a.trim()).filter(Boolean),
                                    next: o.next || undefined,
                                    value: o.value || undefined,
                                }));
                            }
                            if (s.action.type) {
                                out.action = {type: s.action.type};
                                if (s.action.type === 'handoff' && s.action.team_uuid) out.action.team_uuid = s.action.team_uuid;
                                if (s.action.type === 'goto_flow') out.action.flow = s.action.flow;
                            }
                            if (s.next && !['handoff', 'close', 'goto_flow'].includes(s.action.type)) out.next = s.next;
                            steps[s.key] = out;
                        });

                        const h = this.settings.hours;
                        const def = {
                            start: this.settings.start,
                            triggers: {
                                any: this.triggerMode === 'any',
                                texts: this.triggerMode === 'texts' ? this.settings.triggerTexts.split(',').map(t => t.trim()).filter(Boolean) : [],
                                only_goto: this.triggerMode === 'goto',
                            },
                            timeout_minutes: num(this.settings.timeout_minutes),
                            max_attempts: num(this.settings.max_attempts),
                            steps,
                        };
                        if (this.settings.on_max_team) def.on_max_attempts = {type: 'handoff', team_uuid: this.settings.on_max_team};
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

                    /* ---------- passos ---------- */

                    stepKeys() { return this.steps.map(s => s.key); },

                    newKey(base) {
                        let k = base, n = 2;
                        while (this.stepKeys().includes(k)) k = `${base}_${n++}`;
                        return k;
                    },

                    addStep(afterIndex) {
                        const s = this.stepFromDef(this.newKey('passo'), {say: {type: 'text', text: ''}});
                        s._open = true;
                        this.steps.splice(afterIndex + 1, 0, s);
                        if (!this.settings.start) this.settings.start = s.key;
                    },

                    duplicateStep(i) {
                        const copia = JSON.parse(JSON.stringify(this.steps[i]));
                        copia._uid = ++uid;
                        copia.key = this.newKey(this.steps[i].key);
                        this.steps.splice(i + 1, 0, copia);
                    },

                    moveStep(i, dir) {
                        const j = i + dir;
                        if (j < 0 || j >= this.steps.length) return;
                        const [s] = this.steps.splice(i, 1);
                        this.steps.splice(j, 0, s);
                    },

                    removeStep(i) {
                        const key = this.steps[i].key;
                        const usado = this.steps.filter(s => s.next === key || s.options.some(o => o.next === key)).map(s => s.key);
                        const aviso = usado.length ? `\n\nOs passos ${usado.join(', ')} apontam para ele e vão passar a terminar a conversa.` : '';
                        if (!confirm(`Apagar o passo "${key}"?${aviso}`)) return;
                        this.steps.splice(i, 1);
                        this.replaceRef(key, '');
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

                    variables() {
                        return ['contato', ...new Set(this.steps.map(s => s.save_as).filter(Boolean))];
                    },

                    summary(s) {
                        const diz = {text: 'texto', menu: 'menu', template: 'template'}[s.say.type];
                        const espera = {option: 'opção', text: 'texto', plate: 'placa', date: 'data', number: 'número', yes_no: 'sim/não', image: 'imagem', any: 'qualquer'}[s.expect.type];
                        const acao = {handoff: 'passa p/ time', close: 'encerra', uber_request: 'pedido de carro', goto_flow: 'vai p/ fluxo'}[s.action.type];
                        const txt = s.say.type === 'template' ? (this.templateById(s.say.template_uuid)?.key || '') : (s.say.text || '').slice(0, 60);
                        return [diz && `diz ${diz}`, espera && `espera ${espera}`, acao, txt && `“${txt}”`].filter(Boolean).join(' · ');
                    },

                    /* ---------- mapa ---------- */

                    edges(s) {
                        if (['handoff', 'close'].includes(s.action.type)) {
                            return [{label: s.action.type === 'handoff' ? 'passa para atendente' : 'encerra', to: ''}];
                        }
                        if (s.action.type === 'goto_flow') return [{label: 'fluxo ' + (s.action.flow || '?'), to: ''}];
                        const saidas = [];
                        if (s.expect.type === 'option' || s.say.type === 'menu') {
                            s.options.forEach((o, i) => { if (o.next) saidas.push({label: `${i + 1}. ${o.label}`, to: o.next}); });
                        }
                        const semProprio = s.options.some(o => !o.next) || !(s.expect.type === 'option' || s.say.type === 'menu');
                        if (semProprio) saidas.push({label: s.expect.type ? 'resposta válida' : 'em seguida', to: s.next});
                        return saidas;
                    },

                    reachable() {
                        const alcancados = new Set();
                        const fila = [this.settings.start];
                        if (this.settings.hours.enabled && this.settings.hours.out_of_hours) fila.push(this.settings.hours.out_of_hours);
                        const porChave = Object.fromEntries(this.steps.map(s => [s.key, s]));
                        while (fila.length) {
                            const k = fila.shift();
                            if (!k || alcancados.has(k) || !porChave[k]) continue;
                            alcancados.add(k);
                            this.edges(porChave[k]).forEach(e => e.to && fila.push(e.to));
                        }
                        return alcancados;
                    },

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
                                window.scrollTo({top: 0, behavior: 'smooth'});
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
                        this.message = `Versão de ${v.at} carregada — confira e salve.`;
                    },

                    /* ---------- simulador ---------- */

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
                            (dados.replies || []).forEach(r => this.sim.messages.push(this.bubble(r)));
                            if (!(dados.replies || []).length && body.acao === 'enviar') this.sim.messages.push({from: 'bot', kind: 'ACTION', text: '(o bot não respondeu)'});
                            this.sim.session = dados.session;
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
                    simStart() { this.sim.messages = []; this.simCall({acao: 'comecar', fluxo: this.flow.slug}, null); },
                    async simReset() { this.sim.messages = []; this.sim.session = null; await this.simCall({acao: 'reiniciar'}, null); },

                    sessionLabel() {
                        const s = this.sim.session;
                        if (!s) return '';
                        const estado = {idle: 'sem conversa', flow: 'no fluxo', human: 'com atendente'}[s.state] || s.state;
                        const dados = Object.entries(s.data || {}).map(([k, v]) => `${k}=${v}`).join(', ');
                        return `Estado: ${estado}` + (s.flow ? ` · ${s.flow} › ${s.step}` : '') + (s.tentativas ? ` · ${s.tentativas} erro(s)` : '') + (dados ? ` · ${dados}` : '');
                    },
                };
            }
        </script>
    </x-slot>
</x-app-layout>
