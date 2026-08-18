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

    /**
     * Como se chama o período nesta modalidade — o telão pede "2 tempos
     * restantes NESTE SET", não "neste período".
     */
    public static function nomeDoPeriodo(string $modalidadeSlug): string
    {
        return match ($modalidadeSlug) {
            Modalidade::VOLEI => 'set',
            Modalidade::BASQUETE => 'quarto',
            default => 'tempo',
        };
    }

    /**
     * Tempos técnicos por time, por período.
     *
     * São os limites usuais de cada modalidade e servem para o placar
     * MOSTRAR quantos restam — a API não bloqueia o evento que passar do
     * limite. Regulamento de torneio muda esses números, e travar aqui
     * pararia um jogo real por causa de uma tabela nossa; o log é a
     * verdade, e o que sobra é informação para a operação.
     */
    public static function timeoutsPorPeriodo(string $modalidadeSlug): int
    {
        return match ($modalidadeSlug) {
            Modalidade::VOLEI => 2,
            Modalidade::BASQUETE => 2,
            default => 1,
        };
    }

    /**
     * Substituições por time, por período — `null` quando a modalidade não
     * limita (futsal e basquete trocam à vontade; no vôlei são 6 por set).
     * Mesma natureza informativa dos tempos técnicos: não bloqueia.
     */
    public static function substituicoesPorPeriodo(string $modalidadeSlug): ?int
    {
        return $modalidadeSlug === Modalidade::VOLEI ? 6 : null;
    }
}
