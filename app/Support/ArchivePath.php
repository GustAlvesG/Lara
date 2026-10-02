<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Nomes de pasta e de arquivo do servidor de arquivos (FTP), onde ficam as
 * cópias dos documentos assinados — os do balcão (SignatureArchiver) e os
 * contratos de freelancer (FreelancerContractArchiver).
 *
 * A organização é uma só para os dois, pensada para quem procura um documento
 * sem abrir o sistema — primeiro o TIPO, depois a PESSOA:
 *
 *     Lara/DocumentosAssinados/
 *       Contrato de Locacao de Espaco/              ← tipo: o modelo
 *         Maria de Souza/                           ← pessoa: o primeiro signatário
 *           2026-10-03 - Maria de Souza e Joao Pereira - 6W5YTT.pdf
 *       Freelancers/                                ← tipo
 *         Joao Antonio da Conceicao/                ← pessoa: o freelancer
 *           2026-10-03 - Contrato - C1234.pdf
 *           2026-10-03 - Termo aditivo - C1240.pdf
 *
 * - **A data da assinatura abre o nome do arquivo**, em ano-mês-dia: dentro da
 *   pasta da pessoa, os documentos ficam em ordem de data sozinhos.
 * - **A quantidade de signatários aparece nos nomes**: um, o nome; dois, os
 *   dois; três ou mais, os dois primeiros e "e mais N".
 * - **O código no fim** torna o nome único e liga o arquivo ao registro no
 *   sistema. CPF não entra em nome de pasta nem de arquivo — o preço disso é
 *   que duas pessoas de nome idêntico dividem a mesma pasta.
 *
 * Tudo sem acento e sem os caracteres que Windows e FTP recusam: servidor FTP
 * antigo troca "ç" por lixo, e uma pasta com nome quebrado ninguém acha.
 */
final class ArchivePath
{
    /**
     * Um trecho de nome de pasta ou arquivo: sem acento, sem caractere
     * proibido, sem ponto ou espaço nas pontas.
     */
    public static function clean(?string $texto, int $limite): string
    {
        $texto = Str::ascii((string) $texto);
        $texto = (string) preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/', ' ', $texto);
        $texto = (string) preg_replace('/[^A-Za-z0-9 ._()\-]+/', '', $texto);
        $texto = trim((string) preg_replace('/\s+/', ' ', $texto), ' .');

        return trim(mb_substr($texto, 0, $limite), ' .');
    }

    /**
     * Quem assinou, resumido: "Ana", "Ana e Beto", "Ana e Beto e mais 2".
     *
     * @param  iterable<int, string|null>  $nomes  na ordem de assinatura
     */
    public static function signers(iterable $nomes): string
    {
        $limpos = [];

        foreach ($nomes as $nome) {
            $limpo = self::clean($nome, 40);

            if ($limpo !== '') {
                $limpos[] = $limpo;
            }
        }

        $resumo = implode(' e ', array_slice($limpos, 0, 2));

        if (count($limpos) > 2) {
            $resumo .= ' e mais ' . (count($limpos) - 2);
        }

        return $resumo;
    }

    /** A pasta da pessoa: o nome dela, ou um nome fixo quando não há. */
    public static function person(?string $nome): string
    {
        return self::clean($nome, 60) ?: 'Sem nome';
    }

    /**
     * O nome do arquivo: os trechos não vazios, separados por " - ".
     *
     * @param  array<int, string|null>  $trechos
     */
    public static function file(array $trechos): string
    {
        return implode(' - ', array_filter($trechos, fn($trecho) => $trecho !== null && $trecho !== '')) . '.pdf';
    }
}
