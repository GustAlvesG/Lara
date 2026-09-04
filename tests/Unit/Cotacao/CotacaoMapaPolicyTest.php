<?php

namespace Tests\Unit\Cotacao;

use App\Models\CotacaoMapa;
use App\Models\User;
use App\Policies\CotacaoMapaPolicy;
use Tests\TestCase;

/**
 * Quem entra no mapa de cotação.
 *
 * A REGRA QUE ESTE TESTE PROTEGE: o módulo é do setor **Contabilidade**, e
 * estar no setor — **em qualquer papel, colaborador ou coordenador** — basta
 * para a aba e para o trabalho todo. Não há permissão do Spatie no caminho, e é
 * de propósito: uma segunda porta que ninguém lembra de abrir faria o
 * funcionário entrar no setor e continuar levando 403.
 *
 * As duas exceções, ambas testadas aqui:
 *   - a role `admin` NÃO abre nada;
 *   - reabrir mapa fechado é do COORDENADOR do setor.
 *
 * Sem banco: o model User está preso à conexão `mysql` e a suíte roda em
 * SQLite. Os dublês respondem pelo vínculo de setor sem consultar nada.
 */
class CotacaoMapaPolicyTest extends TestCase
{
    private CotacaoMapaPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CotacaoMapaPolicy;
    }

    /**
     * O caso que motivou a regra: colaborador do setor, sem permissão nenhuma,
     * trabalha no mapa inteiro.
     */
    public function test_colaborador_do_setor_faz_tudo_sem_precisar_de_permissao(): void
    {
        $colaborador = $this->doSetor(coordenador: false);
        $mapa = $this->mapa();

        $this->assertFalse($colaborador->hasPermissionTo('cotacao.visualizar', 'web'));

        $this->assertTrue($this->policy->viewAny($colaborador));
        $this->assertTrue($this->policy->view($colaborador, $mapa));
        $this->assertTrue($this->policy->create($colaborador));
        $this->assertTrue($this->policy->update($colaborador, $mapa));
        $this->assertTrue($this->policy->editarPrecos($colaborador, $mapa));
        $this->assertTrue($this->policy->definirVencedor($colaborador, $mapa));
        $this->assertTrue($this->policy->exportar($colaborador, $mapa));
        $this->assertTrue($this->policy->fechar($colaborador, $mapa));
        $this->assertTrue($this->policy->delete($colaborador, $mapa));
    }

    public function test_coordenador_do_setor_faz_tudo_tambem(): void
    {
        $coordenador = $this->doSetor(coordenador: true);
        $mapa = $this->mapa();

        $this->assertTrue($this->policy->viewAny($coordenador));
        $this->assertTrue($this->policy->create($coordenador));
        $this->assertTrue($this->policy->editarPrecos($coordenador, $mapa));
        $this->assertTrue($this->policy->definirVencedor($coordenador, $mapa));
        $this->assertTrue($this->policy->exportar($coordenador, $mapa));
    }

    /**
     * Quem está noutro setor — ou em setor nenhum — não entra, nem para olhar.
     */
    public function test_fora_do_setor_nada_e_liberado(): void
    {
        foreach ([$this->deOutroSetor('Gerência'), $this->deOutroSetor(null)] as $forasteiro) {
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
            $this->assertFalse($this->policy->reabrir($forasteiro, $this->mapa(CotacaoMapa::STATUS_FECHADO)));
        }
    }

    /**
     * Quem administra o sistema não cota compra por consequência disso — mesmo
     * tendo TODAS as permissões, que é o que a role `admin` recebe no seeder.
     */
    public function test_a_role_admin_sozinha_nao_abre_o_modulo(): void
    {
        $admin = $this->comTodasAsPermissoes();
        $mapa = $this->mapa();

        $this->assertFalse($this->policy->viewAny($admin));
        $this->assertFalse($this->policy->view($admin, $mapa));
        $this->assertFalse($this->policy->create($admin));
        $this->assertFalse($this->policy->editarPrecos($admin, $mapa));
        $this->assertFalse($this->policy->exportar($admin, $mapa));
        $this->assertFalse($this->policy->reabrir($admin, $this->mapa(CotacaoMapa::STATUS_FECHADO)));
    }

    /**
     * Mapa fechado é somente leitura para todo mundo, inclusive para o
     * coordenador do setor.
     */
    public function test_mapa_fechado_so_permite_ver_exportar_e_reabrir(): void
    {
        $coordenador = $this->doSetor(coordenador: true);
        $fechado = $this->mapa(CotacaoMapa::STATUS_FECHADO);

        $this->assertTrue($this->policy->view($coordenador, $fechado));
        $this->assertTrue($this->policy->exportar($coordenador, $fechado));
        $this->assertTrue($this->policy->reabrir($coordenador, $fechado));

        $this->assertFalse($this->policy->update($coordenador, $fechado));
        $this->assertFalse($this->policy->editarPrecos($coordenador, $fechado));
        $this->assertFalse($this->policy->definirVencedor($coordenador, $fechado));
        $this->assertFalse($this->policy->fechar($coordenador, $fechado));
        $this->assertFalse($this->policy->delete($coordenador, $fechado));
    }

    /**
     * A única ação que o setor não dá por si: reabrir devolve à edição um
     * documento que já fundamentou uma compra.
     */
    public function test_reabrir_e_do_coordenador_do_setor(): void
    {
        $fechado = $this->mapa(CotacaoMapa::STATUS_FECHADO);

        $this->assertTrue($this->policy->reabrir($this->doSetor(coordenador: true), $fechado));
        $this->assertFalse($this->policy->reabrir($this->doSetor(coordenador: false), $fechado));
    }

    public function test_reabrir_so_existe_para_mapa_fechado(): void
    {
        $coordenador = $this->doSetor(coordenador: true);

        $this->assertFalse($this->policy->reabrir($coordenador, $this->mapa()));
        $this->assertFalse($this->policy->reabrir($coordenador, $this->mapa(CotacaoMapa::STATUS_CANCELADO)));
    }

    public function test_mapa_cancelado_nao_aceita_edicao(): void
    {
        $membro = $this->doSetor(coordenador: false);
        $cancelado = $this->mapa(CotacaoMapa::STATUS_CANCELADO);

        $this->assertTrue($this->policy->view($membro, $cancelado));
        $this->assertFalse($this->policy->update($membro, $cancelado));
        $this->assertFalse($this->policy->editarPrecos($membro, $cancelado));
    }

    /**
     * O vínculo vale em qualquer papel, e o nome do setor casa sem depender de
     * caixa — é `belongsToSectorNamed`, não `isCoordinatorOfSectorNamed`.
     */
    public function test_o_acesso_vem_do_vinculo_com_o_setor_em_qualquer_papel(): void
    {
        $this->assertTrue($this->comSetor('Contabilidade')->canAccessCotacao());
        $this->assertTrue($this->comSetor('contabilidade')->canAccessCotacao());
        $this->assertTrue($this->comSetor('CONTABILIDADE')->canAccessCotacao());
        $this->assertFalse($this->comSetor('Gerência')->canAccessCotacao());
        $this->assertFalse($this->comSetor(null)->canAccessCotacao());
    }

    /**
     * Memorizado por instância: o menu, a policy e cada ação da grade perguntam
     * a mesma coisa na mesma requisição.
     */
    public function test_o_vinculo_e_consultado_uma_vez_por_requisicao(): void
    {
        $user = $this->comSetor('Contabilidade');

        $user->canAccessCotacao();
        $user->canAccessCotacao();
        $user->canAccessCotacao();

        $this->assertSame(1, $user->consultas);
    }

    /**
     * A convenção que sustenta o Gate: **nenhuma permissão do Spatie pode se
     * chamar `acessar-cotacao`.**
     *
     * O Spatie registra um `Gate::before` que consulta as permissões do usuário
     * para qualquer habilidade. Uma permissão com esse nome exato passaria por
     * cima da checagem de setor — e a role `admin`, que recebe
     * `Permission::all()`, entraria no módulo sem estar na Contabilidade.
     *
     * O projeto se protege por nomenclatura: Gates com hífen
     * (`acessar-cotacao`, `manage-freelancer-payments`), permissões com espaço
     * (`manage users`) ou ponto. Este teste é o alarme se alguém quebrar isso.
     */
    public function test_nenhuma_permissao_pode_ter_o_nome_do_gate_de_setor(): void
    {
        $gates = ['acessar-cotacao', 'manage-freelancer-payments', 'track-freelancer-batches'];

        foreach ($gates as $gate) {
            $this->assertDoesNotMatchRegularExpression(
                '/[ .]/',
                $gate,
                "O Gate `{$gate}` não pode usar espaço nem ponto: é como as permissões do "
                . 'Spatie são nomeadas, e a colisão faria a permissão passar por cima do Gate.'
            );
        }

        // E, do outro lado: as permissões deste app nunca são só-hífen.
        foreach (['manage users', 'authorize purchase orders', 'manage fleet'] as $permissao) {
            $this->assertMatchesRegularExpression('/[ .]/', $permissao);
        }
    }

    // ------------------------------------------------------------------

    private function mapa(string $status = CotacaoMapa::STATUS_EM_COTACAO): CotacaoMapa
    {
        return (new CotacaoMapa)->forceFill(['id' => 1, 'status' => $status]);
    }

    /**
     * Membro da Contabilidade, sem permissão nenhuma do Spatie.
     *
     * `can()` cai no Gate real (que chama `canAccessCotacao()`) para as
     * habilidades sem model, e devolve falso para qualquer permissão — é assim
     * que o teste prova que o setor basta.
     */
    private function doSetor(bool $coordenador): User
    {
        $user = new class extends User
        {
            public bool $coordenador = false;

            public function belongsToSectorNamed(string $name): bool
            {
                return mb_strtolower($name) === mb_strtolower(self::ACCOUNTING_SECTOR);
            }

            public function isCoordinatorOfSectorNamed(string $name): bool
            {
                return $this->coordenador
                    && mb_strtolower($name) === mb_strtolower(self::ACCOUNTING_SECTOR);
            }

            public function hasPermissionTo($permission, $guardName = null): bool
            {
                return false;
            }
        };

        $user->coordenador = $coordenador;

        return $user;
    }

    private function deOutroSetor(?string $setor): User
    {
        $user = new class extends User
        {
            public ?string $setor = null;

            public function belongsToSectorNamed(string $name): bool
            {
                return $this->setor !== null && mb_strtolower($this->setor) === mb_strtolower($name);
            }

            public function isCoordinatorOfSectorNamed(string $name): bool
            {
                return false;
            }

            public function hasPermissionTo($permission, $guardName = null): bool
            {
                return false;
            }
        };

        $user->setor = $setor;

        return $user;
    }

    /**
     * O admin do seeder: recebe `Permission::all()` e nenhum vínculo de setor.
     *
     * O dublê concede toda permissão QUE EXISTE neste app — as com espaço
     * (`manage users`) ou com ponto (`cotacao.visualizar`) — e nega o resto.
     *
     * A distinção não é preciosismo de teste. O Spatie registra um
     * `Gate::before` (config `register_permission_check_method`) que consulta
     * `hasPermissionTo()` para QUALQUER habilidade, inclusive as de Gate: se
     * existisse uma permissão chamada `acessar-cotacao`, quem a tivesse
     * entraria no módulo sem estar no setor. É por isso que os Gates deste
     * projeto usam hífen e as permissões usam espaço ou ponto — a convenção
     * está registrada no `AppServiceProvider` e é o que impede a colisão.
     */
    private function comTodasAsPermissoes(): User
    {
        return new class extends User
        {
            public function belongsToSectorNamed(string $name): bool
            {
                return false;
            }

            public function isCoordinatorOfSectorNamed(string $name): bool
            {
                return false;
            }

            public function hasPermissionTo($permission, $guardName = null): bool
            {
                $nome = is_string($permission) ? $permission : (string) ($permission->name ?? '');

                return str_contains($nome, ' ') || str_contains($nome, '.');
            }
        };
    }

    /**
     * Dublê que conta quantas vezes o vínculo foi consultado.
     */
    private function comSetor(?string $setor): User
    {
        $user = new class extends User
        {
            public ?string $setor = null;

            public int $consultas = 0;

            public function belongsToSectorNamed(string $name): bool
            {
                $this->consultas++;

                return $this->setor !== null && mb_strtolower($this->setor) === mb_strtolower($name);
            }
        };

        $user->setor = $setor;

        return $user;
    }
}
