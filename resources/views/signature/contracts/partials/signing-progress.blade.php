{{--
    O andamento da assinatura de um contrato (05/11/2026, pedido do usuário: acompanhar
    dentro de Contratos, e não só em Assinaturas). $contract com `document.signers`
    carregado; $compact = a célula da lista (uma linha e quem falta resumido).
--}}
@php
    use App\Models\SignatureDocument as D;

    $compact = $compact ?? false;
    $andamento = $contract->signingProgress();
    $cor = fn (string $s) => match ($s) {
        D::STATUS_AWAITING_SIGNATURE => 'warn',
        D::STATUS_SIGNED, D::STATUS_FINALIZED => 'ok',
        D::STATUS_REFUSED, D::STATUS_CANCELED, D::STATUS_EXPIRED => 'danger',
        default => 'off',
    };
@endphp
@if(!$andamento)
    <span class="text-ink-3">—</span>
@elseif($compact)
    <div class="space-y-1">
        <div class="flex flex-wrap items-center gap-1.5">
            <x-pill :kind="$cor($andamento['status'])">{{ $andamento['label'] }}</x-pill>
            @if($andamento['total'] > 0 && $andamento['status'] !== D::STATUS_DRAFT)
                <span class="font-bold text-ink">{{ $andamento['signed'] }}/{{ $andamento['total'] }}</span>
            @endif
        </div>
        @if($andamento['refused'])
            <p class="text-danger">Recusado por {{ $andamento['refused'] }}</p>
        @elseif($andamento['status'] === D::STATUS_AWAITING_SIGNATURE && $andamento['pending'] !== [])
            <p class="max-w-[16rem] truncate text-ink-2" title="Falta: {{ implode('; ', $andamento['pending']) }}">
                Falta: {{ implode('; ', $andamento['pending']) }}
            </p>
        @elseif($andamento['status'] === D::STATUS_DRAFT)
            <p class="text-ink-2">Falta congelar</p>
        @endif
    </div>
@else
    <div class="space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <x-pill :kind="$cor($andamento['status'])">{{ $andamento['label'] }}</x-pill>
            @if($andamento['total'] > 0)
                <span class="text-sm font-bold text-ink">{{ $andamento['signed'] }} de {{ $andamento['total'] }} assinaram</span>
            @endif
        </div>
        @if($andamento['total'] > 0)
            <div class="h-2 overflow-hidden rounded-full bg-subtle" aria-hidden="true">
                <div class="h-full rounded-full bg-ok" style="width: {{ (int) round(100 * $andamento['signed'] / max(1, $andamento['total'])) }}%"></div>
            </div>
        @endif
        <ul class="space-y-1 text-sm">
            @foreach($contract->document->signers->where('role', \App\Models\SignatureSigner::ROLE_SIGNER)->sortBy('position') as $s)
                <li class="flex items-start justify-between gap-2">
                    <span class="min-w-0 text-ink">
                        {{ $s->name }}
                        @if($s->party_label)<span class="block text-xs text-ink-2">{{ $s->party_label }}</span>@endif
                    </span>
                    <span @class([
                        'shrink-0 rounded-full px-2 text-xs font-bold',
                        'bg-ok-soft text-ok' => $s->status === \App\Models\SignatureSigner::STATUS_SIGNED,
                        'bg-danger-soft text-danger' => $s->status === \App\Models\SignatureSigner::STATUS_REFUSED,
                        'bg-subtle text-ink-2' => !in_array($s->status, [\App\Models\SignatureSigner::STATUS_SIGNED, \App\Models\SignatureSigner::STATUS_REFUSED], true),
                    ])>
                        {{ $s->statusLabel() }}{{ $s->signed_at ? ' ' . $s->signed_at->format('d/m') : '' }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
