<?php

namespace App\Services\Placar;

use App\Models\Placar\Modalidade;

/**
 * Regras específicas por modalidade — ponto único de verdade, para não
 * espalhar `if ($modalidade === 'volei')` pelos controllers e serviços.
 * Usado pela validação de eventos (JogoEventoLoteService).
 */
class ModalidadeRegras
{
    /** Vôlei pontua por sets; futsal e basquete, não. */
    public static function permiteSet(string $modalidadeSlug): bool
    {
        return $modalidadeSlug === Modalidade::VOLEI;
    }

    /** Vôlei não tem falta acumulada por período. */
    public static function permiteFalta(string $modalidadeSlug): bool
    {
        return $modalidadeSlug !== Modalidade::VOLEI;
    }

    /** Ponto vale 1 no vôlei e no futsal; 1, 2 ou 3 no basquete. */
    public static function valorValidoDePonto(string $modalidadeSlug, ?int $valor): bool
    {
        if ($valor === null) {
            return false;
        }

        return match ($modalidadeSlug) {
            Modalidade::BASQUETE => in_array($valor, [1, 2, 3], true),
            default => $valor === 1,
        };
    }
}
