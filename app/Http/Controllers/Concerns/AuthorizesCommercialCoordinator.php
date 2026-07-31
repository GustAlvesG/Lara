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
 *  - **presencialmente**, informando a própria matrícula e o próprio PIN;
 *  - **à distância**, ditando o código de 6 dígitos que o sistema mandou para
 *    TODOS os coordenadores do Comercial. Aqui não há matrícula: o código é do
 *    contrato, não de uma pessoa, e quem registra não precisa saber quem está
 *    de plantão. Em compensação não há a quem atribuir a liberação — o método
 *    devolve `null`, e o contrato fica com `weekly_limit_authorized_by` vazio.
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
     * Confere a liberação. `$secret` é o código enviado por e-mail OU o PIN do
     * coordenador; o contrato ($freelancerId + $startDate) é o que prende o
     * código a este registro e não a outro.
     *
     * O código é conferido PRIMEIRO, e sem matrícula: como o mesmo número foi
     * para todos os coordenadores, exigir que se diga qual deles ditou seria
     * pedir uma informação que não dá para provar. Devolve `null` nesse caso —
     * liberado, mas sem dono. Só o caminho do PIN identifica uma pessoa.
     *
     * @throws CoordinatorAuthorizationException
     */
    protected function authorizeCommercialCoordinator(
        ?string $matricula,
        ?string $secret,
        ?int $freelancerId = null,
        $startDate = null,
    ): ?User {
        $codes = $this->weeklyLimitCodes();
        $boundToContract = $freelancerId !== null && $startDate !== null;

        // Sem matrícula, só o código libera — é o caminho de quem recebeu o
        // e-mail. Nada a conferir contra uma pessoa, e nada a atribuir.
        if (!filled($matricula)) {
            if ($boundToContract && $codes->consume($freelancerId, $startDate, $secret)) {
                return null;
            }

            throw new CoordinatorAuthorizationException(
                'Código inválido ou expirado. Envie um código novo, ou libere com a matrícula e o '
                . 'PIN de um coordenador do setor ' . self::COORDINATOR_SECTOR . '.',
                'pin'
            );
        }

        $coordinator = $this->findCommercialCoordinator($matricula);

        // A matrícula já foi aceita daqui para baixo: o problema passa a ser do
        // segredo, e a tela deve deixar tentar de novo sem redigitá-la.
        if ($coordinator->hasPin() && $coordinator->checkPin($secret)) {
            return $coordinator;
        }

        // Ainda pode ser o código: informar a matrícula não impede que quem
        // ditou tenha sido outro coordenador. Só se tenta depois do PIN, para
        // que o uso normal do PIN não gaste as tentativas do código.
        if ($boundToContract && $codes->consume($freelancerId, $startDate, $secret)) {
            return null;
        }

        if (!$coordinator->hasPin()) {
            throw new CoordinatorAuthorizationException(
                'Este coordenador não tem PIN definido. Cadastre um PIN no painel (Usuários) '
                . 'ou libere pelo código enviado por e-mail.',
                'pin'
            );
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
