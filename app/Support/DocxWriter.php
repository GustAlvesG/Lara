<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Gerador mínimo de WordprocessingML (.docx), sem biblioteca: um .docx é um
 * zip com XML, e `zip` já é exigido pelo projeto.
 *
 * Usado pela exportação do contrato (`ContractDocxExporter`) e pela do modelo
 * de assinatura (`DocxTemplateExporter`). Só o que os dois precisam:
 * parágrafo, negrito/itálico/sublinhado, quebra, tabulação, títulos com o
 * estilo do Word e lista com marcador de verdade — o importador
 * (`DocxTemplateImporter`) reconhece título pelo NOME do estilo e marcador pela
 * numeração, e é por isso que eles saem como o Word os grava.
 */
final class DocxWriter
{
    public const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** numId da lista com marcador (numbering.xml de `package(..., bullets: true)`). */
    public const BULLET_NUM_ID = 1;

    /**
     * @param  array<int, string>  $runs
     * @param  array{style?: string, bullet?: int, jc?: string, left?: int, hanging?: int, before?: int, border?: bool}  $opts
     *     `bullet` = nível (0…) na lista com marcador; `border` = linha embaixo (o <hr>).
     */
    public static function paragraph(array $runs, array $opts = []): string
    {
        // A ordem dos filhos de <w:pPr> é a do esquema: estilo, numeração,
        // borda, espaçamento, recuo, alinhamento.
        $pr = '<w:pPr>';

        if (isset($opts['style'])) {
            $pr .= '<w:pStyle w:val="' . htmlspecialchars($opts['style'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"/>';
        }

        if (isset($opts['bullet'])) {
            $pr .= '<w:numPr><w:ilvl w:val="' . (int) $opts['bullet'] . '"/><w:numId w:val="' . self::BULLET_NUM_ID . '"/></w:numPr>';
        }

        if (!empty($opts['border'])) {
            $pr .= '<w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="auto"/></w:pBdr>';
        }

        $pr .= '<w:spacing w:before="' . ($opts['before'] ?? 0) . '" w:after="120"/>';

        if (($opts['left'] ?? 0) > 0) {
            $pr .= '<w:ind w:left="' . (int) $opts['left'] . '"'
                . (($opts['hanging'] ?? 0) > 0 ? ' w:hanging="' . (int) $opts['hanging'] . '"' : '') . '/>';
        }

        $pr .= '<w:jc w:val="' . ($opts['jc'] ?? 'both') . '"/></w:pPr>';

        return '<w:p>' . $pr . implode('', $runs) . '</w:p>';
    }

    /** @param  array{b?: bool, i?: bool, u?: bool}  $fmt */
    public static function run(string $texto, array $fmt = []): string
    {
        $pr = (!empty($fmt['b']) ? '<w:b/>' : '') . (!empty($fmt['i']) ? '<w:i/>' : '') . (!empty($fmt['u']) ? '<w:u w:val="single"/>' : '');

        $partes = explode("\t", $texto);
        $xml = '';

        foreach ($partes as $i => $parte) {
            if ($i > 0) {
                $xml .= '<w:tab/>';
            }

            if ($parte !== '') {
                $xml .= '<w:t xml:space="preserve">' . htmlspecialchars($parte, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t>';
            }
        }

        return '<w:r>' . ($pr !== '' ? '<w:rPr>' . $pr . '</w:rPr>' : '') . $xml . '</w:r>';
    }

    /** Quebra de linha dentro do parágrafo. */
    public static function lineBreak(): string
    {
        return '<w:r><w:br/></w:r>';
    }

    /**
     * Tabela com borda simples. Cada célula é o XML de um ou mais parágrafos.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    public static function table(array $rows): string
    {
        $borda = '<w:top w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:left w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:right w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="auto"/>';

        $colunas = max(array_map('count', $rows) ?: [1]);
        $grade = str_repeat('<w:gridCol w:w="' . intdiv(9354, $colunas) . '"/>', $colunas);

        $xml = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>' . $borda . '</w:tblBorders></w:tblPr>'
            . '<w:tblGrid>' . $grade . '</w:tblGrid>';

        foreach ($rows as $celulas) {
            $xml .= '<w:tr>';

            foreach ($celulas as $celula) {
                // Célula sem parágrafo é XML inválido para o Word.
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/></w:tcPr>'
                    . ($celula !== '' ? $celula : '<w:p/>') . '</w:tc>';
            }

            $xml .= '</w:tr>';
        }

        return $xml . '</w:tbl>';
    }

    /**
     * O pacote .docx, em bytes.
     *
     * @param  bool  $headings  inclui os estilos "heading 1…3" (o importador lê o NOME do estilo)
     * @param  bool  $bullets  inclui a numeração da lista com marcador (`BULLET_NUM_ID`)
     */
    public static function package(string $corpo, bool $headings = false, bool $bullets = false): string
    {
        $documento = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="' . self::W . '"><w:body>' . $corpo
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1418" w:right="1134" w:bottom="1418" w:left="1418" w:header="709" w:footer="709" w:gutter="0"/>'
            . '</w:sectPr></w:body></w:document>';

        $titulos = '';

        if ($headings) {
            foreach ([1 => 28, 2 => 24, 3 => 22] as $nivel => $tamanho) {
                $titulos .= '<w:style w:type="paragraph" w:styleId="Heading' . $nivel . '">'
                    . '<w:name w:val="heading ' . $nivel . '"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
                    . '<w:pPr><w:keepNext/><w:spacing w:before="240" w:after="120"/><w:outlineLvl w:val="' . ($nivel - 1) . '"/></w:pPr>'
                    . '<w:rPr><w:b/><w:sz w:val="' . $tamanho . '"/></w:rPr></w:style>';
            }
        }

        $estilos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="' . self::W . '"><w:docDefaults><w:rPrDefault><w:rPr>'
            . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:sz w:val="22"/><w:lang w:val="pt-BR"/>'
            . '</w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:line="300" w:lineRule="auto"/></w:pPr></w:pPrDefault>'
            . '</w:docDefaults>'
            . ($titulos !== '' ? '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>' . $titulos : '')
            . '</w:styles>';

        $tipos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . ($bullets ? '<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>' : '')
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';

        $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . ($bullets ? '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>' : '')
            . '</Relationships>';

        $arquivo = tempnam(sys_get_temp_dir(), 'ctdocx');
        $zip = new ZipArchive();

        if ($zip->open($arquivo, ZipArchive::OVERWRITE) !== true) {
            @unlink($arquivo);

            throw new RuntimeException('Não foi possível montar o arquivo Word.');
        }

        $zip->addFromString('[Content_Types].xml', $tipos);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/document.xml', $documento);
        $zip->addFromString('word/styles.xml', $estilos);
        $zip->addFromString('word/_rels/document.xml.rels', $docRels);

        if ($bullets) {
            $zip->addFromString('word/numbering.xml', self::bulletNumbering());
        }

        $zip->close();

        $bytes = (string) file_get_contents($arquivo);
        @unlink($arquivo);

        return $bytes;
    }

    /** Uma lista com marcador de três níveis (•, ◦, ▪). */
    private static function bulletNumbering(): string
    {
        $niveis = '';

        foreach (['•', '◦', '▪'] as $nivel => $marcador) {
            $niveis .= '<w:lvl w:ilvl="' . $nivel . '"><w:start w:val="1"/><w:numFmt w:val="bullet"/>'
                . '<w:lvlText w:val="' . $marcador . '"/><w:lvlJc w:val="left"/>'
                . '<w:pPr><w:ind w:left="' . (720 + 360 * $nivel) . '" w:hanging="360"/></w:pPr></w:lvl>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:numbering xmlns:w="' . self::W . '">'
            . '<w:abstractNum w:abstractNumId="0"><w:multiLevelType w:val="hybridMultilevel"/>' . $niveis . '</w:abstractNum>'
            . '<w:num w:numId="' . self::BULLET_NUM_ID . '"><w:abstractNumId w:val="0"/></w:num>'
            . '</w:numbering>';
    }
}
