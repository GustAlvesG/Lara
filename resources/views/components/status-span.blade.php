{{--
    Status de reserva/pagamento ($status->id: 1 confirmado, 3 pendente,
    0 cancelado). Mesma entrada de antes, desenhado com x-pill.
--}}
@php
    $kind = match (strtolower((string) ($status->id ?? ''))) {
        '1' => 'ok',
        '3' => 'warn',
        '0' => 'danger',
        default => 'off',
    };
@endphp

<x-pill :kind="$kind">{{ Str::ucfirst($status->portuguese ?? 'Status não definido') }}</x-pill>
