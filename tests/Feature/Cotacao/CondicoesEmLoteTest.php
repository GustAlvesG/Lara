<?php

namespace Tests\Feature\Cotacao;

use App\Http\Controllers\Cotacao\FornecedorController;
use App\Http\Requests\AtualizarCondicoesCotacaoRequest;
use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaFornecedor;
use App\Models\CotacaoMapaLog;
use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesCotacaoSchema;
use Tests\TestCase;

/**
 * O "salvar geral" das condições de fornecedor.
 *
 * O botão existe porque cotação se anota de uma vez: o comprador volta do
 * telefone com frete, prazo e pagamento de vários fornecedores. Com um botão
 * por linha, bastava esquecer um clique para o mapa sair com uma condição
 * velha — e frete e desconto entram no TOTAL, ou seja, a decisão de compra
 * saía errada sem nada na tela avisando.
 *
 * DUAS COISAS SÃO O MOTIVO DESTE ARQUIVO EXISTIR:
 *
 *   1. A TRAVA DE ESCOPO. As chaves do array vêm do formulário e são ids. Uma
 *      delas apontando para a coluna do mapa de outro comprador gravaria lá
 *      (IDOR) — e o lote inteiro tem de cair, não só a chave estranha: salvar
 *      4 de 5 e responder "salvo" é pior que recusar, porque o comprador não
 *      teria como saber qual ficou de fora.
 *
 *   2. A TRILHA. Um evento por ato do comprador, não um por coluna — e nenhum
 *      quando nada mudou, senão o histórico do mapa vira ruído a cada clique.
 *
 * Os testes exercitam o FormRequest e o controller direto, sem passar pela
 * rota: o middleware de sessão precisaria de um usuário, e `App\Models\User`
 * fixa a conexão `mysql` (fora do SQLite da suíte).
 */
class CondicoesEmLoteTest extends TestCase
{
    use CreatesCotacaoSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCotacaoSchema();
    }

    /* ---------------------------------------------------------------------
     | A trava de escopo
     |---------------------------------------------------------------------*/

    public function test_todas_as_colunas_do_proprio_mapa_sao_autorizadas(): void
    {
        [$mapa, $a, $b] = $this->mapaComDuasColunas();

        $request = $this->requisicao($mapa, ['fornecedores' => [
            $a->id => ['nome' => $a->nome],
            $b->id => ['nome' => $b->nome],
        ]]);

        $this->assertTrue($request->authorize());
    }

    /**
     * O caso que a trava existe para pegar: um id legítimo, mas de outro mapa,
     * escondido no meio de um lote correto.
     */
    public function test_uma_coluna_de_outro_mapa_derruba_o_lote_inteiro(): void
    {
        [$mapa, $a] = $this->mapaComDuasColunas();
        [, $alheio] = $this->mapaComDuasColunas();

        $request = $this->requisicao($mapa, ['fornecedores' => [
            $a->id => ['nome' => 'PRÓPRIO'],
            $alheio->id => ['nome' => 'INVASOR'],
        ]]);

        $this->assertFalse($request->authorize());
    }

    public function test_id_inexistente_e_recusado(): void
    {
        [$mapa, $a] = $this->mapaComDuasColunas();

        $request = $this->requisicao($mapa, ['fornecedores' => [
            $a->id => ['nome' => $a->nome],
            999999 => ['nome' => 'FANTASMA'],
        ]]);

        $this->assertFalse($request->authorize());
    }

    public function test_chave_nao_numerica_e_recusada(): void
    {
        [$mapa] = $this->mapaComDuasColunas();

        $request = $this->requisicao($mapa, ['fornecedores' => [
            'nao-e-id' => ['nome' => 'QUALQUER'],
        ]]);

        $this->assertFalse($request->authorize());
    }

    /**
     * Lote vazio não é tentativa de invasão — a validação responde melhor que
     * um 403 seco.
     */
    public function test_lote_vazio_passa_pela_autorizacao_e_cai_na_validacao(): void
    {
        [$mapa] = $this->mapaComDuasColunas();

        $request = $this->requisicao($mapa, ['fornecedores' => []]);

        $this->assertTrue($request->authorize());

        $this->expectException(ValidationException::class);
        $request->validateResolved();
    }

    /* ---------------------------------------------------------------------
     | Os dois campos em reais
     |---------------------------------------------------------------------*/

    public function test_valores_no_formato_brasileiro_viram_numero_em_todas_as_colunas(): void
    {
        [$mapa, $a, $b] = $this->mapaComDuasColunas();

        $lote = $this->validado($mapa, [
            $a->id => ['nome' => 'A', 'valor_frete' => '1.234,56', 'desconto' => '10,50'],
            $b->id => ['nome' => 'B', 'valor_frete' => '80', 'desconto' => ''],
        ]);

        $this->assertEqualsWithDelta(1234.56, $lote[$a->id]['valor_frete'], 0.0001);
        $this->assertEqualsWithDelta(10.50, $lote[$a->id]['desconto'], 0.0001);
        $this->assertEqualsWithDelta(80.0, $lote[$b->id]['valor_frete'], 0.0001);

        // Campo apagado é "não tem frete" = zero, e não nulo: a coluna do banco
        // tem DEFAULT 0, e apagar é justamente como se zera na tela.
        $this->assertSame(0.0, $lote[$b->id]['desconto']);
    }

    public function test_frete_negativo_derruba_a_validacao(): void
    {
        [$mapa, $a] = $this->mapaComDuasColunas();

        $request = $this->requisicao($mapa, ['fornecedores' => [
            $a->id => ['nome' => 'A', 'valor_frete' => '-5'],
        ]]);

        $this->expectException(ValidationException::class);
        $request->validateResolved();
    }

    /* ---------------------------------------------------------------------
     | A gravação e a trilha
     |---------------------------------------------------------------------*/

    public function test_um_clique_salva_todas_as_colunas(): void
    {
        [$mapa, $a, $b] = $this->mapaComDuasColunas();

        $this->salvar($mapa, [
            $a->id => [
                'nome' => 'D C DE ALMEIDA',
                'frete' => 'CIF',
                'prazo_entrega' => '3DU',
                'condicao_pagamento' => '28 D',
                'valor_frete' => '120,00',
                'desconto' => '0',
            ],
            $b->id => [
                'nome' => 'BARRA COR',
                'frete' => 'FOB',
                'prazo_entrega' => 'CONFIRMAR',
                'condicao_pagamento' => 'Á VISTA',
                'valor_frete' => '0',
                'desconto' => '35,90',
            ],
        ]);

        $a->refresh();
        $b->refresh();

        $this->assertSame('D C DE ALMEIDA', $a->nome);
        $this->assertSame('CIF', $a->frete);
        $this->assertSame('3DU', $a->prazo_entrega);
        $this->assertEqualsWithDelta(120.0, (float) $a->valor_frete, 0.0001);

        $this->assertSame('BARRA COR', $b->nome);
        $this->assertSame('CONFIRMAR', $b->prazo_entrega);
        $this->assertSame('Á VISTA', $b->condicao_pagamento);
        $this->assertEqualsWithDelta(35.90, (float) $b->desconto, 0.0001);
    }

    /**
     * UM registro para o ato todo. Um por coluna faria o comprador procurar em
     * cinco linhas o que ele fez de uma vez.
     */
    public function test_a_trilha_registra_um_evento_com_todas_as_mudancas(): void
    {
        [$mapa, $a, $b] = $this->mapaComDuasColunas();

        $this->salvar($mapa, [
            $a->id => ['nome' => $a->nome, 'valor_frete' => '90,00'],
            $b->id => ['nome' => $b->nome, 'condicao_pagamento' => '14 D'],
        ]);

        $logs = CotacaoMapaLog::where('cotacao_mapa_id', $mapa->id)
            ->where('acao', CotacaoMapaLog::ACAO_FORNECEDOR)
            ->get();

        $this->assertCount(1, $logs);

        $payload = $logs->first()->payload;

        $this->assertSame('condicoes_em_lote', $payload['evento']);
        $this->assertSame(2, $payload['colunas_alteradas']);
        $this->assertCount(2, $payload['mudancas']);

        // O antes E o depois: frete e desconto mudam o TOTAL, e "quem mudou o
        // frete desta coluna?" é a pergunta que aparece depois da compra.
        $frete = collect($payload['mudancas'])->firstWhere('fornecedor_id', $a->id);

        $this->assertArrayHasKey('valor_frete', $frete['de']);
        $this->assertArrayHasKey('valor_frete', $frete['para']);
        $this->assertEqualsWithDelta(90.0, (float) $frete['para']['valor_frete'], 0.0001);
    }

    /**
     * Clicar em "salvar" sem ter mudado nada não pode virar registro: a tela
     * tem um botão só, e ele vai ser clicado por hábito.
     */
    public function test_salvar_sem_mudar_nada_nao_registra_no_historico(): void
    {
        [$mapa, $a, $b] = $this->mapaComDuasColunas();

        $mesmo = fn (CotacaoMapaFornecedor $f) => [
            'nome' => $f->nome,
            'frete' => $f->frete,
            'prazo_entrega' => $f->prazo_entrega,
            'condicao_pagamento' => $f->condicao_pagamento,
            'valor_frete' => (string) $f->valor_frete,
            'desconto' => (string) $f->desconto,
        ];

        $this->salvar($mapa, [$a->id => $mesmo($a), $b->id => $mesmo($b)]);

        $this->assertSame(0, CotacaoMapaLog::where('cotacao_mapa_id', $mapa->id)
            ->where('acao', CotacaoMapaLog::ACAO_FORNECEDOR)
            ->count());
    }

    /**
     * Só as colunas que mudaram entram na trilha, mesmo quando o lote traz
     * todas — é o que faz o histórico continuar legível.
     */
    public function test_apenas_as_colunas_alteradas_entram_na_trilha(): void
    {
        [$mapa, $a, $b] = $this->mapaComDuasColunas();

        $this->salvar($mapa, [
            $a->id => ['nome' => 'NOME NOVO'],
            $b->id => [
                'nome' => $b->nome,
                'frete' => $b->frete,
                'prazo_entrega' => $b->prazo_entrega,
                'condicao_pagamento' => $b->condicao_pagamento,
                'valor_frete' => (string) $b->valor_frete,
                'desconto' => (string) $b->desconto,
            ],
        ]);

        $payload = CotacaoMapaLog::where('cotacao_mapa_id', $mapa->id)
            ->where('acao', CotacaoMapaLog::ACAO_FORNECEDOR)
            ->firstOrFail()
            ->payload;

        $this->assertSame(1, $payload['colunas_alteradas']);
        $this->assertSame($a->id, $payload['mudancas'][0]['fornecedor_id']);
    }

    // ------------------------------------------------------------------

    /**
     * @return array{0: CotacaoMapa, 1: CotacaoMapaFornecedor, 2: CotacaoMapaFornecedor}
     */
    private function mapaComDuasColunas(): array
    {
        $mapa = CotacaoMapa::factory()->emCotacao()->create();

        return [
            $mapa,
            CotacaoMapaFornecedor::factory()->create([
                'cotacao_mapa_id' => $mapa->id, 'ordem' => 1, 'valor_frete' => 0, 'desconto' => 0,
            ]),
            CotacaoMapaFornecedor::factory()->create([
                'cotacao_mapa_id' => $mapa->id, 'ordem' => 2, 'valor_frete' => 0, 'desconto' => 0,
            ]),
        ];
    }

    /**
     * O lote já validado e normalizado, como o controller o recebe.
     *
     * @param  array<int|string, array<string, mixed>>  $fornecedores
     * @return array<int, array<string, mixed>>
     */
    private function validado(CotacaoMapa $mapa, array $fornecedores): array
    {
        $request = $this->requisicao($mapa, ['fornecedores' => $fornecedores]);
        $request->validateResolved();

        return $request->paraGravacao();
    }

    /**
     * Roda a ação do controller de ponta a ponta.
     *
     * O Gate é liberado à mão porque quem é do setor é decidido por
     * `App\Models\User`, preso à conexão `mysql`; o que está sob teste aqui é a
     * gravação e a trilha, e a policy tem os testes dela.
     *
     * @param  array<int|string, array<string, mixed>>  $fornecedores
     */
    private function salvar(CotacaoMapa $mapa, array $fornecedores): void
    {
        // O parâmetro precisa ser NULÁVEL: o Gate salta um `before` que não
        // aceite visitante, e aqui não há usuário autenticado.
        Gate::before(fn (?User $user) => true);

        $request = $this->requisicao($mapa, ['fornecedores' => $fornecedores]);
        $request->validateResolved();

        app(FornecedorController::class)->atualizarCondicoes($request, $mapa);
    }

    /**
     * Monta o FormRequest com o mapa já resolvido na rota, como o roteador
     * faria — é o `{mapa}` da URL que define o escopo da trava.
     *
     * `prepareForValidation` NÃO é chamado aqui de propósito: ele não é
     * idempotente (o separador de milhar é removido) e `validateResolved` o
     * chama no momento certo.
     *
     * @param  array<string, mixed>  $dados
     */
    private function requisicao(CotacaoMapa $mapa, array $dados): AtualizarCondicoesCotacaoRequest
    {
        $uri = '/cotacao/mapas/' . $mapa->id . '/fornecedores';

        $request = AtualizarCondicoesCotacaoRequest::create($uri, 'PATCH', $dados);

        $rota = new Route(['PATCH'], '/cotacao/mapas/{mapa}/fornecedores', []);
        $rota->bind($request);
        $rota->setParameter('mapa', $mapa);

        $request->setRouteResolver(fn () => $rota);
        $request->setContainer($this->app);
        // Sem o redirector, `failedValidation` estoura um `Error` antes de
        // chegar à ValidationException que o framework entregaria.
        $request->setRedirector($this->app['redirect']);
        $request->setLaravelSession($this->app['session.store']);

        return $request;
    }
}
