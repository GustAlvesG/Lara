<?php

namespace Tests\Unit\Cotacao;

use App\Models\CotacaoMapa;
use App\Models\User;
use App\Policies\CotacaoMapaPolicy;
use Tests\TestCase;

/**
 * Quem entra no mapa de cotação.
 *
 * A REGRA QUE ESTE TESTE PROTEGE: o módulo é do setor **Contabilidade**. O
 * vínculo com o setor é a porta, e vale para toda ação — inclusive só olhar.
 * Ter a permissão `cotacao.*` sem estar no setor não abre nada, e **a role
 * `admin` não abre nada**: quem administra o sistema não cota compra por
 * consequência disso.
 *
 * Dentro do setor, as permissões continuam separando o que cada um faz.
 *
 * Sem banco: o model User está preso à conexão `mysql` e a suíte roda em
 * SQLite. O dublê responde `can()` a partir de uma lista fixa de habilidades,
 * que é o que a policy consulta.
 */
class CotacaoMapaPolicyTest extends TestCase
{
    private CotacaoMapaPolicy $policy;

    /** Tudo o que alguém da Contabilidade com acesso total teria. */
    private const TUDO = [
        'acessar-cotacao',
        'cotacao.visualizar',
        'cotacao.criar',
        'cotacao.editar_precos',
        'cotacao.definir_vencedor',
        'cotacao.exportar',
        'cotacao.reabrir',
    ];

    /** As permissões sem o vínculo de setor — o caso do admin de fora. */
    private const SO_PERMISSOES = [
        'cotacao.visualizar',
        'cotacao.criar',
        'cotacao.editar_precos',
        'cotacao.definir_vencedor',
        'cotacao.exportar',
        'cotacao.reabrir',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CotacaoMapaPolicy;
    }

    /**
     * O caso central: todas as permissões, nenhum vínculo com a Contabilidade.
     * É exatamente o que um `admin` tem, já que a role recebe todas as
     * permissões do sistema.
     */
    public function test_sem_o_setor_contabilidade_nada_e_liberado(): void
    {
        $forasteiro = $this->usuario(self::SO_PERMISSOES);
        $mapa = $this->mapa();

        $this->assertFalse($this->policy->viewAny($forasteiro));
        $this->assertFalse($this->policy->view($forasteiro, $mapa));
        $this->assertFalse($this->policy->create($forasteiro));
        $this->assertFalse($this->policy->update($forasteiro, $mapa));
        $this->assertFalse($this->policy->editarPrecos($forasteiro, $mapa));
        $this->assertFalse($this->policy->definirVencedor($forasteiro, $mapa));
        $this->assertFalse($this->policy->exportar($forasteiro, $mapa));
        $this->assertFalse($this->policy->fechar($forasteiro, $mapa));
        $this->assertFalse($this->policy->reabrir($forasteiro, $this->mapa(CotacaoMapa::STATUS_FECHADO)));
        $this->assertFalse($this->policy->delete($forasteiro, $mapa));
    }

    public function test_com_o_setor_e_as_permissoes_tudo_e_liberado_num_mapa_aberto(): void
    {
        $contabilidade = $this->usuario(self::TUDO);
        $mapa = $this->mapa();

        $this->assertTrue($this->policy->viewAny($contabilidade));
        $this->assertTrue($this->policy->view($contabilidade, $mapa));
        $this->assertTrue($this->policy->create($contabilidade));
        $this->assertTrue($this->policy->update($contabilidade, $mapa));
        $this->assertTrue($this->policy->editarPrecos($contabilidade, $mapa));
        $this->assertTrue($this->policy->definirVencedor($contabilidade, $mapa));
        $this->assertTrue($this->policy->exportar($contabilidade, $mapa));
        $this->assertTrue($this->policy->fechar($contabilidade, $mapa));
        $this->assertTrue($this->policy->delete($contabilidade, $mapa));
    }

    /**
     * O setor é necessário, não suficiente: dentro da Contabilidade as
     * permissões continuam separando quem faz o quê.
     */
    public function test_dentro_do_setor_a_permissao_ainda_separa_as_acoes(): void
    {
        // Só olha: vê o mapa, não digita preço nem decide nada.
        $observador = $this->usuario(['acessar-cotacao', 'cotacao.visualizar']);
        $mapa = $this->mapa();

        $this->assertTrue($this->policy->view($observador, $mapa));
        $this->assertFalse($this->policy->create($observador));
        $this->assertFalse($this->policy->editarPrecos($observador, $mapa));
        $this->assertFalse($this->policy->definirVencedor($observador, $mapa));
        $this->assertFalse($this->policy->exportar($observador, $mapa));

        // Cota, mas não decide: é o caso de quem liga para os fornecedores.
        $cotador = $this->usuario(['acessar-cotacao', 'cotacao.visualizar', 'cotacao.editar_precos']);

        $this->assertTrue($this->policy->editarPrecos($cotador, $mapa));
        $this->assertFalse($this->policy->definirVencedor($cotador, $mapa));
        $this->assertFalse($this->policy->fechar($cotador, $mapa));
    }

    /**
     * Mapa fechado é somente leitura para todo mundo, inclusive para quem tem
     * todas as permissões e está no setor.
     */
    public function test_mapa_fechado_so_permite_ver_exportar_e_reabrir(): void
    {
        $contabilidade = $this->usuario(self::TUDO);
        $fechado = $this->mapa(CotacaoMapa::STATUS_FECHADO);

        $this->assertTrue($this->policy->view($contabilidade, $fechado));
        $this->assertTrue($this->policy->exportar($contabilidade, $fechado));
        $this->assertTrue($this->policy->reabrir($contabilidade, $fechado));

        $this->assertFalse($this->policy->update($contabilidade, $fechado));
        $this->assertFalse($this->policy->editarPrecos($contabilidade, $fechado));
        $this->assertFalse($this->policy->definirVencedor($contabilidade, $fechado));
        $this->assertFalse($this->policy->fechar($contabilidade, $fechado));
        $this->assertFalse($this->policy->delete($contabilidade, $fechado));
    }

    /**
     * Reabrir tem permissão própria — e só existe para mapa fechado.
     */
    public function test_reabrir_exige_permissao_propria_e_mapa_fechado(): void
    {
        $semPermissao = $this->usuario([
            'acessar-cotacao', 'cotacao.visualizar', 'cotacao.criar',
            'cotacao.editar_precos', 'cotacao.definir_vencedor', 'cotacao.exportar',
        ]);

        $this->assertFalse($this->policy->reabrir($semPermissao, $this->mapa(CotacaoMapa::STATUS_FECHADO)));

        // Com a permissão, mas num mapa que não está fechado: não há o que reabrir.
        $comPermissao = $this->usuario(self::TUDO);

        $this->assertFalse($this->policy->reabrir($comPermissao, $this->mapa()));
        $this->assertFalse($this->policy->reabrir($comPermissao, $this->mapa(CotacaoMapa::STATUS_CANCELADO)));
    }

    public function test_mapa_cancelado_nao_aceita_edicao(): void
    {
        $contabilidade = $this->usuario(self::TUDO);
        $cancelado = $this->mapa(CotacaoMapa::STATUS_CANCELADO);

        $this->assertTrue($this->policy->view($contabilidade, $cancelado));
        $this->assertFalse($this->policy->update($contabilidade, $cancelado));
        $this->assertFalse($this->policy->editarPrecos($contabilidade, $cancelado));
    }

    /**
     * O Gate `acessar-cotacao` pergunta ao usuário, e o usuário responde pelo
     * vínculo com o setor Contabilidade — em qualquer papel, colaborador ou
     * coordenador.
     */
    public function test_o_acesso_vem_do_vinculo_com_o_setor_contabilidade(): void
    {
        $this->assertTrue($this->usuarioDeSetor('Contabilidade')->canAccessCotacao());
        $this->assertTrue($this->usuarioDeSetor('contabilidade')->canAccessCotacao());
        $this->assertFalse($this->usuarioDeSetor('Gerência')->canAccessCotacao());
        $this->assertFalse($this->usuarioDeSetor(null)->canAccessCotacao());
    }

    /**
     * A resposta é memorizada por instância: o menu, a policy e cada ação da
     * grade perguntam a mesma coisa na mesma requisição.
     */
    public function test_o_vinculo_e_consultado_uma_vez_por_requisicao(): void
    {
        $user = $this->usuarioDeSetor('Contabilidade');

        $user->canAccessCotacao();
        $user->canAccessCotacao();
        $user->canAccessCotacao();

        $this->assertSame(1, $user->consultas);
    }

    // ------------------------------------------------------------------

    private function mapa(string $status = CotacaoMapa::STATUS_EM_COTACAO): CotacaoMapa
    {
        return (new CotacaoMapa)->forceFill(['id' => 1, 'status' => $status]);
    }

    /**
     * Dublê que responde `can()` a partir de uma lista fixa — é o que a policy
     * consulta, tanto para o Gate de setor quanto para as permissões.
     *
     * @param  array<int, string>  $habilidades
     */
    private function usuario(array $habilidades): User
    {
        // Sem construtor com argumento obrigatório: o Eloquent instancia o
        // model sem parâmetros ao inicializá-lo, e o dublê quebraria ali.
        $user = new class extends User
        {
            /** @var array<int, string> */
            public array $habilidades = [];

            public function can($abilities, $arguments = []): bool
            {
                return in_array($abilities, $this->habilidades, true);
            }
        };

        $user->habilidades = $habilidades;

        return $user;
    }

    /**
     * Dublê que responde pelo vínculo de setor sem ir ao banco, contando
     * quantas vezes foi consultado.
     */
    private function usuarioDeSetor(?string $setor): User
    {
        $user = new class extends User
        {
            public ?string $setor = null;

            public int $consultas = 0;

            public function belongsToSectorNamed(string $name): bool
            {
                $this->consultas++;

                return $this->setor !== null
                    && mb_strtolower($this->setor) === mb_strtolower($name);
            }
        };

        $user->setor = $setor;

        return $user;
    }
}
