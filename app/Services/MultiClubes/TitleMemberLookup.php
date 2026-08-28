<?php

namespace App\Services\MultiClubes;

use Illuminate\Support\Facades\DB;

/**
 * Consulta de sócios por título no MultiClubes (SQL Server, somente leitura).
 *
 * O vínculo é dbo.Members.Title -> dbo.Titles.Id; a matrícula que o associado
 * informa no WhatsApp é dbo.Titles.Code. Um título tem várias pessoas (titular
 * e dependentes), então a consulta devolve a lista e quem chama decide.
 */
class TitleMemberLookup
{
    /**
     * Tipos de título especiais, excluídos de qualquer validação de sócio.
     * Mesma lista usada por MemberService::queryMember() no cadastro.
     */
    private const EXCLUDED_TITLE_TYPES = [
        374, 375, 693320, 1297904, 3804861, 4062070,
        6736996, 6736997, 6736998, 6737000,
    ];

    /**
     * Nomes das pessoas vinculadas a uma matrícula de título ativo.
     *
     * @return string[] Lista possivelmente vazia (matrícula inexistente,
     *                  inativa ou de tipo especial).
     */
    public function namesForTitle(string $matricula): array
    {
        $placeholders = implode(',', array_fill(0, count(self::EXCLUDED_TITLE_TYPES), '?'));

        $rows = DB::connection('mc_sqlsrv')->select(
            "SELECT m.Name
             FROM dbo.Members m
             INNER JOIN dbo.Titles t ON m.Title = t.Id
             WHERE t.Code = ?
               AND t.Status = 0
               AND t.TitleType NOT IN ({$placeholders})",
            array_merge([$matricula], self::EXCLUDED_TITLE_TYPES)
        );

        $names = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row->Name ?? ''));

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
