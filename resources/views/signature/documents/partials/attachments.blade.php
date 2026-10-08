{{--
    Anexos do documento: os itens pedidos (do modelo e do documento), o que já foi enviado em cada um e os
    anexos avulsos. O atendente envia aqui; em rascunho também remove. Ver SignatureAttachmentService.
--}}
@php
    $servico = app(\App\Services\Signature\SignatureAttachmentService::class);
    $itens = $document->attachmentRequirements();
    $anexos = $document->attachments;
    $chaves = collect($itens)->pluck('key')->all();
    $avulsos = $anexos->reject(fn($a) => $a->requirement_key !== null && in_array($a->requirement_key, $chaves, true));
    $podeEnviar = $servico->uploadBlockReason($document) === null;
    $podeRemover = $document->status === \App\Models\SignatureDocument::STATUS_DRAFT;
    $limiteMb = round((int) config('signature.attachments.max_kb', 10240) / 1024);
@endphp

<div id="anexos" class="bg-surface rounded-card shadow-card p-6 space-y-4" data-attachments-box>
    <div>
        <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider">Anexos</h3>
        <p class="text-xs text-ink-2 mt-1">
            PDF, JPG ou PNG, até {{ $limiteMb }} MB cada. Podem chegar a qualquer momento antes da conclusão: o
            documento só conclui com os obrigatórios aqui.
            @if(!$podeRemover && $podeEnviar)
                Depois do congelamento, o que for enviado fica registrado e não pode ser removido.
            @endif
        </p>
    </div>

    @php $faltando = $document->missingAttachments(); @endphp
    @if($document->status === \App\Models\SignatureDocument::STATUS_SIGNED && $faltando !== [])
        {{-- Todos assinaram: o que segura a conclusão é o anexo. --}}
        <div class="rounded-xl bg-warn-soft p-3 text-sm text-warn" data-attachments-pending>
            Todos assinaram. Para concluir, falta enviar: <span class="font-bold">{{ implode(', ', $faltando) }}</span>.
            O documento conclui assim que o último chegar.
        </div>
    @endif

    @foreach($itens as $item)
        @php $doItem = $anexos->where('requirement_key', $item['key']); @endphp
        <div class="rounded-xl border border-line p-4 space-y-2" data-attachment-item="{{ $item['key'] }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm font-semibold text-ink">
                    {{ $item['label'] }}
                    <span class="text-[11px] font-normal text-ink-3">
                        · {{ $item['required'] ? 'obrigatório' : 'opcional' }}
                        · pedido {{ $item['origin'] === 'modelo' ? 'pelo modelo' : 'neste documento' }}
                    </span>
                </div>
                @if($doItem->isNotEmpty())
                    <x-pill kind="ok">Enviado</x-pill>
                @elseif($item['required'])
                    <x-pill kind="warn">Falta</x-pill>
                @endif
            </div>

            @foreach($doItem as $anexo)
                @include('signature.documents.partials.attachment-file', ['anexo' => $anexo, 'podeRemover' => $podeRemover])
            @endforeach

            @if($podeEnviar)
                @can('attach', $document)
                    <form method="POST" action="{{ route('signature-documents.attachments.store', $document) }}"
                          enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <input type="hidden" name="item" value="{{ $item['key'] }}">
                        <input type="file" name="arquivo" accept="application/pdf,image/jpeg,image/png" required
                               aria-label="Arquivo de {{ $item['label'] }}"
                               class="min-w-0 text-sm text-ink file:mr-3 file:rounded-full file:border-0 file:bg-subtle file:px-4 file:py-2 file:text-sm file:font-bold file:text-ink hover:file:bg-line">
                        <button type="submit" class="px-4 py-2 bg-grena text-white rounded-full font-bold text-xs hover:bg-grena-hover transition">
                            {{ $doItem->isNotEmpty() ? 'Enviar mais um arquivo' : 'Enviar' }}
                        </button>
                    </form>
                @endcan
            @endif
        </div>
    @endforeach

    @if($avulsos->isNotEmpty())
        <div class="rounded-xl border border-line p-4 space-y-2">
            <div class="text-sm font-semibold text-ink">Outros anexos</div>
            @foreach($avulsos as $anexo)
                @include('signature.documents.partials.attachment-file', ['anexo' => $anexo, 'podeRemover' => $podeRemover])
            @endforeach
        </div>
    @endif

    @if($podeEnviar)
        @can('attach', $document)
            {{-- Anexo que ninguém pediu, mas que o atendimento quer guardar com o documento. --}}
            <form method="POST" action="{{ route('signature-documents.attachments.store', $document) }}"
                  enctype="multipart/form-data" class="border-t border-line pt-4 space-y-2">
                @csrf
                <div class="text-xs font-bold text-ink-2">Outro anexo</div>
                <div class="flex flex-wrap items-center gap-3">
                    <input type="text" name="rotulo" maxlength="120" required placeholder="O que é este arquivo"
                           aria-label="O que é este arquivo"
                           class="min-w-0 flex-1 rounded-full border border-line bg-surface px-4 py-2 text-sm text-ink">
                    <input type="file" name="arquivo" accept="application/pdf,image/jpeg,image/png" required
                           aria-label="Arquivo do outro anexo"
                           class="min-w-0 text-sm text-ink file:mr-3 file:rounded-full file:border-0 file:bg-subtle file:px-4 file:py-2 file:text-sm file:font-bold file:text-ink hover:file:bg-line">
                    <button type="submit" class="px-4 py-2 bg-grena text-white rounded-full font-bold text-xs hover:bg-grena-hover transition">
                        Enviar
                    </button>
                </div>
            </form>
        @endcan
    @elseif($itens === [] && $anexos->isEmpty())
        <p class="text-sm text-ink-2">Nenhum anexo.</p>
    @endif

    @error('arquivo')
        <p class="text-xs text-danger">{{ $message }}</p>
    @enderror
</div>
