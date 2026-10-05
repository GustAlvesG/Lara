<?php

namespace App\Services\Signature;

use App\Exceptions\DocxImportException;
use App\Support\HtmlSanitizer;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Transforma um documento do Word (.docx) num modelo de assinatura.
 *
 * Existe para que escrever um modelo não exija HTML: quem redige o termo
 * continua no Word, marca `[[Data do evento]]` onde entra um dado do
 * atendimento e `[[assinatura]]` onde a pessoa assina, e envia o arquivo.
 *
 * O .docx é CONVERTIDO para o mesmo `body_html` de sempre, e não guardado como
 * fonte: o tablet exibe HTML, o congelamento grava HTML e o PDF sai do dompdf.
 * Manter o Word como origem exigiria um conversor de escritório no servidor e
 * um segundo caminho de renderização — a divergência que o
 * SignatureDocumentRenderer existe para impedir.
 *
 * O que atravessa a conversão é a ESTRUTURA do texto: parágrafos, títulos,
 * negrito/itálico/sublinhado, marcadores, numeração e tabelas. Fonte, cor,
 * alinhamento, cabeçalho, rodapé e imagens ficam de fora — a aparência é a do
 * documento do clube, a mesma para todos os modelos.
 *
 * Sem biblioteca: um .docx é um zip com XML, e `zip` e `dom` já são exigidos
 * pelo projeto.
 */
class DocxTemplateImporter
{
    /** A chave reservada do marcador de assinatura. */
    public const SIGNATURE_KEY = 'assinatura';

    /**
     * Teto do XML descompactado. Um termo de dezenas de páginas não passa de
     * algumas centenas de KB; acima disso é arquivo errado ou zip-bomba.
     */
    private const MAX_XML_BYTES = 8 * 1024 * 1024;

    private const BOLD = 1;
    private const ITALIC = 2;
    private const UNDERLINE = 4;

    /** @var array<string, int> id do estilo => nível do título em HTML */
    private array $headingStyles = [];

    /**
     * Estilos que carregam numeração própria — é como o Word numera títulos
     * ("1. Objeto") e os estilos de lista prontos.
     *
     * @var array<string, array{numId: string, ilvl: int|null}>
     */
    private array $styleNumbering = [];

    /** @var array<string, array<int, array{fmt: string, text: string, start: int, style: string|null}>> */
    private array $numbering = [];

    /** @var array<string, array<int, int>> contadores correntes, por lista */
    private array $counters = [];

    /** @var array<string, string> chave => rótulo, na ordem em que aparecem */
    private array $variables = [];

    /** @var array<string, string> partes que assinam: chave => rótulo */
    private array $parties = [];

    /** @var array<string, string> */
    private array $warnings = [];

    private bool $hasSignature = false;

    /**
     * @return array{
     *     html: string,
     *     variables: array<int, array{key: string, label: string, required: bool, type: string}>,
     *     has_signature: bool,
     *     warnings: array<int, string>
     * }
     */
    public function import(string $path): array
    {
        $this->headingStyles = [];
        $this->styleNumbering = [];
        $this->numbering = [];
        $this->counters = [];
        $this->variables = [];
        $this->parties = [];
        $this->warnings = [];
        $this->hasSignature = false;

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new DocxImportException($this->notDocxMessage());
        }

        try {
            $documento = $this->part($zip, 'word/document.xml');

            if ($documento === null) {
                throw new DocxImportException($this->notDocxMessage());
            }

            $this->readStyles($this->part($zip, 'word/styles.xml'));
            $this->readNumbering($this->part($zip, 'word/numbering.xml'));
        } finally {
            $zip->close();
        }

        $body = $this->child($documento->documentElement, 'body');

        $html = $body ? $this->blocks($body) : '';

        // Saneado aqui e de novo na gravação: a prévia da tela já mostra
        // exatamente o que vai ser gravado.
        $html = HtmlSanitizer::clean($html, SignatureDocumentRenderer::EXTRA_HTML_TAGS);

        if ($html === '') {
            throw new DocxImportException('O documento não tem texto. Confira se enviou o arquivo certo.');
        }

        $variaveis = [];

        foreach ($this->variables as $chave => $rotulo) {
            // Obrigatório por padrão: um campo escrito no texto e deixado em
            // branco vira um buraco no documento assinado.
            $variaveis[] = [
                'key' => $chave,
                'label' => $rotulo,
                'required' => true,
                // Palpite pelo nome do campo ("CPF", "Data do evento"); a tela
                // mostra e deixa trocar.
                'type' => SignatureFieldTypes::guess($rotulo),
            ];
        }

        return [
            'html' => $html,
            'variables' => $variaveis,
            // `[[assinatura: Contratante]]` no Word: quem assina, e onde.
            'parties' => array_map(
                fn(string $chave, string $rotulo) => ['key' => $chave, 'label' => $rotulo],
                array_keys($this->parties),
                array_values($this->parties),
            ),
            'has_signature' => $this->hasSignature,
            'warnings' => array_values($this->warnings),
        ];
    }

    /**
     * A chave de uma variável a partir do que a pessoa escreveu entre os
     * colchetes: `Data do evento` vira `data_do_evento`. Vazio quando não
     * sobra nada aproveitável.
     */
    public static function keyFor(string $raw): string
    {
        $chave = strtolower(Str::ascii($raw));
        $chave = trim((string) preg_replace('/[^a-z0-9]+/', '_', $chave), '_');

        if ($chave === '') {
            return '';
        }

        // A chave começa por letra — é a regra do StoreSignatureTemplateRequest.
        if (!ctype_alpha($chave[0])) {
            $chave = 'campo_' . $chave;
        }

        return rtrim(substr($chave, 0, 60), '_');
    }

    /**
     * O rótulo mostrado ao atendente. O que a pessoa escreveu no Word já é o
     * rótulo; só uma chave "crua" (`data_evento`) é que precisa virar texto.
     */
    public static function labelFor(string $raw): string
    {
        $rotulo = trim((string) preg_replace('/\s+/u', ' ', $raw));

        if (preg_match('/^[a-z0-9_]+$/', $rotulo)) {
            $rotulo = ucfirst(trim(str_replace('_', ' ', $rotulo)));
        }

        return mb_substr($rotulo, 0, 120);
    }

    private function notDocxMessage(): string
    {
        return 'O arquivo não é um documento do Word (.docx). Se ele é um .doc antigo ou um PDF, '
            . 'abra no Word e use Arquivo → Salvar como → Documento do Word (.docx).';
    }

    /** Uma parte do pacote, já como XML. Null quando o pacote não a tem. */
    private function part(ZipArchive $zip, string $name): ?DOMDocument
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            return null;
        }

        if ($stat['size'] > self::MAX_XML_BYTES) {
            throw new DocxImportException('O documento é grande demais para virar um modelo.');
        }

        $xml = $zip->getFromName($name);

        // Um .docx legítimo nunca traz DOCTYPE; recusar fecha a porta de
        // entidade externa e de expansão de entidades de uma vez.
        if ($xml === false || stripos($xml, '<!DOCTYPE') !== false) {
            throw new DocxImportException($this->notDocxMessage());
        }

        $dom = new DOMDocument();

        $anterior = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        if (!$ok || !$dom->documentElement) {
            throw new DocxImportException($this->notDocxMessage());
        }

        return $dom;
    }

    /**
     * Quais estilos são título. O Word grava o id do estilo conforme o idioma
     * da instalação (`Heading1`, `Ttulo1`), então quem decide é o NOME.
     */
    private function readStyles(?DOMDocument $styles): void
    {
        if (!$styles) {
            return;
        }

        foreach ($this->children($styles->documentElement, 'style') as $style) {
            $id = $this->attr($style, 'styleId');
            $nome = $this->val($this->child($style, 'name')) ?? '';

            if ($id === null) {
                continue;
            }

            $pPr = $this->child($style, 'pPr');
            $numPr = $pPr ? $this->child($pPr, 'numPr') : null;

            if ($numPr && ($numId = $this->val($this->child($numPr, 'numId'))) !== null) {
                $nivel = $this->val($this->child($numPr, 'ilvl'));

                $this->styleNumbering[$id] = ['numId' => $numId, 'ilvl' => $nivel !== null ? (int) $nivel : null];
            }

            if (preg_match('/^(heading|t[ií]tulo)\s*([1-9])$/iu', $nome, $m)) {
                // O <h1> é o título do documento, impresso pelo renderer.
                $this->headingStyles[$id] = min(((int) $m[2]) + 1, 4);
            } elseif (preg_match('/^(title|t[ií]tulo)$/iu', $nome)) {
                $this->headingStyles[$id] = 2;
            }
        }
    }

    private function readNumbering(?DOMDocument $numbering): void
    {
        if (!$numbering) {
            return;
        }

        $abstratos = [];

        foreach ($this->children($numbering->documentElement, 'abstractNum') as $abstrato) {
            $niveis = [];

            foreach ($this->children($abstrato, 'lvl') as $lvl) {
                $niveis[(int) $this->attr($lvl, 'ilvl')] = [
                    'fmt' => $this->val($this->child($lvl, 'numFmt')) ?? 'decimal',
                    'text' => $this->val($this->child($lvl, 'lvlText')) ?? '',
                    'start' => (int) ($this->val($this->child($lvl, 'start')) ?? 1),
                    // Lista multinível ligada a estilos: é o nível que diz
                    // qual estilo ele numera, e não o contrário.
                    'style' => $this->val($this->child($lvl, 'pStyle')),
                ];
            }

            $abstratos[(string) $this->attr($abstrato, 'abstractNumId')] = $niveis;
        }

        foreach ($this->children($numbering->documentElement, 'num') as $num) {
            $niveis = $abstratos[(string) $this->val($this->child($num, 'abstractNumId'))] ?? [];

            foreach ($this->children($num, 'lvlOverride') as $override) {
                $inicio = $this->val($this->child($override, 'startOverride'));
                $nivel = (int) $this->attr($override, 'ilvl');

                if ($inicio !== null && isset($niveis[$nivel])) {
                    $niveis[$nivel]['start'] = (int) $inicio;
                }
            }

            $this->numbering[(string) $this->attr($num, 'numId')] = $niveis;
        }
    }

    /** Os blocos (parágrafos e tabelas) de um contêiner, em HTML. */
    private function blocks(DOMElement $container): string
    {
        $html = '';
        $listaAberta = false;

        $fecha = function () use (&$html, &$listaAberta) {
            if ($listaAberta) {
                $html .= "</ul>\n";
                $listaAberta = false;
            }
        };

        foreach ($this->elements($container) as $no) {
            switch ($no->localName) {
                case 'p':
                    [$tipo, $bloco] = $this->paragraph($no);

                    if ($bloco === '') {
                        break;
                    }

                    if ($tipo === 'li') {
                        if (!$listaAberta) {
                            $html .= "<ul>\n";
                            $listaAberta = true;
                        }
                    } else {
                        $fecha();
                    }

                    $html .= $bloco . "\n";
                    break;

                case 'tbl':
                    $fecha();
                    $html .= $this->table($no);
                    break;

                case 'sdt':
                    // Controle de conteúdo: o texto está dentro.
                    $conteudo = $this->child($no, 'sdtContent');

                    if ($conteudo) {
                        $fecha();
                        $html .= $this->blocks($conteudo);
                    }
                    break;
            }
        }

        $fecha();

        return $html;
    }

    /**
     * @return array{0: 'li'|'block', 1: string}
     */
    private function paragraph(DOMElement $p): array
    {
        [$inline, $texto] = $this->inline($p);

        if ($inline === '') {
            // Linha em branco usada como espaçamento: o espaço entre
            // parágrafos é do estilo do documento.
            return ['block', ''];
        }

        // O marcador sozinho na linha sai SEM parágrafo em volta: no lugar
        // dele entra um bloco, e bloco dentro de <p> é HTML inválido.
        if ($this->isSignatureMarker($texto)) {
            return ['block', $texto];
        }

        $pPr = $this->child($p, 'pPr');
        $numPr = $pPr ? $this->child($pPr, 'numPr') : null;
        $estilo = $pPr ? $this->val($this->child($pPr, 'pStyle')) : null;
        $prefixo = '';
        $numId = '';
        $nivel = 0;

        if ($numPr) {
            // Numeração aplicada direto no parágrafo vence a do estilo —
            // inclusive `numId` 0, que é o Word dizendo "este não é numerado".
            $numId = (string) $this->val($this->child($numPr, 'numId'));
            $nivel = (int) ($this->val($this->child($numPr, 'ilvl')) ?? 0);
        } elseif ($estilo !== null && isset($this->styleNumbering[$estilo])) {
            $numId = $this->styleNumbering[$estilo]['numId'];
            $nivel = $this->styleNumbering[$estilo]['ilvl'] ?? $this->levelOfStyle($numId, $estilo);
        }

        if ($numId !== '' && $numId !== '0') {
            $formato = $this->numbering[$numId][$nivel]['fmt'] ?? 'bullet';

            if ($formato === 'bullet') {
                return ['li', '<li>' . $inline . '</li>'];
            }

            $prefixo = $this->numberLabel($numId, $nivel);
        }

        /*
         | A numeração automática vira TEXTO ("3.1."), e não <ol>: num contrato
         | as cláusulas numeradas são intercaladas por parágrafos comuns, e uma
         | lista HTML recomeçaria do 1 a cada interrupção. O número de uma
         | cláusula não pode mudar na conversão.
         */
        if ($prefixo !== '') {
            $inline = htmlspecialchars($prefixo, ENT_QUOTES, 'UTF-8') . ' ' . $inline;
        }

        $titulo = $estilo !== null ? ($this->headingStyles[$estilo] ?? null) : null;
        $tag = $titulo ? 'h' . $titulo : 'p';

        return ['block', "<{$tag}>{$inline}</{$tag}>"];
    }

    private function levelOfStyle(string $numId, string $estilo): int
    {
        foreach ($this->numbering[$numId] ?? [] as $nivel => $definicao) {
            if ($definicao['style'] === $estilo) {
                return $nivel;
            }
        }

        return 0;
    }

    /** O número que o Word mostraria na frente do parágrafo. */
    private function numberLabel(string $numId, int $nivel): string
    {
        $niveis = $this->numbering[$numId] ?? [];
        $contadores = $this->counters[$numId] ?? [];

        // Um subitem sem item pai antes dele ainda precisa do número do pai.
        for ($i = 0; $i < $nivel; $i++) {
            $contadores[$i] ??= $niveis[$i]['start'] ?? 1;
        }

        $contadores[$nivel] = isset($contadores[$nivel])
            ? $contadores[$nivel] + 1
            : ($niveis[$nivel]['start'] ?? 1);

        // Avançar um nível reinicia os que estão abaixo dele.
        foreach (array_keys($contadores) as $k) {
            if ($k > $nivel) {
                unset($contadores[$k]);
            }
        }

        $this->counters[$numId] = $contadores;

        $mascara = $niveis[$nivel]['text'] ?? '';

        if ($mascara === '') {
            $mascara = '%' . ($nivel + 1) . '.';
        }

        return trim((string) preg_replace_callback('/%([1-9])/', function ($m) use ($contadores, $niveis) {
            $k = ((int) $m[1]) - 1;

            return $this->formatNumber($contadores[$k] ?? 1, $niveis[$k]['fmt'] ?? 'decimal');
        }, $mascara));
    }

    private function formatNumber(int $n, string $formato): string
    {
        return match ($formato) {
            'lowerLetter' => $this->letters($n),
            'upperLetter' => strtoupper($this->letters($n)),
            'lowerRoman' => strtolower($this->roman($n)),
            'upperRoman' => $this->roman($n),
            'decimalZero' => str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'none' => '',
            default => (string) $n,
        };
    }

    /** a, b, … z, aa, bb — a sequência do Word. */
    private function letters(int $n): string
    {
        $n = max($n, 1);

        return str_repeat(chr(97 + (($n - 1) % 26)), intdiv($n - 1, 26) + 1);
    }

    private function roman(int $n): string
    {
        $mapa = [
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90,
            'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1,
        ];

        $out = '';

        foreach ($mapa as $letra => $valor) {
            while ($n >= $valor) {
                $out .= $letra;
                $n -= $valor;
            }
        }

        return $out;
    }

    private function isSignatureMarker(string $texto): bool
    {
        return (bool) preg_match('/^\[\[' . self::SIGNATURE_KEY . '(:[a-z][a-z0-9_]*)?\]\]$/', $texto);
    }

    private function table(DOMElement $tbl): string
    {
        $linhas = '';
        $temTexto = false;

        // Os textos das células com conteúdo — para reconhecer a tabela que só
        // existe para pôr as assinaturas lado a lado.
        $textos = [];

        foreach ($this->children($tbl, 'tr') as $tr) {
            $celulas = '';

            foreach ($this->children($tr, 'tc') as $tc) {
                $partes = [];

                foreach ($this->elements($tc) as $no) {
                    if ($no->localName === 'p') {
                        [$inline, $texto] = $this->inline($no);

                        if ($inline !== '') {
                            $partes[] = $inline;
                            $textos[] = $texto;
                        }
                    } elseif ($no->localName === 'tbl') {
                        $partes[] = $this->table($no);
                        $textos[] = '';
                    }
                }

                $temTexto = $temTexto || $partes !== [];
                $celulas .= '<td>' . implode('<br>', $partes) . '</td>';
            }

            if ($celulas !== '') {
                $linhas .= '<tr>' . $celulas . "</tr>\n";
            }
        }

        /*
         | Tabela só de marcadores de assinatura é LAYOUT: a pessoa a usou para
         | pôr Contratante e Contratado lado a lado. Saem os marcadores, um
         | depois do outro — e marcadores vizinhos o renderer já dispõe lado a
         | lado, sem a borda de tabela do texto.
         */
        if ($textos !== [] && array_filter($textos, fn(string $t) => !$this->isSignatureMarker($t)) === []) {
            return implode("\n", $textos) . "\n";
        }

        // Tabela vazia é moldura de layout, não conteúdo.
        return $temTexto ? "<table>\n" . $linhas . "</table>\n" : '';
    }

    /**
     * O texto de um parágrafo, com a formatação de caractere.
     *
     * Devolve o HTML e o texto puro (já com os marcadores normalizados).
     *
     * @return array{0: string, 1: string}
     */
    private function inline(DOMElement $p): array
    {
        $caracteres = [];
        $formatos = [];

        $this->collectRuns($p, $caracteres, $formatos);

        [$caracteres, $formatos] = $this->normalizePlaceholders($caracteres, $formatos);

        // Espaço nas pontas do parágrafo não é conteúdo.
        while ($caracteres !== [] && trim($caracteres[0]) === '') {
            array_shift($caracteres);
            array_shift($formatos);
        }

        while ($caracteres !== [] && trim(end($caracteres)) === '') {
            array_pop($caracteres);
            array_pop($formatos);
        }

        if ($caracteres === []) {
            return ['', ''];
        }

        $html = '';
        $total = count($caracteres);

        for ($i = 0; $i < $total;) {
            $formato = $formatos[$i];
            $trecho = '';

            while ($i < $total && $formatos[$i] === $formato) {
                $trecho .= $caracteres[$i];
                $i++;
            }

            $trecho = str_replace("\n", '<br>', htmlspecialchars($trecho, ENT_QUOTES, 'UTF-8'));

            if ($formato & self::UNDERLINE) {
                $trecho = '<u>' . $trecho . '</u>';
            }

            if ($formato & self::ITALIC) {
                $trecho = '<em>' . $trecho . '</em>';
            }

            if ($formato & self::BOLD) {
                $trecho = '<strong>' . $trecho . '</strong>';
            }

            $html .= $trecho;
        }

        return [$html, implode('', $caracteres)];
    }

    /**
     * Percorre os trechos ("runs") do parágrafo, um caractere por posição.
     *
     * @param  array<int, string>  $caracteres
     * @param  array<int, int>  $formatos
     */
    private function collectRuns(DOMElement $container, array &$caracteres, array &$formatos): void
    {
        foreach ($this->elements($container) as $no) {
            switch ($no->localName) {
                case 'r':
                    $this->collectRun($no, $caracteres, $formatos);
                    break;

                // Invólucros cujo texto é parte do parágrafo. `ins` é
                // inserção de revisão: o texto está valendo.
                case 'hyperlink':
                case 'ins':
                case 'smartTag':
                case 'sdt':
                case 'sdtContent':
                case 'fldSimple':
                    $this->collectRuns($no, $caracteres, $formatos);
                    break;

                // `del` e `moveFrom` são texto REMOVIDO em revisão — entrar
                // no modelo seria ressuscitar o que o jurídico cortou.
            }
        }
    }

    /**
     * @param  array<int, string>  $caracteres
     * @param  array<int, int>  $formatos
     */
    private function collectRun(DOMElement $run, array &$caracteres, array &$formatos): void
    {
        $rPr = $this->child($run, 'rPr');
        $formato = 0;

        if ($rPr) {
            $formato |= $this->isOn($this->child($rPr, 'b')) ? self::BOLD : 0;
            $formato |= $this->isOn($this->child($rPr, 'i')) ? self::ITALIC : 0;
            $formato |= $this->isOn($this->child($rPr, 'u')) ? self::UNDERLINE : 0;
        }

        foreach ($this->elements($run) as $no) {
            $texto = match ($no->localName) {
                't' => $no->textContent,
                'tab' => ' ',
                'cr' => "\n",
                'noBreakHyphen' => '-',
                // Quebra de página e de coluna são do papel do Word, não do texto.
                'br' => in_array($this->attr($no, 'type'), ['page', 'column'], true) ? '' : "\n",
                default => null,
            };

            if ($texto === null) {
                if (in_array($no->localName, ['drawing', 'pict', 'object', 'AlternateContent'], true)) {
                    $this->warnings['imagem'] = 'O documento tem imagem ou caixa de texto, que não entram no '
                        . 'modelo. O cabeçalho do clube é aplicado automaticamente.';
                }

                continue;
            }

            foreach (mb_str_split($texto) as $caractere) {
                $caracteres[] = $caractere;
                $formatos[] = $formato;
            }
        }
    }

    /**
     * Encontra os `[[...]]` do parágrafo e troca cada um pela forma canônica.
     *
     * Trabalha no parágrafo inteiro, e não trecho a trecho, porque o Word
     * parte o texto onde bem entende: um `[[Data do evento]]` digitado de uma
     * vez pode estar gravado em três pedaços (corretor ortográfico, revisão,
     * mudança de formatação no meio). O marcador inteiro passa a ocupar UMA
     * posição, com a formatação do primeiro caractere — é o que impede uma
     * tag de cair no meio dele.
     *
     * @param  array<int, string>  $caracteres
     * @param  array<int, int>  $formatos
     * @return array{0: array<int, string>, 1: array<int, int>}
     */
    private function normalizePlaceholders(array $caracteres, array $formatos): array
    {
        $texto = implode('', $caracteres);

        if (!preg_match_all('/\[\[([^\[\]\n]{1,120}?)\]\]/u', $texto, $achados, PREG_OFFSET_CAPTURE)) {
            return [$caracteres, $formatos];
        }

        $novosCaracteres = [];
        $novosFormatos = [];
        $cursor = 0;

        foreach ($achados[0] as $i => [$marcador, $byte]) {
            // preg devolve a posição em bytes; os vetores são por caractere.
            $inicio = mb_strlen(substr($texto, 0, $byte));
            $tamanho = mb_strlen($marcador);

            $cru = $achados[1][$i][0];

            // `[[assinatura: Contratante]]` — a parte que assina ali.
            $parte = preg_match('/^\s*assinaturas?\s*[:\-–—]\s*(.+)$/iu', $cru, $m) ? self::labelFor($m[1]) : null;

            $chave = $parte !== null ? self::keyFor($parte) : self::keyFor($cru);

            if ($chave === '') {
                continue;
            }

            for (; $cursor < $inicio; $cursor++) {
                $novosCaracteres[] = $caracteres[$cursor];
                $novosFormatos[] = $formatos[$cursor];
            }

            if ($parte !== null) {
                $this->parties[$chave] ??= $parte;
                $this->hasSignature = true;
                $chave = self::SIGNATURE_KEY . ':' . $chave;
            } elseif (in_array($chave, [self::SIGNATURE_KEY, 'assinaturas'], true)) {
                $chave = self::SIGNATURE_KEY;
                $this->hasSignature = true;
            } else {
                $this->variables[$chave] ??= self::labelFor($cru);
            }

            $novosCaracteres[] = '[[' . $chave . ']]';
            $novosFormatos[] = $formatos[$inicio];

            $cursor = $inicio + $tamanho;
        }

        for ($total = count($caracteres); $cursor < $total; $cursor++) {
            $novosCaracteres[] = $caracteres[$cursor];
            $novosFormatos[] = $formatos[$cursor];
        }

        return [$novosCaracteres, $novosFormatos];
    }

    /** Propriedade liga/desliga do Word: presente vale ligada, salvo `val` dizendo o contrário. */
    private function isOn(?DOMElement $propriedade): bool
    {
        if (!$propriedade) {
            return false;
        }

        return !in_array($this->attr($propriedade, 'val'), ['0', 'false', 'off', 'none'], true);
    }

    /*
     | Os auxiliares abaixo casam por NOME LOCAL, sem namespace: o Word grava
     | o namespace "transitional" e o modo "Strict Open XML" grava outro, com
     | os mesmos elementos.
     */

    /** @return array<int, DOMElement> */
    private function elements(DOMElement $pai): array
    {
        $filhos = [];

        foreach ($pai->childNodes as $no) {
            if ($no instanceof DOMElement) {
                $filhos[] = $no;
            }
        }

        return $filhos;
    }

    /** @return array<int, DOMElement> */
    private function children(DOMElement $pai, string $nome): array
    {
        return array_values(array_filter(
            $this->elements($pai),
            fn(DOMElement $no) => $no->localName === $nome,
        ));
    }

    private function child(DOMElement $pai, string $nome): ?DOMElement
    {
        return $this->children($pai, $nome)[0] ?? null;
    }

    private function attr(DOMElement $elemento, string $nome): ?string
    {
        foreach ($elemento->attributes as $atributo) {
            if ($atributo->localName === $nome) {
                return $atributo->value;
            }
        }

        return null;
    }

    private function val(?DOMElement $elemento): ?string
    {
        return $elemento ? $this->attr($elemento, 'val') : null;
    }
}
