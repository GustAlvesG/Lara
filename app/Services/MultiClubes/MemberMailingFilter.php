<?php

namespace App\Services\MultiClubes;

use Illuminate\Support\Carbon;

/**
 * Filtro da lista de sócios do MultiClubes para o Disparo de E-mails.
 *
 * Os códigos são os do MultiClubes (não há tabela de domínio; conferidos com
 * GROUP BY na base em 09/11/2026):
 *  - `Titles.Status`: 0 Ativo · 1 Bloqueado · 2 Cancelado · 3 Transferido · 4 Vencido;
 *  - `Members.Status`: 0 Ativo · 1 Desativado · 2 Excluído · 3 Vencido;
 *  - `Members.Sex`: 0 Masculino · 1 Feminino;
 *  - `Members.Titular`: 1 titular · 0 dependente.
 *
 * A idade vira faixa de data de nascimento ({@see whereSql()}): dá a mesma
 * conta de anos completos do `dbo.GetYearDiff`, sem chamar função por linha e
 * usando o índice da coluna.
 */
final class MemberMailingFilter
{
    public const TITLE_STATUSES = [
        0 => 'Ativo',
        1 => 'Bloqueado',
        2 => 'Cancelado',
        3 => 'Transferido',
        4 => 'Vencido',
    ];

    public const MEMBER_STATUSES = [
        0 => 'Ativo',
        1 => 'Desativado',
        2 => 'Excluído',
        3 => 'Vencido',
    ];

    public const SEXES = [
        0 => 'Masculino',
        1 => 'Feminino',
    ];

    public const LINK_ALL = 'todos';
    public const LINK_TITULAR = 'titular';
    public const LINK_DEPENDENT = 'dependente';

    public const LINKS = [
        self::LINK_ALL => 'Todos',
        self::LINK_TITULAR => 'Só titulares',
        self::LINK_DEPENDENT => 'Só dependentes',
    ];

    /**
     * @param  list<int>  $titleTypes  vazio = todos, menos os tipos especiais
     * @param  list<int>  $titleStatuses  vazio = qualquer situação
     * @param  list<int>  $memberStatuses  vazio = qualquer situação
     * @param  int|null  $sex  null = os dois
     */
    public function __construct(
        public readonly array $titleTypes = [],
        public readonly array $titleStatuses = [0],
        public readonly array $memberStatuses = [0],
        public readonly ?int $ageMin = null,
        public readonly ?int $ageMax = null,
        public readonly ?int $sex = null,
        public readonly string $link = self::LINK_ALL,
        public readonly ?Carbon $referenceDate = null,
    ) {
    }

    /** A partir do formulário já validado (`MemberMailingRequest`). */
    public static function fromInput(array $input): self
    {
        $ints = fn (string $key) => array_values(array_unique(array_map('intval', (array) ($input[$key] ?? []))));
        $optional = fn (string $key) => isset($input[$key]) && $input[$key] !== '' ? (int) $input[$key] : null;

        return new self(
            titleTypes: $ints('tipos'),
            titleStatuses: $ints('situacao_titulo'),
            memberStatuses: $ints('situacao_socio'),
            ageMin: $optional('idade_min'),
            ageMax: $optional('idade_max'),
            sex: $optional('sexo'),
            link: (string) ($input['vinculo'] ?? self::LINK_ALL),
        );
    }

    public function reference(): Carbon
    {
        return ($this->referenceDate ?? Carbon::today())->copy()->startOfDay();
    }

    /**
     * WHERE (sem a palavra) e os parâmetros, na ordem. Aliases: `t` Titles,
     * `m` Members. Nada do usuário é concatenado: só `?` e inteiros.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public function whereSql(): array
    {
        $where = [];
        $bindings = [];

        $in = function (string $column, array $values) use (&$where, &$bindings) {
            $where[] = "{$column} IN (" . implode(',', array_fill(0, count($values), '?')) . ')';
            array_push($bindings, ...$values);
        };

        if ($this->titleTypes !== []) {
            $in('t.TitleType', $this->titleTypes);
        } else {
            // Sem escolha: todos os tipos de sócio. Os especiais (funcionários,
            // escolinha não sócio, terceirizados…) só entram marcados à mão.
            $excluded = TitleMemberLookup::EXCLUDED_TITLE_TYPES;
            $where[] = 't.TitleType NOT IN (' . implode(',', array_fill(0, count($excluded), '?')) . ')';
            array_push($bindings, ...$excluded);
        }

        if ($this->titleStatuses !== []) {
            $in('t.Status', $this->titleStatuses);
        }

        if ($this->memberStatuses !== []) {
            $in('m.Status', $this->memberStatuses);
        }

        if ($this->sex !== null) {
            $where[] = 'm.Sex = ?';
            $bindings[] = $this->sex;
        }

        if ($this->link === self::LINK_TITULAR) {
            $where[] = 'm.Titular = 1';
        } elseif ($this->link === self::LINK_DEPENDENT) {
            $where[] = 'm.Titular = 0';
        }

        // Idade N completa na data de referência ⇔ nasceu até (ref − N anos).
        // "Até M anos" ⇔ ainda não fez M + 1. Comparação com o dia seguinte
        // (`<`) para não depender de hora gravada na coluna datetime. Sem
        // data de nascimento, quem filtra por idade fica de fora.
        $ref = $this->reference();

        if ($this->ageMin !== null) {
            $where[] = 'm.BirthDate < ?';
            $bindings[] = $ref->copy()->subYearsNoOverflow($this->ageMin)->addDay()->toDateString();
        }

        if ($this->ageMax !== null) {
            $where[] = 'm.BirthDate >= ?';
            $bindings[] = $ref->copy()->subYearsNoOverflow($this->ageMax + 1)->addDay()->toDateString();
        }

        return [implode(' AND ', $where), $bindings];
    }

    /** Resumo legível, gravado como nome da lista na campanha. */
    public function describe(array $typeNames = []): string
    {
        $parts = [];

        $parts[] = $this->titleTypes === []
            ? 'todos os tipos'
            : implode(', ', array_map(fn ($id) => $typeNames[$id] ?? "tipo {$id}", $this->titleTypes));

        if ($this->titleStatuses !== []) {
            $parts[] = 'título ' . mb_strtolower(implode('/', array_map(fn ($s) => self::TITLE_STATUSES[$s] ?? $s, $this->titleStatuses)));
        }

        if ($this->memberStatuses !== []) {
            $parts[] = 'sócio ' . mb_strtolower(implode('/', array_map(fn ($s) => self::MEMBER_STATUSES[$s] ?? $s, $this->memberStatuses)));
        }

        if ($this->link !== self::LINK_ALL) {
            $parts[] = mb_strtolower(self::LINKS[$this->link]);
        }

        if ($this->sex !== null) {
            $parts[] = mb_strtolower(self::SEXES[$this->sex] ?? (string) $this->sex);
        }

        if ($this->ageMin !== null && $this->ageMax !== null) {
            $parts[] = "{$this->ageMin} a {$this->ageMax} anos";
        } elseif ($this->ageMin !== null) {
            $parts[] = "{$this->ageMin} anos ou mais";
        } elseif ($this->ageMax !== null) {
            $parts[] = "até {$this->ageMax} anos";
        }

        return 'MultiClubes: ' . implode('; ', $parts);
    }
}
