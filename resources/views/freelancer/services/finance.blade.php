<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Serviços / Contratos') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    {{-- Tabela de 11 colunas: sem o cap de largura ela deixa de ser cortada. --}}
    <div class="max-w-full mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Financeiro</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Contratos assinados pelo freelancer e pelo coordenador, prontos para baixa de pagamento.</p>
        </div>

        {{-- Com o Pix ligado, "Dar baixa" transfere dinheiro de verdade. O aviso
             fica no topo da tela porque o botão é o mesmo de antes, e ninguém
             deve descobrir a diferença depois de clicar. --}}
        @if($pixEnabled)
            <div class="mb-6 rounded-2xl border-l-4 border-amber-500 bg-amber-50 dark:bg-amber-900/20 p-4">
                <p class="text-sm font-bold text-amber-800 dark:text-amber-300">
                    Pix automático ativo
                    @if($pixAmbiente !== 'producao')
                        <span class="ml-1 px-2 py-0.5 rounded-full bg-gray-800 text-white text-xs uppercase">{{ $pixAmbiente }}</span>
                    @endif
                </p>
                <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                    Dar baixa <strong>transfere o valor</strong> para a chave PIX do freelancer.
                    A baixa só é registrada quando o banco confirmar — até lá o contrato aparece como
                    <em>em processamento</em>. Não clique duas vezes: o sistema recusa um segundo envio para o mesmo contrato.
                </p>
            </div>
        @endif

        @include('freelancer.services.partials.tabs')

        @include('partials.alerts')

        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">A pagar</p>
                <p class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white">R$ {{ number_format($pendingTotal, 2, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Pago</p>
                <p class="mt-1 text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">R$ {{ number_format($paidTotal, 2, ',', '.') }}</p>
            </div>
        </div>

        @php
            // Só os pendentes entram na seleção em lote (e são os únicos exibidos
            // no modo reduzido). Ids como string para casar com o value do checkbox.
            //
            // Com o Pix ligado, contrato que já tem transferência em andamento
            // sai da seleção: marcar "todos" não pode virar um segundo envio.
            $selectable = $services->filter(fn($service) => $pixEnabled ? $service->canRequestPix() : $service->canBePaid());

            $pendingIds = $selectable->pluck('id')->map(fn($id) => (string) $id)->values();
            $pendingValues = $selectable->pluck('price', 'id')->map(fn($price) => (float) $price);
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
                get allSelected() {
                    return this.pending.length > 0 && this.selected.length === this.pending.length;
                },
                toggleAll(event) {
                    this.selected = event.target.checked ? [...this.pending] : [];
                },
                get selectedTotal() {
                    return this.selected.reduce((total, id) => total + (this.values[id] ?? 0), 0);
                },
                formatMoney(value) {
                    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                },
            }"
        >
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" x-model="compact" @change="persistCompact()"
                           class="rounded border-gray-300 dark:border-gray-600 text-[#A00001] focus:ring-[#A00001] dark:bg-gray-700">
                    <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Tabela reduzida</span>
                    <span class="text-xs text-gray-400 dark:text-gray-500">(só pendentes: nome, valor, período e chave PIX)</span>
                </label>

                <p class="text-sm text-gray-500 dark:text-gray-400" x-show="selected.length > 0" x-cloak>
                    <span x-text="selected.length"></span> selecionado(s) ·
                    <span class="font-bold text-gray-800 dark:text-gray-200" x-text="formatMoney(selectedTotal)"></span>
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

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    @if($services->isEmpty())
                        <div class="p-12 text-center">
                            <p class="text-gray-500 dark:text-gray-400">Nenhum contrato assinado pelas duas partes até o momento.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-4 py-3 w-10">
                                            @if($pendingIds->isNotEmpty())
                                                <input type="checkbox" :checked="allSelected" @change="toggleAll($event)"
                                                       title="Selecionar todos os pendentes"
                                                       class="rounded border-gray-300 dark:border-gray-600 text-[#A00001] focus:ring-[#A00001] dark:bg-gray-700">
                                            @endif
                                        </th>
                                        <th class="px-4 py-3">Freelancer</th>
                                        <th class="px-4 py-3" x-show="!compact">Função</th>
                                        <th class="px-4 py-3" x-show="!compact">Evento/Local</th>
                                        <th class="px-4 py-3">Período</th>
                                        <th class="px-4 py-3" x-show="!compact">Duração</th>
                                        <th class="px-4 py-3">Chave PIX</th>
                                        <th class="px-4 py-3">Valor</th>
                                        <th class="px-4 py-3" x-show="!compact">Pagamento</th>
                                        <th class="px-4 py-3 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($services as $service)
                                    @php
                                        $paid = $service->isPaid();
                                        $pix = $service->latestPixPayment;
                                        $selecionavel = $pixEnabled ? $service->canRequestPix() : $service->canBePaid();
                                    @endphp
                                    {{-- No modo reduzido a lista vira folha de pagamento: só o que falta pagar. --}}
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition" @if($paid) x-show="!compact" @endif>
                                        <td class="px-4 py-4">
                                            @if($selecionavel)
                                                <input type="checkbox" name="services[]" value="{{ $service->id }}" x-model="selected"
                                                       class="rounded border-gray-300 dark:border-gray-600 text-[#A00001] focus:ring-[#A00001] dark:bg-gray-700">
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 font-semibold text-gray-900 dark:text-white">{{ $service->freelancer->name }}</td>
                                        <td class="px-4 py-4 text-gray-700 dark:text-gray-300" x-show="!compact">{{ $service->functionFreelancer->name }}</td>
                                        <td class="px-4 py-4 text-gray-700 dark:text-gray-300" x-show="!compact">
                                            {{ $service->location ?? '—' }}
                                            @if(filled($service->description))
                                                <span class="block max-w-[16rem] truncate text-xs text-gray-400 dark:text-gray-500" title="{{ $service->description }}">{{ $service->description }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                            {{ $service->start_date->format('d/m/Y') }}
                                            <span class="text-gray-400 dark:text-gray-500">
                                                {{ substr($service->start_time, 0, 5) }}–{{ substr($service->end_time, 0, 5) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-gray-700 dark:text-gray-300" x-show="!compact">{{ $service->formattedDuration() }}</td>
                                        <td class="px-4 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                            <span class="font-mono">{{ $service->freelancer->pix_key ?? '—' }}</span>
                                            @if($service->freelancer->pix_key)
                                                <button type="button" data-pix="{{ $service->freelancer->pix_key }}"
                                                        @click="navigator.clipboard.writeText($el.dataset.pix)"
                                                        title="Copiar chave PIX"
                                                        class="ml-1 text-gray-400 hover:text-[#A00001] dark:hover:text-red-400 transition align-middle">
                                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                                </button>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 font-semibold text-gray-900 dark:text-white whitespace-nowrap">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap" x-show="!compact">
                                            @if($paid)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Pago</span>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $service->paid_at?->format('d/m/Y H:i') }}
                                                    @if($service->paidBy)
                                                        · {{ $service->paidBy->name }}
                                                    @endif
                                                </p>
                                                @if($pix?->end_to_end_id)
                                                    <p class="mt-0.5 text-[10px] font-mono text-gray-400 dark:text-gray-500" title="Identificador fim a fim da transação no Pix">
                                                        {{ $pix->end_to_end_id }}
                                                    </p>
                                                @endif
                                            {{-- Estados do Pix. A distinção que a tela precisa passar é entre
                                                 "não saiu" (rejeitado/falhou, pode refazer) e "não sabemos"
                                                 (conferir no banco, NÃO refazer). --}}
                                            @elseif($pix && $pix->status === \App\Models\PixPayment::STATUS_UNKNOWN)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Conferir no banco</span>
                                                <p class="mt-1 max-w-[18rem] text-xs text-red-600 dark:text-red-400">
                                                    A resposta do banco não chegou e o Pix pode ter sido feito. Não refaça a baixa — avise a TI.
                                                </p>
                                            @elseif($pix && $pix->isPending())
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">{{ $pix->statusLabel() }}</span>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pix enviado {{ $pix->created_at?->format('d/m/Y H:i') }}</p>
                                            @elseif($pix && in_array($pix->status, [\App\Models\PixPayment::STATUS_REJECTED, \App\Models\PixPayment::STATUS_FAILED], true))
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">{{ $pix->statusLabel() }}</span>
                                                <p class="mt-1 max-w-[18rem] text-xs text-red-600 dark:text-red-400">
                                                    {{ $pix->rejection_detail ?: 'O banco não concluiu a transferência.' }}
                                                    <span class="text-gray-500 dark:text-gray-400">Nada foi transferido; pode tentar de novo.</span>
                                                </p>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Pendente</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-right space-x-3 whitespace-nowrap">
                                            <a href="{{ route('freelancer-services.show', $service) }}" x-show="!compact"
                                               class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium text-xs">Ver</a>
                                            @if($selecionavel)
                                                {{-- A confirmação muda de texto quando o clique move dinheiro:
                                                     ela precisa dizer o valor e para quem vai. --}}
                                                <button type="submit" name="only" value="{{ $service->id }}"
                                                        onclick="return confirm(@js($pixEnabled
                                                            ? 'ENVIAR PIX de R$ ' . number_format((float) $service->price, 2, ',', '.')
                                                              . ' para ' . $service->freelancer->name . ' (chave ' . $service->freelancer->pix_key . ')?'
                                                              . "\n\n" . 'O dinheiro sai da conta do clube. A ação fica registrada com seu usuário.'
                                                            : 'Confirmar a baixa de pagamento deste contrato? A ação fica registrada com seu usuário.'))"
                                                        class="inline-flex items-center px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition">
                                                    {{ $pixEnabled ? 'Pagar via Pix' : 'Dar baixa' }}
                                                </button>
                                            @elseif($pixEnabled && $service->hasPixInProgress() && !$paid)
                                                <span class="text-xs text-gray-400 dark:text-gray-500">Pix em andamento</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Barra de ação em lote: fica fixa no rodapé enquanto houver seleção. --}}
                <div x-show="selected.length > 0" x-cloak
                     class="sticky bottom-4 mt-4 flex justify-center">
                    <div class="flex items-center gap-4 px-4 py-3 bg-gray-900 dark:bg-gray-700 text-white rounded-2xl shadow-2xl">
                        <span class="text-sm font-semibold">
                            <span x-text="selected.length"></span> contrato(s) ·
                            <span x-text="formatMoney(selectedTotal)"></span>
                        </span>
                        <button type="submit"
                                class="px-4 py-2 bg-emerald-600 rounded-lg text-sm font-bold hover:bg-emerald-700 transition">
                            {{ $pixEnabled ? 'Pagar via Pix os selecionados' : 'Dar baixa nos selecionados' }}
                        </button>
                        <button type="button" @click="selected = []"
                                class="text-sm text-gray-300 hover:text-white transition">Limpar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
