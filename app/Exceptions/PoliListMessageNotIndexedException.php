<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A resposta cita um menu que ainda não está indexado.
 *
 * Não é veredito: pode ser a corrida entre o webhook de saída e o de entrada,
 * e nesse caso a resposta é legítima e basta tentar de novo daqui a pouco.
 * Quem decide entre reprocessar e desistir é o job — não o fluxo, que não sabe
 * em qual tentativa está.
 */
class PoliListMessageNotIndexedException extends RuntimeException
{
    public function __construct(public readonly string $poliMessageUuid)
    {
        parent::__construct("Menu da Poli ainda não indexado: {$poliMessageUuid}");
    }
}
