<?php

namespace App\Services\Signature;

use App\Support\Cpf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * As pessoas de um título, lidas do MultiClubes — titular E dependentes.
 *
 * A tabela local `members` só tem quem se cadastrou no aplicativo, e cada
 * pessoa só aparece lá depois de se cadastrar: procurar um título nela acha,
 * na prática, só o titular. Quem vem ao balcão assinar muitas vezes é o
 * dependente, e ele está no MultiClubes.
 *
 * É só leitura, e só para adiantar a digitação do atendente: se o MultiClubes
 * não responder, a busca segue com o que a tabela local tiver.
 */
class SignatureMemberDirectory
{
    /** Um título de família não chega perto disso; é só um teto. */
    private const LIMIT = 30;

    /**
     * O termo tem cara de código de título? Os códigos têm de 5 a 10
     * caracteres, sem espaço, e nem todos são só números.
     */
    public function looksLikeTitle(string $term): bool
    {
        return (bool) preg_match('/^[\pL\d.\-\/]{3,10}$/u', $term);
    }

    /**
     * Titular e dependentes ativos de um título ativo, o titular primeiro.
     *
     * @return list<array{name: string, cpf: string, title: string, email: ?string, phone: ?string, titular: bool}>
     */
    public function byTitle(string $code): array
    {
        try {
            // Mesmo recorte do cadastro de sócio no app (MemberService::queryMember):
            // título ativo e fora dos tipos especiais.
            $linhas = DB::connection('mc_sqlsrv')->select(
                'SELECT TOP ' . self::LIMIT . ' m.Name, m.DocumentUnmasked, m.Email, m.MobilePhone, m.Titular, t.Code
                FROM dbo.Members m
                INNER JOIN dbo.Titles t ON m.Title = t.Id
                WHERE t.Code = ?
                    AND t.Status = 0
                    AND t.TitleType NOT IN (374, 375, 693320, 1297904, 3804861, 4062070, 6736996, 6736997, 6736998, 6737000)
                    AND m.Status = 0
                ORDER BY m.Titular DESC, m.Name',
                [$code],
            );
        } catch (Throwable $e) {
            Log::warning('Assinatura: busca de título no MultiClubes falhou.', ['erro' => $e->getMessage()]);

            return [];
        }

        return array_map(fn(object $linha) => [
            'name' => trim((string) $linha->Name),
            'cpf' => Cpf::digits((string) $linha->DocumentUnmasked),
            'title' => (string) $linha->Code,
            'email' => trim((string) $linha->Email) ?: null,
            'phone' => trim((string) $linha->MobilePhone) ?: null,
            'titular' => (bool) $linha->Titular,
        ], $linhas);
    }
}
