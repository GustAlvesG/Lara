<?php

namespace Tests\Unit\Placar;

use App\Authorization\Permissions;
use App\Authorization\UserAccess;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Tests\TestCase;

/**
 * Acesso ao Placar Clube: as permissões `placar.cadastro` e `placar.scout`,
 * que o setor Esporte recebe na matriz inicial. Unit, sem banco: User está
 * preso à conexão mysql (ver docs/models.md), então o acesso efetivo entra
 * pelo mock de access(), e não por uma consulta às tabelas de setor.
 */
class PlacarGatesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function usuario(UserAccess $access): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('access')->andReturn($access);

        return $user;
    }

    public function test_quem_tem_as_duas_permissoes_tem_cadastro_e_scout(): void
    {
        $user = $this->usuario(new UserAccess([Permissions::PLACAR_CADASTRO, Permissions::PLACAR_SCOUT]));

        $this->assertTrue(Gate::forUser($user)->allows(Permissions::PLACAR_CADASTRO));
        $this->assertTrue(Gate::forUser($user)->allows(Permissions::PLACAR_SCOUT));
    }

    public function test_sem_permissao_nao_tem_nenhuma_das_duas(): void
    {
        $user = $this->usuario(UserAccess::none());

        $this->assertFalse(Gate::forUser($user)->allows(Permissions::PLACAR_CADASTRO));
        $this->assertFalse(Gate::forUser($user)->allows(Permissions::PLACAR_SCOUT));
    }

    public function test_cadastro_e_scout_podem_ir_para_setores_diferentes(): void
    {
        $user = $this->usuario(new UserAccess([Permissions::PLACAR_SCOUT]));

        $this->assertFalse(Gate::forUser($user)->allows(Permissions::PLACAR_CADASTRO));
        $this->assertTrue(Gate::forUser($user)->allows(Permissions::PLACAR_SCOUT));
    }

    public function test_acesso_total_alcanca_o_placar(): void
    {
        $user = $this->usuario(new UserAccess([], ['TI']));

        $this->assertTrue(Gate::forUser($user)->allows(Permissions::PLACAR_CADASTRO));
        $this->assertTrue(Gate::forUser($user)->allows(Permissions::PLACAR_SCOUT));
    }
}
