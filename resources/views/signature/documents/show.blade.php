<x-app-layout :bootstrap-grid="false">
<div>
    <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-5 px-4 pb-12 pt-6 sm:px-6 lg:px-8">
        <x-page-title :title="$document->title" :back="\App\Support\DocumentRoutes::route('index')"></x-page-title>

        @include('partials.alerts')

        {{--
            Andamento do documento (exibição, 11/11/2026): em que fase ele está
            (rascunho → liberado → assinado → finalizado) e quantos já assinaram,
            antes de qualquer detalhe. Recusado, cancelado ou vencido marcam a
            fase em que pararam.
        --}}
        @php
            $D = \App\Models\SignatureDocument::class;
            $S = \App\Models\SignatureSigner::class;
            $fases = ['Rascunho', 'Liberado para assinar', 'Assinado', 'Finalizado'];
            $parou = in_array($document->status, [$D::STATUS_REFUSED, $D::STATUS_CANCELED, $D::STATUS_EXPIRED], true);
            $fase = match (true) {
                $document->status === $D::STATUS_FINALIZED => $document->awaitsReview() ? 2 : 3,
                $document->status === $D::STATUS_SIGNED => 2,
                $document->status === $D::STATUS_AWAITING_SIGNATURE => 1,
                $parou => $document->isFrozen() ? 1 : 0,
                default => 0,
            };
            $totalSign = $document->signers->count();
            $assinaram = $document->signers->where('status', $S::STATUS_SIGNED)->count();
        @endphp
        <section class="mod-band mod-band--pen rounded-[28px] shadow-card" aria-label="Andamento do documento">
            <div class="auth-enter flex flex-col gap-7 p-6 sm:p-8">
                <div class="flex flex-wrap items-end justify-between gap-6">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/60">
                            {{ $document->template?->name ?? 'Documento enviado pronto' }}{{ $document->validation_code ? ' · ' . $document->validation_code : '' }}
                        </p>
                        <p class="mt-2 font-display text-[26px] font-semibold leading-tight tracking-tight sm:text-[32px]">{{ $document->statusLabel() }}</p>
                    </div>
                    @if($totalSign)
                        <div class="w-full max-w-xs">
                            <p class="font-mono text-[34px] font-semibold leading-none">{{ $assinaram }}<span class="text-white/50">/{{ $totalSign }}</span></p>
                            <p class="mt-1 text-sm text-white/70">{{ $totalSign === 1 ? 'assinatura' : 'assinaturas' }}</p>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/15" role="meter" aria-label="Assinaturas colhidas" aria-valuemin="0" aria-valuemax="{{ $totalSign }}" aria-valuenow="{{ $assinaram }}">
                                <div class="h-full rounded-full bg-[rgb(var(--pen-glow))]" style="width: {{ $assinaram / $totalSign * 100 }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>

                <ol class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Fases do documento">
                    @foreach($fases as $i => $nomeFase)
                        @php
                            $estadoFase = $parou && $i === $fase ? 'parou' : ($i < $fase || ($i === $fase && $i === 3) ? 'feito' : ($i === $fase ? 'atual' : 'depois'));
                        @endphp
                        <li class="flex items-center gap-2.5" @if($i === $fase) aria-current="step" @endif>
                            <span @class([
                                'grid h-8 w-8 shrink-0 place-items-center rounded-full font-mono text-xs font-bold',
                                'bg-[rgb(134_239_172)] text-[rgb(var(--pen-ink))]' => $estadoFase === 'feito',
                                'bg-white text-[rgb(var(--pen-ink))] ring-4 ring-white/25' => $estadoFase === 'atual',
                                'bg-[rgb(252_165_165)] text-[rgb(var(--pen-ink))]' => $estadoFase === 'parou',
                                'bg-white/10 text-white/60' => $estadoFase === 'depois',
                            ]) aria-hidden="true">{{ $estadoFase === 'feito' ? '✓' : ($estadoFase === 'parou' ? '✕' : $i + 1) }}</span>
                            <span class="text-sm font-semibold {{ $estadoFase === 'depois' ? 'text-white/55' : 'text-white' }}">{{ $nomeFase }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <span></span>

            <div class="flex flex-wrap gap-3">
                @if($document->isUploaded())
                    <a href="{{ \App\Support\DocumentRoutes::route('pdf', [$document, 'versao' => 'enviado']) }}" target="_blank"
                       class="inline-flex h-11 items-center rounded-full border border-line-strong bg-surface px-5 text-sm font-bold text-ink no-underline transition hover:-translate-y-px hover:bg-subtle active:translate-y-0 active:scale-[.98] motion-reduce:transform-none">
                        PDF enviado
                    </a>
                @endif

                @if($document->original_path)
                    <a href="{{ \App\Support\DocumentRoutes::route('pdf', $document) }}" target="_blank"
                       class="inline-flex h-11 items-center rounded-full border border-line-strong bg-surface px-5 text-sm font-bold text-ink no-underline transition hover:-translate-y-px hover:bg-subtle active:translate-y-0 active:scale-[.98] motion-reduce:transform-none">
                        PDF original
                    </a>
                @endif

                @if($document->final_path)
                    <a href="{{ \App\Support\DocumentRoutes::route('pdf', [$document, 'versao' => 'final']) }}" target="_blank"
                       class="inline-flex h-11 items-center gap-2 rounded-full bg-ok px-5 text-sm font-bold text-white no-underline transition hover:-translate-y-px hover:opacity-90 active:translate-y-0 active:scale-[.98] dark:text-canvas motion-reduce:transform-none">
                        PDF assinado
                    </a>
                @endif

                @if($document->report_path)
                    {{-- Assinado pelo gov.br: o manifesto é este PDF à parte — ver FinalizeSignatureDocument. --}}
                    <a href="{{ \App\Support\DocumentRoutes::route('pdf', [$document, 'versao' => 'relatorio']) }}" target="_blank"
                       class="inline-flex h-11 items-center rounded-full border border-line-strong bg-surface px-5 text-sm font-bold text-ink no-underline transition hover:-translate-y-px hover:bg-subtle active:translate-y-0 active:scale-[.98] motion-reduce:transform-none">
                        Relatório gov.br
                    </a>
                @endif

                @can('update', $document)
                    <a href="{{ \App\Support\DocumentRoutes::route('edit', $document) }}"
                       class="inline-flex h-11 items-center rounded-full border border-line-strong bg-surface px-5 text-sm font-bold text-ink no-underline transition hover:-translate-y-px hover:bg-subtle active:translate-y-0 active:scale-[.98] motion-reduce:transform-none">
                        Editar
                    </a>

                    <form method="POST" action="{{ \App\Support\DocumentRoutes::route('freeze', $document) }}"
                          onsubmit="return confirm('Congelar o documento? Depois disso o texto e os dados não mudam mais — para corrigir algo será preciso cancelar e emitir outro.')">
                        @csrf
                        <button type="submit" class="inline-flex h-11 items-center rounded-full bg-grena px-5 text-sm font-bold text-white transition hover:-translate-y-px hover:bg-grena-hover active:translate-y-0 active:scale-[.98] motion-reduce:transform-none">
                            Congelar e liberar
                        </button>
                    </form>
                @endcan

                @can('cancel', $document)
                    @if($document->isOpen())
                        <form method="POST" action="{{ \App\Support\DocumentRoutes::route('cancel', $document) }}"
                              onsubmit="return confirm('Cancelar este documento? A sessão aberta no tablet será encerrada.')">
                            @csrf
                            <input type="hidden" name="reason" value="Cancelado pelo atendente">
                            <button type="submit" class="inline-flex h-11 items-center rounded-full px-5 text-sm font-bold text-danger transition hover:bg-danger-soft">
                                Cancelar
                            </button>
                        </form>
                    @endif
                @endcan

                {{-- Arquivar só tira da lista padrão; não muda a situação do documento. --}}
                @can('hide', $document)
                    @if($document->isHidden())
                        <form method="POST" action="{{ \App\Support\DocumentRoutes::route('unhide', $document) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex h-11 items-center rounded-full border border-line-strong bg-surface px-5 text-sm font-bold text-ink no-underline transition hover:-translate-y-px hover:bg-subtle active:translate-y-0 active:scale-[.98] motion-reduce:transform-none">
                                Desarquivar
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ \App\Support\DocumentRoutes::route('hide', $document) }}"
                              onsubmit="return confirm('Arquivar este documento? Ele sai da lista de documentos (fica no filtro &quot;Arquivados&quot;). Nada mais muda: a situação continua a mesma.')">
                            @csrf
                            <button type="submit" class="inline-flex h-11 items-center rounded-full border border-line-strong bg-surface px-5 text-sm font-bold text-ink no-underline transition hover:-translate-y-px hover:bg-subtle active:translate-y-0 active:scale-[.98] motion-reduce:transform-none">
                                Arquivar
                            </button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        @if($document->isHidden())
            <div class="rounded-card bg-subtle px-5 py-3 text-sm text-ink-2" role="status">
                <strong class="text-ink">Arquivado</strong> em {{ $document->hidden_at->format('d/m/Y H:i') }}{{ $document->hidden_by_name ? ' por ' . $document->hidden_by_name : '' }}.
                Fora da lista de documentos; aparece no filtro "Arquivados".
            </div>
        @endif

        <div class="split-lg grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="split-pane lg:col-span-2 space-y-6">

                {{-- Abas pelo endereço (?aba=govbr), e não por script: o envio do PDF do gov.br volta direto para a aba dele. --}}
                @php
                    $tabClasses = 'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[13px] font-bold text-ink-2 transition aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-card';
                @endphp
                <div class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-full bg-subtle p-1" role="tablist" aria-label="Seções do documento">
                    <a href="{{ \App\Support\DocumentRoutes::route('show', $document) }}" role="tab"
                       aria-selected="{{ $aba === 'documento' ? 'true' : 'false' }}" class="{{ $tabClasses }}">
                        Documento
                    </a>
                    <a href="{{ \App\Support\DocumentRoutes::route('show', [$document, 'aba' => 'govbr']) }}" role="tab"
                       aria-selected="{{ $aba === 'govbr' ? 'true' : 'false' }}" class="{{ $tabClasses }}">
                        Assinatura gov.br
                        @if($document->govbrChecks->isNotEmpty())
                            <span class="font-mono text-[11px] text-ink-3">{{ $document->govbrChecks->count() }}</span>
                        @endif
                    </a>
                    @if($document->status === \App\Models\SignatureDocument::STATUS_FINALIZED)
                        <a href="{{ \App\Support\DocumentRoutes::route('show', [$document, 'aba' => 'revisao']) }}" role="tab"
                           aria-selected="{{ $aba === 'revisao' ? 'true' : 'false' }}" class="{{ $tabClasses }}">
                            Revisão
                            @if($document->awaitsReview())
                                <span class="inline-block h-2 w-2 rounded-full {{ $document->review_status ? 'bg-danger' : 'bg-warn' }}" aria-label="{{ $document->reviewStatusLabel() }}"></span>
                            @endif
                        </a>
                    @endif
                </div>

                @if($aba === 'govbr')
                    @include('signature.documents.partials.govbr', ['document' => $document])
                @elseif($aba === 'revisao')
                    @include('signature.documents.partials.review', ['document' => $document])
                @else

                {{-- Congelado, os anexos vêm ANTES do texto do documento (pedido do usuário, 26/10/2026);
                     no rascunho, ficam depois, junto da edição. --}}
                @if($document->isFrozen())
                    @include('signature.documents.partials.attachments', ['document' => $document])
                @endif

                <div class="bg-surface rounded-card shadow-card p-6">
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div>
                            <h3 class="text-lg font-bold text-ink">{{ $document->title }}</h3>
                            <p class="text-xs text-ink-2">
                                {{ $document->template?->name }} versão {{ $document->template_version }}
                                @if($document->linkedTemplateLabels())
                                    + {{ implode(' + ', $document->linkedTemplateLabels()) }}
                                @endif
                                · criado em {{ $document->created_at?->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        @php $rotuloStatus = $document->statusLabel(); @endphp
                        <x-pill :kind="match (true) {
                            $document->status === \App\Models\SignatureDocument::STATUS_AWAITING_SIGNATURE => 'warn',
                            $rotuloStatus === \App\Models\SignatureDocument::LABEL_AWAITING_ATTACHMENTS => 'warn',
                            in_array($document->status, [\App\Models\SignatureDocument::STATUS_SIGNED, \App\Models\SignatureDocument::STATUS_FINALIZED], true) => 'ok',
                            in_array($document->status, [\App\Models\SignatureDocument::STATUS_REFUSED, \App\Models\SignatureDocument::STATUS_CANCELED, \App\Models\SignatureDocument::STATUS_EXPIRED], true) => 'danger',
                            default => 'off',
                        }">{{ $rotuloStatus }}</x-pill>
                    </div>

                    @if($document->awaitsReview())
                        <p class="text-xs text-ink-2 mb-3">* {{ \App\Models\SignatureDocument::LABEL_AWAITING_REVIEW }}: todos assinaram e o PDF está pronto. Vira Finalizado depois da revisão interna em ordem.</p>
                    @endif

                    {{-- Rastreio: quem, no sistema, gerou este documento. O QR Code de cada pessoa tem o seu, na lista de signatários. --}}
                    <p class="text-xs text-ink-2 mb-3">
                        <span class="font-bold">Gerado por:</span> {{ $document->created_by_name ?? 'não registrado' }}
                        @if($document->created_at) em {{ $document->created_at->format('d/m/Y H:i') }} @endif
                    </p>

                    @if($document->isFrozen())
                        <div class="rounded-xl bg-subtle p-4 text-xs text-ink-2 space-y-1 mb-5">
                            <div><span class="font-bold">Congelado em:</span> {{ $document->frozen_at?->format('d/m/Y H:i:s') }}</div>
                            <div class="break-all"><span class="font-bold">SHA-256 do original:</span> <span class="font-mono">{{ $document->original_sha256 }}</span></div>
                            @if($document->final_sha256)
                                <div class="break-all"><span class="font-bold">SHA-256 do final:</span> <span class="font-mono">{{ $document->final_sha256 }}</span></div>
                            @endif
                            <div><span class="font-bold">Código de validação:</span> <span class="font-mono">{{ $document->validation_code }}</span></div>
                            @if($document->archived_at)
                                <div class="break-all">
                                    <span class="font-bold">Cópia no servidor de arquivos:</span>
                                    <span class="font-mono">{{ $document->archive_path }}</span>
                                    ({{ $document->archived_at->format('d/m/Y H:i') }})
                                </div>
                            @elseif($document->final_path && config('signature.archive.enabled'))
                                <div><span class="font-bold">Cópia no servidor de arquivos:</span> pendente — é reenviada automaticamente.</div>
                            @endif
                        </div>
                    @else
                        <div class="rounded-xl bg-warn-soft border border-warn/40 p-4 text-xs text-warn mb-5">
                            Rascunho: ainda pode ser editado. O PDF e o hash só existem depois de congelar — e é o congelamento
                            que libera a assinatura no tablet.
                        </div>
                    @endif

                    @php
                        $doSignatario = collect($document->signerFields())->pluck('label');
                        $automaticos = collect($document->automaticFields())->pluck('label');
                    @endphp

                    @if($doSignatario->isNotEmpty() || $automaticos->isNotEmpty())
                        <div class="rounded-xl bg-subtle p-4 text-xs text-ink-2 space-y-1 mb-5">
                            @if($doSignatario->isNotEmpty())
                                <div>
                                    <span class="font-bold">Respondido por quem assina, no tablet:</span>
                                    {{ $doSignatario->join(', ') }}
                                    —
                                    @if($document->signing_answered_at)
                                        respondido em {{ $document->signing_answered_at->format('d/m/Y H:i:s') }}.
                                    @else
                                        ainda não respondido; aparece como lacuna até lá.
                                    @endif
                                </div>
                            @endif
                            @if($automaticos->isNotEmpty())
                                <div>
                                    <span class="font-bold">Preenchido pelo sistema na assinatura:</span>
                                    {{ $automaticos->join(', ') }}.
                                </div>
                            @endif
                            @if($document->isFrozen())
                                <div>
                                    O PDF original e o hash acima são refeitos quando esses dados entram — sempre antes
                                    de a pessoa ler e assinar. Cada troca fica na trilha de auditoria.
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($document->isUploaded())
                        {{-- Documento pronto: não há texto de modelo para mostrar — o documento É o PDF. --}}
                        <div class="border-t border-line pt-5 text-sm text-ink-2">
                            <h4 class="text-xs font-bold text-ink-3 uppercase tracking-wider mb-3">Documento enviado em PDF</h4>
                            O arquivo é aproveitado na íntegra: texto, imagens e diagramação ficam como foram enviados.
                            O sistema acrescenta a linha de validação no pé de cada página
                            @if($document->requiresInitials()), os campos de visto @endif
                            e as assinaturas — no lugar marcado abaixo, ou numa folha de assinaturas ao fim.
                        </div>
                    @else
                        {{-- Prévia do que a pessoa vai ler. Sai sem escapar: é HTML da allow-list, saneado na gravação do modelo, e os valores entram escapados pelo renderer. --}}
                        <div class="border-t border-line pt-5">
                            <h4 class="text-xs font-bold text-ink-3 uppercase tracking-wider mb-3">Texto do documento</h4>
                            <div class="prose prose-sm max-w-none dark:prose-invert text-ink leading-relaxed">
                                {!! app(\App\Services\Signature\SignatureDocumentRenderer::class)->resolved($document) !!}
                            </div>
                        </div>
                    @endif
                </div>

                @if($document->isUploaded())
                    @include('signature.documents.partials.positions', ['document' => $document])
                @endif

                @unless($document->isFrozen())
                    @include('signature.documents.partials.attachments', ['document' => $document])
                @endunless

                <div class="bg-surface rounded-card shadow-card overflow-hidden">
                    <div class="px-6 py-4 border-b border-line">
                        <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider">Trilha de auditoria</h3>
                        <p class="text-xs text-ink-2 mt-1">
                            Somente inserção, com cada evento encadeado ao anterior. Horários do servidor.
                        </p>
                    </div>

                    <ul class="divide-y divide-line">
                        @forelse($events as $event)
                            <li class="px-6 py-3 flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-sm font-semibold text-ink">{{ $event->label() }}</div>
                                    @if($event->payload)
                                        <div class="text-[11px] text-ink-2 font-mono break-all">
                                            {{ json_encode($event->payload, JSON_UNESCAPED_UNICODE) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="text-[11px] text-ink-3 whitespace-nowrap text-right">
                                    {{ $event->occurred_at?->format('d/m/Y H:i:s') }}
                                    <div>{{ $event->actor_type }}{{ $event->ip ? ' · ' . $event->ip : '' }}</div>
                                </div>
                            </li>
                        @empty
                            <li class="px-6 py-6 text-sm text-ink-2">Sem eventos.</li>
                        @endforelse
                    </ul>
                </div>

                @endif
            </div>

            <div class="split-pane space-y-6">
                <div class="bg-surface rounded-card shadow-card p-6"
                     data-signers-box>
                    <h3 class="mb-4 font-display text-lg font-semibold tracking-tight text-ink">Quem assina</h3>

                    <ol class="sign-flow">
                        @foreach($document->signers as $signer)
                            @php
                                $assinou = $signer->status === \App\Models\SignatureSigner::STATUS_SIGNED;
                                $recusou = in_array($signer->status, [\App\Models\SignatureSigner::STATUS_REFUSED, \App\Models\SignatureSigner::STATUS_CANCELED, \App\Models\SignatureSigner::STATUS_EXPIRED], true);
                            @endphp
                            <li data-signer-card="{{ $signer->id }}" @if($assinou) data-done @endif>
                                <span @class([
                                    'relative z-[1] grid h-9 w-9 place-items-center rounded-full font-mono text-sm font-bold ring-4 ring-surface',
                                    'bg-ok text-white dark:text-canvas' => $assinou,
                                    'bg-danger text-white dark:text-canvas' => $recusou,
                                    'bg-subtle text-ink-2' => ! $assinou && ! $recusou,
                                ]) aria-hidden="true">{{ $assinou ? '✓' : ($recusou ? '✕' : $loop->iteration) }}</span>
                                <div class="min-w-0 rounded-xl border border-line p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="font-semibold text-ink text-sm">{{ $signer->name }}</div>
                                        <div class="text-xs text-ink-2">
                                            {{ $signer->capacityLabel() }} · CPF {{ $signer->maskedCpf() }}
                                        </div>
                                        @if($signer->isLaraUser())
                                            {{-- 09/11/2026: dados do cadastro do usuário; assina pelo QR ou pelo link, como qualquer pessoa. --}}
                                            <div class="mt-1 inline-block rounded-full bg-subtle px-2 py-0.5 text-[11px] font-bold text-ink-2" data-signer-lara-user-badge>
                                                Usuário do Lara
                                            </div>
                                        @endif
                                    </div>
                                    <span @class([
                                            'text-[11px] font-bold whitespace-nowrap',
                                            'text-ok' => $signer->status === \App\Models\SignatureSigner::STATUS_SIGNED,
                                            'text-warn' => $signer->status === \App\Models\SignatureSigner::STATUS_PENDING,
                                            'text-danger' => in_array($signer->status, [\App\Models\SignatureSigner::STATUS_REFUSED, \App\Models\SignatureSigner::STATUS_CANCELED, \App\Models\SignatureSigner::STATUS_EXPIRED], true),
                                        ])
                                        data-signer-status>
                                        {{ $signer->statusLabel() }}
                                    </span>
                                </div>

                                @if($signer->isDirector() && $signer->status === \App\Models\SignatureSigner::STATUS_PENDING)
                                    {{-- Assinatura Diretoria: o diretor abre o documento no tablet com matrícula e PIN; a distância, pelo link remoto (09/11/2026). --}}
                                    <div class="mt-2 rounded-lg bg-subtle px-3 py-2 text-[11px] text-ink-2" data-signer-director>
                                        Diretor: assina no tablet, em <b>Assinatura da Diretoria</b>, com a matrícula e o PIN do
                                        Lara — sem QR —, pelo link de <b>Assinatura remota</b> ou pelo gov.br, se o documento for preparado para lá.
                                        @if(($faltamAntes = $document->pendingNonDirectors())->isNotEmpty())
                                            {{-- O documento só chega à diretoria depois de quem é de fora dela. --}}
                                            <span class="block mt-1 font-bold" data-signer-director-waiting>
                                                Vai para a diretoria depois da assinatura de {{ $faltamAntes->pluck('name')->join(', ', ' e ') }}:
                                                até lá, não aparece no tablet do diretor.
                                            </span>
                                        @elseif($signer->director_notified_at)
                                            Avisado por e-mail em {{ $signer->director_notified_at->format('d/m/Y H:i') }}.
                                        @elseif($document->isFrozen())
                                            Aviso por e-mail ainda não enviado.
                                        @endif
                                    </div>
                                @endif

                                {{-- Atualizado pelo acompanhamento ao vivo (ver partials/release). --}}
                                <div class="mt-2 text-[11px] text-ink-2 hidden" data-signer-live></div>

                                @if($signer->signed_at)
                                    <div class="mt-2 text-[11px] text-ink-2">
                                        Assinou em {{ $signer->signed_at->format('d/m/Y H:i:s') }}
                                        @if($signer->signedViaGovbr())
                                            pelo gov.br — conferido por {{ $signer->govbrCheck?->checked_by_name ?? 'não registrado' }}
                                        @endif
                                    </div>
                                @endif

                                {{-- O QR Code pelo qual a pessoa assinou; sem assinatura, o último gerado. --}}
                                @php $liberacao = $signer->signingRequest() ?? $signer->requests->first(); @endphp
                                @if($liberacao?->isRemote())
                                    {{-- Aberta pelo link do e-mail, no site — o "gerado por" é quem enviou o link. --}}
                                    <div class="mt-1 text-[11px] text-ink-2">
                                        {{ $signer->signed_at ? 'Assinou a distância, pelo' : 'Abriu o' }} link enviado por
                                        {{ $liberacao->created_by_name ?? 'não registrado' }}
                                        ({{ $liberacao->consumed_at?->format('d/m/Y H:i:s') }})
                                    </div>
                                @elseif($liberacao)
                                    <div class="mt-1 text-[11px] text-ink-2">
                                        QR Code gerado por {{ $liberacao->created_by_name ?? 'não registrado' }}
                                        em {{ $liberacao->created_at?->format('d/m/Y H:i:s') }}
                                    </div>
                                @endif

                                @if($signer->refused_at)
                                    <div class="mt-2 text-[11px] text-danger">
                                        Recusou em {{ $signer->refused_at->format('d/m/Y H:i:s') }}
                                        @if($signer->refusal_reason) — {{ $signer->refusal_reason }} @endif
                                    </div>
                                @endif

                                @if($document->status === \App\Models\SignatureDocument::STATUS_FINALIZED && $signer->email)
                                    <div class="mt-3 flex items-center justify-between gap-2">
                                        <span class="text-[11px] text-ink-2">
                                            @if($signer->copy_sent_at)
                                                Via enviada em {{ $signer->copy_sent_at->format('d/m/Y H:i') }}
                                            @elseif($signer->wants_copy)
                                                Via pedida, ainda não enviada
                                            @else
                                                Via não solicitada
                                            @endif
                                        </span>

                                        @can('release', $document)
                                            <form method="POST" action="{{ \App\Support\DocumentRoutes::route('resend-copy', [$document, $signer]) }}">
                                                @csrf
                                                <button type="submit" class="text-[11px] font-bold text-grena-ink hover:underline">
                                                    {{ $signer->copy_sent_at ? 'Reenviar' : 'Enviar via' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>

                </div>

                @can('release', $document)
                    @include('signature.documents.partials.release')
                    @include('signature.documents.partials.remote')
                @endcan

                <div class="bg-surface rounded-card shadow-card p-6">
                    <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider mb-4">Atendimento</h3>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-ink-2">Gerado por</dt>
                            <dd class="font-semibold text-ink">{{ $document->created_by_name ?? 'não registrado' }}</dd>
                        </div>
                        @if($document->expires_at)
                            <div>
                                <dt class="text-xs text-ink-2">Validade do documento</dt>
                                <dd class="font-semibold text-ink">{{ $document->expires_at->format('d/m/Y H:i') }}</dd>
                            </div>
                        @endif
                        @if($document->canceled_reason)
                            <div>
                                <dt class="text-xs text-ink-2">Motivo do cancelamento</dt>
                                <dd class="font-semibold text-ink">{{ $document->canceled_reason }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
