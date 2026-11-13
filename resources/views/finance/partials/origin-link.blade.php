{{-- Link para a tela de origem de um recebimento, só para quem a alcança:
     Reservas → o pagamento; Torneios → o financeiro do torneio; Ingressos →
     o pedido. $origin, $ref (reference_id do FinanceLedger), $text. --}}
@php
    use App\Authorization\Permissions as P;
    use App\Services\Finance\FinanceLedger;

    $href = match (true) {
        ! $ref => null,
        $origin === FinanceLedger::ORIGIN_RESERVAS && auth()->user()?->can(P::RESERVAS_PAGAMENTOS) => route('payment.show', $ref),
        $origin === FinanceLedger::ORIGIN_TORNEIOS && auth()->user()?->can(P::TORNEIOS_FINANCEIRO) => route('tournaments.finance.show', $ref),
        $origin === FinanceLedger::ORIGIN_INGRESSOS && auth()->user()?->can(P::INGRESSOS_VENDAS) => route('tickets.orders.show', $ref),
        default => null,
    };
@endphp
@if ($href)<a href="{{ $href }}" class="font-semibold text-grena-ink hover:underline">{{ $text }}</a>@else<span class="font-semibold text-ink">{{ $text }}</span>@endif
