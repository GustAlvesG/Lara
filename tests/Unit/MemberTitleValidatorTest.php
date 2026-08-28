<?php

namespace Tests\Unit;

use App\Models\UberAccessRequest;
use App\Services\MultiClubes\MemberTitleValidator;
use App\Services\MultiClubes\TitleMemberLookup;
use RuntimeException;
use Throwable;

/**
 * Regra de conferência do sócio, sem tocar em banco: o lookup do MultiClubes
 * é substituído por uma lista fixa de nomes.
 *
 * Estende Tests\TestCase (e não PHPUnit direto) só porque o caminho de
 * indisponibilidade registra em Log, que precisa da aplicação de pé. Nenhuma
 * migration é aplicada — nada aqui toca banco.
 */
class MemberTitleValidatorTest extends \Tests\TestCase
{
    /** @param  string[]|Throwable  $names */
    private function validator(array|Throwable $names): MemberTitleValidator
    {
        $lookup = new class($names) extends TitleMemberLookup {
            public function __construct(private array|Throwable $names) {}

            public function namesForTitle(string $matricula): array
            {
                if ($this->names instanceof Throwable) {
                    throw $this->names;
                }

                return $this->names;
            }
        };

        return new MemberTitleValidator($lookup);
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

    public function test_outage_is_reported_as_unavailable_not_as_mismatch(): void
    {
        $result = $this->validator(new RuntimeException('sql server unreachable'))
            ->validate('00010', 'Gustavo Alves');

        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_INDISPONIVEL, $result->status);
    }
}
