<?php

namespace App\Http\Controllers\Cotacao\Concerns;

use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaItem;
use App\Services\Cotacao\MapaCalculoService;

/**
 * Recarrega o mapa do banco e devolve a matriz recalculada.
 *
 * Existe porque três lugares precisam da MESMA coisa — a tela ao abrir, o
 * salvamento de preço e o registro do vencedor — e a divergência entre eles
 * seria invisível: o rodapé mostraria um número, o XLSX outro, e ninguém saberia
 * qual está certo.
 *
 * O `load()` (e não `loadMissing()`) é deliberado: quem chama aqui acabou de
 * gravar, e as relações em memória estão velhas. Recarregar é o que garante que
 * o número devolvido à tela seja o número que está no banco.
 *
 * ISTO NÃO ENTROU NO {@see MapaCalculoService} de propósito. Aquele serviço é
 * puro — nenhum banco, cache ou relógio — e é essa pureza que permite exercitar
 * as contas que decidem para onde vai dinheiro sem montar schema nenhum. Dar a
 * ele um método que consulta o banco custaria essa propriedade inteira para
 * poupar quatro linhas.
 */
trait RecalculaMapa
{
    /**
     * @return array{
     *     itens: array<int, array<string, mixed>>,
     *     fornecedores: array<int, array<string, mixed>>,
     *     totais: array<string, mixed>
     * }
     */
    protected function recalcular(CotacaoMapa $mapa, MapaCalculoService $calculo): array
    {
        $mapa->load(['itens.precos', 'fornecedores']);

        return $calculo->calcular(
            $mapa->itens,
            $mapa->fornecedores,
            $mapa->itens->flatMap(fn (CotacaoMapaItem $i) => $i->precos)
        );
    }
}
