<?php

namespace App\Services\EmailCampaign;

use App\Services\MultiClubes\MemberMailingDirectory;
use App\Services\MultiClubes\MemberMailingFilter;

/**
 * Monta a lista de destinatários de uma campanha a partir dos sócios do
 * MultiClubes, no mesmo formato do {@see RecipientListReader}: assim ela
 * passa pelas mesmas regras do {@see RecipientImporter} (e-mail válido,
 * repetido, teto por campanha) e o "Não enviar para" vale no envio.
 *
 * Sócio sem e-mail no MultiClubes não vira recusa (seriam milhares de linhas
 * de aviso): é só contado e informado na tela.
 */
final class MultiClubesRecipientSource
{
    /** Colunas que a lista do MultiClubes entrega, além de `email` e `nome`. */
    public const COLUMNS = [
        'nome' => 'Nome',
        'titulo' => 'Título',
        'tipo_titulo' => 'Tipo de título',
        'vinculo' => 'Vínculo',
        'parentesco' => 'Parentesco',
        'idade' => 'Idade',
        'sexo' => 'Sexo',
    ];

    /** Partículas que ficam minúsculas no nome ("Maria da Silva"). */
    private const PARTICLES = ['da', 'das', 'de', 'do', 'dos', 'e', 'di', 'du', 'del', 'van', 'von'];

    public function __construct(private readonly MemberMailingDirectory $directory)
    {
    }

    /**
     * @return array{columns: array<string, string>, rows: list<array{data: array<string, string>, label: string}>, without_email: int, members: int}
     */
    public function read(MemberMailingFilter $filter): array
    {
        $reference = $filter->reference();
        $rows = [];
        $withoutEmail = 0;
        $members = $this->directory->members($filter);

        foreach ($members as $member) {
            if ($member['email'] === null) {
                $withoutEmail++;

                continue;
            }

            $name = self::properName($member['name']);

            $rows[] = [
                'label' => "Título {$member['title']} ({$name})",
                'data' => [
                    'email' => $member['email'],
                    'nome' => $name,
                    'titulo' => $member['title'],
                    'tipo_titulo' => $member['title_type'],
                    'vinculo' => $member['titular'] ? 'Titular' : 'Dependente',
                    'parentesco' => $member['titular'] ? 'Titular' : (string) ($member['parentage'] ?? ''),
                    'idade' => $member['birth_date'] ? (string) (int) floor($member['birth_date']->diffInYears($reference)) : '',
                    'sexo' => MemberMailingFilter::SEXES[$member['sex']] ?? '',
                ],
            ];
        }

        return [
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'without_email' => $withoutEmail,
            'members' => count($members),
        ];
    }

    /**
     * "JOÃO DA SILVA" → "João da Silva". O MultiClubes grava em maiúsculas, e
     * o nome vai na saudação do e-mail. Nome já misto fica como está.
     */
    public static function properName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        if ($name === '' || $name !== mb_strtoupper($name, 'UTF-8')) {
            return $name;
        }

        $words = explode(' ', mb_strtolower($name, 'UTF-8'));

        foreach ($words as $i => $word) {
            if ($i > 0 && in_array($word, self::PARTICLES, true)) {
                continue;
            }

            $words[$i] = mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }

        return implode(' ', $words);
    }
}
