<?php

namespace Tests\Feature\Cotacao;

use App\Http\Requests\DefinirVencedorCotacaoRequest;
use App\Http\Requests\SalvarPrecoCotacaoRequest;
use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaFornecedor;
use App\Models\CotacaoMapaItem;
use App\Models\CotacaoPreco;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\CreatesCotacaoSchema;
use Tests\TestCase;

/**
 * A trava de escopo da grade — a defesa contra IDOR.
 *
 * A rota carrega o mapa (`/cotacao/mapas/{mapa}/precos`), mas o item e o
 * fornecedor vêm no CORPO da requisição. Sem conferir que os dois pertencem
 * àquele mapa, trocar um id no corpo escreveria preço no mapa de outro
 * comprador — e o mapa de destino nem apareceria no log de quem mexeu nele.
 *
 * A checagem é de AUTORIZAÇÃO, não de formato: apontar para a linha de outro
 * mapa não é um dado mal digitado, é uma tentativa de escrever onde não se
 * pode. Por isso vive em `authorize()` e o resultado é 403.
 *
 * Estes testes exercitam os FormRequests direto, sem passar pela rota: o
 * middleware de sessão precisaria de um usuário, e `App\Models\User` fixa a
 * conexão `mysql` (fora do SQLite da suíte).
 */
class SalvarPrecoEscopoTest extends TestCase
{
    use CreatesCotacaoSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCotacaoSchema();
    }

    public function test_item_e_fornecedor_do_proprio_mapa_sao_autorizados(): void
    {
        [$mapa, $item, $fornecedor] = $this->mapaComLinha();

        $request = $this->requisicaoDePreco($mapa, [
            'item_id' => $item->id,
            'fornecedor_id' => $fornecedor->id,
            'situacao' => CotacaoPreco::SITUACAO_COTADO,
            'valor_unitario' => '189,43',
        ]);

        $this->assertTrue($request->authorize());
    }

    public function test_item_de_outro_mapa_e_recusado(): void
    {
        [$mapa, , $fornecedor] = $this->mapaComLinha();
        [, $itemAlheio] = $this->mapaComLinha();

        $request = $this->requisicaoDePreco($mapa, [
            'item_id' => $itemAlheio->id,
            'fornecedor_id' => $fornecedor->id,
            'situacao' => CotacaoPreco::SITUACAO_COTADO,
            'valor_unitario' => '10',
        ]);

        $this->assertFalse($request->authorize());
    }

    public function test_fornecedor_de_outro_mapa_e_recusado(): void
    {
        [$mapa, $item] = $this->mapaComLinha();
        [, , $fornecedorAlheio] = $this->mapaComLinha();

        $request = $this->requisicaoDePreco($mapa, [
            'item_id' => $item->id,
            'fornecedor_id' => $fornecedorAlheio->id,
            'situacao' => CotacaoPreco::SITUACAO_COTADO,
            'valor_unitario' => '10',
        ]);

        $this->assertFalse($request->authorize());
    }

    /**
     * Id que não existe em lugar nenhum também não passa: o mapa da rota não o
     * tem, e é essa a pergunta que a trava faz.
     */
    public function test_id_inexistente_e_recusado(): void
    {
        [$mapa, , $fornecedor] = $this->mapaComLinha();

        $request = $this->requisicaoDePreco($mapa, [
            'item_id' => 999999,
            'fornecedor_id' => $fornecedor->id,
            'situacao' => CotacaoPreco::SITUACAO_COTADO,
            'valor_unitario' => '10',
        ]);

        $this->assertFalse($request->authorize());
    }

    /**
     * Campo ausente é problema de validação, não de autorização: "o campo é
     * obrigatório" é uma resposta melhor que um 403 seco.
     */
    public function test_campo_ausente_passa_pela_autorizacao_e_cai_na_validacao(): void
    {
        [$mapa] = $this->mapaComLinha();

        $request = $this->requisicaoDePreco($mapa, ['situacao' => CotacaoPreco::SITUACAO_COTADO]);

        $this->assertTrue($request->authorize());

        $validador = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validador->fails());
        $this->assertArrayHasKey('item_id', $validador->errors()->toArray());
        $this->assertArrayHasKey('fornecedor_id', $validador->errors()->toArray());
    }

    public function test_a_regra_exists_tambem_amarra_o_id_ao_mapa_da_rota(): void
    {
        [$mapa, , $fornecedor] = $this->mapaComLinha();
        [, $itemAlheio] = $this->mapaComLinha();

        $request = $this->requisicaoDePreco($mapa, [
            'item_id' => $itemAlheio->id,
            'fornecedor_id' => $fornecedor->id,
            'situacao' => CotacaoPreco::SITUACAO_COTADO,
            'valor_unitario' => '10',
        ]);

        $validador = Validator::make($request->all(), $request->rules());

        // Defesa em profundidade: mesmo que a autorização mudasse, o `exists`
        // com o `where` do mapa continuaria barrando.
        $this->assertTrue($validador->fails());
        $this->assertArrayHasKey('item_id', $validador->errors()->toArray());
    }

    public function test_valor_digitado_no_formato_brasileiro_vira_numero(): void
    {
        [$mapa, $item, $fornecedor] = $this->mapaComLinha();

        $request = $this->requisicaoDePreco($mapa, [
            'item_id' => $item->id,
            'fornecedor_id' => $fornecedor->id,
            'situacao' => CotacaoPreco::SITUACAO_COTADO,
            'valor_unitario' => '1.234,56',
        ]);

        $this->assertEqualsWithDelta(1234.56, (float) $request->paraGravacao()['valor_unitario'], 0.0001);
    }

    /**
     * A coerência entre situação e valor é resolvida no request: fora de
     * `cotado`, o valor é nulo — e não o número que ficou na tela antes de o
     * comprador mudar o seletor.
     */
    public function test_situacao_sem_preco_zera_o_valor_para_nulo_e_nao_para_zero(): void
    {
        [$mapa, $item, $fornecedor] = $this->mapaComLinha();

        foreach ([CotacaoPreco::SITUACAO_NAO_TRABALHA, CotacaoPreco::SITUACAO_SEM_RESPOSTA] as $situacao) {
            $request = $this->requisicaoDePreco($mapa, [
                'item_id' => $item->id,
                'fornecedor_id' => $fornecedor->id,
                'situacao' => $situacao,
                'valor_unitario' => '99,90',
            ]);

            $dados = $request->paraGravacao();

            $this->assertSame($situacao, $dados['situacao']);
            $this->assertNull($dados['valor_unitario'], "Situação {$situacao} deveria zerar o valor para nulo.");
        }
    }

    public function test_cotado_sem_valor_e_recusado_pela_validacao(): void
    {
        [$mapa, $item, $fornecedor] = $this->mapaComLinha();

        $request = $this->requisicaoDePreco($mapa, [
            'item_id' => $item->id,
            'fornecedor_id' => $fornecedor->id,
            'situacao' => CotacaoPreco::SITUACAO_COTADO,
            'valor_unitario' => null,
        ]);

        $validador = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validador->fails());
        $this->assertArrayHasKey('valor_unitario', $validador->errors()->toArray());
    }

    public function test_preco_negativo_e_recusado(): void
    {
        [$mapa, $item, $fornecedor] = $this->mapaComLinha();

        $request = $this->requisicaoDePreco($mapa, [
            'item_id' => $item->id,
            'fornecedor_id' => $fornecedor->id,
            'situacao' => CotacaoPreco::SITUACAO_COTADO,
            'valor_unitario' => '-5',
        ]);

        $validador = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validador->fails());
        $this->assertArrayHasKey('valor_unitario', $validador->errors()->toArray());
    }

    public function test_definir_vencedor_tem_a_mesma_trava_de_escopo(): void
    {
        [$mapa, $item, $fornecedor] = $this->mapaComLinha();
        [, , $fornecedorAlheio] = $this->mapaComLinha();

        $proprio = $this->requisicao(DefinirVencedorCotacaoRequest::class, $mapa, [
            'item_id' => $item->id,
            'fornecedor_id' => $fornecedor->id,
        ]);

        $alheio = $this->requisicao(DefinirVencedorCotacaoRequest::class, $mapa, [
            'item_id' => $item->id,
            'fornecedor_id' => $fornecedorAlheio->id,
        ]);

        $this->assertTrue($proprio->authorize());
        $this->assertFalse($alheio->authorize());
    }

    /**
     * Desfazer a escolha é um caso legítimo: o comprador tem de poder voltar
     * atrás sem apagar o mapa.
     */
    public function test_definir_vencedor_aceita_fornecedor_nulo_para_desfazer_a_escolha(): void
    {
        [$mapa, $item] = $this->mapaComLinha();

        $request = $this->requisicao(DefinirVencedorCotacaoRequest::class, $mapa, [
            'item_id' => $item->id,
            'fornecedor_id' => null,
        ]);

        $this->assertTrue($request->authorize());
        $this->assertFalse(Validator::make($request->all(), $request->rules())->fails());
    }

    // ------------------------------------------------------------------

    /**
     * @return array{0: CotacaoMapa, 1: CotacaoMapaItem, 2: CotacaoMapaFornecedor}
     */
    private function mapaComLinha(): array
    {
        $mapa = CotacaoMapa::factory()->emCotacao()->create();

        return [
            $mapa,
            CotacaoMapaItem::factory()->create(['cotacao_mapa_id' => $mapa->id]),
            CotacaoMapaFornecedor::factory()->create(['cotacao_mapa_id' => $mapa->id]),
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function requisicaoDePreco(CotacaoMapa $mapa, array $dados): SalvarPrecoCotacaoRequest
    {
        return $this->requisicao(SalvarPrecoCotacaoRequest::class, $mapa, $dados);
    }

    /**
     * Monta o FormRequest com o mapa já resolvido na rota, como o roteador
     * faria — é o `{mapa}` da URL que define o escopo da trava.
     *
     * @template T of FormRequest
     *
     * @param  class-string<T>  $classe
     * @param  array<string, mixed>  $dados
     * @return T
     */
    private function requisicao(string $classe, CotacaoMapa $mapa, array $dados): FormRequest
    {
        /** @var T $request */
        $request = $classe::create('/cotacao/mapas/' . $mapa->id . '/precos', 'POST', $dados);

        $rota = new Route(['POST'], '/cotacao/mapas/{mapa}/precos', []);
        $rota->bind($request);
        $rota->setParameter('mapa', $mapa);

        $request->setRouteResolver(fn () => $rota);
        $request->setContainer($this->app);

        // `prepareForValidation` roda no ciclo do framework; aqui é chamado à
        // mão porque o request não passa pelo pipeline de middleware.
        (fn () => $this->prepareForValidation())->call($request);

        return $request;
    }
}
