<?php

namespace App\Services\Placar;

use App\Models\Placar\JogoEvento;
use App\Models\Placar\Modalidade;

/**
 * Como cada esporte chama as coisas. Ponto único de verdade dos rótulos,
 * pelo mesmo motivo que ModalidadeRegras é o das regras: sem isso, a
 * súmula, a impressão e o telão acabam escrevendo nomes diferentes para o
 * mesmo evento.
 *
 * No futsal se faz **gol**, no basquete **cesta** (e o valor faz parte do
 * nome — "cesta de 3"), no vôlei **ponto**. O período é **quarter** no
 * basquete, **set** no vôlei e **período** no futsal. Vôlei não tem falta
 * (ver ModalidadeRegras::permiteFalta), então nem se escreve a palavra.
 *
 * Só nomes aqui — nada que decida o que é válido.
 */
class Vocabulario
{
    /** Rótulos que não mudam com a modalidade. */
    private const EVENTOS = [
        JogoEvento::TIPO_INICIO_JOGO => 'Início do jogo',
        JogoEvento::TIPO_FIM_JOGO => 'Fim do jogo',
        JogoEvento::TIPO_FALTA => 'Falta',
        JogoEvento::TIPO_CRONO_PLAY => 'Cronômetro rodando',
        JogoEvento::TIPO_CRONO_PAUSE => 'Cronômetro parado',
        JogoEvento::TIPO_SUBSTITUICAO => 'Substituição',
        JogoEvento::TIPO_TIMEOUT => 'Tempo técnico',
        JogoEvento::TIPO_CARTAO => 'Cartão',
        JogoEvento::TIPO_ESTORNO => 'Estorno',
    ];

    /**
     * O que é marcar um ponto nesta modalidade. No basquete o valor faz
     * parte do nome: "cesta de 3" e "cesta de 2" são lances diferentes, e
     * chamar as duas de "ponto" apaga a diferença que a súmula existe para
     * mostrar.
     */
    public static function ponto(string $modalidadeSlug, ?int $valor = null): string
    {
        return match ($modalidadeSlug) {
            Modalidade::FUTSAL => 'Gol',
            Modalidade::BASQUETE => $valor === null ? 'Cesta' : "Cesta de {$valor}",
            default => 'Ponto',
        };
    }

    /** Como se chama o total acumulado: gols no futsal, pontos nos demais. */
    public static function totalDePontos(string $modalidadeSlug): string
    {
        return $modalidadeSlug === Modalidade::FUTSAL ? 'gols' : 'pontos';
    }

    /** Minúsculo, para compor frases ("início do quarter"). */
    public static function periodo(string $modalidadeSlug): string
    {
        return match ($modalidadeSlug) {
            Modalidade::VOLEI => 'set',
            Modalidade::BASQUETE => 'quarter',
            default => 'período',
        };
    }

    /** Com inicial maiúscula, para título e chip ("Quarter 2"). */
    public static function periodoTitulo(string $modalidadeSlug): string
    {
        return ucfirst(self::periodo($modalidadeSlug));
    }

    /** "Set 3", "Quarter 2", "Período 1". */
    public static function periodoNumerado(string $modalidadeSlug, int $numero): string
    {
        return self::periodoTitulo($modalidadeSlug) . ' ' . $numero;
    }

    /**
     * Rótulo de um evento da linha do tempo — é o que a súmula mostra no
     * lugar do slug cru (`ponto`, `crono_set`).
     */
    public static function evento(string $modalidadeSlug, string $tipo, ?int $valor = null): string
    {
        return match ($tipo) {
            JogoEvento::TIPO_PONTO => self::ponto($modalidadeSlug, $valor),
            // O `set` marca quem VENCEU a parcial; os dois "crono_set" e
            // "periodo" marcam o começo de uma.
            JogoEvento::TIPO_SET => self::periodoTitulo($modalidadeSlug) . ' vencido',
            JogoEvento::TIPO_PERIODO, JogoEvento::TIPO_CRONO_SET => 'Início do ' . self::periodo($modalidadeSlug),
            default => self::EVENTOS[$tipo] ?? ucfirst(str_replace('_', ' ', $tipo)),
        };
    }

    /**
     * O bloco que vai no payload da súmula para o telão rotular igual sem
     * repetir esta tabela do lado dele.
     */
    public static function daModalidade(string $modalidadeSlug): array
    {
        return [
            'ponto' => self::ponto($modalidadeSlug),
            'pontos' => self::totalDePontos($modalidadeSlug),
            'periodo' => self::periodo($modalidadeSlug),
            'tem_falta' => ModalidadeRegras::permiteFalta($modalidadeSlug),
        ];
    }
}
