<?php

namespace App\Services\Signature;

use App\Models\SignatureTemplate;
use App\Support\DocxWriter;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * O caminho de volta do `DocxTemplateImporter`: o texto do modelo em Word
 * (.docx), para baixar, editar no Word e enviar de novo em "Revisar".
 *
 * O modelo não guarda o Word que o originou (o importador converte e
 * descarta — ver lá o porquê), e um modelo escrito direto na tela nunca teve
 * Word. Por isso o arquivo é GERADO do `body_html` da versão pedida: é sempre
 * o texto que está valendo, e não o rascunho de quem enviou da última vez.
 *
 * O que importa é a volta. Os marcadores saem como o importador os lê:
 *  - campo `[[chave]]` sai `[[Rótulo]]` quando o rótulo dá a mesma chave na
 *    reimportação (`DocxTemplateImporter::keyFor`), senão `[[chave]]` mesmo.
 *    A tela casa os campos importados com os do formulário PELA CHAVE, então
 *    tipo, pergunta e opções sobrevivem à volta;
 *  - `[[assinatura:parte]]` sai `[[assinatura: Rótulo da parte]]` (mesma regra)
 *    e sempre sozinho na linha, que é como o importador o reconhece;
 *  - títulos usam os estilos "heading N" e listas com marcador usam numeração
 *    do Word — o importador lê os dois assim.
 * Lista numerada sai com o número como texto, que é o que o importador
 * produziria de qualquer forma (ver o comentário em `paragraph()` de lá).
 */
class DocxTemplateExporter
{
    /** Recuo do <blockquote>, em twips. */
    private const QUOTE_INDENT = 720;

    /** @var array<int, string> runs do parágrafo em curso */
    private array $runs = [];

    private string $xml = '';

    /** @var array<string, string> chave do campo => texto entre os colchetes */
    private array $fieldMarkers = [];

    /** @var array<string, string> chave da parte => texto depois de "assinatura: " */
    private array $partyMarkers = [];

    private string $placeholder = '[[assinatura]]';

    public function export(SignatureTemplate $template): string
    {
        $this->runs = [];
        $this->xml = '';
        $this->placeholder = $template->signature_placeholder ?: '[[assinatura]]';
        $this->fieldMarkers = $this->markers($template->declaredVariables());
        $this->partyMarkers = $this->markers($template->declaredParties());

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="r">' . ($template->body_html ?? '') . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        if ($raiz = $dom->getElementById('r')) {
            $this->walk($raiz, [], []);
        }

        $this->flush([]);

        return DocxWriter::package($this->xml !== '' ? $this->xml : DocxWriter::paragraph([]), headings: true, bullets: true);
    }

    /**
     * Texto entre colchetes de cada chave: o rótulo, se ele voltar à mesma
     * chave; senão a própria chave.
     *
     * @param  array<int, array<string, mixed>>  $declarados
     * @return array<string, string>
     */
    private function markers(array $declarados): array
    {
        $marcadores = [];

        foreach ($declarados as $item) {
            $chave = (string) ($item['key'] ?? '');
            $rotulo = trim((string) ($item['label'] ?? ''));

            if ($chave === '') {
                continue;
            }

            $marcadores[$chave] = $rotulo !== ''
                && !preg_match('/[\[\]\n]/', $rotulo)
                && DocxTemplateImporter::keyFor($rotulo) === $chave
                ? $rotulo
                : $chave;
        }

        return $marcadores;
    }

    /**
     * @param  array{b?: bool, i?: bool, u?: bool}  $fmt
     * @param  array<string, mixed>  $popts  opções do parágrafo que se fecha aqui dentro
     */
    private function walk(DOMNode $no, array $fmt, array $popts, int $nivelLista = 0): void
    {
        foreach ($no->childNodes as $filho) {
            if ($filho instanceof DOMText) {
                $this->text($filho->wholeText, $fmt, $popts);

                continue;
            }

            if (!$filho instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($filho->tagName);

            switch ($tag) {
                case 'strong':
                case 'b':
                case 'th':
                    $this->walk($filho, ['b' => true] + $fmt, $popts, $nivelLista);
                    break;
                case 'em':
                case 'i':
                    $this->walk($filho, ['i' => true] + $fmt, $popts, $nivelLista);
                    break;
                case 'u':
                    $this->walk($filho, ['u' => true] + $fmt, $popts, $nivelLista);
                    break;
                case 'br':
                    $this->runs[] = DocxWriter::lineBreak();
                    break;
                case 'p':
                case 'div':
                    $this->flush($popts);
                    $this->walk($filho, $fmt, $popts, $nivelLista);
                    $this->flush($popts);
                    break;
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                    // <h2> é o título 1 do Word: o importador soma um (o <h1> é o título do documento).
                    $this->flush($popts);
                    $this->walk($filho, $fmt, ['style' => 'Heading' . max(1, (int) $tag[1] - 1)], $nivelLista);
                    $this->flush(['style' => 'Heading' . max(1, (int) $tag[1] - 1)]);
                    break;
                case 'ul':
                case 'ol':
                    $this->flush($popts);
                    $this->list($filho, $tag === 'ol', $fmt, $nivelLista);
                    break;
                case 'blockquote':
                    $this->flush($popts);
                    $recuo = ['left' => ($popts['left'] ?? 0) + self::QUOTE_INDENT] + $popts;
                    $this->walk($filho, $fmt, $recuo, $nivelLista);
                    $this->flush($recuo);
                    break;
                case 'hr':
                    $this->flush($popts);
                    $this->xml .= DocxWriter::paragraph([], ['border' => true]);
                    break;
                case 'table':
                    $this->flush($popts);
                    $this->xml .= $this->table($filho);
                    break;
                default:
                    // span, thead/tbody fora de tabela, e o que mais o saneador deixar.
                    $this->walk($filho, $fmt, $popts, $nivelLista);
            }
        }
    }

    /** @param  array{b?: bool, i?: bool, u?: bool}  $fmt */
    private function list(DOMElement $lista, bool $numerada, array $fmt, int $nivel): void
    {
        $n = 0;

        foreach ($lista->childNodes as $li) {
            if (!$li instanceof DOMElement || strtolower($li->tagName) !== 'li') {
                continue;
            }

            $n++;
            $opts = $numerada
                ? ['left' => 360 * ($nivel + 1), 'hanging' => 360]
                : ['bullet' => min($nivel, 2)];

            if ($numerada) {
                $this->runs[] = DocxWriter::run($n . ".\t", $fmt);
            }

            $this->walk($li, $fmt, $opts, $nivel + 1);
            $this->flush($opts);
        }
    }

    private function table(DOMElement $tabela): string
    {
        $linhas = [];

        foreach ($tabela->getElementsByTagName('tr') as $tr) {
            // Só as linhas DESTA tabela: getElementsByTagName desce nas aninhadas.
            if ($this->closestTable($tr) !== $tabela) {
                continue;
            }

            $celulas = [];

            foreach ($tr->childNodes as $td) {
                if (!$td instanceof DOMElement || !in_array(strtolower($td->tagName), ['td', 'th'], true)) {
                    continue;
                }

                // A célula é montada à parte e o parágrafo em curso fica guardado.
                [$xml, $runs] = [$this->xml, $this->runs];
                $this->xml = '';
                $this->runs = [];

                $this->walk($td, strtolower($td->tagName) === 'th' ? ['b' => true] : [], ['jc' => 'left']);
                $this->flush(['jc' => 'left']);

                $celulas[] = $this->xml;
                [$this->xml, $this->runs] = [$xml, $runs];
            }

            if ($celulas !== []) {
                $linhas[] = $celulas;
            }
        }

        return $linhas === [] ? '' : DocxWriter::table($linhas);
    }

    private function closestTable(DOMNode $no): ?DOMElement
    {
        for ($pai = $no->parentNode; $pai; $pai = $pai->parentNode) {
            if ($pai instanceof DOMElement && strtolower($pai->tagName) === 'table') {
                return $pai;
            }
        }

        return null;
    }

    /**
     * Texto corrido, com os marcadores reescritos. O de assinatura sai num
     * parágrafo só dele.
     *
     * @param  array{b?: bool, i?: bool, u?: bool}  $fmt
     * @param  array<string, mixed>  $popts
     */
    private function text(string $texto, array $fmt, array $popts): void
    {
        $texto = (string) preg_replace('/\s+/u', ' ', $texto);

        if (trim($texto) === '' && $this->runs === []) {
            return;
        }

        $pedacos = preg_split('/(\[\[[^\[\]\n]{1,120}?\]\])/u', $texto, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        foreach ($pedacos as $pedaco) {
            $assinatura = $this->signatureMarker($pedaco);

            if ($assinatura !== null) {
                $this->flush($popts);
                $this->xml .= DocxWriter::paragraph([DocxWriter::run($assinatura)], ['jc' => 'left']);

                continue;
            }

            if (preg_match('/^\[\[([a-z][a-z0-9_]{0,59})\]\]$/', $pedaco, $m)) {
                $pedaco = '[[' . ($this->fieldMarkers[$m[1]] ?? $m[1]) . ']]';
            }

            // Espaço que sobrou entre um marcador de assinatura e o bloco seguinte.
            if ($this->runs === [] && trim($pedaco) === '') {
                continue;
            }

            $this->runs[] = DocxWriter::run($this->runs === [] ? ltrim($pedaco) : $pedaco, $fmt);
        }
    }

    /** O marcador de assinatura como vai para o Word; null se não é um. */
    private function signatureMarker(string $pedaco): ?string
    {
        if ($pedaco === $this->placeholder || $pedaco === '[[' . DocxTemplateImporter::SIGNATURE_KEY . ']]') {
            return $pedaco;
        }

        if (preg_match('/^\[\[' . DocxTemplateImporter::SIGNATURE_KEY . ':([a-z][a-z0-9_]{0,59})\]\]$/', $pedaco, $m)) {
            return '[[' . DocxTemplateImporter::SIGNATURE_KEY . ': ' . ($this->partyMarkers[$m[1]] ?? $m[1]) . ']]';
        }

        return null;
    }

    /** @param  array<string, mixed>  $opts */
    private function flush(array $opts): void
    {
        if ($this->runs === []) {
            return;
        }

        $this->xml .= DocxWriter::paragraph($this->runs, $opts);
        $this->runs = [];
    }
}
