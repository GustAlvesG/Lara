{{-- Uma linha de arquivo anexado: abrir, quem enviou, hash e — em rascunho — remover. --}}
<div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-subtle px-3 py-2 text-xs" data-attachment-file="{{ $anexo->id }}">
    <div class="min-w-0">
        @can('download', $document)
            <a href="{{ route('signature-documents.attachments.show', [$document, $anexo]) }}" target="_blank"
               class="font-bold text-grena-ink hover:underline break-all">{{ $anexo->original_name }}</a>
        @else
            <span class="font-bold text-ink break-all">{{ $anexo->original_name }}</span>
        @endcan
        <span class="text-ink-2">· {{ $anexo->sizeLabel() }}</span>
        <div class="text-[11px] text-ink-3">
            {{ $anexo->label }} · enviado em {{ $anexo->created_at?->format('d/m/Y H:i') }}
            por {{ $anexo->uploaded_by_name ?? 'não registrado' }}
            · <span class="font-mono" title="SHA-256 {{ $anexo->sha256 }}">{{ substr($anexo->sha256, 0, 12) }}…</span>
        </div>
    </div>

    @if($podeRemover)
        @can('attach', $document)
            <form method="POST" action="{{ route('signature-documents.attachments.destroy', [$document, $anexo]) }}"
                  onsubmit="return confirm('Remover este anexo?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-[11px] font-bold text-danger hover:underline">Remover</button>
            </form>
        @endcan
    @endif
</div>
