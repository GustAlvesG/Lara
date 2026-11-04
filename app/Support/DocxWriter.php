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
 * estilo do Word, lista com marcador de verdade e — para o papel timbrado do
 * contrato (`SignatureLayoutDocx`) — cabeçalho e rodapé com imagem — o importador
 * (`DocxTemplateImporter`) reconhece título pelo NOME do estilo e marcador pela
 * numeração, e é por isso que eles saem como o Word os grava.
 */
final class DocxWriter
{
    public const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    public const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    public const WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

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

    /**
     * @param  array{b?: bool, i?: bool, u?: bool, color?: string, sz?: int}  $fmt
     *     `color` = hex sem `#`; `sz` = tamanho em meios-pontos (16 = 8 pt).
     */
    public static function run(string $texto, array $fmt = []): string
    {
        // Ordem do esquema dentro de <w:rPr>: b, i, color, sz, u.
        $pr = (!empty($fmt['b']) ? '<w:b/>' : '') . (!empty($fmt['i']) ? '<w:i/>' : '')
            . (!empty($fmt['color']) ? '<w:color w:val="' . preg_replace('/[^0-9A-Fa-f]/', '', $fmt['color']) . '"/>' : '')
            . (!empty($fmt['sz']) ? '<w:sz w:val="' . (int) $fmt['sz'] . '"/><w:szCs w:val="' . (int) $fmt['sz'] . '"/>' : '')
            . (!empty($fmt['u']) ? '<w:u w:val="single"/>' : '');

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

    /** EMU (unidade do DrawingML) por pixel CSS a 96 dpi. */
    public const EMU_PER_PX = 9525;

    /** Twips (1/20 de ponto, a unidade das medidas da página) por pixel CSS. */
    public const TWIPS_PER_PX = 15;

    /** A4 em twips. */
    public const PAGE_WIDTH = 11906;
    public const PAGE_HEIGHT = 16838;

    /**
     * Uma imagem, como run. `$relId` é o da relação na PARTE onde ela entra
     * (cabeçalho e rodapé têm cada um o seu .rels).
     *
     * `$anchor` null = em linha, segue o alinhamento do parágrafo. `top` ou
     * `bottom` = presa à borda de cima/de baixo do PAPEL, a partir da esquerda,
     * atrás do texto — é como o papel timbrado "de borda a borda" sai da margem.
     */
    public static function picture(string $relId, int $docPrId, int $widthPx, int $heightPx, ?string $anchor = null): string
    {
        $cx = max(1, $widthPx) * self::EMU_PER_PX;
        $cy = max(1, $heightPx) * self::EMU_PER_PX;
        $nome = 'Imagem ' . $docPrId;

        $grafico = '<wp:docPr id="' . $docPrId . '" name="' . $nome . '"/>'
            . '<wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:nvPicPr><pic:cNvPr id="0" name="' . $nome . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . htmlspecialchars($relId, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic>';

        $medida = '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:effectExtent l="0" t="0" r="0" b="0"/>';

        if ($anchor === null) {
            $desenho = '<wp:inline distT="0" distB="0" distL="0" distR="0">' . $medida . $grafico . '</wp:inline>';
        } else {
            $vertical = $anchor === 'bottom' ? '<wp:align>bottom</wp:align>' : '<wp:posOffset>0</wp:posOffset>';

            // Ordem do esquema: simplePos, positionH, positionV, extent,
            // effectExtent, wrap, docPr, cNvGraphicFramePr, graphic.
            $desenho = '<wp:anchor distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="251658240"'
                . ' behindDoc="1" locked="1" layoutInCell="1" allowOverlap="1">'
                . '<wp:simplePos x="0" y="0"/>'
                . '<wp:positionH relativeFrom="page"><wp:posOffset>0</wp:posOffset></wp:positionH>'
                . '<wp:positionV relativeFrom="page">' . $vertical . '</wp:positionV>'
                . $medida . '<wp:wrapNone/>' . $grafico . '</wp:anchor>';
        }

        return '<w:r><w:drawing>' . $desenho . '</w:drawing></w:r>';
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
     * @param  array{
     *     header?: array{xml: string, media?: array<string, array{bytes: string, ext: string}>},
     *     footer?: array{xml: string, media?: array<string, array{bytes: string, ext: string}>},
     *     margins?: array{top?: int, bottom?: int, header?: int, footer?: int},
     * }|null  $pages  cabeçalho e rodapé de toda página: `xml` = os parágrafos,
     *     `media` = as imagens por id de relação (o mesmo de `picture()`), e as margens em twips.
     */
    public static function package(string $corpo, bool $headings = false, bool $bullets = false, ?array $pages = null): string
    {
        $margens = ($pages['margins'] ?? []) + ['top' => 1418, 'bottom' => 1418, 'header' => 709, 'footer' => 709];
        $partes = array_filter(['header' => $pages['header'] ?? null, 'footer' => $pages['footer'] ?? null]);

        $refs = '';

        foreach (array_keys($partes) as $tipo) {
            $refs .= '<w:' . $tipo . 'Reference w:type="default" r:id="rId' . $tipo . '"/>';
        }

        $documento = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="' . self::W . '" xmlns:r="' . self::R . '"><w:body>' . $corpo
            . '<w:sectPr>' . $refs . '<w:pgSz w:w="' . self::PAGE_WIDTH . '" w:h="' . self::PAGE_HEIGHT . '"/>'
            . '<w:pgMar w:top="' . (int) $margens['top'] . '" w:right="1134" w:bottom="' . (int) $margens['bottom'] . '" w:left="1418"'
            . ' w:header="' . (int) $margens['header'] . '" w:footer="' . (int) $margens['footer'] . '" w:gutter="0"/>'
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
            . (isset($partes['header']) ? '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>' : '')
            . (isset($partes['footer']) ? '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>' : '')
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Default Extension="jpeg" ContentType="image/jpeg"/>'
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';

        $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . ($bullets ? '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>' : '')
            . (isset($partes['header']) ? '<Relationship Id="rIdheader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>' : '')
            . (isset($partes['footer']) ? '<Relationship Id="rIdfooter" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>' : '')
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

        foreach ($partes as $tipo => $parte) {
            $raiz = $tipo === 'header' ? 'hdr' : 'ftr';
            $relacoes = '';

            foreach ($parte['media'] ?? [] as $relId => $midia) {
                $ext = $midia['ext'] === 'png' ? 'png' : 'jpeg';
                $nome = $tipo . '-' . preg_replace('/[^A-Za-z0-9]/', '', $relId) . '.' . $ext;
                $zip->addFromString('word/media/' . $nome, $midia['bytes']);
                $relacoes .= '<Relationship Id="' . htmlspecialchars($relId, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"'
                    . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/' . $nome . '"/>';
            }

            // Parte sem parágrafo é XML inválido para o Word.
            $zip->addFromString('word/' . $tipo . '1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<w:' . $raiz . ' xmlns:w="' . self::W . '" xmlns:r="' . self::R . '" xmlns:wp="' . self::WP . '">'
                . ($parte['xml'] !== '' ? $parte['xml'] : '<w:p/>') . '</w:' . $raiz . '>');

            if ($relacoes !== '') {
                $zip->addFromString('word/_rels/' . $tipo . '1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relacoes . '</Relationships>');
            }
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
