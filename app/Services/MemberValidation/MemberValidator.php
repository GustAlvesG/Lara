<?php

namespace App\Services\MemberValidation;

use App\Services\MultiClubes\TitleMemberLookup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Confere se o par (nome, identificador) informado no WhatsApp corresponde a
 * um sócio ou a um funcionário do clube.
 *
 *   - Sócio: identificador é a matrícula do título no MultiClubes.
 *   - Funcionário: identificador é a matrícula (employee_code) ou o CPF.
 *
 * A consulta nunca derruba o fluxo: se o SQL Server estiver fora, o resultado
 * é "indisponível" e o pedido segue — quem decide o que fazer com um pedido
 * não conferido é a portaria, não este serviço.
 */
class MemberValidator
{
    /**
     * Onze dígitos só pode ser CPF: os códigos de título ativos no MultiClubes
     * vão de 5 a 10 caracteres. E CPF identifica apenas funcionário.
     */
    private const CPF_LENGTH = 11;

    public function __construct(
        private readonly TitleMemberLookup $titles,
        private readonly EmployeeLookup $employees,
    ) {}

    public function validate(?string $identifier, ?string $informedName): MemberValidationResult
    {
        $identifier = trim((string) $identifier);
        $informedName = trim((string) $informedName);

        if ($identifier === '' || $informedName === '') {
            return MemberValidationResult::naoEncontrado();
        }

        $digits = preg_replace('/\D/', '', $identifier);

        if (strlen($digits) === self::CPF_LENGTH) {
            $employee = $this->firstMatch($this->employees->namesForCpf($digits), $informedName);

            return $employee !== null
                ? MemberValidationResult::funcionario($employee)
                : MemberValidationResult::naoEncontrado();
        }

        // Matrícula de funcionário e código de título têm o mesmo formato
        // (5 dígitos), então o mesmo número pode existir dos dois lados.
        // Funcionário primeiro: é banco local, e assim uma queda do
        // MultiClubes não esconde um funcionário que confere.
        $employee = $this->firstMatch($this->employees->namesForCode($identifier), $informedName);

        if ($employee !== null) {
            return MemberValidationResult::funcionario($employee);
        }

        try {
            $memberNames = $this->titles->namesForTitle($identifier);
        } catch (Throwable $e) {
            Log::warning('MemberValidator: MultiClubes indisponível', [
                'matricula' => $identifier,
                'error' => $e->getMessage(),
            ]);

            return MemberValidationResult::indisponivel();
        }

        $member = $this->firstMatch($memberNames, $informedName);

        return $member !== null
            ? MemberValidationResult::socio($member)
            : MemberValidationResult::naoEncontrado();
    }

    /**
     * @param  string[]  $officialNames
     * @return string|null O nome oficial que conferiu.
     */
    private function firstMatch(array $officialNames, string $informedName): ?string
    {
        foreach ($officialNames as $officialName) {
            if ($this->namesMatch($informedName, $officialName)) {
                return $officialName;
            }
        }

        return null;
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
