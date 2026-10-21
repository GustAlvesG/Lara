@php
    /**
     * Assinatura remota: o link por e-mail que abre a tela de quem assina no
     * site do clube, sem login. Ver RemoteSignatureService.
     *
     * Um cartão por signatário pendente (diretor fora: assina só no tablet).
     * O e-mail vem preenchido com o do cadastro e pode ser corrigido — o
     * informado passa a ser o do signatário, como no convite do gov.br.
     */
    $remote = app(\App\Services\Signature\RemoteSignatureService::class);
    $emAberto = $document->status === \App\Models\SignatureDocument::STATUS_AWAITING_SIGNATURE && !$document->isGovbr();
    $candidatos = $emAberto
        ? $document->signers->where('status', \App\Models\SignatureSigner::STATUS_PENDING)->reject(fn($s) => $s->isDirector())->values()
        : collect();
@endphp

{{-- Sem o endereço do site (SIGNATURE_REMOTE_SITE_URL) o cartão não aparece: não há link a montar. --}}
@if($candidatos->isNotEmpty() && $remote->enabled())
<div class="bg-surface rounded-card shadow-card p-6" data-remote-box>
    <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider mb-1">Assinatura remota</h3>
    <p class="text-xs text-ink-2 mb-4">
        A pessoa recebe um link por e-mail e assina pelo site do clube, com o CPF completo e uma foto.
        O link vale {{ (int) config('signature.remote.link_ttl_hours', 72) }} horas.
    </p>

        <ul class="space-y-4">
            @foreach($candidatos as $signer)
                @php
                    $ultimo = $signer->remoteLinks->first();
                    $motivo = $remote->blockReason($signer);
                @endphp
                <li class="rounded-xl border border-line p-4" data-remote-signer="{{ $signer->id }}">
                    <div class="text-sm font-semibold text-ink">{{ $signer->name }}</div>

                    @if($ultimo)
                        <div class="mt-1 text-[11px] text-ink-2" data-remote-link-status>
                            Link {{ mb_strtolower($ultimo->statusLabel()) }} — enviado a {{ $ultimo->maskedEmail() }}
                            por {{ $ultimo->sent_by_name ?? 'não registrado' }} em {{ $ultimo->created_at?->format('d/m/Y H:i') }}.
                            @if($ultimo->isUsable())
                                Vale até {{ $ultimo->expires_at->format('d/m/Y H:i') }}.
                            @endif
                            @if($ultimo->opened_count > 0)
                                Aberto {{ $ultimo->opened_count }} vez(es), a última em {{ $ultimo->last_opened_at?->format('d/m/Y H:i') }}.
                            @else
                                Ainda não aberto.
                            @endif
                            @if($ultimo->identity_attempts > 0)
                                CPF errado {{ $ultimo->identity_attempts }} vez(es).
                            @endif
                        </div>

                        @if($ultimo->isUsable())
                            <form method="POST" action="{{ route('signature-documents.remote-link.destroy', [$document, $ultimo]) }}" class="mt-2"
                                  onsubmit="return confirm('Revogar o link de {{ $signer->name }}? Ele deixa de abrir, e a sessão aberta por ele é encerrada.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-[11px] font-bold text-danger hover:underline">Revogar link</button>
                            </form>
                        @endif
                    @endif

                    @if($motivo)
                        <p class="mt-2 text-[11px] text-ink-2">{{ $motivo }}</p>
                    @else
                        <form method="POST" action="{{ route('signature-documents.remote-link.store', [$document, $signer]) }}"
                              class="mt-3 flex flex-col gap-2 sm:flex-row"
                              @if($ultimo?->isUsable()) onsubmit="return confirm('Enviar um novo link? O anterior deixa de valer.')" @endif>
                            @csrf
                            <label class="sr-only" for="remote-email-{{ $signer->id }}">E-mail de {{ $signer->name }}</label>
                            <input type="email" name="email" id="remote-email-{{ $signer->id }}" required maxlength="150"
                                   value="{{ old('email', $signer->email) }}" placeholder="e-mail de quem assina"
                                   class="min-w-0 flex-1 rounded-xl border-line-strong text-sm">
                            <button type="submit"
                                    class="px-4 py-2 rounded-full text-xs font-bold bg-grena text-white hover:bg-grena-hover transition whitespace-nowrap">
                                {{ $ultimo ? 'Enviar novo link' : 'Enviar link' }}
                            </button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
</div>
@endif
