{{--
    Aba "Assinatura gov.br": o atendente prepara o documento para o gov.br, recebe de volta o PDF assinado,
    envia aqui e vê a conferência. Aprovado, o envio conclui a assinatura de quem assinou — ver GovbrCheckService.
--}}
@php
    $iconeDe = fn(?bool $ok) => match ($ok) {
        true => ['check', 'text-ok', 'passou'],
        false => ['x', 'text-danger', 'não passou'],
        null => [null, 'text-ink-3', 'não conferido'],
    };

    $bloqueio = $document->isGovbr() ? null : $document->govbrBlockReason();

    // O arquivo que a próxima pessoa assina: o último que concluiu alguma assinatura. Sem ele, o original.
    $ultimoAprovado = $document->govbrChecks->first(fn($c) => $c->valid && $c->concludedNames() !== []);

    // Sem ordem obrigatória: qualquer pendente pode receber o convite.
    $pendentes = $document->status === \App\Models\SignatureDocument::STATUS_AWAITING_SIGNATURE
        ? $document->signers->where('status', \App\Models\SignatureSigner::STATUS_PENDING)->values()
        : collect();
@endphp

<div class="bg-surface rounded-card shadow-card p-6 space-y-4">
    <div>
        <h3 class="text-lg font-bold text-ink">Assinatura pelo gov.br</h3>
        <p class="text-sm text-ink-2 mt-1">
            Para quem não vem ao balcão: a pessoa assina o PDF deste documento no portal do gov.br e o devolve.
            O Lara confere se é este documento, se nada mudou depois da assinatura, se o certificado é do gov.br
            e se o CPF é de um dos signatários — e, aprovado, registra a assinatura.
        </p>
    </div>

    @if($document->isGovbr())
        <div class="rounded-xl bg-subtle p-4 text-xs text-ink-2 space-y-1">
            <div><span class="font-bold">Preparado para o gov.br em:</span> {{ $document->govbr_sent_at->format('d/m/Y H:i') }}</div>
            @if($document->status === \App\Models\SignatureDocument::STATUS_AWAITING_SIGNATURE && $document->expires_at)
                <div><span class="font-bold">Prazo para assinar:</span> {{ $document->expires_at->format('d/m/Y H:i') }}</div>
            @endif
            <div>O tablet não libera a assinatura deste documento: ele é assinado só pelo gov.br.</div>
        </div>

        @if($pendentes->isNotEmpty())
            {{--
                Um convite por pendente, em qualquer ordem. O e-mail leva o arquivo a assinar: o último aprovado (com as
                assinaturas de quem já assinou) ou o original. A resposta volta para quem enviou.
            --}}
            @can('checkGovbr', $document)
                <div class="rounded-xl border border-line p-4 space-y-3">
                    <div class="text-sm text-ink">
                        Enviar por e-mail o PDF para assinar e o passo a passo. A pessoa responde ao e-mail com o arquivo
                        assinado — a resposta vem para você — e você o envia aqui embaixo.
                    </div>
                    @foreach($pendentes as $pendente)
                        <form method="POST" action="{{ route('signature-documents.govbr.invite', [$document, $pendente]) }}"
                              class="flex flex-wrap items-center gap-3" data-govbr-invite-form="{{ $pendente->id }}">
                            @csrf
                            <input type="hidden" name="convite_para" value="{{ $pendente->id }}">
                            <div class="w-full sm:w-auto sm:min-w-[12rem] text-sm">
                                <span class="font-bold text-ink">{{ $pendente->name }}</span>
                                <span class="text-ink-3">({{ $pendente->capacityLabel() }})</span>
                            </div>
                            <input type="email" name="email" required maxlength="191"
                                   value="{{ (int) old('convite_para') === $pendente->id ? old('email') : $pendente->email }}"
                                   placeholder="email@exemplo.com" aria-label="E-mail de {{ $pendente->name }}"
                                   class="min-w-0 flex-1 rounded-full border border-line bg-surface px-4 py-2 text-sm text-ink">
                            <button type="submit" class="px-5 py-2.5 bg-grena text-white rounded-full font-bold text-sm hover:bg-grena-hover transition">
                                {{ $pendente->govbrInvites->isNotEmpty() ? 'Reenviar por e-mail' : 'Enviar por e-mail' }}
                            </button>
                        </form>
                    @endforeach
                    @error('email')
                        <p class="text-xs text-danger">{{ $message }}</p>
                    @enderror
                    @if($pendentes->count() > 1)
                        <p class="text-xs text-ink-3">
                            A ordem é livre, mas é <b>um de cada vez</b>: as assinaturas se somam no mesmo arquivo. Se duas
                            pessoas assinarem o mesmo arquivo ao mesmo tempo, a segunda a voltar é recusada e assina de
                            novo, sobre o arquivo da primeira — reenvie o convite a ela.
                        </p>
                    @endif
                </div>
            @endcan

            <p class="text-xs text-ink-3">Ou envie o arquivo por conta própria:</p>

            <ol class="list-decimal pl-5 text-sm text-ink-2 space-y-1">
                <li>
                    Envie à pessoa o arquivo
                    @if($ultimoAprovado)
                        <a href="{{ route('signature-documents.govbr.pdf', [$document, $ultimoAprovado]) }}" target="_blank"
                           class="font-bold text-grena-ink hover:underline">já assinado por {{ collect($ultimoAprovado->concludedNames())->join(', ') }}</a>
                        — é sobre ele que a próxima assinatura entra, e não sobre o original.
                    @else
                        <a href="{{ route('signature-documents.pdf', $document) }}" target="_blank"
                           class="font-bold text-grena-ink hover:underline">PDF original</a>.
                    @endif
                    Envie como foi baixado: salvo de novo por outro programa, ele é recusado.
                </li>
                <li>A pessoa assina em <span class="font-mono">assinador.iti.br</span>, com conta gov.br prata ou ouro, e devolve o arquivo baixado de lá.</li>
                <li>Envie esse arquivo aqui.</li>
            </ol>
        @endif

        @php $convites = $document->signers->flatMap->govbrInvites->sortByDesc('id'); @endphp
        @if($convites->isNotEmpty())
            <div class="border-t border-line pt-4">
                <h4 class="text-xs font-bold text-ink-3 uppercase tracking-wider mb-2">Convites por e-mail</h4>
                <ul class="space-y-1 text-xs text-ink-2" data-govbr-invites>
                    @foreach($convites as $convite)
                        @php $signatario = $document->signers->firstWhere('id', $convite->signature_signer_id); @endphp
                        <li>
                            <span class="font-semibold text-ink">{{ $signatario?->name }}</span>
                            — {{ $convite->maskedEmail() }}, enviado em {{ $convite->created_at?->format('d/m/Y H:i') }}
                            por {{ $convite->sent_by_name ?? 'não registrado' }}.
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @elseif($bloqueio === null)
        <div class="rounded-xl bg-subtle p-4 text-sm text-ink-2 space-y-2">
            <p>
                Para assinar pelo gov.br, comece por <span class="font-bold text-ink">Preparar para o gov.br</span>. Isso:
            </p>
            <ul class="list-disc pl-5 text-xs space-y-1">
                <li>põe no documento a data da assinatura, se o modelo tiver esse campo — por isso, baixe o PDF <span class="font-bold">depois</span> de preparar;</li>
                <li>muda o prazo para {{ (int) config('signature.govbr.ttl_days', 7) }} dias;</li>
                <li>cancela o QR Code que estiver aberto e faz o tablet parar de liberar este documento.</li>
            </ul>
        </div>

        @can('checkGovbr', $document)
            <form method="POST" action="{{ route('signature-documents.govbr.prepare', $document) }}"
                  onsubmit="return confirm('Preparar para o gov.br? A partir daqui este documento não é mais assinado no tablet.')">
                @csrf
                <button type="submit" class="px-5 py-2.5 bg-grena text-white rounded-full font-bold text-sm hover:bg-grena-hover transition">
                    Preparar para o gov.br
                </button>
            </form>
        @endcan
    @else
        <p class="rounded-xl bg-subtle p-4 text-sm text-ink-2">{{ $bloqueio }}</p>
    @endif

    @can('checkGovbr', $document)
        @if($document->isFrozen())
            <form method="POST" action="{{ route('signature-documents.govbr.store', $document) }}" enctype="multipart/form-data"
                  class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
                @csrf
                <input type="file" name="documento" accept="application/pdf" required
                       class="text-sm text-ink file:mr-3 file:rounded-full file:border-0 file:bg-subtle file:px-4 file:py-2 file:text-sm file:font-bold file:text-ink hover:file:bg-line">
                <button type="submit" class="px-5 py-2.5 bg-grena text-white rounded-full font-bold text-sm hover:bg-grena-hover transition">
                    Conferir assinatura
                </button>
                @error('documento')
                    <p class="w-full text-xs text-danger">{{ $message }}</p>
                @enderror
                @unless($document->isGovbr())
                    <p class="w-full text-xs text-ink-3">
                        Sem preparar, o envio só confere e registra: não conclui a assinatura.
                    </p>
                @endunless
            </form>
        @endif
    @endcan
</div>

@forelse($document->govbrChecks as $conferencia)
    <details class="bg-surface rounded-card shadow-card overflow-hidden group" @if($loop->first) open @endif
             data-govbr-check="{{ $conferencia->id }}">
        <summary class="px-6 py-4 flex flex-wrap items-center justify-between gap-3 cursor-pointer list-none">
            <div>
                <div class="text-sm font-bold text-ink">
                    Enviado em {{ $conferencia->created_at?->format('d/m/Y H:i:s') }}
                    por {{ $conferencia->checked_by_name ?? 'não registrado' }}
                </div>
                <div class="text-xs text-ink-2">
                    @if($conferencia->baseLabel())
                        Assinado sobre o {{ $conferencia->baseLabel() }}
                    @else
                        Não corresponde a nenhum PDF deste documento
                    @endif
                    · {{ number_format($conferencia->file_bytes / 1024, 0, ',', '.') }} KB
                </div>
            </div>
            <x-pill :kind="$conferencia->valid ? 'ok' : 'danger'">{{ $conferencia->valid ? 'Válido' : 'Recusado' }}</x-pill>
        </summary>

        <div class="px-6 pb-6 space-y-5 border-t border-line pt-4">
            {{-- O que a conferência fez no documento. --}}
            @if($conferencia->concludedNames() !== [])
                <div class="rounded-xl bg-ok-soft p-3 text-sm text-ok">
                    Registrou a assinatura de <span class="font-bold">{{ collect($conferencia->concludedNames())->join(', ', ' e ') }}</span>.
                    @if($conferencia->closedDocument())
                        Todos assinaram: este arquivo é o PDF final do documento.
                    @endif
                </div>
            @elseif($conferencia->conclusionReason())
                <div class="rounded-xl bg-warn-soft p-3 text-sm text-warn">
                    Não registrou assinatura: {{ $conferencia->conclusionReason() }}
                </div>
            @endif

            <ul class="space-y-2">
                @foreach($conferencia->checks() as $check)
                    @php [$icone, $cor, $leitura] = $iconeDe($check['ok']); @endphp
                    <li class="flex items-start gap-2 text-sm">
                        <span class="mt-0.5 w-4 shrink-0 {{ $cor }}" title="{{ $leitura }}">
                            @if($icone)<x-icon :name="$icone" class="w-4 h-4" />@else&ndash;@endif
                        </span>
                        <span>
                            <span class="font-semibold text-ink">{{ $check['label'] }}</span>
                            <span class="block text-xs text-ink-2">{{ $check['detail'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>

            @foreach($conferencia->signatures() as $assinatura)
                <div class="rounded-xl border border-line p-4 space-y-3">
                    <div>
                        <div class="text-sm font-bold text-ink">
                            Assinatura {{ $assinatura['order'] }}: {{ $assinatura['name'] ?? 'sem certificado' }}
                        </div>
                        <div class="text-xs text-ink-2">
                            @if($assinatura['cpf']) CPF {{ $assinatura['cpf'] }} @endif
                            @if($assinatura['signer_name']) · signatário: {{ $assinatura['signer_name'] }} @endif
                            @if($assinatura['signed_at'])
                                · {{ \Illuminate\Support\Carbon::parse($assinatura['signed_at'])->format('d/m/Y H:i:s') }}
                            @endif
                        </div>
                        @if($assinatura['issuer'])
                            <div class="text-[11px] text-ink-3">Emitido por {{ $assinatura['issuer'] }} · série {{ $assinatura['serial'] }}</div>
                        @endif
                    </div>

                    <ul class="space-y-2">
                        @foreach($assinatura['checks'] as $check)
                            @php [$icone, $cor, $leitura] = $iconeDe($check['ok']); @endphp
                            <li class="flex items-start gap-2 text-sm">
                                <span class="mt-0.5 w-4 shrink-0 {{ $cor }}" title="{{ $leitura }}">
                                    @if($icone)<x-icon :name="$icone" class="w-4 h-4" />@else&ndash;@endif
                                </span>
                                <span>
                                    <span class="font-semibold text-ink">{{ $check['label'] }}</span>
                                    <span class="block text-xs text-ink-2">{{ $check['detail'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="flex flex-wrap items-center justify-between gap-3 text-[11px] text-ink-3">
                <span class="font-mono break-all">SHA-256 {{ $conferencia->file_sha256 }}</span>
                @can('download', $document)
                    <a href="{{ route('signature-documents.govbr.pdf', [$document, $conferencia]) }}" target="_blank"
                       class="font-bold text-grena-ink hover:underline whitespace-nowrap">
                        Baixar o arquivo enviado
                    </a>
                @endcan
            </div>
        </div>
    </details>
@empty
    <div class="bg-surface rounded-card shadow-card p-6 text-sm text-ink-2">
        Nenhum PDF assinado pelo gov.br foi enviado para este documento.
    </div>
@endforelse
