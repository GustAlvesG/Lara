<?php

namespace Tests\Unit\Cotacao;

use App\Authorization\Permissions;
use App\Authorization\UserAccess;
use App\Models\CotacaoMapa;
use App\Models\User;
use App\Policies\CotacaoMapaPolicy;
use Tests\TestCase;

/**
 * Quem entra no mapa de cotação.
 *
 * A porta é a permissão `compras` (setor Contabilidade na matriz inicial, ou
 * quem tiver acesso total). Tê-la basta para a aba e para o trabalho todo.
 *
 * O que a permissão NÃO dá, e este teste protege:
 *   - reabrir mapa fechado, que é do COORDENADOR da Contabilidade (cargo);
 *   - editar mapa fechado ou cancelado — nem com acesso total.
 *
 * Sem banco: o model User está preso à conexão `mysql` e a suíte roda em
 * SQLite. Os dublês respondem pelo acesso efetivo e pelo cargo sem consultar
 * nada.
 */
class CotacaoMapaPolicyTest extends TestCase
{
    private CotacaoMapaPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CotacaoMapaPolicy;
    }

    public function test_quem_tem_a_permissao_faz_tudo_no_mapa_aberto(): void
    {
        $membro = $this->usuario(new UserAccess([Permissions::COMPRAS]));
        $mapa = $this->mapa();

        $this->assertTrue($this->policy->viewAny($membro));
        $this->assertTrue($this->policy->view($membro, $mapa));
        $this->assertTrue($this->policy->create($membro));
        $this->assertTrue($this->policy->update($membro, $mapa));
        $this->assertTrue($this->policy->editarPrecos($membro, $mapa));
        $this->assertTrue($this->policy->definirVencedor($membro, $mapa));
        $this->assertTrue($this->policy->exportar($membro, $mapa));
        $this->assertTrue($this->policy->fechar($membro, $mapa));
        $this->assertTrue($this->policy->delete($membro, $mapa));
    }

    public function test_sem_a_permissao_nada_e_liberado(): void
    {
        $forasteiro = $this->usuario(new UserAccess([Permissions::FREELANCERS_FINANCEIRO]), coordenador: true);
        $mapa = $this->mapa();

        $this->assertFalse($this->policy->viewAny($forasteiro));
        $this->assertFalse($this->policy->view($forasteiro, $mapa));
        $this->assertFalse($this->policy->create($forasteiro));
        $this->assertFalse($this->policy->update($forasteiro, $mapa));
        $this->assertFalse($this->policy->editarPrecos($forasteiro, $mapa));
        $this->assertFalse($this->policy->definirVencedor($forasteiro, $mapa));
        $this->assertFalse($this->policy->exportar($forasteiro, $mapa));
        $this->assertFalse($this->policy->fechar($forasteiro, $mapa));
        $this->assertFalse($this->policy->delete($forasteiro, $mapa));
        // Nem o cargo abre a porta: coordenar a Contabilidade sem a permissão
        // não reabre nada.
        $this->assertFalse($this->policy->reabrir($forasteiro, $this->mapa(CotacaoMapa::STATUS_FECHADO)));
    }

    public function test_acesso_total_entra_mas_nao_reabre_sem_ser_coordenador(): void
    {
        $ti = $this->usuario(new UserAccess([], ['TI']));

        $this->assertTrue($this->policy->viewAny($ti));
        $this->assertTrue($this->policy->editarPrecos($ti, $this->mapa()));
        $this->assertFalse($this->policy->reabrir($ti, $this->mapa(CotacaoMapa::STATUS_FECHADO)));
    }

    /** O estado do mapa vale para todo mundo, acesso total incluído. */
    public function test_mapa_fechado_e_somente_leitura_ate_para_acesso_total(): void
    {
        foreach ([$this->usuario(new UserAccess([], ['TI']), coordenador: true), $this->usuario(new UserAccess([Permissions::COMPRAS]), coordenador: true)] as $user) {
            $fechado = $this->mapa(CotacaoMapa::STATUS_FECHADO);

            $this->assertTrue($this->policy->view($user, $fechado));
            $this->assertTrue($this->policy->exportar($user, $fechado));
            $this->assertTrue($this->policy->reabrir($user, $fechado));

            $this->assertFalse($this->policy->update($user, $fechado));
            $this->assertFalse($this->policy->editarPrecos($user, $fechado));
            $this->assertFalse($this->policy->definirVencedor($user, $fechado));
            $this->assertFalse($this->policy->fechar($user, $fechado));
            $this->assertFalse($this->policy->delete($user, $fechado));
        }
    }

    public function test_reabrir_e_do_coordenador_da_contabilidade(): void
    {
        $fechado = $this->mapa(CotacaoMapa::STATUS_FECHADO);

        $this->assertTrue($this->policy->reabrir($this->usuario(new UserAccess([Permissions::COMPRAS]), coordenador: true), $fechado));
        $this->assertFalse($this->policy->reabrir($this->usuario(new UserAccess([Permissions::COMPRAS])), $fechado));
    }

    public function test_reabrir_so_existe_para_mapa_fechado(): void
    {
        $coordenador = $this->usuario(new UserAccess([Permissions::COMPRAS]), coordenador: true);

        $this->assertFalse($this->policy->reabrir($coordenador, $this->mapa()));
        $this->assertFalse($this->policy->reabrir($coordenador, $this->mapa(CotacaoMapa::STATUS_CANCELADO)));
    }

    public function test_mapa_cancelado_nao_aceita_edicao(): void
    {
        $membro = $this->usuario(new UserAccess([Permissions::COMPRAS]));
        $cancelado = $this->mapa(CotacaoMapa::STATUS_CANCELADO);

        $this->assertTrue($this->policy->view($membro, $cancelado));
        $this->assertFalse($this->policy->update($membro, $cancelado));
        $this->assertFalse($this->policy->editarPrecos($membro, $cancelado));
    }

    // ------------------------------------------------------------------

    private function mapa(string $status = CotacaoMapa::STATUS_EM_COTACAO): CotacaoMapa
    {
        return (new CotacaoMapa)->forceFill(['id' => 1, 'status' => $status]);
    }

    private function usuario(UserAccess $access, bool $coordenador = false): User
    {
        $user = new class extends User
        {
            public UserAccess $dubleAccess;

            public bool $coordenador = false;

            public function access(): UserAccess
            {
                return $this->dubleAccess;
            }

            public function isCoordinatorOfSectorNamed(string $name): bool
            {
                return $this->coordenador
                    && mb_strtolower($name) === mb_strtolower(self::ACCOUNTING_SECTOR);
            }
        };

        $user->dubleAccess = $access;
        $user->coordenador = $coordenador;

        return $user;
    }
}
