<?php

namespace App\Services\Placar;

use App\Models\Placar\Time;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Categoria de time ("Adulto", "Sub-15", "Master") — campo livre, mas com
 * grafia canônica e reaproveitamento do que já existe.
 *
 * O problema que isto resolve: "Sub 15", "Sub-15", "sub15" e "SUB 15" são a
 * mesma categoria para quem cadastra, mas quatro strings diferentes para o
 * banco. Como `times` tem UNIQUE (equipe_id, modalidade_id, categoria), cada
 * variação furava o índice e criava um time duplicado — e o filtro por
 * categoria passava a mostrar listas incompletas.
 *
 * Duas regras, nesta ordem:
 *
 * 1. Se já existe categoria cadastrada equivalente (mesma `chave`), a grafia
 *    JÁ EXISTENTE vence — não adianta canonizar se o resultado ainda difere
 *    do que o cadastro vinha usando.
 * 2. Só quando é categoria realmente nova é que a grafia canônica é
 *    aplicada.
 */
class CategoriaService
{
    /**
     * Chave de comparação: sem acento, sem caixa, sem separador. É o que
     * faz "Sub 15", "Sub-15" e "sub15" colidirem de propósito.
     */
    public static function chave(?string $categoria): string
    {
        $texto = Str::ascii(trim((string) $categoria));

        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($texto)) ?? '';
    }

    /**
     * Grafia canônica de uma categoria nova: "sub 15" e "SUB15" viram
     * "Sub-15"; o resto vira Title Case.
     */
    public static function canonizar(string $categoria): string
    {
        $limpo = trim(preg_replace('/\s+/', ' ', $categoria) ?? '');

        if ($limpo === '') {
            return Time::CATEGORIA_PADRAO;
        }

        // "sub 15", "sub-15", "sub15" (com qualquer sufixo: "Sub-15 Feminino")
        if (preg_match('/^sub\s*-?\s*(\d+)\s*(.*)$/iu', $limpo, $partes)) {
            $sufixo = trim($partes[2]);

            return 'Sub-' . $partes[1] . ($sufixo !== '' ? ' ' . Str::title($sufixo) : '');
        }

        return Str::title($limpo);
    }

    /**
     * Categoria a gravar: reaproveita a grafia já cadastrada quando houver
     * equivalente, senão canoniza. Ponto único chamado pelo cadastro web e
     * pela criação em campo da API — as duas portas de entrada de time.
     */
    public static function resolver(?string $categoria): string
    {
        $entrada = trim((string) $categoria);

        if ($entrada === '') {
            return Time::CATEGORIA_PADRAO;
        }

        $chave = self::chave($entrada);

        $existente = self::existentes()->first(fn (string $atual) => self::chave($atual) === $chave);

        return $existente ?? self::canonizar($entrada);
    }

    /**
     * Categorias já cadastradas, em ordem alfabética — alimenta o datalist
     * das telas de cadastro para que quem digita veja o que já existe antes
     * de inventar uma variação.
     *
     * @return Collection<int, string>
     */
    public static function existentes(): Collection
    {
        return Time::query()
            ->select('categoria')
            ->whereNotNull('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria')
            ->filter(fn (?string $c) => filled($c))
            ->values();
    }
}
