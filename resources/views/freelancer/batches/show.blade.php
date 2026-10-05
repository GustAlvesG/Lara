<x-app-layout>
<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">Lote #{{ $batch->id }}</h1>
                <p class="text-ink-2 font-medium">
                    Montado por {{ $batch->createdBy->name ?? '—' }} ·
                    enviado em {{ $batch->sent_at?->format('d/m/Y H:i') ?? 'ainda não enviado' }}
                    @if($batch->isReviewed())
                        · analisado por {{ $batch->reviewedBy->name ?? '—' }} em {{ $batch->reviewed_at?->format('d/m/Y H:i') }}
                    @endif
                </p>
            </div>
            @php
                $tomLote = match (true) {
                    $batch->isDirectorApproved() => 'bg-ok-soft text-ok',
                    $batch->isDirectorRejected(), $batch->isClosed() => 'bg-danger-soft text-danger',
                    default => 'bg-warn-soft text-warn',
                };
            @endphp
            <span class="px-4 py-2 rounded-xl text-sm font-bold {{ $tomLote }}">
                {{ $batch->statusLabel() }}
            </span>
        </div>

        {{-- A tela do lote era um beco sem saída: com as abas dá para voltar a
             qualquer frente sem passar pelo menu. O gerente chegou aqui pela
             Aprovação; o coordenador, pelos Lotes. --}}
        @include('freelancer.services.partials.tabs', [
            'activeTab' => $isManager ? 'freelancer-batches.queue' : 'freelancer-batches.index',
        ])

        @include('partials.alerts')

        @if($errors->any())
            <div class="mb-6 bg-danger text-white dark:text-canvas px-6 py-4 rounded-2xl shadow-pop">
                <p class="font-extrabold">Confira o formulário</p>
                <ul class="mt-2 text-sm list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $total = $batch->services->sum('price');
            $aprovadosPelaGerencia = $batch->services->filter->isManagerApproved();
            $comissoes = $batch->services->filter->isCommissionAmendment();
        @endphp

        {{-- ============ DIRETORIA ============ --}}
        @if($batch->isAwaitingDirector())
            <div class="bg-surface rounded-2xl shadow-card border-2 border-warn/40 mb-8 overflow-hidden">
                <div class="px-6 py-5 bg-warn-soft border-b border-warn/40">
                    <h2 class="text-lg font-extrabold text-ink">Aprovação da diretoria</h2>
                    <p class="text-sm text-ink-2 mt-1">
                        {{ $aprovadosPelaGerencia->count() }} contrato(s) ·
                        R$ {{ number_format($aprovadosPelaGerencia->sum('price'), 2, ',', '.') }}
                        aguardando o aval do diretor.
                    </p>
                </div>

                <div class="px-6 py-5">
                    @if($batch->director_notified_at)
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-5 text-sm">
                            <p class="text-ink-2">
                                E-mail enviado para <b>{{ $batch->director_email }}</b>
                                em {{ $batch->director_notified_at->format('d/m/Y H:i') }}.
                            </p>
                            <form action="{{ route('freelancer-batches.director.notify', $batch) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-xs font-bold text-grena-ink hover:underline">
                                    Reenviar e-mail
                                </button>
                            </form>
                        </div>

                        <form action="{{ route('freelancer-batches.director.decision', $batch) }}" method="POST"
                              onsubmit="return confirm('Registrar a decisão da diretoria? A decisão é definitiva.')">
                            @csrf
                            <p class="text-sm text-ink-2 mb-4">
                                O diretor recebeu <b>dois códigos</b> por e-mail: um aprova, o outro recusa o lote
                                inteiro. Peça a ele o código e digite abaixo — o próprio código diz qual foi a decisão.
                            </p>

                            <div class="flex flex-wrap items-end gap-4">
                                <div>
                                    <label for="pin" class="block text-xs font-bold uppercase tracking-wider text-ink-3 mb-1">
                                        Código informado pelo diretor
                                    </label>
                                    <input type="text" id="pin" name="pin" inputmode="numeric" autocomplete="off"
                                           maxlength="{{ \App\Models\FreelancerServiceBatch::PIN_LENGTH }}"
                                           pattern="\d{{ '{' . \App\Models\FreelancerServiceBatch::PIN_LENGTH . '}' }}"
                                           placeholder="{{ str_repeat('•', \App\Models\FreelancerServiceBatch::PIN_LENGTH) }}" required
                                           class="w-48 rounded-xl border-line-strong
                                                  text-2xl font-extrabold tracking-[0.4em] text-center tabular-nums
                                                  focus:border-grena focus:ring-grena-tint">
                                </div>
                                <div class="flex-1 min-w-[220px]">
                                    <label for="note" class="block text-xs font-bold uppercase tracking-wider text-ink-3 mb-1">
                                        Observação (opcional)
                                    </label>
                                    <input type="text" id="note" name="note" maxlength="255"
                                           placeholder="Ex.: informado por telefone em {{ now()->format('d/m') }}"
                                           class="w-full rounded-xl border-line-strong text-sm
                                                  focus:border-grena focus:ring-grena-tint">
                                </div>
                                <button type="submit"
                                        class="px-6 py-3 rounded-xl text-sm font-bold text-white bg-grena hover:bg-[#7c0001] shadow-card transition">
                                    Registrar decisão
                                </button>
                            </div>
                        </form>
                    @else
                        <p class="text-sm text-ink-2 mb-4">
                            A diretoria ainda <b>não foi avisada</b> deste lote.
                            @if($director)
                                O e-mail com os códigos de aprovação vai para <b>{{ $director->name }}</b>
                                (<b>{{ $director->email }}</b>).
                            @else
                                <span class="text-grena-ink">
                                    Nenhum diretor cadastrado — cadastre nome, e-mail e assinatura na aba
                                    <a href="{{ route('freelancer-director.edit') }}" class="underline font-bold">Diretoria</a>.
                                </span>
                            @endif
                        </p>
                        {{-- Contratos da redação 2 são assinados pela diretoria na
                             aprovação: sem a imagem cadastrada não há o que aplicar. --}}
                        @if($directorSignatureMissing)
                            <p class="text-sm text-grena-ink mb-4">
                                Este lote tem contratos assinados pela diretoria (redação 2), e o cadastro está sem a
                                imagem da assinatura. Envie-a na aba
                                <a href="{{ route('freelancer-director.edit') }}" class="underline font-bold">Diretoria</a>
                                antes de enviar o lote.
                            </p>
                        @endif
                        <form action="{{ route('freelancer-batches.director.notify', $batch) }}" method="POST">
                            @csrf
                            <button type="submit" @disabled(!$director || $directorSignatureMissing)
                                    class="px-6 py-3 rounded-xl text-sm font-bold text-white bg-grena hover:bg-[#7c0001] shadow-card transition
                                           disabled:opacity-40 disabled:cursor-not-allowed">
                                Enviar à diretoria
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @elseif($batch->isDirectorApproved() || $batch->isDirectorRejected())
            <div class="bg-surface rounded-2xl shadow-card border border-line mb-8 px-6 py-5">
                <h2 class="text-lg font-extrabold text-ink mb-1">Decisão da diretoria</h2>
                <p class="text-sm text-ink-2">
                    <span class="font-bold {{ $batch->isDirectorApproved() ? 'text-ok' : 'text-grena-ink' }}">
                        {{ $batch->isDirectorApproved() ? 'Aprovado' : 'Recusado' }}
                    </span>
                    em {{ $batch->director_decided_at?->format('d/m/Y H:i') }},
                    código informado por {{ $batch->directorDecidedBy->name ?? '—' }}
                    @if($batch->director_email) · e-mail enviado a {{ $batch->director_email }} @endif
                </p>
                @if($batch->director_note)
                    <p class="text-sm text-ink-2 mt-2"><b>Observação:</b> {{ $batch->director_note }}</p>
                @endif
            </div>
        @endif

        <form action="{{ route('freelancer-batches.review', $batch) }}" method="POST">
            @csrf

            <div class="bg-surface rounded-2xl shadow-card border border-line overflow-hidden">
                <div class="px-6 py-5 border-b border-line flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-extrabold text-ink">{{ $batch->services->count() }} contrato(s)</h2>
                        <p class="text-sm text-ink-2">Total do lote: R$ {{ number_format($total, 2, ',', '.') }}</p>
                        @if($comissoes->isNotEmpty())
                            <p class="mt-1 text-sm font-bold text-ok">
                                {{ $comissoes->count() }} termo(s) de comissão de venda ·
                                R$ {{ number_format($comissoes->sum('price'), 2, ',', '.') }} —
                                pagos <b>além</b> do contrato do turno.
                            </p>
                        @endif
                    </div>
                    @if($canReview)
                        <p class="text-sm text-ink-2">
                            Tudo começa aprovado. Marque <b>Recusar</b> só no que voltar para o coordenador.
                        </p>
                    @endif
                </div>

                <div class="divide-y divide-line">
                    @foreach($batch->services as $service)
                        <div class="px-6 py-5" x-data="{ decision: '{{ $service->isManagerRejected() ? 'reject' : 'approve' }}' }">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-extrabold text-ink">
                                        <span class="font-mono text-xs font-normal text-ink-3">#{{ $service->id }}</span>
                                        {{ $service->freelancer->name ?? '—' }}
                                        <x-freelancer-kind-badge :service="$service" class="ml-1 align-middle" />
                                    </p>
                                    <p class="text-sm text-ink-2">
                                        {{ $service->functionFreelancer->name ?? '—' }} · {{ $service->location }}
                                    </p>
                                    {{-- Na comissão a duração é do turno, não do que se está pagando:
                                         some, para não sugerir que o valor foi calculado por hora. --}}
                                    <p class="text-xs text-ink-3 tabular-nums mt-1">
                                        {{ $service->formattedPeriod() }}
                                        @unless($service->isCommissionAmendment()) · {{ $service->formattedDuration() }} @endunless
                                    </p>
                                    @if($service->kindNote())
                                        <p class="mt-2 text-xs rounded-lg px-3 py-2 border
                                                  {{ $service->isCommissionAmendment()
                                                      ? 'text-ok bg-ok-soft border-ok/40'
                                                      : 'text-grena-ink bg-grena-tint border-grena/40' }}">
                                            {{ $service->kindNote() }}
                                            @if($service->isCommissionAmendment() && $service->hasSalesReport())
                                                Vendas apuradas no MultiVendas sob o login
                                                <b>{{ $service->sales_login }}</b>{{ $service->salesPeriodLabel() ? ' (' . $service->salesPeriodLabel() . ')' : '' }},
                                                com relatório anexo ao termo.
                                                @if($service->salesAmountWasAdjusted())
                                                    <b>O valor de vendas foi ajustado à mão</b> em relação ao apurado
                                                    (R$ {{ number_format((float) ($service->sales_report['base'] ?? 0), 2, ',', '.') }}).
                                                    {{-- A justificativa do operador é o que a gerência precisa para
                                                         julgar o ajuste; ela consta do termo assinado. --}}
                                                    Justificativa: <b>{{ $service->sales_adjustment_reason ?: 'não informada' }}</b>.
                                                @endif
                                            @elseif($service->isCommissionAmendment())
                                                Vendas informadas manualmente no encerramento do expediente, sem relatório do MultiVendas.
                                            @endif
                                        </p>
                                    @endif
                                    @if(filled($service->description))
                                        <p class="text-xs text-ink-2 mt-2 whitespace-pre-line bg-subtle border border-line rounded-lg px-3 py-2">
                                            <span class="font-bold text-ink-3 uppercase tracking-wide text-[10px] block mb-0.5">Descrição / justificativa</span>
                                            {{ $service->description }}
                                        </p>
                                    @endif
                                    <a href="{{ route('freelancer-services.document', $service) }}" target="_blank"
                                       class="inline-block mt-2 text-xs font-bold text-grena-ink hover:underline">
                                        {{ $service->isAmendment() ? 'Abrir termo assinado' : 'Abrir contrato assinado' }}
                                    </a>
                                </div>

                                <div class="text-right shrink-0">
                                    <p class="text-xl font-extrabold text-ink tabular-nums">
                                        R$ {{ number_format($service->price, 2, ',', '.') }}
                                    </p>

                                    @if($canReview)
                                        <div class="mt-3 inline-flex rounded-xl border border-line overflow-hidden">
                                            <label class="px-4 py-2 text-sm font-bold cursor-pointer transition"
                                                   :class="decision === 'approve' ? 'bg-ok text-white dark:text-canvas' : 'text-ink-2'">
                                                <input type="radio" class="sr-only" value="approve" x-model="decision"
                                                       name="decisions[{{ $service->id }}][decision]">
                                                Aprovar
                                            </label>
                                            <label class="px-4 py-2 text-sm font-bold cursor-pointer transition"
                                                   :class="decision === 'reject' ? 'bg-grena text-white' : 'text-ink-2'">
                                                <input type="radio" class="sr-only" value="reject" x-model="decision"
                                                       name="decisions[{{ $service->id }}][decision]">
                                                Recusar
                                            </label>
                                        </div>
                                    @else
                                        @php
                                            $tom = match (true) {
                                                $service->isDirectorApproved() => 'bg-ok-soft text-ok',
                                                $service->isDirectorRejected(), $service->isManagerRejected() => 'bg-danger-soft text-danger',
                                                default => 'bg-warn-soft text-warn',
                                            };
                                        @endphp
                                        <span class="mt-3 inline-block px-3 py-1 rounded-full text-xs font-bold {{ $tom }}">
                                            {{ $service->approvalLabel() }}
                                        </span>
                                        @if($service->isManagerApproved() && $service->managerApprovedBy)
                                            <p class="mt-1 text-xs text-ink-3">gerência: {{ $service->managerApprovedBy->name }}</p>
                                        @elseif($service->isManagerRejected() && $service->managerRejectedBy)
                                            <p class="mt-1 text-xs text-ink-3">gerência: {{ $service->managerRejectedBy->name }}</p>
                                        @endif
                                    @endif
                                </div>
                            </div>

                            @if($canReview)
                                <div class="mt-3" x-show="decision === 'reject'" x-cloak>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-ink-3 mb-1">
                                        Motivo da recusa
                                    </label>
                                    <input type="text" name="decisions[{{ $service->id }}][reason]" maxlength="255"
                                           value="{{ old('decisions.' . $service->id . '.reason', $service->manager_rejection_reason) }}"
                                           placeholder="Ex.: horário divergente do informado pelo evento"
                                           class="w-full rounded-xl border-line-strong text-sm focus:border-grena focus:ring-grena-tint">
                                    <p class="mt-1 text-xs text-ink-3">O coordenador vê este motivo ao remontar o lote.</p>
                                </div>
                            @elseif($service->isManagerRejected() && $service->manager_rejection_reason)
                                <p class="mt-3 text-sm text-danger">
                                    <b>Motivo da recusa:</b> {{ $service->manager_rejection_reason }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($canReview)
                    <div class="px-6 py-5 border-t border-line flex items-center justify-end gap-3">
                        <a href="{{ route('freelancer-batches.queue') }}"
                           class="px-4 py-2.5 rounded-xl text-sm font-bold text-ink-2 hover:bg-subtle transition">
                            Voltar
                        </a>
                        <button type="submit"
                                onclick="return confirm('Concluir a análise do lote? A decisão é definitiva.')"
                                class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-grena hover:bg-[#7c0001] shadow-card transition">
                            Concluir análise do lote
                        </button>
                    </div>
                @endif
            </div>
        </form>

    </div>
</div>
</x-app-layout>
