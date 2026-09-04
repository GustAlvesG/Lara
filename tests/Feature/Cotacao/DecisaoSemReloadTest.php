<?php

namespace Tests\Feature\Cotacao;

use App\Http\Controllers\Cotacao\MapaController;
use App\Http\Requests\DefinirVencedorCotacaoRequest;
use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaFornecedor;
use App\Models\CotacaoMapaItem;
use App\Models\CotacaoPreco;
use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesCotacaoSchema;
use Tests\TestCase;

/**
 * Escolher o vencedor devolve a matriz recalculada — é o que dispensa o reload.
 *
 * A tela antes recarregava a página a cada item decidido, para o rodapé ficar
 * coerente sem refazer as contas em JavaScript. Num mapa de trinta itens isso
 * custava a rolagem e o foco a cada clique.
 *
 * Agora a resposta traz `calculo`, e a tela só o atribui. **É por isso que este
 * teste existe:** se alguém enxugar a resposta para só `{ok, vencedor_id}`, a
 * tela para de atualizar em silêncio — o rodapé segue mostrando o total da
 * decisão anterior, e ninguém vê erro nenhum. Um mapa de cotação decide para
 * onde vai dinheiro; um rodapé desatualizado é pior que uma tela quebrada.
 *
 * O controller é exercitado direto, sem passar pela rota: o middleware de
 * sessão precisaria de um usuário, e `App\Models\User` fixa a conexão `mysql`
 * (fora do SQLite da suíte).
 */
class DecisaoSemReloadTest extends TestCase
{
    use CreatesCotacaoSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCotacaoSchema();
    }

    public function test_a_resposta_traz_os_totais_recalculados_e_nao_so_o_id(): void
    {
        [$mapa, $item, $a, $b] = $this->mapaCotado();

        $dados = $this->escolher($mapa, $item->id, $b->id);

        $this->assertTrue($dados['ok']);
        $this->assertSame($b->id, $dados['vencedor_id']);

        $this->assertArrayHasKey('calculo', $dados, 'Sem `calculo` a tela volta a precisar de reload.');
        $this->assertArrayHasKey('itens', $dados['calculo']);
        $this->assertArrayHasKey('fornecedores', $dados['calculo']);
        $this->assertArrayHasKey('totais', $dados['calculo']);
    }

    /**
     * Os números que a decisão move — e são vários, o que é justamente o motivo
     * de a tela não conseguir recalculá-los sozinha sem duplicar o serviço.
     */
    public function test_os_numeros_da_decisao_ja_vem_atualizados_na_resposta(): void
    {
        [$mapa, $item, $a, $b] = $this->mapaCotado();

        // B custa 90 a unidade, 2 unidades, frete 30.
        $calculo = $this->escolher($mapa, $item->id, $b->id)['calculo'];

        // Delta e não `assertSame`: o JSON serializa 180.0 como `180`, e o
        // decode devolve int — o que importa é o número que chega à tela.
        $this->assertEqualsWithDelta(180.0, $calculo['totais']['total_decidido'], 0.0001);
        $this->assertEqualsWithDelta(210.0, $calculo['totais']['total_decidido_com_frete'], 0.0001);
        $this->assertSame(1, $calculo['totais']['lojas_decididas']);

        $this->assertEqualsWithDelta(180.0, $calculo['fornecedores'][$b->id]['total_vencidos'], 0.0001);
        $this->assertEqualsWithDelta(210.0, $calculo['fornecedores'][$b->id]['total_vencidos_com_frete'], 0.0001);

        // A não ganhou nada: nulo, e não zero — não se compra R$ 0,00 nela.
        $this->assertNull($calculo['fornecedores'][$a->id]['total_vencidos']);
    }

    /**
     * O `vencedor_id` da linha vem junto porque é dele que a tela tira o
     * desempate do verde: no empate, o destaque é de quem foi escolhido.
     */
    public function test_a_linha_devolvida_carrega_o_vencedor_para_o_desempate_do_verde(): void
    {
        [$mapa, $item, $a, $b] = $this->mapaCotado();

        // Empata os dois em 90: as duas células são o menor preço.
        CotacaoPreco::where('cotacao_mapa_item_id', $item->id)
            ->where('cotacao_mapa_fornecedor_id', $a->id)
            ->update(['valor_unitario' => 90.0]);

        $calculo = $this->escolher($mapa, $item->id, $b->id)['calculo'];
        $linha = $calculo['itens'][$item->id];

        $this->assertTrue($linha['empate']);
        $this->assertSame($b->id, $linha['vencedor_id']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $linha['menor_preco_fornecedores']);
    }

    public function test_desfazer_a_escolha_tambem_devolve_os_totais_zerados_como_nulos(): void
    {
        [$mapa, $item, , $b] = $this->mapaCotado();

        $this->escolher($mapa, $item->id, $b->id);

        $calculo = $this->escolher($mapa, $item->id, null)['calculo'];

        $this->assertNull($calculo['totais']['total_decidido']);
        $this->assertNull($calculo['totais']['total_decidido_com_frete']);
        $this->assertSame(0, $calculo['totais']['lojas_decididas']);
        $this->assertNull($calculo['fornecedores'][$b->id]['total_vencidos']);
    }

    // ------------------------------------------------------------------

    /**
     * Um item de 2 unidades cotado por duas lojas: A a 100, B a 90 com frete 30.
     *
     * @return array{0: CotacaoMapa, 1: CotacaoMapaItem, 2: CotacaoMapaFornecedor, 3: CotacaoMapaFornecedor}
     */
    private function mapaCotado(): array
    {
        $mapa = CotacaoMapa::factory()->emCotacao()->create();

        $item = CotacaoMapaItem::factory()->create([
            'cotacao_mapa_id' => $mapa->id, 'ordem' => 1, 'quantidade' => 2, 'vencedor_id' => null,
        ]);

        $a = CotacaoMapaFornecedor::factory()->create([
            'cotacao_mapa_id' => $mapa->id, 'ordem' => 1, 'valor_frete' => 0, 'desconto' => 0,
        ]);
        $b = CotacaoMapaFornecedor::factory()->create([
            'cotacao_mapa_id' => $mapa->id, 'ordem' => 2, 'valor_frete' => 30, 'desconto' => 0,
        ]);

        foreach ([[$a, 100.0], [$b, 90.0]] as [$fornecedor, $valor]) {
            CotacaoPreco::factory()->create([
                'cotacao_mapa_item_id' => $item->id,
                'cotacao_mapa_fornecedor_id' => $fornecedor->id,
                'valor_unitario' => $valor,
                'situacao' => CotacaoPreco::SITUACAO_COTADO,
            ]);
        }

        return [$mapa, $item, $a, $b];
    }

    /**
     * Chama a ação como a tela chama: JSON pedido no cabeçalho `Accept`.
     *
     * @return array<string, mixed>
     */
    private function escolher(CotacaoMapa $mapa, int $itemId, ?int $fornecedorId): array
    {
        // O parâmetro precisa ser NULÁVEL: o Gate salta um `before` que não
        // aceite visitante, e aqui não há usuário autenticado. Quem é do setor
        // é decisão de `App\Models\User`, preso ao `mysql`; a policy tem os
        // testes dela.
        Gate::before(fn (?User $user) => true);

        $uri = '/cotacao/mapas/' . $mapa->id . '/vencedor';

        $request = DefinirVencedorCotacaoRequest::create($uri, 'POST', [
            'item_id' => $itemId,
            'fornecedor_id' => $fornecedorId,
        ]);
        $request->headers->set('Accept', 'application/json');

        $rota = new Route(['POST'], '/cotacao/mapas/{mapa}/vencedor', []);
        $rota->bind($request);
        $rota->setParameter('mapa', $mapa);

        $request->setRouteResolver(fn () => $rota);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->validateResolved();

        $resposta = app(MapaController::class)->definirVencedor($request, $mapa->fresh());

        return json_decode($resposta->getContent(), true);
    }
}
