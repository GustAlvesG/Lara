<x-app-layout>
@php $locked = !$service->canBeUpdated(); @endphp

<div class="py-6">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        {{-- Aditivo e contrato base andam sempre em par: quem abre um precisa
             chegar ao outro num clique, e saber qual dos dois é o que vale. --}}
        @if($service->isCommissionAmendment())
            <div class="p-4 bg-ok-soft border border-ok/40 text-ok rounded-lg text-sm font-medium">
                💰 Este documento é uma <b>comissão de venda</b>, paga <b>além</b> do contrato do turno — não no lugar dele.
                <span class="block mt-1 font-normal">
                    Vendas apuradas: <b>R$ {{ number_format((float) $service->sales_amount, 2, ',', '.') }}</b>
                    · Critério: {{ $service->commissionMethodLabel() }}
                    · Conta: {{ $service->commissionExplanation() }}
                    @if($service->sales_source === \App\Models\FreelancerService::SALES_SOURCE_MANUAL)
                        <span class="opacity-75">(valor informado manualmente no tablet)</span>
                    @endif
                    {{-- Valor alterado em relação ao relatório: o motivo consta do
                         termo assinado, e aqui também — é onde se confere o documento. --}}
                    @if($service->salesAmountWasAdjusted())
                        <br>Valor ajustado em relação ao apurado
                        (R$ {{ number_format((float) ($service->sales_report['base'] ?? 0), 2, ',', '.') }}).
                        Justificativa: <b>{{ $service->sales_adjustment_reason ?: 'não informada' }}</b>
                    @endif
                    @if($service->baseService)
                        <br>Contrato do turno:
                        <a href="{{ route('freelancer-services.show', $service->baseService) }}" class="underline font-semibold">#{{ $service->baseService->id }}</a>
                        — {{ substr($service->baseService->start_time, 0, 5) }} às {{ substr($service->baseService->end_time, 0, 5) }}
                        · R$ {{ number_format((float) $service->baseService->price, 2, ',', '.') }}, que continua sendo pago.
                    @endif
                </span>
            </div>
        @elseif($service->isAmendment())
            <div class="p-4 bg-grena-tint border border-grena/40 text-grena-ink rounded-lg text-sm font-medium">
                📄 Este contrato é um <b>aditivo</b>{{ $service->amendmentOrder() > 1 ? ' (' . $service->amendmentOrder() . 'º termo)' : '' }}
                e é ele que paga o turno — o contrato original continua sendo assinado, mas não vai ao financeiro.
                @if($service->baseService)
                    <span class="block mt-1 font-normal">
                        Altera o contrato
                        <a href="{{ route('freelancer-services.show', $service->baseService) }}" class="underline font-semibold">#{{ $service->baseService->id }}</a>,
                        de {{ substr($service->baseService->start_time, 0, 5) }} às {{ substr($service->baseService->end_time, 0, 5) }}
                        em {{ $service->baseService->location }}
                        @if($service->amendmentDurationChange()) ({{ $service->amendmentDurationChange() }}) @endif.
                    </span>
                @endif
            </div>
        @endif

        @if($service->isAmended())
            <div class="p-4 bg-subtle border border-line-strong text-ink rounded-lg text-sm font-medium">
                ↪️ Este contrato recebeu um <b>aditivo</b> em {{ $service->amended_at->format('d/m/Y H:i') }}.
                Ele <b>continua sendo assinado normalmente</b> pelas duas partes e permanece no histórico como o
                documento firmado — o que muda é o pagamento: não entra em lote nem no financeiro, para o mesmo
                turno não ser pago duas vezes.
                @if($service->activeAmendment)
                    <span class="block mt-1 font-normal">
                        Quem paga o turno é o aditivo
                        <a href="{{ route('freelancer-services.show', $service->activeAmendment) }}" class="underline font-semibold">#{{ $service->activeAmendment->id }}</a>:
                        {{ substr($service->activeAmendment->start_time, 0, 5) }} às {{ substr($service->activeAmendment->end_time, 0, 5) }}
                        · R$ {{ number_format($service->activeAmendment->price, 2, ',', '.') }}.
                    </span>
                @endif
            </div>
        @endif

        @if($exceedsWeeklyLimit)
            <div class="p-4 bg-warn-soft border border-warn/40 text-warn rounded-lg text-sm font-medium">
                ⚠️ Este freelancer possui mais de {{ \App\Models\FreelancerService::WEEKLY_LIMIT }} serviços registrados numa janela de {{ \App\Models\FreelancerService::WEEKLY_WINDOW_DAYS }} dias em torno desta data.
                {{-- Quem liberou o excedente. Contratos anteriores à regra não têm o registro.
                     Sem autor: veio do código de e-mail, que vai para todos os
                     coordenadores do Comercial e não identifica qual deles ditou. --}}
                @if($service->weekly_limit_authorized_at)
                    <span class="block mt-1 font-normal">
                        @if($service->weeklyLimitAuthorizedBy)
                            Liberado por {{ $service->weeklyLimitAuthorizedBy->name }}
                            (coordenação do setor Comercial, PIN presencial)
                        @else
                            Liberado por código enviado aos coordenadores do setor Comercial
                        @endif
                        em {{ $service->weekly_limit_authorized_at->format('d/m/Y H:i') }}.
                    </span>
                @endif
            </div>
        @endif

        {{-- Contrato assinado depois de o turno já ter começado. A tolerância de
             30 min já está descontada; o tempo mostrado é o atraso em relação ao
             início. Só na web: o tablet é onde a assinatura acontece, e cobrar o
             atraso ali não muda mais nada. --}}
        @if($service->isSignedAfterStart())
            <div class="p-4 bg-danger-soft border border-danger/40 text-danger rounded-lg text-sm font-medium">
                🕒 Contrato assinado <b>após o início do serviço</b>.
                O turno começou em {{ $service->startsAt()->format('d/m/Y H:i') }} e a assinatura do
                freelancer foi registrada em {{ $service->freelancer_signed_at->format('d/m/Y H:i') }} —
                <b>{{ $service->formattedSignatureDelay() }} depois</b>.
                <span class="block mt-1 font-normal">
                    O contrato deve ser assinado antes do início, com tolerância de
                    {{ \App\Models\FreelancerService::SIGNATURE_TOLERANCE_MINUTES }} minutos.
                </span>
            </div>
        @endif

        {{-- O caso vizinho, e mais grave: o turno começou e o freelancer nunca
             assinou — o serviço está sendo prestado sem contrato firmado. --}}
        @if($service->isUnsignedAfterStart())
            <div class="p-4 bg-danger-soft border border-danger/40 text-danger rounded-lg text-sm font-medium">
                ⏳ Contrato <b>sem assinatura do freelancer</b> e o turno já começou.
                O início foi em {{ $service->startsAt()->format('d/m/Y H:i') }} —
                <b>há {{ $service->formattedTimeSinceStart() }}</b>.
                <span class="block mt-1 font-normal">
                    Colha a assinatura no tablet (Kiosk → atendimento) assim que possível: sem ela, o
                    serviço foi prestado sem contrato firmado, e o contrato não entra em lote nem é pago.
                </span>
            </div>
        @endif

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('freelancer-services.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    {{-- O número do documento primeiro: é por ele que a gerência,
                         o financeiro e o e-mail da diretoria se referem a este
                         contrato. --}}
                    <p class="text-sm font-bold text-ink-3 font-mono">
                        {{ $service->isAmendment() ? 'Termo aditivo' : 'Contrato' }} #{{ $service->id }}
                    </p>
                    {{-- O nome leva ao cadastro do freelancer: é de lá que se
                         corrige um dado que o contrato apenas reproduz. --}}
                    <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">
                        <a href="{{ route('freelancers.show', $service->freelancer) }}"
                           title="Abrir o cadastro de {{ $service->freelancer->name }}"
                           class="inline-flex items-center gap-2 hover:text-grena-ink hover:underline decoration-2 underline-offset-4 transition">
                            {{ $service->freelancer->name }}
                            <svg class="w-5 h-5 shrink-0 text-ink-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        </a>
                    </h1>
                    <p class="text-ink-2 font-medium">
                        {{ $service->functionFreelancer->name }} · {{ $service->location }}
                    </p>
                    <p class="text-sm text-ink-2">
                        {{ $service->formattedPeriod() }} ({{ $service->formattedDuration() }})
                    </p>
                    <p class="text-xs text-ink-3 mt-1">
                        @if($service->createdBy)Cadastrado por {{ $service->createdBy->name }}@endif
                        @if($service->updatedBy && $service->updated_by !== $service->created_by) · Atualizado por {{ $service->updatedBy->name }}@endif
                    </p>
                </div>
            </div>

            <x-freelancer-signature-badge :service="$service" />
        </div>

        {{-- Painel de assinaturas --}}
        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            <div class="p-6 border-b border-line bg-subtle">
                <h2 class="text-lg font-bold text-ink">Assinaturas do Contrato</h2>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 rounded-xl border {{ $service->freelancer_signed_at ? 'border-ok/40 bg-ok-soft' : 'border-line' }}">
                    <p class="text-sm font-bold text-ink">Freelancer</p>
                    @if($service->freelancer_signed_at)
                        <p class="text-sm text-ok font-semibold mt-1">
                            ✓ Assinado em {{ $service->freelancer_signed_at->format('d/m/Y H:i') }}
                        </p>
                        @if($service->freelancerSignedBy)
                            <p class="text-xs text-ink-2 mt-1">
                                Atendimento conduzido por {{ $service->freelancerSignedBy->name }}.
                            </p>
                        @endif
                    @else
                        <p class="text-sm text-ink-2 mt-1">Aguardando assinatura (feita pelo bot no Telegram).</p>
                    @endif
                </div>

                @php $validaPelaWeb = $service->usesDirectorSignature(); @endphp
                <div class="p-4 rounded-xl border {{ $service->coordinator_signed_at ? 'border-ok/40 bg-ok-soft' : 'border-line' }}">
                    <p class="text-sm font-bold text-ink">
                        {{ $validaPelaWeb ? 'Validação da coordenação' : 'Coordenador' }}
                    </p>
                    @if($service->coordinator_signed_at)
                        <p class="text-sm text-ok font-semibold mt-1">
                            ✓ {{ $validaPelaWeb ? 'Validado' : 'Assinado' }} por {{ $service->coordinatorSignedBy?->name ?? '—' }}
                            em {{ $service->coordinator_signed_at->format('d/m/Y H:i') }}
                        </p>
                        @if($validaPelaWeb)
                            <p class="text-xs text-ink-2 mt-1">
                                Validação pela web, com PIN. Não entra no documento — pelo CONTRATANTE assina a diretoria.
                            </p>
                        @endif
                    @else
                        <p class="text-sm text-ink-2 mt-1">
                            @if($service->isCancelled())
                                Contrato cancelado.
                            @elseif($validaPelaWeb)
                                {{-- Redação 2: a coordenação valida pela web, contrato a contrato. --}}
                                Aguardando a validação do coordenador do setor Comercial,
                                feita <b>pela web</b> (Serviços / Contratos → Validação).
                                @if($service->awaitsRelease())
                                    Entra na fila em {{ $service->releasesAt()->format('d/m/Y \à\s H:i') }}.
                                @endif
                            @else
                                {{-- A assinatura do coordenador é sempre desenhada; o painel só acompanha. --}}
                                Aguardando assinatura do coordenador do setor Comercial,
                                feita <b>no tablet</b> (Kiosk → modo coordenador).
                            @endif
                        </p>
                    @endif
                </div>

                {{-- Redação 2: pelo CONTRATANTE assina a diretoria, com a imagem do
                     cadastro, quando aprova o lote — ou, no contrato que ganhou
                     aditivo, quando aprova o aditivo que o substituiu. --}}
                @if($validaPelaWeb)
                    <div class="md:col-span-2 p-4 rounded-xl border {{ $service->hasDirectorSignature() ? 'border-ok/40 bg-ok-soft' : 'border-line' }}">
                        <p class="text-sm font-bold text-ink">Diretoria (assina pelo CONTRATANTE)</p>
                        @if($service->hasDirectorSignature())
                            <p class="text-sm text-ok font-semibold mt-1">
                                ✓ Assinado digitalmente por {{ $service->director?->name ?? 'Diretoria' }}
                                em {{ $service->director_signed_at->format('d/m/Y H:i') }}
                            </p>
                        @elseif($service->isCancelled())
                            <p class="text-sm text-ink-2 mt-1">Contrato cancelado.</p>
                        @elseif($service->isAmended())
                            <p class="text-sm text-ink-2 mt-1">
                                Este contrato não vai a lote: recebe a assinatura da diretoria quando o aditivo que o
                                substituiu for aprovado.
                            </p>
                        @else
                            <p class="text-sm text-ink-2 mt-1">
                                Aplicada ao documento quando a diretoria aprovar o lote deste contrato.
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Chave PIX do pagamento. Fica junto das assinaturas porque é isso
                 que ela é: um dado conferido no ato de assinar, e citado no
                 documento. Quando o cadastro muda depois, as duas chaves
                 aparecem lado a lado — o financeiro precisa saber qual delas o
                 Pix vai usar antes de dar a baixa. --}}
            <div class="px-6 pb-6">
                <div class="p-4 rounded-xl border border-line">
                    <p class="text-sm font-bold text-ink">Pagamento via PIX</p>
                    <p class="mt-1 text-sm text-ink">
                        {{ $service->pixKeyTypeLabel() }}:
                        <span class="font-mono font-semibold">{{ $service->pixKeyFormatted() ?: '—' }}</span>
                    </p>
                    @if($service->pixKeyWasConfirmed())
                        <p class="mt-1 text-xs text-ok">
                            ✓ Conferida com o freelancer no tablet em
                            {{ $service->pix_key_confirmed_at->format('d/m/Y H:i') }}, antes da assinatura.
                        </p>
                    @elseif($service->freelancer_signed_at)
                        <p class="mt-1 text-xs text-ink-2">
                            Contrato assinado sem a conferência da chave no tablet — é o caso dos contratos
                            anteriores a essa etapa e dos assinados pela API.
                        </p>
                    @else
                        <p class="mt-1 text-xs text-ink-2">
                            Chave atual do cadastro. O freelancer a confere no tablet antes de assinar.
                        </p>
                    @endif
                    @if($service->pixKeyDivergesFromFreelancer())
                        <div class="mt-3 p-3 rounded-lg bg-warn-soft border border-warn/40 text-xs text-warn">
                            <b>O cadastro mudou depois da assinatura.</b>
                            Hoje o cadastro tem a chave
                            <span class="font-mono">{{ $service->freelancer->pixKeyFormatted() }}</span>,
                            e é para ela que o Pix vai — o documento assinado cita a outra. Confirme a mudança com o
                            freelancer antes de dar a baixa.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Jantar do turno noturno. Aparece só onde a regra alcança: turno de
                 6h ou mais que alcança a janela de 17:30 às 18:30. O painel não
                 responde nem corrige a resposta — quem responde é o freelancer,
                 no tablet; aqui se confere o que a cozinha vai receber. --}}
            @if($service->isDinnerEligible())
                <div class="px-6 pb-6">
                    <div class="p-4 rounded-xl border border-line">
                        <p class="text-sm font-bold text-ink">
                            Jantar ({{ \App\Models\FreelancerService::dinnerWindowLabel() }})
                        </p>
                        @if($service->dinnerWasAnswered())
                            <p class="mt-1 text-sm {{ $service->wantsDinner() ? 'text-ok font-semibold' : 'text-ink' }}">
                                {{ $service->wantsDinner() ? '🍽️ Vai jantar' : 'Não vai jantar' }}
                                @if($service->dinner_date)
                                    · jantar de {{ $service->dinner_date->format('d/m/Y') }}
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-ink-2">
                                Respondido no tablet
                                @if($service->dinner_answered_at) em {{ $service->dinner_answered_at->format('d/m/Y H:i') }} @endif
                                @if($service->dinnerAnsweredBy) · atendimento conduzido por {{ $service->dinnerAnsweredBy->name }} @endif.
                            </p>
                        @else
                            <p class="mt-1 text-sm text-ink-2">
                                Ainda não respondido. A pergunta é feita no tablet logo depois da assinatura, e
                                continua disponível na lista de contratos do freelancer.
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            @if($service->isPaid())
                <div class="px-6 pb-6">
                    <div class="p-4 rounded-xl bg-ok-soft border border-ok/40 text-sm text-ok">
                        Baixa de pagamento registrada
                        @if($service->paid_at) em {{ $service->paid_at->format('d/m/Y H:i') }} @endif
                        @if($service->paidBy) por {{ $service->paidBy->name }} @endif.
                    </div>
                </div>
            @endif

            @if($service->isCancelled())
                <div class="px-6 pb-6">
                    <div class="p-4 rounded-xl bg-subtle border border-line text-sm text-ink-2">
                        {{-- A falta é um cancelamento com outro motivo: o dia não foi
                             trabalhado e a vaga voltou para a semana do freelancer. Quem
                             lê aqui precisa saber qual das duas coisas aconteceu. --}}
                        {{ $service->isNoShow() ? 'Falta do freelancer — turno não cumprido, registrado' : 'Contrato cancelado' }}
                        @if($service->cancelled_at) em {{ $service->cancelled_at->format('d/m/Y H:i') }} @endif
                        @if($service->cancelledBy) por {{ $service->cancelledBy->name }} @endif.
                        @if($service->isNoShow())
                            Este dia deixou de ocupar vaga no limite semanal.
                        @endif
                    </div>
                </div>
            @elseif($locked)
                <div class="px-6 pb-6">
                    <div class="p-4 rounded-xl bg-warn-soft border border-warn/40 text-sm text-warn">
                        Este contrato já possui assinatura e por isso não pode mais ser alterado nem cancelado.
                    </div>
                </div>
            @endif
        </div>

        {{-- Documento do contrato --}}
        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            <div class="p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-xl bg-grena/10 text-grena-ink shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-ink">Documento do Contrato</h2>
                        <p class="text-xs text-ink-2">
                            Contrato Autônomo de Serviços de Freelancer preenchido com os dados deste serviço
                            @if($service->freelancer_signed_at) e a assinatura registrada @endif.
                        </p>
                    </div>
                </div>
                <a href="{{ route('freelancer-services.document', $service) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    Abrir documento
                </a>
            </div>

            {{-- Redação das cláusulas. O texto do instrumento é revisado pelo
                 jurídico de tempos em tempos; um contrato assinado guarda a
                 redação que firmou e continua sendo impresso com ela. --}}
            <div class="px-6 pb-6">
                <div class="p-4 rounded-xl border border-line">
                    <p class="text-sm font-bold text-ink">{{ $service->contractVersionLabel() }}</p>
                    @if($service->contractIsFrozen())
                        <p class="mt-1 text-xs text-ink-2">
                            Congelada na assinatura: este documento continua sendo impresso com o texto que as partes
                            firmaram, ainda que o modelo mude depois.
                            @if($service->contractPartyIsFrozen())
                                A qualificação do freelancer citada no documento também é a do dia da assinatura.
                            @else
                                A qualificação do freelancer vem do cadastro — este contrato é anterior à cópia dos
                                dados das partes.
                            @endif
                        </p>
                        @if($service->contractPartyDivergesFromFreelancer())
                            <div class="mt-3 p-3 rounded-lg bg-warn-soft border border-warn/40 text-xs text-warn">
                                <b>O cadastro do freelancer mudou depois da assinatura.</b>
                                O documento cita os dados de quando foi assinado, e não os de hoje — é assim que
                                deve ser: o instrumento firmado não se reescreve.
                            </div>
                        @endif
                    @else
                        <p class="mt-1 text-xs text-ink-2">
                            Ainda não congelada. Sem assinatura, o documento acompanha a redação vigente e o cadastro
                            atual do freelancer — congela na primeira assinatura.
                        </p>
                    @endif

                    {{-- Cópia em PDF no servidor de arquivos, feita depois das duas assinaturas. --}}
                    @if($service->isArchived())
                        <p class="mt-3 text-xs text-ink-2">
                            <b>Arquivado no servidor de arquivos</b> em {{ $service->archived_at->format('d/m/Y H:i') }}:<br>
                            <span class="font-mono break-all">{{ $service->archive_path }}</span>
                        </p>
                    @endif
                </div>
            </div>

            @if($service->freelancer_signature_path)
                <div class="px-6 pb-6">
                    <p class="text-xs font-bold text-ink-3 uppercase tracking-wider mb-2">Assinatura do freelancer</p>
                    <div class="inline-block bg-surface border border-line rounded-lg p-2">
                        <img src="{{ route('freelancer-services.signature', ['freelancerService' => $service->id, 'party' => 'freelancer']) }}"
                             alt="Assinatura de {{ $service->freelancer->name }}" class="h-20 w-auto object-contain">
                    </div>
                </div>
            @endif

            {{-- Toda assinatura de coordenador é desenhada no tablet. Contratos antigos,
                 assinados pelo painel antes da mudança, não têm traço. --}}
            @if($service->coordinator_signature_path)
                <div class="px-6 pb-6">
                    <p class="text-xs font-bold text-ink-3 uppercase tracking-wider mb-2">Assinatura do coordenador</p>
                    <div class="inline-block bg-surface border border-line rounded-lg p-2">
                        <img src="{{ route('freelancer-services.signature', ['freelancerService' => $service->id, 'party' => 'coordinator']) }}"
                             alt="Assinatura de {{ $service->coordinatorSignedBy->name ?? 'coordenador' }}" class="h-20 w-auto object-contain">
                    </div>
                </div>
            @endif
        </div>

        {{-- Dados do serviço --}}
        <form action="{{ route('freelancer-services.update', $service) }}" method="POST">
            @csrf
            @method('PUT')

            @include('freelancer.services.partials.form', ['locked' => $locked])

            @unless($locked)
                <div class="mt-6 flex justify-end">
                    <button type="submit" class="inline-flex items-center px-6 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition duration-150 transform hover:scale-[1.02]">
                        Salvar Alterações
                    </button>
                </div>
            @endunless
        </form>

        {{-- Ações destrutivas --}}
        @if($canCancel || $service->canBeDeleted())
            <div class="flex flex-wrap justify-end gap-3">
                @if($canCancel)
                    <form method="POST" action="{{ route('freelancer-services.cancel', $service) }}"
                          onsubmit="return confirm('Cancelar este contrato? Essa ação não pode ser desfeita.')">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-sm font-bold text-warn hover:bg-warn-soft rounded-lg transition border border-warn/40">
                            Cancelar Contrato
                        </button>
                    </form>
                @endif

                @if($service->canBeDeleted())
                    <form method="POST" action="{{ route('freelancer-services.destroy', $service) }}"
                          onsubmit="return confirm('Excluir este serviço permanentemente?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 text-sm font-bold text-danger hover:bg-danger-soft rounded-lg transition">
                            Excluir Serviço
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</div>
</x-app-layout>
