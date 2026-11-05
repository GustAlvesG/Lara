<?php

namespace App\Services\Signature\Contracts;

use App\Models\SignatureContract;
use App\Models\SignatureLayout;
use App\Services\Signature\SignatureLayoutDocx;
use App\Support\DocxWriter;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * O contrato em Word (.docx) — para mandar ao fornecedor revisar antes da
 * aprovação. Sai dos mesmos `blocks()` da prévia e do documento de
 * assinatura: o Word nunca diz uma coisa e o Lara outra.
 *
 * O XML sai do `App\Support\DocxWriter` (sem biblioteca): o texto de
 * cláusula só tem parágrafo, negrito, itálico, sublinhado, quebra e lista
 * (a allow-list do editor), e a numeração já vem calculada — não precisa da
 * numeração automática do Word. Sem dependência nova no deploy.
 *
 * O Word é cópia de trabalho: a validade é do documento assinado no Lara.
 *
 * Sai no papel timbrado do Clube (desde 04/11/2026): cabeçalho e rodapé
 * vigentes em "Papel timbrado" (`SignatureLayoutDocx`), os mesmos do PDF.
 */
class ContractDocxExporter
{
    public function __construct(
        private ContractRenderer $renderer,
        private SignatureLayoutDocx $letterhead,
    ) {
    }

    public function export(SignatureContract $contract): string
    {
        $corpo = '';

        foreach ($this->renderer->blocks($contract->content ?? [], $contract->number) as $bloco) {
            $corpo .= match ($bloco['type']) {
                'heading' => DocxWriter::paragraph([DocxWriter::run($bloco['text'], ['b' => true])], ['jc' => 'right', 'left' => 3900]),
                'number' => DocxWriter::paragraph([DocxWriter::run($bloco['text'], ['b' => true])]),
                'html' => $this->fromHtml($bloco['html']),
                'consideration' => $this->fromHtml($bloco['html'], $bloco['label'] . ' ', 500),
                'clause' => DocxWriter::paragraph([DocxWriter::run($bloco['text'], ['b' => true])], ['before' => 240]),
                'item' => $this->fromHtml($bloco['html'], $bloco['number'] . ' - ', $bloco['level'] === 2 ? 400 : 0),
                'closing' => DocxWriter::paragraph([DocxWriter::run($bloco['text'])], ['before' => 240]),
                'signatures' => $this->signatures($bloco['entries']),
                default => '',
            };
        }

        // Sem o título "CTL — título" no cabeçalho de texto (05/11/2026): o cabeçalho do contrato basta.
        return DocxWriter::package($corpo, pages: $this->letterhead->pages(SignatureLayout::current(), ''));
    }

    /**
     * Linhas de assinatura em branco: no Word ninguém assina — é só para a
     * leitura do texto completo.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function signatures(array $entries): string
    {
        $testemunha = fn(array $s) => $s['party'] === ContractContent::SIGNER_TESTEMUNHA;
        $partes = array_values(array_filter($entries, fn($s) => !$testemunha($s)));
        $testemunhas = array_values(array_filter($entries, $testemunha));

        $xml = $this->signatureGrid($partes);

        if ($testemunhas !== []) {
            $xml .= DocxWriter::paragraph([DocxWriter::run('Testemunhas:', ['b' => true])], ['before' => 360, 'keepNext' => true]);
            $xml .= $this->signatureGrid($testemunhas);
        }

        return $xml;
    }

    /**
     * Assinaturas em duas colunas (05/11/2026), como no PDF: tabela sem borda,
     * linha da tabela inteira na mesma folha — a linha "____", o nome e o cargo
     * nunca se separam entre páginas.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function signatureGrid(array $entries): string
    {
        if ($entries === []) {
            return '';
        }

        $celula = function (array $s): string {
            $temCargo = $s['cargo'] !== '';
            $xml = DocxWriter::paragraph([DocxWriter::run('______________________________')], ['before' => 480, 'jc' => 'left', 'keepNext' => true]);
            // Nome em maiúsculas e só o cargo embaixo, como na prévia e no PDF (05/11/2026).
            $xml .= DocxWriter::paragraph([DocxWriter::run($s['name'] !== '' ? mb_strtoupper($s['name']) : '(nome a informar)', ['b' => true])], ['jc' => 'left', 'keepNext' => $temCargo]);

            if ($temCargo) {
                $xml .= DocxWriter::paragraph([DocxWriter::run($s['cargo'])], ['jc' => 'left']);
            }

            return $xml;
        };

        $linhas = array_map(
            fn(array $par) => [$celula($par[0]), isset($par[1]) ? $celula($par[1]) : ''],
            array_chunk($entries, 2),
        );

        return DocxWriter::table($linhas, layout: true);
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
                        $atual[] = DocxWriter::run($texto, $fmt);
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
                        $atual[] = DocxWriter::lineBreak();
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
                array_unshift($runs, DocxWriter::run($p['bullet'] . "\t"));
            }

            if ($i === 0 && $prefix !== '') {
                array_unshift($runs, DocxWriter::run($prefix, ['b' => true]));
            }

            $xml .= DocxWriter::paragraph($runs, ['left' => $p['indent'], 'hanging' => $p['bullet'] !== null ? 300 : 0]);
        }

        return $xml;
    }
}
