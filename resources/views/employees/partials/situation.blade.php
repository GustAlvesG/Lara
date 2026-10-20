{{--
    Selo da situação do funcionário — a mesma regra que decide se ele pode
    pedir carro de aplicativo e dirigir a frota (Employee::blockReason()).
--}}
@php
    $reason = $employee->blockReason();
    $kind = match (true) {
        $reason === null => 'ok',
        $employee->trashed() => 'off',
        ! $employee->isActiveOn() => 'danger',
        default => 'warn',
    };
@endphp
<x-pill :kind="$kind">{{ $reason ?? 'Apto' }}</x-pill>
