<?php

namespace Tests\Concerns;

use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaFornecedor;
use App\Models\CotacaoMapaItem;
use App\Models\CotacaoPreco;

/**
 * O mapa de exemplo dos testes de exportação.
 *
 * Tem a forma do modelo real (dois fornecedores, três itens) e as TRÊS
 * situações de célula representadas — cotado, "NT" e sem resposta —, além de um
 * item sem cadastro no Questor. É o conjunto mínimo que faz os dois layouts
 * exercitarem as regras que importam.
 *
 * Vive num trait porque os dois testes de layout precisam do MESMO mapa: se
 * cada um montasse o seu, uma divergência entre eles apareceria como diferença
 * de layout, quando seria diferença de dado.
 */
trait CriaMapaDeCotacao
{
    protected function mapaDeExemplo(): CotacaoMapa
    {
        $mapa = CotacaoMapa::factory()->emCotacao()->create([
            'questor_solicitacao' => 34334,
            'titulo' => 'MATERIAL PARA PINTURA DA CERCA DO PARQUINHO',
            'departamento' => 'MANUTENÇÃO',
            'solicitante' => 'MANUTENCAO JOAO',
            'comprador' => 'COMPRADOR DE TESTE',
        ]);

        $almeida = CotacaoMapaFornecedor::factory()->create([
            'cotacao_mapa_id' => $mapa->id,
            'nome' => 'D C DE ALMEIDA',
            'frete' => 'CIF',
            'prazo_entrega' => '1 DU',
            'condicao_pagamento' => '14D',
            'valor_frete' => 50.00,
            'ordem' => 1,
        ]);

        $barra = CotacaoMapaFornecedor::factory()->create([
            'cotacao_mapa_id' => $mapa->id,
            'nome' => 'BARRA COR',
            'frete' => 'CIF',
            'prazo_entrega' => '3DU',
            'condicao_pagamento' => 'Á VISTA',
            'valor_frete' => 0,
            'ordem' => 2,
        ]);

        $i1 = CotacaoMapaItem::factory()->create([
            'cotacao_mapa_id' => $mapa->id,
            'questor_cd_item' => 1,
            'questor_cd_material' => 5001,
            'descricao' => 'TINTA ESMALTE AZUL 3,6L',
            'unidade' => 'UN',
            'quantidade' => 2,
            'ordem' => 1,
            'ult_compra_valor' => 200.00,
            'ult_compra_data' => '2026-05-12',
            'ult_compra_fornecedor_nome' => 'BARRA COR',
            'ult_compra_nf' => '55123',
        ]);

        $i2 = CotacaoMapaItem::factory()->create([
            'cotacao_mapa_id' => $mapa->id,
            'questor_cd_item' => 2,
            'questor_cd_material' => 5002,
            'descricao' => 'VERNIZ BASE SOLVENTE IMBUIA 3,6L',
            'unidade' => 'UN',
            'quantidade' => 1,
            'ordem' => 2,
        ]);

        $i3 = CotacaoMapaItem::factory()->semCadastro()->create([
            'cotacao_mapa_id' => $mapa->id,
            'questor_cd_item' => 3,
            'descricao' => 'PARAFUSO ESPECIAL SOB MEDIDA',
            'unidade' => 'UN',
            'quantidade' => 4,
            'ordem' => 3,
        ]);

        CotacaoPreco::factory()->create([
            'cotacao_mapa_item_id' => $i1->id,
            'cotacao_mapa_fornecedor_id' => $almeida->id,
            'valor_unitario' => 180.00,
        ]);

        CotacaoPreco::factory()->create([
            'cotacao_mapa_item_id' => $i1->id,
            'cotacao_mapa_fornecedor_id' => $barra->id,
            'valor_unitario' => 189.43,
        ]);

        CotacaoPreco::factory()->create([
            'cotacao_mapa_item_id' => $i2->id,
            'cotacao_mapa_fornecedor_id' => $almeida->id,
            'valor_unitario' => 161.86,
        ]);

        // O "NT" da planilha: BARRA COR não vende o verniz.
        CotacaoPreco::factory()->naoTrabalha()->create([
            'cotacao_mapa_item_id' => $i2->id,
            'cotacao_mapa_fornecedor_id' => $barra->id,
        ]);

        // Item 3: consultado e sem resposta de um, sem linha nenhuma do outro.
        CotacaoPreco::factory()->semResposta()->create([
            'cotacao_mapa_item_id' => $i3->id,
            'cotacao_mapa_fornecedor_id' => $barra->id,
        ]);

        return $mapa->fresh();
    }
}
