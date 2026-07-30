<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\CoordinatorAuthorizationException;
use App\Models\User;
use App\Services\WeeklyLimitCodeService;

/**
 * Autorização pelo coordenador do setor Comercial.
 *
 * Passar do limite de serviços em 7 dias não é decisão de quem registra o
 * contrato: quem libera é o coordenador do Comercial. Por isso a conferência é
 * sempre contra o usuário da matrícula informada — nunca contra quem está
 * logado no painel ou operando o tablet, que via de regra é outra pessoa.
 *
 * São dois jeitos de ele se autenticar, e o segredo digitado vale para os dois:
 *
 *  - **presencialmente**, digitando o próprio PIN de 6 dígitos;
 *  - **à distância**, ditando o código de 6 dígitos que o sistema mandou para o
 *    e-mail dele — para quando não dá para ir até o balcão.
 */
trait AuthorizesCommercialCoordinator
{
    /** Único setor cujo coordenador libera o excedente. */
    public const COORDINATOR_SECTOR = 'Comercial';

    /**
     * Encontra o coordenador do Comercial pela matrícula, sem conferir segredo
     * nenhum. É o passo comum a liberar com PIN e a pedir o código por e-mail.
     *
     * @throws CoordinatorAuthorizationException
     */
    protected function findCommercialCoordinator(?string $matricula): User
    {
        if (!filled($matricula)) {
            throw new CoordinatorAuthorizationException(
                'Informe a matrícula do coordenador do setor ' . self::COORDINATOR_SECTOR . '.'
            );
        }

        $coordinator = $this->findUserByMatricula($matricula);

        if (!$coordinator || (int) $coordinator->status_id !== 1) {
            throw new CoordinatorAuthorizationException('Matrícula não encontrada ou usuário inativo.');
        }

        if (!$coordinator->isCoordinatorOfSectorNamed(self::COORDINATOR_SECTOR)) {
            throw new CoordinatorAuthorizationException(
                'Apenas o coordenador do setor ' . self::COORDINATOR_SECTOR . ' pode liberar este registro.'
            );
        }

        return $coordinator;
    }

    /**
     * Confere a liberação. `$secret` é o PIN do coordenador OU o código que ele
     * recebeu por e-mail; o contrato ($freelancerId + $startDate) é o que prende
     * o código a este registro e não a outro.
     *
     * @throws CoordinatorAuthorizationException
     */
    protected function authorizeCommercialCoordinator(
        ?string $matricula,
        ?string $secret,
        ?int $freelancerId = null,
        $startDate = null,
    ): User {
        $coordinator = $this->findCommercialCoordinator($matricula);

        $codes = $this->weeklyLimitCodes();
        $boundToContract = $freelancerId !== null && $startDate !== null;

        // A matrícula já foi aceita daqui para baixo: o problema passa a ser do
        // segredo, e a tela deve deixar tentar de novo sem redigitá-la.
        if (!$coordinator->hasPin()
            && !($boundToContract && $codes->hasPending($coordinator, $freelancerId, $startDate))) {
            throw new CoordinatorAuthorizationException(
                'Este coordenador não tem PIN definido nem código pendente. Cadastre um PIN no painel '
                . '(Usuários) ou envie um código por e-mail.',
                'pin'
            );
        }

        if ($coordinator->checkPin($secret)) {
            return $coordinator;
        }

        if ($boundToContract && $codes->consume($coordinator, $freelancerId, $startDate, $secret)) {
            return $coordinator;
        }

        throw new CoordinatorAuthorizationException('PIN ou código inválido.', 'pin');
    }

    /**
     * Ponto único de busca do coordenador. Existe separado para que a regra
     * acima possa ser exercitada nos testes sem banco — o model User está preso
     * à conexão `mysql`, e a suíte roda em SQLite.
     */
    protected function findUserByMatricula(string $matricula): ?User
    {
        return User::where('matricula', $matricula)->first();
    }

    /** Separado pelo mesmo motivo: os testes trocam por um dublê. */
    protected function weeklyLimitCodes(): WeeklyLimitCodeService
    {
        return app(WeeklyLimitCodeService::class);
    }
}
