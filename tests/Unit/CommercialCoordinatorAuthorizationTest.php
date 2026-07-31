<?php

namespace Tests\Unit;

use App\Exceptions\CoordinatorAuthorizationException;
use App\Http\Controllers\Concerns\AuthorizesCommercialCoordinator;
use App\Models\User;
use App\Services\WeeklyLimitCodeService;
use Tests\TestCase;

/**
 * Liberação do limite de 7 dias: quem autoriza é SEMPRE o coordenador do setor
 * Comercial, identificado pela própria matrícula e por um segredo dele — o PIN
 * (presencial) ou o código que recebeu por e-mail (ausente).
 *
 * É a mesma regra usada pelo painel web e pelo tablet, então é testada uma vez
 * só, direto no trait — sem banco, porque o model User está preso à conexão
 * `mysql` e a suíte roda em SQLite.
 */
class CommercialCoordinatorAuthorizationTest extends TestCase
{
    private const FREELANCER_ID = 7;
    private const START_DATE = '2026-08-01';

    /**
     * Dublê do serviço de códigos: guarda um código válido para um contrato.
     * O código não tem dono — o mesmo número vai para todos os coordenadores.
     */
    private function codes(?string $code = null, int $freelancerId = self::FREELANCER_ID, string $startDate = self::START_DATE): WeeklyLimitCodeService
    {
        return new class($code, $freelancerId, $startDate) extends WeeklyLimitCodeService {
            public bool $consumed = false;

            public function __construct(
                private ?string $code,
                private int $freelancerId,
                private string $startDate,
            ) {
            }

            private function isTheContract(int $freelancerId, $startDate): bool
            {
                return $this->code !== null
                    && $freelancerId === $this->freelancerId
                    && (string) $startDate === $this->startDate;
            }

            public function hasPending(int $freelancerId, $startDate): bool
            {
                return $this->isTheContract($freelancerId, $startDate);
            }

            public function consume(int $freelancerId, $startDate, ?string $code): bool
            {
                if (!$this->isTheContract($freelancerId, $startDate) || $code !== $this->code) {
                    return false;
                }

                $this->consumed = true;
                $this->code = null; // vale uma vez só

                return true;
            }
        };
    }

    private function authorizer(?User $found, ?WeeklyLimitCodeService $codes = null): object
    {
        return new class($found, $codes ?? $this->codes()) {
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

            public function run(?string $matricula, ?string $secret, ?int $freelancerId = null, $startDate = null): ?User
            {
                return $this->authorizeCommercialCoordinator($matricula, $secret, $freelancerId, $startDate);
            }

            public function find(?string $matricula): User
            {
                return $this->findCommercialCoordinator($matricula);
            }
        };
    }

    /** @param  string|null  $sector  setor do qual o usuário é coordenador */
    private function user(?string $sector, ?string $pin = '123456', int $statusId = 1): User
    {
        $user = new class extends User {
            public ?string $coordinatorSector = null;

            public function isCoordinatorOfSectorNamed(string $name): bool
            {
                return $this->coordinatorSector !== null
                    && mb_strtolower($this->coordinatorSector) === mb_strtolower($name);
            }
        };

        $user->coordinatorSector = $sector;
        $user->name = 'Coordenador';
        $user->matricula = '1234';
        $user->email = 'coordenador@exemplo.com';
        $user->status_id = $statusId;

        // O cast `hashed` do model já grava o PIN em hash.
        if ($pin !== null) {
            $user->pin = $pin;
        }

        return $user;
    }

    /* -----------------------------------------------------------------
     | Quem é o coordenador
     |-----------------------------------------------------------------*/

    public function test_coordenador_do_comercial_com_pin_correto_libera(): void
    {
        $coordinator = $this->user('Comercial');

        $authorized = $this->authorizer($coordinator)->run('1234', '123456');

        $this->assertSame($coordinator, $authorized);
    }

    public function test_nome_do_setor_nao_diferencia_maiusculas(): void
    {
        $this->assertNotNull($this->authorizer($this->user('comercial'))->run('1234', '123456'));
    }

    public function test_coordenador_de_outro_setor_nao_libera(): void
    {
        $this->expectException(CoordinatorAuthorizationException::class);
        $this->expectExceptionMessage('Apenas o coordenador do setor Comercial pode liberar este registro.');

        $this->authorizer($this->user('Operacional'))->run('1234', '123456');
    }

    public function test_usuario_que_nao_e_coordenador_nao_libera(): void
    {
        $this->expectException(CoordinatorAuthorizationException::class);

        $this->authorizer($this->user(null))->run('1234', '123456');
    }

    public function test_matricula_desconhecida_e_recusada(): void
    {
        try {
            $this->authorizer(null)->run('9999', '123456');
            $this->fail('Deveria ter recusado a matrícula inexistente.');
        } catch (CoordinatorAuthorizationException $e) {
            $this->assertSame('Matrícula não encontrada ou usuário inativo.', $e->getMessage());
            $this->assertSame('matricula', $e->step);
        }
    }

    public function test_coordenador_inativo_nao_libera(): void
    {
        $this->expectException(CoordinatorAuthorizationException::class);
        $this->expectExceptionMessage('Matrícula não encontrada ou usuário inativo.');

        $this->authorizer($this->user('Comercial', '123456', statusId: 2))->run('1234', '123456');
    }

    /** Sem matrícula e sem código pendente não há como liberar. */
    public function test_sem_matricula_e_sem_codigo_nao_libera(): void
    {
        try {
            $this->authorizer($this->user('Comercial'))->run(null, '123456');
            $this->fail('Deveria ter recusado.');
        } catch (CoordinatorAuthorizationException $e) {
            $this->assertStringContainsString('Código inválido ou expirado', $e->getMessage());
            $this->assertSame('pin', $e->step);
        }
    }

    /** O envio do código não passa por matrícula nenhuma. */
    public function test_envio_do_codigo_nao_depende_de_matricula(): void
    {
        $codes = $this->codes('654321');

        $this->assertTrue($codes->hasPending(self::FREELANCER_ID, self::START_DATE));
        $this->assertFalse($codes->hasPending(self::FREELANCER_ID, '2026-08-02'));
    }

    /** O envio do código por e-mail usa só este passo — sem segredo nenhum. */
    public function test_busca_do_coordenador_para_o_envio_de_codigo_aplica_as_mesmas_regras(): void
    {
        $this->assertNotNull($this->authorizer($this->user('Comercial'))->find('1234'));

        $this->expectException(CoordinatorAuthorizationException::class);
        $this->authorizer($this->user('Financeiro'))->find('1234');
    }

    /* -----------------------------------------------------------------
     | PIN (presencial)
     |-----------------------------------------------------------------*/

    public function test_pin_errado_do_coordenador_certo_pede_o_segredo_de_novo(): void
    {
        try {
            $this->authorizer($this->user('Comercial'))->run('1234', '999999');
            $this->fail('Deveria ter recusado o PIN errado.');
        } catch (CoordinatorAuthorizationException $e) {
            $this->assertSame('PIN ou código inválido.', $e->getMessage());
            // A matrícula já foi aceita: a tela só repete o passo do segredo.
            $this->assertSame('pin', $e->step);
        }
    }

    public function test_pin_em_branco_e_recusado(): void
    {
        $this->expectException(CoordinatorAuthorizationException::class);
        $this->expectExceptionMessage('PIN ou código inválido.');

        $this->authorizer($this->user('Comercial'))->run('1234', null);
    }

    public function test_coordenador_sem_pin_e_sem_codigo_nao_libera(): void
    {
        $this->expectException(CoordinatorAuthorizationException::class);
        $this->expectExceptionMessage('Este coordenador não tem PIN definido.');

        $this->authorizer($this->user('Comercial', pin: null))->run('1234', '123456');
    }

    /* -----------------------------------------------------------------
     | Código por e-mail (coordenador ausente)
     |-----------------------------------------------------------------*/

    /**
     * Sem matrícula: é o caminho de quem recebeu o e-mail. Libera, mas sem
     * dono — o mesmo código foi para todos os coordenadores e não dá para dizer
     * qual deles ditou.
     */
    public function test_codigo_enviado_por_email_libera_sem_matricula_e_sem_dono(): void
    {
        $codes = $this->codes('654321');

        $authorized = $this->authorizer($this->user('Comercial'), $codes)
            ->run(null, '654321', self::FREELANCER_ID, self::START_DATE);

        $this->assertNull($authorized);
        $this->assertTrue($codes->consumed);
    }

    /** Informar a matrícula não atrapalha: o código continua valendo. */
    public function test_codigo_vale_mesmo_com_matricula_informada(): void
    {
        $codes = $this->codes('654321');

        $authorized = $this->authorizer($this->user('Comercial'), $codes)
            ->run('1234', '654321', self::FREELANCER_ID, self::START_DATE);

        $this->assertNull($authorized);
        $this->assertTrue($codes->consumed);
    }

    public function test_coordenador_sem_pin_ainda_libera_pelo_codigo(): void
    {
        $codes = $this->codes('654321');

        $authorized = $this->authorizer($this->user('Comercial', pin: null), $codes)
            ->run(null, '654321', self::FREELANCER_ID, self::START_DATE);

        $this->assertNull($authorized);
        $this->assertTrue($codes->consumed);
    }

    /** O código é preso ao contrato: não serve para registrar outro. */
    public function test_codigo_de_outro_contrato_nao_libera(): void
    {
        $codes = $this->codes('654321');

        $this->expectException(CoordinatorAuthorizationException::class);
        $this->expectExceptionMessage('PIN ou código inválido.');

        $this->authorizer($this->user('Comercial'), $codes)
            ->run('1234', '654321', self::FREELANCER_ID, '2026-08-02');
    }

    public function test_codigo_vale_uma_vez_so(): void
    {
        $codes = $this->codes('654321');
        $authorizer = $this->authorizer($this->user('Comercial'), $codes);

        $authorizer->run('1234', '654321', self::FREELANCER_ID, self::START_DATE);

        $this->expectException(CoordinatorAuthorizationException::class);
        $authorizer->run('1234', '654321', self::FREELANCER_ID, self::START_DATE);
    }

    /** Sem contrato informado o código nem é consultado — só o PIN vale. */
    public function test_sem_contrato_informado_o_codigo_nao_e_aceito(): void
    {
        $codes = $this->codes('654321');

        $this->expectException(CoordinatorAuthorizationException::class);

        $this->authorizer($this->user('Comercial'), $codes)->run('1234', '654321');
    }

    public function test_pin_continua_valendo_mesmo_havendo_codigo_pendente(): void
    {
        $codes = $this->codes('654321');

        $authorized = $this->authorizer($this->user('Comercial'), $codes)
            ->run('1234', '123456', self::FREELANCER_ID, self::START_DATE);

        $this->assertNotNull($authorized);
        // Liberou pelo PIN: o código continua disponível.
        $this->assertFalse($codes->consumed);
    }
}
