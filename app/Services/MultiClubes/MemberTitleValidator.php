<?php

namespace App\Services\MultiClubes;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Confere se o par (nome, matrícula) informado no WhatsApp corresponde a
 * alguém do título no MultiClubes.
 *
 * A consulta nunca derruba o fluxo: se o SQL Server estiver fora, o resultado
 * é "indisponível" e o pedido segue — quem decide o que fazer com um pedido
 * não conferido é a portaria, não este serviço.
 */
class MemberTitleValidator
{
    public function __construct(private readonly TitleMemberLookup $lookup) {}

    public function validate(?string $matricula, ?string $informedName): MemberValidationResult
    {
        $matricula = trim((string) $matricula);
        $informedName = trim((string) $informedName);

        if ($matricula === '' || $informedName === '') {
            return MemberValidationResult::naoEncontrado();
        }

        try {
            $officialNames = $this->lookup->namesForTitle($matricula);
        } catch (Throwable $e) {
            Log::warning('MemberTitleValidator: MultiClubes indisponível', [
                'matricula' => $matricula,
                'error' => $e->getMessage(),
            ]);

            return MemberValidationResult::indisponivel();
        }

        foreach ($officialNames as $officialName) {
            if ($this->namesMatch($informedName, $officialName)) {
                return MemberValidationResult::validado($officialName);
            }
        }

        return MemberValidationResult::naoEncontrado();
    }

    /**
     * A conferência é só do primeiro nome: sobrenomes são ignorados dos dois
     * lados. Isso cobre de uma vez o caso mais comum, o associado que informa
     * o nome incompleto ("Gustavo Alves" para "GUSTAVO DELGADO ALVES
     * GONCALVES"), sem depender de quantos sobrenomes ele resolveu digitar.
     *
     * A normalização é obrigatória, não cosmética: o MultiClubes guarda os
     * nomes em caixa alta e sem acento (apenas 1% dos 53 mil têm acento), e o
     * associado digita "José". Sem remover acento e caixa, sócio legítimo
     * seria reprovado quase sempre.
     */
    private function namesMatch(string $informedName, string $officialName): bool
    {
        $informedFirst = $this->firstName($informedName);
        $officialFirst = $this->firstName($officialName);

        return $informedFirst !== '' && $informedFirst === $officialFirst;
    }

    private function firstName(string $name): string
    {
        $normalized = $this->normalize($name);

        if ($normalized === '') {
            return '';
        }

        return explode(' ', $normalized)[0];
    }

    private function normalize(string $name): string
    {
        $ascii = Str::ascii($name);

        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $ascii)));
    }
}
