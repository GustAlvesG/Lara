<x-app-layout :bootstrap-grid="false">
<div>
    <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-5 px-4 pb-12 pt-6 sm:px-6 lg:px-8">
        <x-page-title :title="$document->title" :back="route('signature-documents.index')"></x-page-title>

        @include('partials.alerts')

        <div class="flex flex-wrap items-center justify-between gap-3">
            <span></span>

            <div class="flex flex-wrap gap-3">
                @if($document->isUploaded())
                    <a href="{{ route('signature-documents.pdf', [$document, 'versao' => 'enviado']) }}" target="_blank"
                       class="px-5 py-2.5 rounded-full font-bold text-sm bg-subtle text-ink hover:bg-line transition">
                        PDF enviado
                    </a>
                @endif

                @if($document->original_path)
                    <a href="{{ route('signature-documents.pdf', $document) }}" target="_blank"
                       class="px-5 py-2.5 rounded-full font-bold text-sm bg-subtle text-ink hover:bg-line transition">
                        PDF original
                    </a>
                @endif

                @if($document->final_path)
                    <a href="{{ route('signature-documents.pdf', [$document, 'versao' => 'final']) }}" target="_blank"
                       class="px-5 py-2.5 rounded-full font-bold text-sm bg-ok text-white dark:text-canvas hover:bg-ok/90 transition">
                        PDF assinado
                    </a>
                @endif

                @can('update', $document)
                    <a href="{{ route('signature-documents.edit', $document) }}"
                       class="px-5 py-2.5 rounded-full font-bold text-sm bg-subtle text-ink hover:bg-line transition">
                        Editar
                    </a>

                    <form method="POST" action="{{ route('signature-documents.freeze', $document) }}"
                          onsubmit="return confirm('Congelar o documento? Depois disso o texto e os dados não mudam mais — para corrigir algo será preciso cancelar e emitir outro.')">
                        @csrf
                        <button type="submit" class="px-5 py-2.5 bg-grena text-white rounded-full font-bold text-sm hover:bg-grena-hover transition">
                            Congelar e liberar
                        </button>
                    </form>
                @endcan

                @can('cancel', $document)
                    @if($document->isOpen())
                        <form method="POST" action="{{ route('signature-documents.cancel', $document) }}"
                              onsubmit="return confirm('Cancelar este documento? A sessão aberta no tablet será encerrada.')">
                            @csrf
                            <input type="hidden" name="reason" value="Cancelado pelo atendente">
                            <button type="submit" class="px-5 py-2.5 rounded-full font-bold text-sm text-danger hover:bg-danger-soft transition">
                                Cancelar
                            </button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 space-y-6">

                <div class="bg-surface rounded-card shadow-card p-6">
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div>
                            <h3 class="text-lg font-bold text-ink">{{ $document->title }}</h3>
                            <p class="text-xs text-ink-2">
                                {{ $document->template?->name }} versão {{ $document->template_version }}
                                · criado em {{ $document->created_at?->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        <x-pill :kind="match (true) {
                            $document->status === \App\Models\SignatureDocument::STATUS_AWAITING_SIGNATURE => 'warn',
                            in_array($document->status, [\App\Models\SignatureDocument::STATUS_SIGNED, \App\Models\SignatureDocument::STATUS_FINALIZED], true) => 'ok',
                            in_array($document->status, [\App\Models\SignatureDocument::STATUS_REFUSED, \App\Models\SignatureDocument::STATUS_CANCELED, \App\Models\SignatureDocument::STATUS_EXPIRED], true) => 'danger',
                            default => 'off',
                        }">{{ $document->statusLabel() }}</x-pill>
                    </div>

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
                        $doSignatario = collect($document->template?->signerFields() ?? [])->pluck('label');
                        $automaticos = collect($document->template?->automaticFields() ?? [])->pluck('label');
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
                            @if($document->template?->requires_initials), os campos de visto @endif
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
            </div>

            <div class="space-y-6">
                <div class="bg-surface rounded-card shadow-card p-6"
                     data-signers-box>
                    <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider mb-4">Signatários</h3>

                    <ol class="space-y-4">
                        @foreach($document->signers as $signer)
                            <li class="rounded-xl border border-line p-4"
                                data-signer-card="{{ $signer->id }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="font-semibold text-ink text-sm">{{ $signer->name }}</div>
                                        <div class="text-xs text-ink-2">
                                            {{ $signer->capacityLabel() }} · CPF {{ $signer->maskedCpf() }}
                                        </div>
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

                                {{-- Atualizado pelo acompanhamento ao vivo (ver partials/release). --}}
                                <div class="mt-2 text-[11px] text-ink-2 hidden" data-signer-live></div>

                                @if($signer->signed_at)
                                    <div class="mt-2 text-[11px] text-ink-2">
                                        Assinou em {{ $signer->signed_at->format('d/m/Y H:i:s') }}
                                    </div>
                                @endif

                                {{-- O QR Code pelo qual a pessoa assinou; sem assinatura, o último gerado. --}}
                                @php $liberacao = $signer->signingRequest() ?? $signer->requests->first(); @endphp
                                @if($liberacao)
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
                                            <form method="POST" action="{{ route('signature-documents.resend-copy', [$document, $signer]) }}">
                                                @csrf
                                                <button type="submit" class="text-[11px] font-bold text-grena-ink hover:underline">
                                                    {{ $signer->copy_sent_at ? 'Reenviar' : 'Enviar via' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>

                </div>

                @can('release', $document)
                    @include('signature.documents.partials.release')
                @endcan

                <div class="bg-surface rounded-card shadow-card p-6">
                    <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider mb-4">Atendimento</h3>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-ink-2">Local</dt>
                            <dd class="font-semibold text-ink">{{ $document->location ?? '—' }}</dd>
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
