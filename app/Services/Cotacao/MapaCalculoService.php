<?php

namespace App\Services\Cotacao;

use App\Models\CotacaoMapaFornecedor;
use App\Models\CotacaoMapaItem;
use App\Models\CotacaoPreco;
use Illuminate\Support\Collection;

/**
 * Todas as contas do mapa, num lugar só e SEM NENHUM I/O.
 *
 * Nada aqui consulta banco, cache, sessão ou relógio: entram três coleções
 * (itens, fornecedores e preços já carregados) e sai a matriz calculada. É de
 * propósito — estas contas decidem para onde vai dinheiro, e uma conta que só
 * dá para exercitar com banco montado é uma conta que ninguém testa.
 *
 * A REGRA QUE ATRAVESSA TUDO: **célula vazia nunca vira zero.** Nem em
 * subtotal, nem em menor preço, nem em média, nem em economia. Um fornecedor
 * que não respondeu não é um fornecedor que cobrou zero — e a planilha em Excel
 * erra exatamente aí, porque `SUMPRODUCT` trata vazio como zero e o total dele
 * aparece como o mais barato.
 *
 * Por isso a distinção entre `nao_trabalha` (o "NT" da planilha) e
 * `sem_resposta` também chega até aqui: as duas saem das somas, mas só a
 * segunda conta contra a cobertura do fornecedor.
 */
class MapaCalculoService
{
    /**
     * Tolerância de comparação de preços.
     *
     * Preço é decimal(18,6) no banco e vira float aqui; comparar dois floats
     * com `===` faria dois preços iguais deixarem de empatar por um resto de
     * arredondamento. Meio centavo de milésimo é bem menor que qualquer preço
     * real e bem maior que o erro de ponto flutuante.
     */
    private const EPSILON = 0.0000005;

    /**
     * A matriz inteira: célula a célula, coluna a coluna, mais os totais.
     *
     * @param  Collection<int, CotacaoMapaItem>  $itens
     * @param  Collection<int, CotacaoMapaFornecedor>  $fornecedores
     * @param  Collection<int, CotacaoPreco>  $precos
     * @return array{
     *     itens: array<int, array<string, mixed>>,
     *     fornecedores: array<int, array<string, mixed>>,
     *     totais: array<string, mixed>
     * }
     */
    public function calcular(Collection $itens, Collection $fornecedores, Collection $precos): array
    {
        $matriz = $this->indexarPrecos($precos);
        $idsFornecedores = $fornecedores->pluck('id')->map(fn ($id) => (int) $id)->all();

        $linhas = [];

        foreach ($itens as $item) {
            $linhas[(int) $item->id] = $this->calcularLinha($item, $idsFornecedores, $matriz);
        }

        $colunas = $this->calcularColunas($fornecedores, $itens, $linhas);

        return [
            'itens' => $linhas,
            'fornecedores' => $colunas,
            'totais' => $this->calcularTotais($itens, $fornecedores, $linhas, $colunas),
        ];
    }

    /**
     * Uma linha do mapa: as células daquele item, o menor preço, o segundo
     * menor e a variação contra a última compra.
     *
     * @param  array<int, int>  $idsFornecedores
     * @param  array<int, array<int, CotacaoPreco>>  $matriz
     * @return array<string, mixed>
     */
    private function calcularLinha(CotacaoMapaItem $item, array $idsFornecedores, array $matriz): array
    {
        $quantidade = (float) $item->quantidade;
        $ultimaCompra = $item->ult_compra_valor !== null ? (float) $item->ult_compra_valor : null;

        $celulas = [];
        $valores = [];

        foreach ($idsFornecedores as $fornecedorId) {
            $preco = $matriz[(int) $item->id][$fornecedorId] ?? null;

            $situacao = $preco?->situacao ?? CotacaoPreco::SITUACAO_SEM_RESPOSTA;

            // Só `cotado` COM valor entra nas contas. `cotado` sem valor é
            // estado inconsistente que a validação impede de nascer — o cálculo
            // não confia mesmo assim.
            $valor = ($preco !== null && $preco->temPreco())
                ? (float) $preco->valor_unitario
                : null;

            if ($valor !== null) {
                $valores[$fornecedorId] = $valor;
            }

            $celulas[$fornecedorId] = [
                'preco_id' => $preco?->id,
                'situacao' => $situacao,
                'valor' => $valor,
                // Total da célula é nulo, não zero, quando não há preço.
                'total' => $valor !== null ? $valor * $quantidade : null,
                'marca' => $preco?->marca,
                'observacao' => $preco?->observacao,
                'variacao_vs_ultima' => $this->variacao($valor, $ultimaCompra),
                'menor' => false,
                'segundo' => false,
            ];
        }

        [$menor, $idsMenor, $segundo, $idsSegundo] = $this->doisMenores($valores);

        foreach ($idsMenor as $id) {
            $celulas[$id]['menor'] = true;
        }

        foreach ($idsSegundo as $id) {
            $celulas[$id]['segundo'] = true;
        }

        $vencedorId = $item->vencedor_id !== null ? (int) $item->vencedor_id : null;

        return [
            'item_id' => (int) $item->id,
            'quantidade' => $quantidade,
            'ult_compra_valor' => $ultimaCompra,
            'ult_compra_total' => $ultimaCompra !== null ? $ultimaCompra * $quantidade : null,
            'celulas' => $celulas,
            'menor_preco' => $menor,
            'menor_preco_total' => $menor !== null ? $menor * $quantidade : null,
            // Lista, e não um id: empate é comum e escolher um por sorteio
            // esconderia do comprador que há dois preços iguais.
            'menor_preco_fornecedores' => $idsMenor,
            'empate' => count($idsMenor) > 1,
            'segundo_menor' => $segundo,
            'segundo_menor_fornecedores' => $idsSegundo,
            'cotado_por' => count($valores),
            'vencedor_id' => $vencedorId,
            'vencedor_total' => $vencedorId !== null ? ($celulas[$vencedorId]['total'] ?? null) : null,
            'economia_vs_ultima' => ($ultimaCompra !== null && $menor !== null)
                ? ($ultimaCompra - $menor) * $quantidade
                : null,
        ];
    }

    /**
     * As colunas: subtotal, frete, total, cobertura.
     *
     * QUATRO NÚMEROS POR FORNECEDOR, e é a melhoria mais importante sobre a
     * planilha atual:
     *
     *   `subtotal`      — tudo o que ele cotou, à quantidade pedida.
     *   `total`         — o subtotal com frete e desconto. É o número honesto.
     *   `total_cheio`   — só existe quando ele cotou TODOS os itens; é o único
     *                     caso em que comparar total contra total faz sentido.
     *   `total_vencidos`— só os itens em que ele ganhou: quanto se compra dele
     *                     de fato, dada a decisão já registrada.
     *
     * Na planilha de hoje essas distinções não existem: quem cotou 2 de 6 itens
     * aparece com o menor total da linha e parece o mais barato, e não há
     * nenhum lugar que responda "quanto vai sair o pedido desta loja".
     *
     * @param  Collection<int, CotacaoMapaFornecedor>  $fornecedores
     * @param  Collection<int, CotacaoMapaItem>  $itens
     * @param  array<int, array<string, mixed>>  $linhas
     * @return array<int, array<string, mixed>>
     */
    private function calcularColunas(Collection $fornecedores, Collection $itens, array $linhas): array
    {
        $totalItens = $itens->count();
        $colunas = [];

        foreach ($fornecedores as $fornecedor) {
            $id = (int) $fornecedor->id;

            $subtotal = 0.0;
            $cotados = 0;
            $naoTrabalha = 0;
            $semResposta = 0;
            $vencidos = 0;
            $totalVencidos = 0.0;

            foreach ($linhas as $linha) {
                $celula = $linha['celulas'][$id] ?? null;

                if ($celula === null) {
                    $semResposta++;

                    continue;
                }

                if ($celula['total'] !== null) {
                    $subtotal += $celula['total'];
                    $cotados++;
                } elseif ($celula['situacao'] === CotacaoPreco::SITUACAO_NAO_TRABALHA) {
                    $naoTrabalha++;
                } else {
                    $semResposta++;
                }

                if ($linha['vencedor_id'] === $id && $celula['total'] !== null) {
                    $vencidos++;
                    $totalVencidos += $celula['total'];
                }
            }

            $frete = (float) $fornecedor->valor_frete;
            $desconto = (float) $fornecedor->desconto;

            // Frete e desconto só entram se houve alguma cotação. Um fornecedor
            // que não respondeu nada não pode aparecer no rodapé devendo frete.
            $total = $cotados > 0 ? $subtotal + $frete - $desconto : 0.0;

            $colunas[$id] = [
                'fornecedor_id' => $id,
                'subtotal' => $subtotal,
                'frete' => $frete,
                'desconto' => $desconto,
                'total' => $total,
                'itens_cotados' => $cotados,
                'itens_nao_trabalha' => $naoTrabalha,
                'itens_sem_resposta' => $semResposta,
                'itens_total' => $totalItens,
                'cobertura_completa' => $totalItens > 0 && $cotados === $totalItens,
                // Nulo quando ele não cobre tudo: é a forma de a tela não
                // conseguir compará-lo como se cobrisse.
                'total_cheio' => ($totalItens > 0 && $cotados === $totalItens) ? $total : null,
                'itens_vencidos' => $vencidos,
                // O SUBTOTAL DOS ESCOLHIDOS: só os itens em que este fornecedor
                // ganhou. É o que de fato se vai comprar dele — enquanto
                // `subtotal` acima é o que ele cotaria se levasse tudo o que
                // cotou. Os dois juntos é que respondem "quanto compro aqui?".
                //
                // Nulo, e não zero, quando ele não ganhou nada: zero seria uma
                // compra de R$ 0,00 nesta loja, e é a mesma regra da célula
                // vazia que atravessa a classe.
                'total_vencidos' => $vencidos > 0 ? $totalVencidos : null,
                // O pedido inteiro a esta loja: frete e desconto entram uma vez
                // só, porque o frete não é por item. Sem item escolhido não há
                // pedido — logo não há frete a pagar aqui.
                'total_vencidos_com_frete' => $vencidos > 0
                    ? $totalVencidos + $frete - $desconto
                    : null,
            ];
        }

        return $colunas;
    }

    /**
     * O rodapé do mapa — e a comparação das duas estratégias de compra.
     *
     * `melhor_combinacao` é a compra DIVIDIDA: cada item pelo menor preço,
     * possivelmente de fornecedores diferentes. `melhor_fornecedor_unico` é a
     * compra concentrada. As duas aparecem lado a lado porque a diferença entre
     * elas é o que o comprador está de fato decidindo — e porque a divisão
     * costuma perder parte da vantagem no frete, que é o motivo de existir
     * `melhor_combinacao_com_frete`.
     *
     * @param  Collection<int, CotacaoMapaItem>  $itens
     * @param  Collection<int, CotacaoMapaFornecedor>  $fornecedores
     * @param  array<int, array<string, mixed>>  $linhas
     * @param  array<int, array<string, mixed>>  $colunas
     * @return array<string, mixed>
     */
    private function calcularTotais(
        Collection $itens,
        Collection $fornecedores,
        array $linhas,
        array $colunas
    ): array {
        $melhorCombinacao = 0.0;
        $itensComPreco = 0;
        $fornecedoresEnvolvidos = [];

        $baseUltimaCompra = 0.0;
        $melhorComparavel = 0.0;
        $itensComparaveis = 0;

        $totalDecidido = 0.0;
        $itensDecididos = 0;

        foreach ($linhas as $linha) {
            if ($linha['menor_preco_total'] !== null) {
                $melhorCombinacao += $linha['menor_preco_total'];
                $itensComPreco++;

                // No empate, o primeiro da ordem das colunas — a escolha só
                // afeta de quem seria o frete, e o comprador ainda decide.
                $fornecedoresEnvolvidos[$linha['menor_preco_fornecedores'][0]] = true;
            }

            // Economia só soma onde há os DOIS lados. Item sem última compra
            // (ou sem cotação) fica fora da conta em vez de entrar como zero e
            // inventar economia.
            if ($linha['ult_compra_total'] !== null && $linha['menor_preco_total'] !== null) {
                $baseUltimaCompra += $linha['ult_compra_total'];
                $melhorComparavel += $linha['menor_preco_total'];
                $itensComparaveis++;
            }

            if ($linha['vencedor_total'] !== null) {
                $totalDecidido += $linha['vencedor_total'];
                $itensDecididos++;
            }
        }

        $freteDaCombinacao = 0.0;

        foreach (array_keys($fornecedoresEnvolvidos) as $id) {
            $freteDaCombinacao += $colunas[$id]['frete'] ?? 0.0;
        }

        // Frete e desconto da decisão JÁ REGISTRADA — uma vez por loja em que
        // se comprou algo, não por item. Dividir a compra entre três lojas paga
        // três fretes, e é exatamente esse número que costuma virar a compra
        // dividida do avesso.
        $freteDecidido = 0.0;
        $lojasDecididas = 0;

        foreach ($colunas as $coluna) {
            if ($coluna['itens_vencidos'] > 0) {
                $lojasDecididas++;
                $freteDecidido += $coluna['frete'] - $coluna['desconto'];
            }
        }

        // Só quem cotou o mapa inteiro pode ser comparado como fornecedor
        // único: quem não cobre tudo não é uma alternativa de compra concentrada.
        $completos = array_filter($colunas, fn (array $c) => $c['cobertura_completa']);

        $melhorUnico = null;

        foreach ($completos as $coluna) {
            if ($melhorUnico === null || $coluna['total'] < $melhorUnico['total']) {
                $melhorUnico = $coluna;
            }
        }

        $totalItens = $itens->count();

        return [
            'itens_total' => $totalItens,
            'itens_com_preco' => $itensComPreco,
            // Falso = ainda há item sem nenhuma cotação; o "total geral" abaixo
            // é parcial, e a tela precisa dizer isso.
            'cotacao_completa' => $totalItens > 0 && $itensComPreco === $totalItens,

            'melhor_combinacao' => $itensComPreco > 0 ? $melhorCombinacao : null,
            'melhor_combinacao_frete' => $freteDaCombinacao,
            'melhor_combinacao_com_frete' => $itensComPreco > 0
                ? $melhorCombinacao + $freteDaCombinacao
                : null,
            'melhor_combinacao_fornecedores' => array_keys($fornecedoresEnvolvidos),

            'melhor_fornecedor_unico_id' => $melhorUnico['fornecedor_id'] ?? null,
            'melhor_fornecedor_unico_total' => $melhorUnico['total'] ?? null,
            'fornecedores_completos' => count($completos),
            'fornecedores_total' => $fornecedores->count(),

            'total_decidido' => $itensDecididos > 0 ? $totalDecidido : null,
            'total_decidido_com_frete' => $itensDecididos > 0
                ? $totalDecidido + $freteDecidido
                : null,
            'total_decidido_frete' => $freteDecidido,
            'itens_decididos' => $itensDecididos,
            // Em quantas lojas a decisão atual manda comprar. É o custo
            // escondido da compra dividida: cada loja é um pedido, um frete e
            // uma nota a conferir.
            'lojas_decididas' => $lojasDecididas,

            'base_ultima_compra' => $itensComparaveis > 0 ? $baseUltimaCompra : null,
            'melhor_comparavel' => $itensComparaveis > 0 ? $melhorComparavel : null,
            // Negativa é resultado legítimo: quer dizer que a cotação saiu mais
            // cara que a última compra, e o comprador tem de ver isso.
            'economia' => $itensComparaveis > 0 ? $baseUltimaCompra - $melhorComparavel : null,
            'itens_comparaveis' => $itensComparaveis,
        ];
    }

    /**
     * Menor e segundo menor valor, com os empates de cada um.
     *
     * "Segundo menor" é o segundo VALOR distinto, não a segunda célula: com
     * três colunas a 100, 100 e 120, o menor é 100 (empatado) e o segundo é
     * 120. Destacar a segunda célula de 100 como "segundo menor" diria ao
     * comprador que ela é pior que a primeira, e ela não é.
     *
     * @param  array<int, float>  $valores  fornecedorId => valor
     * @return array{0: ?float, 1: array<int, int>, 2: ?float, 3: array<int, int>}
     */
    private function doisMenores(array $valores): array
    {
        if ($valores === []) {
            return [null, [], null, []];
        }

        $menor = min($valores);

        $idsMenor = array_keys(array_filter(
            $valores,
            fn (float $v) => abs($v - $menor) <= self::EPSILON
        ));

        $restantes = array_filter($valores, fn (float $v) => abs($v - $menor) > self::EPSILON);

        if ($restantes === []) {
            return [$menor, $idsMenor, null, []];
        }

        $segundo = min($restantes);

        $idsSegundo = array_keys(array_filter(
            $restantes,
            fn (float $v) => abs($v - $segundo) <= self::EPSILON
        ));

        return [$menor, $idsMenor, $segundo, $idsSegundo];
    }

    /**
     * Variação proporcional do preço cotado contra a última compra.
     *
     * Negativa é bom (mais barato). Nula quando falta um dos lados ou quando a
     * última compra é zero — dividir por zero para mostrar "-100%" seria pior
     * que não mostrar nada.
     */
    private function variacao(?float $valor, ?float $ultimaCompra): ?float
    {
        if ($valor === null || $ultimaCompra === null || abs($ultimaCompra) <= self::EPSILON) {
            return null;
        }

        return ($valor - $ultimaCompra) / $ultimaCompra;
    }

    /**
     * Preços indexados por [item][fornecedor] — a matriz que o cálculo percorre.
     *
     * @param  Collection<int, CotacaoPreco>  $precos
     * @return array<int, array<int, CotacaoPreco>>
     */
    private function indexarPrecos(Collection $precos): array
    {
        $matriz = [];

        foreach ($precos as $preco) {
            $matriz[(int) $preco->cotacao_mapa_item_id][(int) $preco->cotacao_mapa_fornecedor_id] = $preco;
        }

        return $matriz;
    }
}
