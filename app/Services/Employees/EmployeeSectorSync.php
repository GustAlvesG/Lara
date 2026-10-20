<?php

namespace App\Services\Employees;

use App\Authorization\AccessManager;
use App\Models\Employee;
use App\Models\User;

/**
 * Regra do RH: o setor do funcionário coloca o usuário de mesma matrícula
 * nesse setor. O contrário não acontece — o setor do usuário nunca muda o do
 * funcionário.
 *
 * Chamado de propósito por quem grava (tela de Funcionários, planilha, tela
 * de Usuários quando a matrícula muda), e não por evento de model: o User
 * mora na conexão `mysql`, e um gancho no `saved` de Employee faria qualquer
 * teste que cria funcionário abrir essa conexão.
 *
 * Funcionário excluído ou desligado não leva ninguém a setor nenhum: dar
 * acesso ao painel a quem saiu da casa seria o contrário do que o RH quer.
 * Férias e afastamento não impedem — a pessoa continua no setor.
 */
class EmployeeSectorSync
{
    public function __construct(private readonly AccessManager $access)
    {
    }

    /**
     * @return string|null frase para a tela quando o usuário entrou no setor agora
     */
    public function syncEmployee(Employee $employee): ?string
    {
        if ($employee->trashed() || !$employee->isActiveOn() || !$employee->sector_id) {
            return null;
        }

        $user = $employee->user()->first();
        $sector = $employee->sector()->first();

        if (!$user || !$sector) {
            return null;
        }

        return $this->access->addMemberFromEmployee($sector, $user, (string) $employee->employee_code)
            ? "O usuário {$user->name} entrou no setor {$sector->name}."
            : null;
    }

    /** O usuário ganhou (ou trocou) matrícula: vale o setor do funcionário dela. */
    public function syncUser(User $user): ?string
    {
        if (blank($user->matricula)) {
            return null;
        }

        $employee = Employee::where('employee_code', $user->matricula)->first();

        return $employee ? $this->syncEmployee($employee) : null;
    }
}
