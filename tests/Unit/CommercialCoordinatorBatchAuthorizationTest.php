<?php

namespace Tests\Unit;

use App\Exceptions\CoordinatorAuthorizationException;
use App\Http\Controllers\Concerns\AuthorizesCommercialCoordinator;
use App\Models\User;
use App\Services\WeeklyLimitCodeService;
use Tests\TestCase;

/**
 * Liberação do registro em massa: o mesmo coordenador do Comercial, pelo PIN
 * ou por UM código que cobre todos os contratos do lote acima do limite.
 *
 * Antes o lote com várias linhas nem consultava o código — só o PIN valia, e
 * o código pedido no registro individual era recusado sem explicação.
 *
 * Sem banco, como CommercialCoordinatorAuthorizationTest: o serviço de
 * códigos é um dublê, e a conferência de verdade (consumeAll) é testada em
 * Tests\Feature\WeeklyLimitBatchCodeTest.
 */
class CommercialCoordinatorBatchAuthorizationTest extends TestCase
{
    private const CONTRACTS = [[7, '2026-08-01'], [8, '2026-08-01'], [7, '2026-08-03']];

    /** Dublê: um código vale para exatamente este conjunto de contratos. */
    private function codes(?string $code, array $contracts = self::CONTRACTS): WeeklyLimitCodeService
    {
        return new class($code, $contracts) extends WeeklyLimitCodeService {
            public ?array $consumedFor = null;

            public function __construct(private ?string $code, private array $contracts)
            {
            }

            public function consume(int $freelancerId, $startDate, ?string $code): bool
            {
                return $this->consumeAll([[$freelancerId, $startDate]], $code);
            }

            public function consumeAll(array $contracts, ?string $code): bool
            {
                if ($this->code === null || $code !== $this->code
                    || self::distinctContracts($contracts) !== self::distinctContracts($this->contracts)) {
                    return false;
                }

                $this->consumedFor = $contracts;
                $this->code = null;

                return true;
            }
        };
    }

    private function authorizer(?User $found, WeeklyLimitCodeService $codes): object
    {
        return new class($found, $codes) {
            use AuthorizesCommercialCoordinator;

            public function __construct(private ?User $found, private WeeklyLimitCodeService $codes)
            {
            }

            protected function findUserByMatricula(string $matricula): ?User
            {
                return $this->found;
            }

            protected function weeklyLimitCodes(): WeeklyLimitCodeService
            {
                return $this->codes;
            }

            public function run(?string $matricula, ?string $secret, array $contracts): ?User
            {
                return $this->authorizeCommercialCoordinatorFor($matricula, $secret, $contracts);
            }
        };
    }

    private function coordinator(): User
    {
        $user = new class extends User {
            public function isCoordinatorOfSectorNamed(string $name): bool
            {
                return mb_strtolower($name) === 'comercial';
            }
        };

        $user->name = 'Coordenador';
        $user->matricula = '1234';
        $user->status_id = 1;
        $user->pin = '123456';

        return $user;
    }

    public function test_codigo_do_lote_libera_todas_as_linhas_sem_matricula(): void
    {
        $codes = $this->codes('654321');

        $authorized = $this->authorizer(null, $codes)->run(null, '654321', self::CONTRACTS);

        $this->assertNull($authorized);
        $this->assertSame(self::CONTRACTS, $codes->consumedFor);
    }

    public function test_codigo_do_lote_vale_mesmo_com_matricula_informada(): void
    {
        $codes = $this->codes('654321');

        $this->assertNull($this->authorizer($this->coordinator(), $codes)->run('1234', '654321', self::CONTRACTS));
    }

    /** Código pedido para outro lote (ex.: as linhas mudaram) não serve. */
    public function test_codigo_de_outro_lote_nao_libera(): void
    {
        $codes = $this->codes('654321', [[7, '2026-08-01']]);

        $this->expectException(CoordinatorAuthorizationException::class);

        $this->authorizer(null, $codes)->run(null, '654321', self::CONTRACTS);
    }

    public function test_codigo_errado_nao_libera(): void
    {
        $this->expectException(CoordinatorAuthorizationException::class);

        $this->authorizer(null, $this->codes('654321'))->run(null, '111111', self::CONTRACTS);
    }

    public function test_pin_continua_liberando_o_lote(): void
    {
        $codes = $this->codes('654321');
        $coordinator = $this->coordinator();

        $this->assertSame($coordinator, $this->authorizer($coordinator, $codes)->run('1234', '123456', self::CONTRACTS));
        $this->assertNull($codes->consumedFor);
    }
}
