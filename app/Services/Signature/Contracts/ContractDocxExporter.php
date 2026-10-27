<?php

namespace App\Services\Signature\Contracts;

use App\Models\SignatureContract;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use RuntimeException;
use ZipArchive;

/**
 * O contrato em Word (.docx) — para mandar ao fornecedor revisar antes da
 * aprovação. Sai dos mesmos `blocks()` da prévia e do documento de
 * assinatura: o Word nunca diz uma coisa e o Lara outra.
 *
 * Um gerador mínimo de WordprocessingML em vez de uma biblioteca: o texto de
 * cláusula só tem parágrafo, negrito, itálico, sublinhado, quebra e lista
 * (a allow-list do editor), e a numeração já vem calculada — não precisa da
 * numeração automática do Word. Sem dependência nova no deploy.
 *
 * O Word é cópia de trabalho: a validade é do documento assinado no Lara.
 */
class ContractDocxExporter
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function __construct(private ContractRenderer $renderer)
    {
    }

    public function export(SignatureContract $contract): string
    {
        $corpo = '';

        foreach ($this->renderer->blocks($contract->content ?? [], $contract->number) as $bloco) {
            $corpo .= match ($bloco['type']) {
                'heading' => $this->paragraph([$this->run($bloco['text'], ['b' => true])], ['jc' => 'right', 'left' => 3900]),
                'number' => $this->paragraph([$this->run($bloco['text'], ['b' => true])]),
                'html' => $this->fromHtml($bloco['html']),
                'consideration' => $this->fromHtml($bloco['html'], $bloco['label'] . ' ', 500),
                'clause' => $this->paragraph([$this->run($bloco['text'], ['b' => true])], ['before' => 240]),
                'item' => $this->fromHtml($bloco['html'], $bloco['number'] . ' - ', $bloco['level'] === 2 ? 400 : 0),
                'closing' => $this->paragraph([$this->run($bloco['text'])], ['before' => 240]),
                'signatures' => $this->signatures($bloco['entries']),
                default => '',
            };
        }

        return $this->package($corpo);
    }

    /**
     * Linhas de assinatura em branco: no Word ninguém assina — é só para a
     * leitura do texto completo.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function signatures(array $entries): string
    {
        $xml = '';
        $testemunhas = false;

        foreach ($entries as $s) {
            if ($s['party'] === ContractContent::SIGNER_TESTEMUNHA && !$testemunhas) {
                $testemunhas = true;
                $xml .= $this->paragraph([$this->run('Testemunhas:', ['b' => true])], ['before' => 360]);
            }

            $xml .= $this->paragraph([$this->run('________________________________________')], ['before' => 480, 'jc' => 'left']);
            $xml .= $this->paragraph([$this->run($s['name'] !== '' ? $s['name'] : '(nome a informar)', ['b' => true])], ['jc' => 'left']);
            $xml .= $this->paragraph([$this->run($s['label'])], ['jc' => 'left']);
        }

        return $xml;
    }

    /**
     * HTML saneado do contrato → parágrafos do Word. `$prefix` (o número do
     * item) entra em negrito no começo do primeiro parágrafo.
     */
    private function fromHtml(string $html, string $prefix = '', int $indent = 0): string
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="r">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $raiz = $dom->getElementById('r');
        $paragrafos = [];
        $atual = [];

        $fecha = function (int $recuo = 0, ?string $marcador = null) use (&$paragrafos, &$atual, $indent) {
            if ($atual !== [] || $marcador !== null) {
                $paragrafos[] = ['runs' => $atual, 'indent' => $indent + $recuo, 'bullet' => $marcador];
            }

            $atual = [];
        };

        $anda = function (DOMNode $no, array $fmt) use (&$anda, &$atual, $fecha) {
            foreach ($no->childNodes as $filho) {
                if ($filho instanceof DOMText) {
                    $texto = (string) preg_replace('/\s+/u', ' ', $filho->wholeText);

                    if ($texto !== '' && trim($texto) !== '' || ($texto === ' ' && $atual !== [])) {
                        $atual[] = $this->run($texto, $fmt);
                    }

                    continue;
                }

                if (!$filho instanceof DOMElement) {
                    continue;
                }

                $tag = strtolower($filho->tagName);

                switch ($tag) {
                    case 'strong':
                    case 'b':
                        $anda($filho, ['b' => true] + $fmt);
                        break;
                    case 'em':
                    case 'i':
                        $anda($filho, ['i' => true] + $fmt);
                        break;
                    case 'u':
                        $anda($filho, ['u' => true] + $fmt);
                        break;
                    case 'br':
                        $atual[] = '<w:r><w:br/></w:r>';
                        break;
                    case 'p':
                    case 'div':
                        $fecha();
                        $anda($filho, $fmt);
                        $fecha();
                        break;
                    case 'ul':
                    case 'ol':
                        $fecha();
                        $n = 0;

                        foreach ($filho->childNodes as $li) {
                            if ($li instanceof DOMElement && strtolower($li->tagName) === 'li') {
                                $n++;
                                $anda($li, $fmt);
                                $fecha(360, $tag === 'ol' ? $n . '.' : '•');
                            }
                        }
                        break;
                    default:
                        $anda($filho, $fmt);
                }
            }
        };

        if ($raiz) {
            $anda($raiz, []);
        }

        $fecha();

        if ($paragrafos === []) {
            $paragrafos[] = ['runs' => [], 'indent' => $indent, 'bullet' => null];
        }

        $xml = '';

        foreach ($paragrafos as $i => $p) {
            $runs = $p['runs'];

            if ($p['bullet'] !== null) {
                array_unshift($runs, $this->run($p['bullet'] . "\t"));
            }

            if ($i === 0 && $prefix !== '') {
                array_unshift($runs, $this->run($prefix, ['b' => true]));
            }

            $xml .= $this->paragraph($runs, ['left' => $p['indent'], 'hanging' => $p['bullet'] !== null ? 300 : 0]);
        }

        return $xml;
    }

    /**
     * @param  array<int, string>  $runs
     * @param  array{jc?: string, left?: int, hanging?: int, before?: int}  $opts
     */
    private function paragraph(array $runs, array $opts = []): string
    {
        $pr = '<w:pPr><w:spacing w:before="' . ($opts['before'] ?? 0) . '" w:after="120"/>';

        if (($opts['left'] ?? 0) > 0) {
            $pr .= '<w:ind w:left="' . (int) $opts['left'] . '"'
                . (($opts['hanging'] ?? 0) > 0 ? ' w:hanging="' . (int) $opts['hanging'] . '"' : '') . '/>';
        }

        $pr .= '<w:jc w:val="' . ($opts['jc'] ?? 'both') . '"/></w:pPr>';

        return '<w:p>' . $pr . implode('', $runs) . '</w:p>';
    }

    /** @param  array{b?: bool, i?: bool, u?: bool}  $fmt */
    private function run(string $texto, array $fmt = []): string
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

    private function package(string $corpo): string
    {
        $documento = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="' . self::W . '"><w:body>' . $corpo
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1418" w:right="1134" w:bottom="1418" w:left="1418" w:header="709" w:footer="709" w:gutter="0"/>'
            . '</w:sectPr></w:body></w:document>';

        $estilos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="' . self::W . '"><w:docDefaults><w:rPrDefault><w:rPr>'
            . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:sz w:val="22"/><w:lang w:val="pt-BR"/>'
            . '</w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:line="300" w:lineRule="auto"/></w:pPr></w:pPrDefault>'
            . '</w:docDefaults></w:styles>';

        $tipos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';

        $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
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
        $zip->close();

        $bytes = (string) file_get_contents($arquivo);
        @unlink($arquivo);

        return $bytes;
    }
}
