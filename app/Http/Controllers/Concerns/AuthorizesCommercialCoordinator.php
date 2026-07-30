<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\CoordinatorAuthorizationException;
use App\Models\User;

/**
 * Autorização pelo PIN do coordenador do setor Comercial.
 *
 * Passar do limite de serviços em 7 dias não é decisão de quem registra o
 * contrato: quem libera é o coordenador do Comercial, que vem até a tela e
 * digita a PRÓPRIA matrícula e o PRÓPRIO PIN. Por isso a conferência é sempre
 * contra o usuário da matrícula informada — nunca contra quem está logado no
 * painel ou operando o tablet, que via de regra é outra pessoa.
 */
trait AuthorizesCommercialCoordinator
{
    /** Único setor cujo coordenador libera o excedente. */
    public const COORDINATOR_SECTOR = 'Comercial';

    /**
     * @throws CoordinatorAuthorizationException
     */
    protected function authorizeCommercialCoordinator(?string $matricula, ?string $pin): User
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

        if (!$coordinator->hasPin()) {
            throw new CoordinatorAuthorizationException(
                'Este coordenador ainda não tem PIN definido. Cadastre um PIN de 6 dígitos no painel (Usuários).'
            );
        }

        // Só aqui o problema passa a ser do PIN: a matrícula já foi aceita, e a
        // tela deve deixar o coordenador tentar de novo sem redigitá-la.
        if (!$coordinator->checkPin($pin)) {
            throw new CoordinatorAuthorizationException('PIN inválido.', 'pin');
        }

        return $coordinator;
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
}
