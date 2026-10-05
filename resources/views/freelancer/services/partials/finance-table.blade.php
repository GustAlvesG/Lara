{{--
    Tabela de contratos do Financeiro, com seleção, baixa individual e baixa em
    massa. É a mesma tabela em três telas — dentro de um lote, nos avulsos e na
    visão de todos os contratos —, por isso vive aqui.

    Espera:
      $services   contratos já filtrados por awaitingFinance()
      $pixEnabled bool
      $batch      lote (opcional): quando presente, a baixa volta para ele e o
                  botão "Pagar o lote inteiro" aparece
      $emptyText  texto do estado vazio (opcional)
--}}
@php
    $batch = $batch ?? null;
    $emptyText = $emptyText ?? 'Nenhum contrato aprovado aguardando pagamento.';

    // Só os pendentes entram na seleção em massa (e são os únicos exibidos no
    // modo reduzido). Ids como string para casar com o value do checkbox.
    //
    // Com o Pix ligado, contrato que já tem transferência em andamento sai da
    // seleção: marcar "todos" não pode virar um segundo envio.
    $selectable = $services->filter(fn($service) => $pixEnabled ? $service->canRequestPix() : $service->canBePaid());

    $pendingIds = $selectable->pluck('id')->map(fn($id) => (string) $id)->values();
    $pendingValues = $selectable->pluck('price', 'id')->map(fn($price) => (float) $price);
    $pendingTotal = (float) $selectable->sum('price');
@endphp

<div
    x-data="{
        compact: localStorage.getItem('freelancerFinanceCompact') === 'true',
        selected: [],
        pending: @js($pendingIds),
        values: @js($pendingValues),
        persistCompact() {
            localStorage.setItem('freelancerFinanceCompact', this.compact);
        },
        // Com a busca filtrando a tabela, 'todos' são os pendentes que estão
        // na tela: selecionar (e pagar) linhas escondidas seria uma surpresa.
        visiblePending() {
            return this.pending.filter((id) => {
                const box = this.$root.querySelector('input[name=\'services[]\'][value=\'' + id + '\']');
                const row = box && box.closest('tr');
                return row && row.style.display !== 'none';
            });
        },
        get allSelected() {
            return this.pending.length > 0 && this.selected.length === this.pending.length;
        },
        toggleAll(event) {
            this.selected = event.target.checked ? this.visiblePending() : [];
        },
        selectAll() {
            this.selected = this.visiblePending();
        },
        get selectedTotal() {
            return this.selected.reduce((total, id) => total + (this.values[id] ?? 0), 0);
        },
        formatMoney(value) {
            return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        },
    }"
>
    {{-- Fora do formulário de baixa: Enter na busca não pode enviar pagamento. --}}
    @if($services->isNotEmpty())
        <x-search-bar mode="client" target="#contratos-financeiro" id="busca-financeiro" placeholder="Buscar freelancer, CPF, chave PIX ou evento" class="mb-4" />
    @endif

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" x-model="compact" @change="persistCompact()"
                   class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
            <span class="text-sm font-semibold text-ink-2">Tabela reduzida</span>
            <span class="text-xs text-ink-3">(só pendentes: nome, valor, período e chave PIX)</span>
        </label>

        <p class="text-sm text-ink-2" x-show="selected.length > 0" x-cloak>
            <span x-text="selected.length"></span> selecionado(s) ·
            <span class="font-bold text-ink" x-text="formatMoney(selectedTotal)"></span>
        </p>
    </div>

    <form method="POST" action="{{ route('freelancer-services.pay') }}"
          @submit="if (!$event.submitter?.name
                       && !confirm(@js($pixEnabled
                           ? 'ENVIAR PIX para os contratos selecionados? O dinheiro sai da conta do clube. A ação fica registrada com seu usuário.'
                           : 'Confirmar a baixa de pagamento dos contratos selecionados? A ação fica registrada com seu usuário.')
                           + '\n\n' + selected.length + ' contrato(s) · ' + formatMoney(selectedTotal))) {
                       $event.preventDefault();
                   }">
        @csrf
        {{-- Volta para o lote de onde a baixa partiu, em vez de jogar a pessoa
             na lista geral. Vai o id, nunca uma URL: o destino é resolvido no
             servidor. --}}
        @if($batch)
            <input type="hidden" name="batch" value="{{ $batch->id }}">
        @endif

        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            @if($services->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-ink-2">{{ $emptyText }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-ink-3 uppercase tracking-wider bg-subtle">
                            <tr>
                                <th class="px-4 py-3 w-10">
                                    @if($pendingIds->isNotEmpty())
                                        <input type="checkbox" :checked="allSelected" @change="toggleAll($event)"
                                               title="Selecionar todos os pendentes"
                                               class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                                    @endif
                                </th>
                                <th class="px-4 py-3">Freelancer</th>
                                <th class="px-4 py-3" x-show="!compact">Função</th>
                                <th class="px-4 py-3" x-show="!compact">Evento/Local</th>
                                @unless($batch)
                                    <th class="px-4 py-3" x-show="!compact">Lote</th>
                                @endunless
                                <th class="px-4 py-3">Período</th>
                                <th class="px-4 py-3" x-show="!compact">Duração</th>
                                <th class="px-4 py-3">Chave PIX</th>
                                <th class="px-4 py-3">Valor</th>
                                <th class="px-4 py-3" x-show="!compact">Pagamento</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="contratos-financeiro" class="divide-y divide-line">
                            @foreach($services as $service)
                            @php
                                $paid = $service->isPaid();
                                $pix = $service->latestPixPayment;
                                $selecionavel = $pixEnabled ? $service->canRequestPix() : $service->canBePaid();
                            @endphp
                            {{-- No modo reduzido a lista vira folha de pagamento: só o que falta pagar. --}}
                            <tr data-search="" class="hover:bg-subtle transition" @if($paid) x-bind:class="compact ? 'hidden' : ''" @endif>
                                <td class="px-4 py-4">
                                    @if($selecionavel)
                                        <input type="checkbox" name="services[]" value="{{ $service->id }}" x-model="selected"
                                               class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                                    @endif
                                </td>
                                <td class="px-4 py-4 font-semibold text-ink">
                                    <span class="text-xs font-mono text-ink-3">#{{ $service->id }}</span>
                                    {{ $service->freelancer->name }}
                                    {{-- A comissão repete nome, data e período do contrato do turno,
                                         com outro valor: sem o selo, parece pagamento em duplicidade. --}}
                                    <x-freelancer-kind-badge :service="$service" :note="true" class="mt-1" />
                                </td>
                                <td class="px-4 py-4 text-ink" x-show="!compact">{{ $service->functionFreelancer->name }}</td>
                                <td class="px-4 py-4 text-ink" x-show="!compact">
                                    {{ $service->location ?? '—' }}
                                    @if(filled($service->description))
                                        <span class="block max-w-[16rem] truncate text-xs text-ink-3" title="{{ $service->description }}">{{ $service->description }}</span>
                                    @endif
                                </td>
                                @unless($batch)
                                    <td class="px-4 py-4 text-ink whitespace-nowrap" x-show="!compact">
                                        @if($service->batch_id)
                                            <a href="{{ route('freelancer-services.finance.batch', $service->batch_id) }}"
                                               class="text-grena-ink hover:underline font-medium">#{{ $service->batch_id }}</a>
                                        @else
                                            <span class="text-warn font-semibold">sem lote</span>
                                        @endif
                                    </td>
                                @endunless
                                <td class="px-4 py-4 text-ink whitespace-nowrap">
                                    {{ $service->start_date->format('d/m/Y') }}
                                    <span class="text-ink-3">
                                        {{ substr($service->start_time, 0, 5) }}–{{ substr($service->end_time, 0, 5) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-ink" x-show="!compact">{{ $service->formattedDuration() }}</td>
                                <td class="px-4 py-4 text-ink whitespace-nowrap">
                                    <span class="font-mono">{{ $service->freelancer->pix_key ?? '—' }}</span>
                                    @if($service->freelancer->pix_key)
                                        <button type="button" data-pix="{{ $service->freelancer->pix_key }}"
                                                @click="navigator.clipboard.writeText($el.dataset.pix)"
                                                title="Copiar chave PIX"
                                                class="ml-1 text-ink-3 hover:text-grena-ink transition align-middle">
                                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        </button>
                                    @endif
                                    {{-- O contrato assinado guarda a chave que o freelancer conferiu. Se o
                                         cadastro mudou depois, o Pix sai para a chave de cima e o documento
                                         cita outra — quem paga tem de ver isso antes de clicar. --}}
                                    @if($service->pixKeyDivergesFromFreelancer())
                                        <span class="block mt-1 text-xs font-semibold text-warn"
                                              title="No contrato assinado: {{ $service->pixKeyFormatted() }}">
                                            ⚠ Diferente da chave assinada
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 font-semibold text-ink whitespace-nowrap">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                                <td class="px-4 py-4 whitespace-nowrap" x-show="!compact">
                                    @if($paid)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-ok-soft text-ok">Pago</span>
                                        <p class="mt-1 text-xs text-ink-2">
                                            {{ $service->paid_at?->format('d/m/Y H:i') }}
                                            @if($service->paidBy)
                                                · {{ $service->paidBy->name }}
                                            @endif
                                        </p>
                                        @if($pix?->end_to_end_id)
                                            <p class="mt-0.5 text-[10px] font-mono text-ink-3" title="Identificador fim a fim da transação no Pix">
                                                {{ $pix->end_to_end_id }}
                                            </p>
                                        @endif
                                    {{-- Estados do Pix. A distinção que a tela precisa passar é entre
                                         "não saiu" (rejeitado/falhou, pode refazer) e "não sabemos"
                                         (conferir no banco, NÃO refazer). --}}
                                    @elseif($pix && $pix->status === \App\Models\PixPayment::STATUS_UNKNOWN)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-danger-soft text-danger">Conferir no banco</span>
                                        <p class="mt-1 max-w-[18rem] text-xs text-danger">
                                            A resposta do banco não chegou e o Pix pode ter sido feito. Não refaça a baixa — avise a TI.
                                        </p>
                                    @elseif($pix && $pix->isPending())
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-grena-tint text-grena-ink">{{ $pix->statusLabel() }}</span>
                                        <p class="mt-1 text-xs text-ink-2">Pix enviado {{ $pix->created_at?->format('d/m/Y H:i') }}</p>
                                    @elseif($pix && in_array($pix->status, [\App\Models\PixPayment::STATUS_REJECTED, \App\Models\PixPayment::STATUS_FAILED], true))
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-danger-soft text-danger">{{ $pix->statusLabel() }}</span>
                                        <p class="mt-1 max-w-[18rem] text-xs text-danger">
                                            {{ $pix->rejection_detail ?: 'O banco não concluiu a transferência.' }}
                                            <span class="text-ink-2">Nada foi transferido; pode tentar de novo.</span>
                                        </p>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-warn-soft text-warn">Pendente</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right space-x-3 whitespace-nowrap">
                                    @can(\App\Authorization\Permissions::FREELANCERS_SERVICOS_GERENCIAR)
                                    <a href="{{ route('freelancer-services.show', $service) }}" x-show="!compact"
                                       class="text-grena-ink hover:underline font-medium text-xs">Ver</a>
                                    @endcan
                                    @if($selecionavel)
                                        {{-- A confirmação muda de texto quando o clique move dinheiro:
                                             ela precisa dizer o valor e para quem vai. --}}
                                        <button type="submit" name="only" value="{{ $service->id }}"
                                                onclick="return confirm(@js($pixEnabled
                                                    ? 'ENVIAR PIX de R$ ' . number_format((float) $service->price, 2, ',', '.')
                                                      . ' para ' . $service->freelancer->name . ' (chave ' . $service->freelancer->pix_key . ')?'
                                                      . "\n\n" . 'O dinheiro sai da conta do clube. A ação fica registrada com seu usuário.'
                                                    : 'Confirmar a baixa de pagamento deste contrato? A ação fica registrada com seu usuário.'))"
                                                class="inline-flex items-center px-3 py-1.5 bg-ok text-white dark:text-canvas rounded-lg text-xs font-bold hover:bg-ok transition">
                                            {{ $pixEnabled ? 'Pagar via Pix' : 'Dar baixa' }}
                                        </button>
                                    @elseif($pixEnabled && $service->hasPixInProgress() && !$paid)
                                        <span class="text-xs text-ink-3">Pix em andamento</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Pagar o lote inteiro: marca tudo o que está pendente e envia. O
             confirm é o mesmo do envio em massa (o submit não tem `name`), e
             some quando não há mais nada a pagar. --}}
        @if($batch && $pendingIds->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-surface px-5 py-4">
                <div>
                    <p class="text-sm font-bold text-ink">Pagar o lote inteiro</p>
                    <p class="text-xs text-ink-2">
                        {{ $pendingIds->count() }} contrato(s) pendente(s) ·
                        R$ {{ number_format($pendingTotal, 2, ',', '.') }}
                        @if($pixEnabled)
                            · sai um Pix por contrato
                        @endif
                    </p>
                </div>
                <button type="button" @click="selectAll()"
                        class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-grena hover:bg-[#7c0001] shadow-card transition">
                    Selecionar o lote inteiro
                </button>
            </div>
        @endif

        {{-- Barra de ação em massa: fica fixa no rodapé enquanto houver seleção. --}}
        <div x-show="selected.length > 0" x-cloak
             class="sticky bottom-4 mt-4 flex justify-center">
            <div class="flex items-center gap-4 px-4 py-3 bg-ink text-canvas rounded-2xl shadow-pop">
                <span class="text-sm font-semibold">
                    <span x-text="selected.length"></span> contrato(s) ·
                    <span x-text="formatMoney(selectedTotal)"></span>
                </span>
                <button type="submit"
                        class="px-4 py-2 bg-ok rounded-lg text-sm font-bold hover:bg-ok transition">
                    {{ $pixEnabled ? 'Pagar via Pix os selecionados' : 'Dar baixa nos selecionados' }}
                </button>
                <button type="button" @click="selected = []"
                        class="text-sm text-ink-3 hover:text-white transition">Limpar</button>
            </div>
        </div>
    </form>
</div>
