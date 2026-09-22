<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $document->title }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @include('partials.alerts')

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('signature-documents.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">&larr; Documentos</a>

            <div class="flex flex-wrap gap-3">
                @if($document->original_path)
                    <a href="{{ route('signature-documents.pdf', $document) }}" target="_blank"
                       class="px-5 py-2.5 rounded-xl font-bold text-sm bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        PDF original
                    </a>
                @endif

                @if($document->final_path)
                    <a href="{{ route('signature-documents.pdf', [$document, 'versao' => 'final']) }}" target="_blank"
                       class="px-5 py-2.5 rounded-xl font-bold text-sm bg-green-600 text-white hover:bg-green-700 transition">
                        PDF assinado
                    </a>
                @endif

                @can('update', $document)
                    <a href="{{ route('signature-documents.edit', $document) }}"
                       class="px-5 py-2.5 rounded-xl font-bold text-sm bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        Editar
                    </a>

                    <form method="POST" action="{{ route('signature-documents.freeze', $document) }}"
                          onsubmit="return confirm('Congelar o documento? Depois disso o texto e os dados não mudam mais — para corrigir algo será preciso cancelar e emitir outro.')">
                        @csrf
                        <button type="submit" class="px-5 py-2.5 bg-[#A00001] text-white rounded-xl font-bold text-sm shadow-lg hover:bg-[#800000] transition">
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
                            <button type="submit" class="px-5 py-2.5 rounded-xl font-bold text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                Cancelar
                            </button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 space-y-6">

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $document->title }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $document->template?->name }} versão {{ $document->template_version }}
                                · criado em {{ $document->created_at?->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        <span class="px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap
                            @class([
                                'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' => $document->status === \App\Models\SignatureDocument::STATUS_DRAFT,
                                'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' => $document->status === \App\Models\SignatureDocument::STATUS_AWAITING_SIGNATURE,
                                'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' => in_array($document->status, [\App\Models\SignatureDocument::STATUS_SIGNED, \App\Models\SignatureDocument::STATUS_FINALIZED], true),
                                'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' => in_array($document->status, [\App\Models\SignatureDocument::STATUS_REFUSED, \App\Models\SignatureDocument::STATUS_CANCELED, \App\Models\SignatureDocument::STATUS_EXPIRED], true),
                            ])">
                            {{ $document->statusLabel() }}
                        </span>
                    </div>

                    @if($document->isFrozen())
                        <div class="rounded-xl bg-gray-50 dark:bg-gray-900/40 p-4 text-xs text-gray-600 dark:text-gray-300 space-y-1 mb-5">
                            <div><span class="font-bold">Congelado em:</span> {{ $document->frozen_at?->format('d/m/Y H:i:s') }}</div>
                            <div class="break-all"><span class="font-bold">SHA-256 do original:</span> <span class="font-mono">{{ $document->original_sha256 }}</span></div>
                            @if($document->final_sha256)
                                <div class="break-all"><span class="font-bold">SHA-256 do final:</span> <span class="font-mono">{{ $document->final_sha256 }}</span></div>
                            @endif
                            <div><span class="font-bold">Código de validação:</span> <span class="font-mono">{{ $document->validation_code }}</span></div>
                        </div>
                    @else
                        <div class="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900 p-4 text-xs text-amber-900 dark:text-amber-200 mb-5">
                            Rascunho: ainda pode ser editado. O PDF e o hash só existem depois de congelar — e é o congelamento
                            que libera a assinatura no tablet.
                        </div>
                    @endif

                    {{-- Prévia do que a pessoa vai ler. Sai sem escapar: é HTML da allow-list, saneado na gravação do modelo. --}}
                    <div class="border-t border-gray-100 dark:border-gray-700 pt-5">
                        <h4 class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Texto do documento</h4>
                        <div class="prose prose-sm max-w-none dark:prose-invert text-gray-800 dark:text-gray-200 leading-relaxed">
                            {!! $document->body_snapshot ?? app(\App\Services\Signature\SignatureDocumentRenderer::class)->body($document->template, $document->data) !!}
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Trilha de auditoria</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Somente inserção, com cada evento encadeado ao anterior. Horários do servidor.
                        </p>
                    </div>

                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($events as $event)
                            <li class="px-6 py-3 flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $event->label() }}</div>
                                    @if($event->payload)
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400 font-mono break-all">
                                            {{ json_encode($event->payload, JSON_UNESCAPED_UNICODE) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="text-[11px] text-gray-400 whitespace-nowrap text-right">
                                    {{ $event->occurred_at?->format('d/m/Y H:i:s') }}
                                    <div>{{ $event->actor_type }}{{ $event->ip ? ' · ' . $event->ip : '' }}</div>
                                </div>
                            </li>
                        @empty
                            <li class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Sem eventos.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6"
                     data-signers-box>
                    <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">Signatários</h3>

                    <ol class="space-y-4">
                        @foreach($document->signers as $signer)
                            <li class="rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                                data-signer-card="{{ $signer->id }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="font-semibold text-gray-900 dark:text-white text-sm">{{ $signer->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $signer->roleLabel() }} · CPF {{ $signer->maskedCpf() }}
                                        </div>
                                    </div>
                                    <span class="text-[11px] font-bold whitespace-nowrap
                                        @class([
                                            'text-green-700 dark:text-green-400' => $signer->status === \App\Models\SignatureSigner::STATUS_SIGNED,
                                            'text-amber-700 dark:text-amber-400' => $signer->status === \App\Models\SignatureSigner::STATUS_PENDING,
                                            'text-red-700 dark:text-red-400' => in_array($signer->status, [\App\Models\SignatureSigner::STATUS_REFUSED, \App\Models\SignatureSigner::STATUS_CANCELED, \App\Models\SignatureSigner::STATUS_EXPIRED], true),
                                        ])"
                                        data-signer-status>
                                        {{ $signer->statusLabel() }}
                                    </span>
                                </div>

                                {{-- Atualizado pelo acompanhamento ao vivo (ver partials/release). --}}
                                <div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400 hidden" data-signer-live></div>

                                @if($signer->signed_at)
                                    <div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                                        Assinou em {{ $signer->signed_at->format('d/m/Y H:i:s') }}
                                    </div>
                                @endif

                                @if($signer->refused_at)
                                    <div class="mt-2 text-[11px] text-red-600 dark:text-red-400">
                                        Recusou em {{ $signer->refused_at->format('d/m/Y H:i:s') }}
                                        @if($signer->refusal_reason) — {{ $signer->refusal_reason }} @endif
                                    </div>
                                @endif

                                @if($document->status === \App\Models\SignatureDocument::STATUS_FINALIZED && $signer->email)
                                    <div class="mt-3 flex items-center justify-between gap-2">
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400">
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
                                                <button type="submit" class="text-[11px] font-bold text-[#A00001] hover:underline">
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

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">Atendimento</h3>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Local</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ $document->location ?? '—' }}</dd>
                        </div>
                        @if($document->expires_at)
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Validade do documento</dt>
                                <dd class="font-semibold text-gray-900 dark:text-white">{{ $document->expires_at->format('d/m/Y H:i') }}</dd>
                            </div>
                        @endif
                        @if($document->canceled_reason)
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Motivo do cancelamento</dt>
                                <dd class="font-semibold text-gray-900 dark:text-white">{{ $document->canceled_reason }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
