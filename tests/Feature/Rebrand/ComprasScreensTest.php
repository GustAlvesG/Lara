<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Http\Controllers\Cotacao\MapaController;
use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaFornecedor;
use App\Models\CotacaoMapaItem;
use App\Models\CotacaoPreco;
use App\Services\Questor\QuestorGate;
use Tests\Concerns\CreatesCotacaoSchema;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * Compras repaginado: a lista de mapas (busca + situação no mesmo
 * formulário), a busca de solicitação e a grade do mapa — que só trocou de
 * paleta, sem mexer no comportamento (salvar por célula, teclado, decisão).
 *
 * As tabelas de cotação vêm de CreatesCotacaoSchema (SQLite); o usuário é
 * mock, porque o User está preso à conexão mysql.
 */
class ComprasScreensTest extends TestCase
{
    use CreatesCotacaoSchema;
    use RendersScreens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCotacaoSchema();
    }

    private function comprador()
    {
        return $this->usuario(new UserAccess([P::COMPRAS]));
    }

    public function test_lista_de_mapas_com_busca_situacao_e_capa_de_compras(): void
    {
        $mapa = CotacaoMapa::factory()->emCotacao()->create(['titulo' => 'Tintas do parquinho', 'questor_solicitacao' => 34334]);

        $html = $this->tela($this->comprador(), 'cotacao.mapas.index', [], 'cotacao.mapas.index', [
            'mapas' => CotacaoMapa::query()->withCount(['itens', 'fornecedores'])->paginate(20),
            'filtros' => ['status' => 'em_cotacao', 'busca' => null],
            'config' => QuestorGate::summary(),
        ], ['status' => 'em_cotacao']);

        $this->assertStringContainsString('Tintas do parquinho', $html);
        $this->assertStringContainsString(route('cotacao.mapas.show', $mapa), $html);
        $this->assertStringContainsString('34334', $html);
        $this->assertStringContainsString('bg-grena-tint text-grena-ink', $html, 'Em cotação vira o selo informativo.');

        // Busca e situação no mesmo formulário: o status não vira hidden duplicado.
        $this->assertStringContainsString('name="busca"', $html);
        $this->assertMatchesRegularExpression('#<option value="em_cotacao"\s+selected#', $html);
        $this->assertStringNotContainsString('type="hidden" name="status"', $html);
        $this->assertStringContainsString('Limpar', $html);

        $this->assertStringContainsString('aria-label="Páginas de Compras"', $html);
        $this->assertStringNotContainsString('bootstrap-grid', $html);
    }

    public function test_lista_vazia_explica_e_oferece_o_caminho(): void
    {
        $html = $this->tela($this->comprador(), 'cotacao.mapas.index', [], 'cotacao.mapas.index', [
            'mapas' => CotacaoMapa::query()->withCount(['itens', 'fornecedores'])->paginate(20),
            'filtros' => ['status' => null, 'busca' => null],
            'config' => QuestorGate::summary(),
        ]);

        $this->assertStringContainsString('Nenhum mapa por aqui ainda.', $html);
        $this->assertStringContainsString(route('cotacao.mapas.previa'), $html);
    }

    public function test_busca_de_solicitacao_com_resultados_por_periodo(): void
    {
        $html = $this->tela($this->comprador(), 'cotacao.mapas.previa', [], 'cotacao.mapas.previa', [
            'config' => QuestorGate::summary(),
            'codigo' => null,
            'solicitacao' => null,
            'itens' => collect(),
            'ultimasCompras' => collect(),
            'sugestoes' => collect(),
            'mapaExistente' => null,
            'resultados' => collect([(object) [
                'codigo' => 34334, 'dataCadastro' => '2026-09-10', 'solicitante' => 'Manutenção',
                'observacoes' => 'Tintas do parquinho', 'qtdItens' => 4, 'status' => 'Aberta',
            ]]),
            'filtros' => ['de' => '2026-04-01', 'ate' => '2026-10-01', 'busca' => 'tinta'],
            'erro' => 'Questor fora do ar por um instante.',
        ]);

        $this->assertStringContainsString('name="solicitacao"', $html);
        $this->assertStringContainsString('value="tinta"', $html);
        $this->assertStringContainsString('1 solicitação encontrada', $html);
        $this->assertStringContainsString(route('cotacao.mapas.previa', ['solicitacao' => 34334]), $html);
        $this->assertStringContainsString('Não deu para consultar', $html);
        $this->assertStringContainsString('aria-label="Voltar"', $html);
    }

    public function test_grade_do_mapa_troca_de_paleta_sem_perder_o_comportamento(): void
    {
        $mapa = CotacaoMapa::factory()->emCotacao()->create(['titulo' => 'Cloro e algicida']);
        $item = CotacaoMapaItem::factory()->create(['cotacao_mapa_id' => $mapa->id, 'ordem' => 1, 'quantidade' => 2]);
        $loja = CotacaoMapaFornecedor::factory()->create(['cotacao_mapa_id' => $mapa->id, 'ordem' => 1, 'valor_frete' => 0, 'desconto' => 0]);
        CotacaoPreco::factory()->create([
            'cotacao_mapa_item_id' => $item->id,
            'cotacao_mapa_fornecedor_id' => $loja->id,
            'valor_unitario' => 45.5,
            'situacao' => CotacaoPreco::SITUACAO_COTADO,
        ]);

        $user = $this->comprador();
        $this->actingAs($user);
        $this->requisicaoEm('cotacao.mapas.show', [$mapa->id]);

        $html = (string) app(MapaController::class)->show($mapa, app(\App\Services\Cotacao\MapaExportService::class));

        // O que a grade precisa para funcionar continua lá.
        $this->assertStringContainsString('x-data="mapaGrade(', $html);
        $this->assertStringContainsString(route('cotacao.mapas.precos.salvar', $mapa), $html);
        $this->assertStringContainsString('Cloro e algicida', $html);

        // Status com cor de verdade (antes o @class abria um segundo atributo class).
        $this->assertStringNotContainsString('font-bold' . "\n" . '                        class="', $html);
        $this->assertMatchesRegularExpression('#<span class="[^"]*bg-grena-tint text-grena-ink[^"]*">\s*(<svg[^>]*>.*?</svg>\s*)?Em cotação#s', $html);

        // Paleta antiga fora; tokens dentro.
        $this->assertDoesNotMatchRegularExpression('#\b(?:bg|text|border)-gray-\d{2,3}\b#', $this->semLayout($html));
        $this->assertStringContainsString('bg-surface', $html);
        $this->assertStringNotContainsString('bootstrap-grid', $html);
        $this->assertStringContainsString('aria-label="Voltar para os mapas"', $html);
    }

    /** Só o miolo da tela: rodapé e menus de antes ainda têm paleta antiga. */
    private function semLayout(string $html): string
    {
        $inicio = strpos($html, 'x-data="mapaGrade(');
        $fim = strpos($html, '<footer', (int) $inicio);

        return $inicio === false ? $html : substr($html, $inicio, $fim === false ? null : $fim - $inicio);
    }
}
