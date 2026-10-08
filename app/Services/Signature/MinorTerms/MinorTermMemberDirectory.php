<?php

namespace App\Services\Signature\MinorTerms;

use App\Support\Cpf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * As pessoas de um título no MultiClubes, com o que o Termo de Menores
 * imprime: nome, nascimento, CPF, RG e e-mail — e o endereço, que no
 * MultiClubes é do TÍTULO (`dbo.Titles`), não da pessoa.
 *
 * Mesmo recorte do cadastro de sócio no app e da busca do módulo
 * (SignatureMemberDirectory): título ativo, fora dos tipos especiais, pessoa
 * ativa. Só leitura.
 *
 * Diferente da busca do atendente, aqui o MultiClubes fora do ar NÃO vira
 * lista vazia: o sócio no tablet leria "título não encontrado", o que é
 * mentira. Lança MinorTermDirectoryUnavailable e a tela diz o que houve.
 */
class MinorTermMemberDirectory
{
    /** Um título de família não chega perto disso; é só um teto. */
    private const LIMIT = 30;

    /** Tipos de título fora do recorte (os mesmos do cadastro de sócio). */
    private const EXCLUDED_TITLE_TYPES = '374, 375, 693320, 1297904, 3804861, 4062070, 6736996, 6736997, 6736998, 6737000';

    /**
     * O título ativo e as pessoas dele. null = título não encontrado (ou fora
     * do recorte).
     *
     * @return array{code: string, address: ?string, people: list<array{
     *     id: int, name: string, birth_date: ?Carbon, cpf: string, rg: ?string,
     *     email: ?string, titular: bool
     * }>}|null
     *
     * @throws MinorTermDirectoryUnavailable  MultiClubes fora do ar
     */
    public function title(string $code): ?array
    {
        try {
            $titulo = DB::connection('mc_sqlsrv')->selectOne(
                'SELECT TOP 1 t.Id, t.Code, t.Street, t.Number, t.Complement, t.Burgh, t.City, t.State, t.PostalCode
                FROM dbo.Titles t
                WHERE t.Code = ?
                    AND t.Status = 0
                    AND t.TitleType NOT IN (' . self::EXCLUDED_TITLE_TYPES . ')',
                [$code],
            );

            if (!$titulo) {
                return null;
            }

            $linhas = DB::connection('mc_sqlsrv')->select(
                'SELECT TOP ' . self::LIMIT . ' m.Id, m.Name, m.BirthDate, m.DocumentUnmasked, m.Rg, m.Email, m.Titular
                FROM dbo.Members m
                WHERE m.Title = ?
                    AND m.Status = 0
                ORDER BY m.Titular DESC, m.Name',
                [$titulo->Id],
            );
        } catch (Throwable $e) {
            Log::warning('Termo de Menores: consulta ao MultiClubes falhou.', ['erro' => $e->getMessage()]);

            throw new MinorTermDirectoryUnavailable('MultiClubes indisponível.', 0, $e);
        }

        return [
            'code' => trim((string) $titulo->Code),
            'address' => $this->address($titulo),
            'people' => array_map(fn(object $linha) => [
                'id' => (int) $linha->Id,
                'name' => trim((string) $linha->Name),
                'birth_date' => $linha->BirthDate ? Carbon::parse($linha->BirthDate)->startOfDay() : null,
                'cpf' => Cpf::digits((string) $linha->DocumentUnmasked),
                'rg' => trim((string) $linha->Rg) ?: null,
                'email' => trim((string) $linha->Email) ?: null,
                'titular' => (bool) $linha->Titular,
            ], $linhas),
        ];
    }

    /** "Rua X, 123, Apto 4 - Bairro, Cidade/UF, CEP 00000-000". */
    private function address(object $titulo): ?string
    {
        $limpo = fn($v) => trim((string) $v);

        $rua = implode(', ', array_filter([$limpo($titulo->Street), $limpo($titulo->Number), $limpo($titulo->Complement)]));
        $cidade = implode('/', array_filter([$limpo($titulo->City), $limpo($titulo->State)]));
        $cep = preg_replace('/\D/', '', $limpo($titulo->PostalCode));

        $partes = array_filter([
            $rua,
            $limpo($titulo->Burgh),
            $cidade,
            strlen((string) $cep) === 8 ? 'CEP ' . substr($cep, 0, 5) . '-' . substr($cep, 5) : '',
        ]);

        return $partes === [] ? null : implode(' - ', $partes);
    }
}
