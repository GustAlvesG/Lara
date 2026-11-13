<?php

namespace App\Services\Finance;

/**
 * As formas de pagamento do Lara, uma lista só para Reservas, Torneios e
 * Ingressos (módulo Financeiro, docs/funcionalidades/financeiro.md).
 *
 * Antes moravam em SchedulePayment e os outros módulos importavam de lá;
 * SchedulePayment::MANUAL_METHODS e ::METHOD_LABELS continuam existindo
 * como apelido destas, para não quebrar quem ainda as lê.
 *
 * - MANUAL: as da baixa manual (recebido fora da eRede). A chave é o que se
 *   grava em `payment_method`.
 * - ONLINE: o que a eRede devolve pelo site (`credit`, `debit`, `pix`...).
 */
final class PaymentMethods
{
    public const MANUAL = [
        'manual_cash' => 'Dinheiro',
        'manual_pix' => 'Pix direto ao clube',
        'manual_card' => 'Cartão na secretaria (maquininha)',
        'manual_transfer' => 'Transferência ou depósito',
        'manual_other' => 'Outro',
    ];

    public const ONLINE = [
        'credit' => 'Cartão de crédito',
        'debit' => 'Cartão de débito',
        'credit_card' => 'Cartão de crédito',
        'debit_card' => 'Cartão de débito',
        'pix' => 'Pix',
    ];

    public const LABELS = self::ONLINE + self::MANUAL;

    public static function label(?string $method): string
    {
        return self::LABELS[$method] ?? (string) $method;
    }

    public static function isManual(?string $method): bool
    {
        return isset(self::MANUAL[$method]);
    }

    /** As chaves da baixa manual, para o `Rule::in` dos formulários. */
    public static function manualKeys(): array
    {
        return array_keys(self::MANUAL);
    }
}
