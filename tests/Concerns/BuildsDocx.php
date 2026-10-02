<?php

namespace Tests\Concerns;

use ZipArchive;

/**
 * Monta um .docx mínimo, de verdade, para os testes do importador de modelos.
 *
 * Um .docx é um zip com XML; aqui vão só as partes que o importador lê. O
 * corpo é escrito em WordprocessingML cru de propósito: o que se testa é
 * justamente como o Word grava as coisas (o marcador partido em trechos, a
 * numeração por referência), e um gerador de alto nível esconderia isso.
 */
trait BuildsDocx
{
    /** @var array<int, string> */
    private array $docxTemporarios = [];

    /**
     * @param  string  $body  o conteúdo de <w:body>
     * @param  array<string, string>  $parts  partes extras do pacote (ex.: word/numbering.xml)
     */
    protected function criaDocx(string $body, array $parts = []): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'docx');
        $this->docxTemporarios[] = $caminho;

        $zip = new ZipArchive();
        $zip->open($caminho, ZipArchive::OVERWRITE);

        $zip->addFromString('word/document.xml', $this->docxXml('document', '<w:body>' . $body . '</w:body>'));

        foreach ($parts as $nome => $xml) {
            $zip->addFromString($nome, $xml);
        }

        $zip->close();

        return $caminho;
    }

    protected function docxXml(string $raiz, string $conteudo): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:' . $raiz . ' xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . $conteudo
            . '</w:' . $raiz . '>';
    }

    /** Um parágrafo de um trecho só. */
    protected function docxP(string $texto, string $pPr = ''): string
    {
        return '<w:p>' . ($pPr !== '' ? '<w:pPr>' . $pPr . '</w:pPr>' : '') . $this->docxR($texto) . '</w:p>';
    }

    protected function docxR(string $texto, string $rPr = ''): string
    {
        return '<w:r>' . ($rPr !== '' ? '<w:rPr>' . $rPr . '</w:rPr>' : '')
            . '<w:t xml:space="preserve">' . htmlspecialchars($texto, ENT_XML1) . '</w:t></w:r>';
    }

    protected function apagaDocxTemporarios(): void
    {
        foreach ($this->docxTemporarios as $caminho) {
            @unlink($caminho);
        }

        $this->docxTemporarios = [];
    }
}
