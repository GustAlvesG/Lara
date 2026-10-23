<?php

namespace App\Services\MultiClubes;

use App\Models\Tournament\TournamentCategoryTemplate;
use App\Support\Cpf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dados de sócio no MultiClubes (SQL Server, somente leitura) por CPF ou por
 * matrícula, para os torneios: nome, nascimento, sexo e e-mail.
 *
 * Mesmo critério de sócio do cadastro (MemberService::queryMember): título
 * ativo (`Titles.Status = 0`) e fora dos tipos especiais. A matrícula é o
 * título da família — traz titular e dependentes, e quem chama escolhe.
 *
 * Parâmetros sempre ligados (`?`), nunca concatenados no SQL.
 */
class MemberProfileLookup
{
    /**
     * `dbo.Members.Sex`. O MultiClubes não tem tabela de domínio; a regra foi
     * fixada em 22/10/2026 (conferida pela distribuição dos nomes).
     */
    public const SEX_MALE = 0;
    public const SEX_FEMALE = 1;

    /**
     * Uma pessoa pode estar em mais de um título ativo (em 22/10/2026 eram
     * 438 CPFs), por isso vem lista.
     *
     * CPF inválido nem vai ao banco: o MultiClubes tem cadastros com CPF de
     * preenchimento (00000000000), e a busca acharia uma pessoa qualquer.
     *
     * @return list<MemberProfile> titular primeiro, depois por nome
     */
    public function byCpf(string $cpf): array
    {
        $cpf = Cpf::digits($cpf);

        if (! Cpf::isValid($cpf)) {
            return [];
        }

        return $this->query('m.DocumentUnmasked = ?', $cpf);
    }

    /** A pessoa daquele CPF naquela matrícula — o sócio logado no site. */
    public function find(string $cpf, string $title): ?MemberProfile
    {
        foreach ($this->byCpf($cpf) as $profile) {
            if ($profile->title === trim($title)) {
                return $profile;
            }
        }

        return null;
    }

    /** @return list<MemberProfile> titular primeiro, depois por nome */
    public function byTitle(string $title): array
    {
        $title = trim($title);

        if ($title === '') {
            return [];
        }

        return $this->query('t.Code = ?', $title);
    }

    public static function genderFromSex(mixed $sex): ?string
    {
        if ($sex === null || $sex === '') {
            return null;
        }

        return match ((int) $sex) {
            self::SEX_MALE => TournamentCategoryTemplate::GENDER_MALE,
            self::SEX_FEMALE => TournamentCategoryTemplate::GENDER_FEMALE,
            default => null,
        };
    }

    /** @return list<MemberProfile> */
    private function query(string $where, string $value): array
    {
        $excluded = TitleMemberLookup::EXCLUDED_TITLE_TYPES;
        $placeholders = implode(',', array_fill(0, count($excluded), '?'));

        $rows = DB::connection('mc_sqlsrv')->select(
            "SELECT m.Name, m.DocumentUnmasked, m.BirthDate, m.Sex, m.Email, m.MobilePhone, m.Barcode, m.Titular, t.Code
             FROM dbo.Members m
             INNER JOIN dbo.Titles t ON m.Title = t.Id
             WHERE {$where}
               AND t.Status = 0
               AND t.TitleType NOT IN ({$placeholders})
             ORDER BY m.Titular DESC, m.Name",
            array_merge([$value], $excluded)
        );

        return array_values(array_map(fn ($row) => $this->profile($row), $rows));
    }

    private function profile(object $row): MemberProfile
    {
        $email = trim((string) ($row->Email ?? ''));

        return new MemberProfile(
            name: trim((string) ($row->Name ?? '')),
            cpf: Cpf::digits((string) ($row->DocumentUnmasked ?? '')),
            title: trim((string) ($row->Code ?? '')),
            birthDate: empty($row->BirthDate) ? null : Carbon::parse($row->BirthDate)->startOfDay(),
            gender: self::genderFromSex($row->Sex ?? null),
            email: $email !== '' ? $email : null,
            titular: (bool) ($row->Titular ?? false),
            phone: filled(trim((string) ($row->MobilePhone ?? ''))) ? trim((string) $row->MobilePhone) : null,
            barcode: filled(trim((string) ($row->Barcode ?? ''))) ? trim((string) $row->Barcode) : null,
        );
    }
}
