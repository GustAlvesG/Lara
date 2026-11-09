<?php

namespace App\Services\MultiClubes;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lista de sócios do MultiClubes (SQL Server, somente leitura) para o
 * Disparo de E-mails, pelos filtros de {@see MemberMailingFilter}.
 *
 * Só SELECT, parâmetros ligados. O e-mail é `dbo.Members.Email` (o mesmo que
 * torneios e ingressos usam); o nome é o social quando houver. Titular vem
 * primeiro em cada título: quando titular e dependente têm o mesmo e-mail, a
 * importação fica com o primeiro (o titular) e descarta o repetido.
 *
 * Os testes trocam esta classe por uma de mentira (`$this->app->instance`).
 */
class MemberMailingDirectory
{
    private const CONNECTION = 'mc_sqlsrv';

    /** E-mail preenchido (o MultiClubes grava vazio e espaço). */
    private const HAS_EMAIL = "NULLIF(LTRIM(RTRIM(m.Email)), '') IS NOT NULL";

    /**
     * Tipos de título que têm algum título, com quantos títulos ativos cada um
     * tem. Tipo inativo no cadastro ainda pode ter título, então entra também.
     *
     * @return list<array{id: int, name: string, active_titles: int, special: bool}>
     */
    public function titleTypes(): array
    {
        $rows = DB::connection(self::CONNECTION)->select(
            'SELECT tt.Id, tt.Name, SUM(CASE WHEN t.Status = 0 THEN 1 ELSE 0 END) AS ActiveTitles
             FROM dbo.TitleTypes tt
             INNER JOIN dbo.Titles t ON t.TitleType = tt.Id
             GROUP BY tt.Id, tt.Name
             ORDER BY tt.Name'
        );

        return array_map(fn ($row) => [
            'id' => (int) $row->Id,
            'name' => trim((string) $row->Name),
            'active_titles' => (int) $row->ActiveTitles,
            'special' => in_array((int) $row->Id, TitleMemberLookup::EXCLUDED_TITLE_TYPES, true),
        ], $rows);
    }

    /**
     * Quantos sócios o filtro pega, quantos têm e-mail e quantos e-mails
     * diferentes isso dá (o que de fato entra na campanha, antes do
     * "Não enviar para").
     *
     * @return array{members: int, with_email: int, distinct_emails: int}
     */
    public function count(MemberMailingFilter $filter): array
    {
        [$where, $bindings] = $filter->whereSql();

        $row = DB::connection(self::CONNECTION)->selectOne(
            'SELECT COUNT(*) AS Members,
                    SUM(CASE WHEN ' . self::HAS_EMAIL . " THEN 1 ELSE 0 END) AS WithEmail,
                    COUNT(DISTINCT CASE WHEN " . self::HAS_EMAIL . " THEN LOWER(LTRIM(RTRIM(m.Email))) END) AS DistinctEmails
             FROM dbo.Members m
             INNER JOIN dbo.Titles t ON t.Id = m.Title
             WHERE {$where}",
            $bindings
        );

        return [
            'members' => (int) ($row->Members ?? 0),
            'with_email' => (int) ($row->WithEmail ?? 0),
            'distinct_emails' => (int) ($row->DistinctEmails ?? 0),
        ];
    }

    /**
     * Os sócios do filtro, com ou sem e-mail (quem chama conta os sem e-mail).
     *
     * @return list<array{title: string, title_type: string, titular: bool, parentage: ?string, name: string, email: ?string, sex: ?int, birth_date: ?Carbon}>
     */
    public function members(MemberMailingFilter $filter): array
    {
        [$where, $bindings] = $filter->whereSql();

        $rows = DB::connection(self::CONNECTION)->select(
            "SELECT t.Code, tt.Name AS TitleTypeName, m.Titular, p.Description AS Parentage,
                    COALESCE(NULLIF(LTRIM(RTRIM(m.SocialName)), ''), m.Name) AS MemberName,
                    m.Email, m.Sex, m.BirthDate
             FROM dbo.Members m
             INNER JOIN dbo.Titles t ON t.Id = m.Title
             INNER JOIN dbo.TitleTypes tt ON tt.Id = t.TitleType
             LEFT JOIN dbo.Parentages p ON p.Id = m.Parentage
             WHERE {$where}
             ORDER BY t.Code, m.Titular DESC, m.Name",
            $bindings
        );

        return array_map(function ($row) {
            $email = trim((string) ($row->Email ?? ''));

            return [
                'title' => trim((string) $row->Code),
                'title_type' => trim((string) $row->TitleTypeName),
                'titular' => (bool) $row->Titular,
                'parentage' => filled(trim((string) ($row->Parentage ?? ''))) ? trim((string) $row->Parentage) : null,
                'name' => trim((string) $row->MemberName),
                'email' => $email !== '' ? $email : null,
                'sex' => $row->Sex === null ? null : (int) $row->Sex,
                'birth_date' => empty($row->BirthDate) ? null : Carbon::parse($row->BirthDate)->startOfDay(),
            ];
        }, $rows);
    }
}
