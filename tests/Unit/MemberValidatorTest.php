<?php

namespace Tests\Unit;

use App\Models\UberAccessRequest;
use App\Services\MemberValidation\EmployeeLookup;
use App\Services\MemberValidation\MemberValidationResult;
use App\Services\MemberValidation\MemberValidator;
use App\Services\MultiClubes\TitleMemberLookup;
use RuntimeException;
use Throwable;

/**
 * Regra de conferência de sócio/funcionário, sem tocar em banco: os lookups do
 * MultiClubes e da tabela employees são substituídos por listas fixas.
 *
 * Estende Tests\TestCase (e não PHPUnit direto) só porque o caminho de
 * indisponibilidade registra em Log, que precisa da aplicação de pé. Nenhuma
 * migration é aplicada — nada aqui toca banco.
 */
class MemberValidatorTest extends \Tests\TestCase
{
    /** Lookup do MultiClubes do último validator(), para ver se foi consultado. */
    private TitleMemberLookup $titles;

    /**
     * @param  string[]|Throwable  $titleNames  Pessoas do título, ou a falha do MultiClubes.
     * @param  string[]  $employeesByCode  Funcionários da matrícula consultada.
     * @param  string[]  $employeesByCpf  Funcionários do CPF consultado.
     */
    private function validator(
        array|Throwable $titleNames = [],
        array $employeesByCode = [],
        array $employeesByCpf = []
    ): MemberValidator {
        $titles = new class($titleNames) extends TitleMemberLookup {
            public array $queried = [];

            public function __construct(private array|Throwable $names) {}

            public function namesForTitle(string $matricula): array
            {
                $this->queried[] = $matricula;

                if ($this->names instanceof Throwable) {
                    throw $this->names;
                }

                return $this->names;
            }
        };

        $employees = new class($employeesByCode, $employeesByCpf) extends EmployeeLookup {
            public function __construct(private array $byCode, private array $byCpf) {}

            public function namesForCode(string $code): array
            {
                return $this->byCode;
            }

            public function namesForCpf(string $cpfDigits): array
            {
                return $this->byCpf;
            }
        };

        $this->titles = $titles;

        return new MemberValidator($titles, $employees);
    }

    private function assertResult(string $status, ?string $type, MemberValidationResult $result): void
    {
        $this->assertSame($status, $result->status);
        $this->assertSame($type, $result->type);
    }

    private function assertValidado(array $officialNames, string $informed): void
    {
        $result = $this->validator($officialNames)->validate('00010', $informed);

        $this->assertSame(
            UberAccessRequest::MEMBER_VALIDATION_VALIDADO,
            $result->status,
            "Esperava validar \"{$informed}\"."
        );
    }

    private function assertNaoEncontrado(array $officialNames, string $informed): void
    {
        $result = $this->validator($officialNames)->validate('00010', $informed);

        $this->assertSame(
            UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO,
            $result->status,
            "Não esperava validar \"{$informed}\"."
        );
    }

    // --- Regra do nome (igual para sócio e funcionário) ---------------------

    public function test_ignores_surnames_entirely(): void
    {
        $title = ['GUSTAVO DELGADO ALVES GONCALVES'];

        $this->assertValidado($title, 'Gustavo Alves');          // nome incompleto
        $this->assertValidado($title, 'Gustavo');                // só o primeiro nome
        $this->assertValidado($title, 'Gustavo Sobrenome Errado'); // sobrenome não importa
    }

    public function test_ignores_accents_and_case(): void
    {
        // O MultiClubes guarda em caixa alta e sem acento; o associado não.
        $this->assertValidado(['JOSE GENTIL DO AMARAL'], 'José Gentil');
        $this->assertValidado(['JOSE GENTIL DO AMARAL'], 'josé');
        $this->assertValidado(['MARIA LUIZA ABBA DO AMARAL'], '  maria   luiza  ');
    }

    public function test_rejects_a_different_first_name(): void
    {
        $title = ['GUSTAVO DELGADO ALVES GONCALVES', 'MARIA SOUZA'];

        $this->assertNaoEncontrado($title, 'Joana Alves');
        // Sobrenome não valida: a conferência é do primeiro nome.
        $this->assertNaoEncontrado($title, 'Alves');
    }

    public function test_matches_any_person_on_the_title(): void
    {
        $title = ['HILDA AQUINO DE OLIVEIRA', 'SYLVIO ESPINDOLA DE OLIVEIRA'];

        $this->assertValidado($title, 'Sylvio');
        $this->assertValidado($title, 'Hilda Oliveira');
    }

    public function test_returns_the_official_name_that_matched(): void
    {
        $result = $this->validator(['MARIA SOUZA', 'GUSTAVO DELGADO ALVES GONCALVES'])
            ->validate('00010', 'Gustavo Alves');

        $this->assertSame('GUSTAVO DELGADO ALVES GONCALVES', $result->matchedName);
    }

    public function test_blank_input_is_not_validated(): void
    {
        $this->assertNaoEncontrado(['GUSTAVO ALVES'], '   ');

        $result = $this->validator(['GUSTAVO ALVES'])->validate('', 'Gustavo');
        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO, $result->status);
    }

    public function test_empty_title_is_not_validated(): void
    {
        $this->assertNaoEncontrado([], 'Gustavo Alves');
    }

    // --- Sócio ---------------------------------------------------------------

    public function test_member_match_is_typed_as_socio(): void
    {
        $result = $this->validator(['JOSE GENTIL DO AMARAL'])->validate('00010', 'José');

        $this->assertResult(UberAccessRequest::MEMBER_VALIDATION_VALIDADO, UberAccessRequest::MEMBER_TYPE_SOCIO, $result);
    }

    public function test_outage_is_reported_as_unavailable_not_as_mismatch(): void
    {
        $result = $this->validator(new RuntimeException('sql server unreachable'))
            ->validate('00010', 'Gustavo Alves');

        $this->assertResult(UberAccessRequest::MEMBER_VALIDATION_INDISPONIVEL, null, $result);
    }

    // --- Funcionário ---------------------------------------------------------

    public function test_employee_matches_by_code(): void
    {
        $result = $this->validator(employeesByCode: ['DANIELLE TRINDADE BERION'])
            ->validate('12152', 'Danielle');

        $this->assertResult(UberAccessRequest::MEMBER_VALIDATION_VALIDADO, UberAccessRequest::MEMBER_TYPE_FUNCIONARIO, $result);
        $this->assertSame('DANIELLE TRINDADE BERION', $result->matchedName);
    }

    public function test_employee_matches_by_cpf_with_or_without_mask(): void
    {
        foreach (['121.983.027-56', '12198302756', ' 121 983 027 56 '] as $cpf) {
            $result = $this->validator(employeesByCpf: ['DANIELLE TRINDADE BERION'])
                ->validate($cpf, 'Danielle Trindade');

            $this->assertResult(UberAccessRequest::MEMBER_VALIDATION_VALIDADO, UberAccessRequest::MEMBER_TYPE_FUNCIONARIO, $result);
        }
    }

    public function test_cpf_is_never_looked_up_as_a_title(): void
    {
        // Nenhum título tem 11 caracteres: CPF é só de funcionário, e uma queda
        // do MultiClubes não pode transformar um CPF errado em "indisponível".
        $result = $this->validator(new RuntimeException('sql server unreachable'))
            ->validate('121.983.027-56', 'Danielle');

        $this->assertResult(UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO, null, $result);
        $this->assertSame([], $this->titles->queried);
    }

    public function test_employee_with_wrong_first_name_is_not_validated(): void
    {
        $result = $this->validator(employeesByCpf: ['DANIELLE TRINDADE BERION'])
            ->validate('12198302756', 'Samara');

        $this->assertResult(UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO, null, $result);
    }

    public function test_same_code_on_both_sides_validates_whoever_matches_the_name(): void
    {
        // Matrícula de funcionário e código de título têm o mesmo formato.
        $validator = fn () => $this->validator(
            titleNames: ['HILDA AQUINO DE OLIVEIRA'],
            employeesByCode: ['DANIELLE TRINDADE BERION']
        );

        $this->assertResult(
            UberAccessRequest::MEMBER_VALIDATION_VALIDADO,
            UberAccessRequest::MEMBER_TYPE_FUNCIONARIO,
            $validator()->validate('12152', 'Danielle')
        );

        $this->assertResult(
            UberAccessRequest::MEMBER_VALIDATION_VALIDADO,
            UberAccessRequest::MEMBER_TYPE_SOCIO,
            $validator()->validate('12152', 'Hilda')
        );
    }

    public function test_employee_still_validates_when_multiclubes_is_down(): void
    {
        $result = $this->validator(new RuntimeException('sql server unreachable'), ['DANIELLE TRINDADE BERION'])
            ->validate('12152', 'Danielle');

        $this->assertResult(UberAccessRequest::MEMBER_VALIDATION_VALIDADO, UberAccessRequest::MEMBER_TYPE_FUNCIONARIO, $result);
        $this->assertSame([], $this->titles->queried);
    }
}
