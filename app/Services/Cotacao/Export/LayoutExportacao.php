<?php

namespace App\Services\Cotacao\Export;

use App\Models\CotacaoMapa;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Um jeito de desenhar o mapa em XLSX.
 *
 * Existem dois, e a escolha é do comprador na hora de exportar, porque as duas
 * saídas servem a momentos diferentes do mesmo trabalho:
 *
 *   {@see LayoutClassico} — o papel da reunião. Reproduz a planilha que a
 *   compra já imprime, assina e arquiva; mudar a posição das linhas ali
 *   quebraria um hábito que funciona.
 *
 *   {@see LayoutCompleto} — o arquivo de análise. Mesma informação, mais o que
 *   não cabe no papel: variação contra a última compra, cobertura por
 *   fornecedor, comparação entre comprar dividido e comprar de um só, e a
 *   decisão registrada.
 *
 * O contrato é só montar o documento. Gravar em disco e nomear o arquivo é da
 * fachada ({@see \App\Services\Cotacao\MapaExportService}) — isso é igual nos
 * dois, e duplicar daria dois lugares para o caminho do arquivo divergir.
 */
interface LayoutExportacao
{
    /**
     * Rótulo curto para o menu de exportação.
     */
    public function nome(): string;

    /**
     * Uma frase dizendo para que serve — é o que o comprador lê antes de
     * escolher.
     */
    public function descricao(): string;

    /**
     * O documento pronto, ainda em memória.
     */
    public function montar(CotacaoMapa $mapa): Spreadsheet;
}
