<?php

namespace App\Services\Signature\Pki;

use RuntimeException;

/**
 * Acrescenta uma assinatura digital a um PDF por ATUALIZAÇÃO INCREMENTAL — sem
 * reescrever um byte do que já existe, como faz o assinador do gov.br.
 *
 * É isso que permite lacrar o PDF que voltou do gov.br sem desfazer as
 * assinaturas das pessoas: cada uma cobre o arquivo até onde ele ia quando
 * assinou, e o que vem depois é uma revisão nova. O DocMDP que o gov.br grava
 * (`/P 2`) permite acrescentar assinaturas.
 *
 * O que vai no fim do arquivo:
 *
 *  - o catálogo, com o campo novo no `/AcroForm` (`/SigFlags 3`);
 *  - a primeira página, com o widget no `/Annots` (ou o array de anotações,
 *    quando ele é objeto à parte);
 *  - o widget invisível (`/Rect [0 0 0 0]`) e o dicionário `/Sig`, com o
 *    `/Contents` reservado e o `/ByteRange` preenchido depois de montado;
 *  - tabela xref clássica e trailer com `/Prev`.
 *
 * Limites, de propósito: só PDF com tabela xref clássica (o dompdf, a FPDI e
 * o gov.br gravam assim; PDF com xref stream já é recusado no envio de PDF
 * pronto) e sem criptografia. Fora disso, falha alto.
 */
class PdfSignatureWriter
{
    /**
     * @param  callable(string): string  $assinar  recebe os bytes cobertos e devolve o CMS (DER)
     * @param  array{name?: ?string, reason?: ?string, reserve?: int}  $opcoes
     *
     * @throws RuntimeException
     */
    public function sign(string $pdf, callable $assinar, array $opcoes = []): string
    {
        $reserva = max(4096, (int) ($opcoes['reserve'] ?? 32768));

        [$offsets, $trailer, $startxref] = $this->xref($pdf);

        if (isset($trailer['/Encrypt'])) {
            throw new RuntimeException('PDF criptografado: o lacre não é aplicado.');
        }

        $raizRef = $trailer['/Root'] ?? null;

        if (($raizRef[0] ?? null) !== 'ref') {
            throw new RuntimeException('PDF sem catálogo (/Root) no trailer.');
        }

        $tamanho = (int) $this->rawValue($trailer['/Size'] ?? ['raw', '0']);
        $campoNum = $tamanho;
        $sigNum = $tamanho + 1;

        /** @var array<int, array{gen: int, body: string}> $novos */
        $novos = [];

        $catalogo = $this->dict($this->object($pdf, $offsets, $raizRef[1]));
        $paginaRef = $this->firstPage($pdf, $offsets, $catalogo);

        // ---- Página: o widget entra nas anotações da primeira página.
        $pagina = $this->dict($this->object($pdf, $offsets, $paginaRef[1]));
        $anotacoes = $pagina['/Annots'] ?? null;
        $widget = ['ref', $campoNum, 0];

        if (($anotacoes[0] ?? null) === 'ref') {
            $array = $this->object($pdf, $offsets, $anotacoes[1]);
            $array[1][] = $widget;
            $novos[$anotacoes[1]] = ['gen' => $anotacoes[2], 'body' => $this->serialize($array)];
        } else {
            $pagina['/Annots'] = ['array', array_merge($anotacoes[1] ?? [], [$widget])];
            $novos[$paginaRef[1]] = ['gen' => $paginaRef[2], 'body' => $this->serialize(['dict', $pagina])];
        }

        // ---- Formulário: o campo entra no /AcroForm do catálogo.
        $formRef = ($catalogo['/AcroForm'][0] ?? null) === 'ref' ? $catalogo['/AcroForm'] : null;
        $form = $formRef !== null
            ? $this->dict($this->object($pdf, $offsets, $formRef[1]))
            : ($catalogo['/AcroForm'][1] ?? []);

        $campos = $form['/Fields'] ?? null;

        if (($campos[0] ?? null) === 'ref') {
            $array = $this->object($pdf, $offsets, $campos[1]);
            $array[1][] = $widget;
            $novos[$campos[1]] = ['gen' => $campos[2], 'body' => $this->serialize($array)];
        } else {
            $form['/Fields'] = ['array', array_merge($campos[1] ?? [], [$widget])];
        }

        $form['/SigFlags'] = ['raw', '3'];

        if ($formRef !== null) {
            $novos[$formRef[1]] = ['gen' => $formRef[2], 'body' => $this->serialize(['dict', $form])];
        } else {
            $catalogo['/AcroForm'] = ['dict', $form];
            $novos[$raizRef[1]] = ['gen' => $raizRef[2], 'body' => $this->serialize(['dict', $catalogo])];
        }

        // ---- O campo (widget invisível) e o valor (/Sig).
        $novos[$campoNum] = ['gen' => 0, 'body' => '<< /Type /Annot /Subtype /Widget /FT /Sig /F 132 /Rect [0 0 0 0]'
            . ' /T ' . $this->text('Lacre ' . date('YmdHis') . '-' . bin2hex(random_bytes(2)))
            . ' /P ' . $paginaRef[1] . ' ' . $paginaRef[2] . ' R /V ' . $sigNum . ' 0 R >>'];

        $marcaIntervalo = '[0 ********** ********** **********]';
        $novos[$sigNum] = ['gen' => 0, 'body' => '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached'
            . (($opcoes['name'] ?? null) ? ' /Name ' . $this->text($opcoes['name']) : '')
            . (($opcoes['reason'] ?? null) ? ' /Reason ' . $this->text($opcoes['reason']) : '')
            . ' /M ' . $this->text('D:' . date('YmdHis') . str_replace(':', "'", date('P')) . "'")
            . ' /ByteRange ' . $marcaIntervalo
            . ' /Contents <' . str_repeat('0', $reserva * 2) . '> >>'];

        // ---- Monta a revisão nova.
        $saida = $pdf;

        if (!str_ends_with($saida, "\n") && !str_ends_with($saida, "\r")) {
            $saida .= "\n";
        }

        ksort($novos);
        $posicoes = [];

        foreach ($novos as $num => $objeto) {
            $posicoes[$num] = [strlen($saida), $objeto['gen']];
            $saida .= $num . ' ' . $objeto['gen'] . " obj\n" . $objeto['body'] . "\nendobj\n";
        }

        $xref = strlen($saida);
        $saida .= "xref\n" . $this->xrefTable($posicoes);

        $id = $trailer['/ID'][1][0] ?? ['raw', '<' . bin2hex(random_bytes(16)) . '>'];
        $saida .= "trailer\n<< /Size " . ($tamanho + 2)
            . ' /Root ' . $raizRef[1] . ' ' . $raizRef[2] . ' R'
            . (isset($trailer['/Info']) ? ' /Info ' . $this->serialize($trailer['/Info']) : '')
            . ' /ID [' . $this->serialize($id) . ' <' . bin2hex(random_bytes(16)) . '>]'
            . ' /Prev ' . $startxref . " >>\nstartxref\n" . $xref . "\n%%EOF\n";

        // ---- ByteRange: tudo, menos o /Contents <...> (com os < >).
        $inicioSig = $posicoes[$sigNum][0];
        $b = strpos($saida, '/Contents <', $inicioSig) + strlen('/Contents ');
        $c = $b + $reserva * 2 + 2;
        $intervalo = sprintf('[0 %d %d %d]', $b, $c, strlen($saida) - $c);

        if (strlen($intervalo) > strlen($marcaIntervalo)) {
            throw new RuntimeException('PDF grande demais para o lacre.');
        }

        $posMarca = strpos($saida, $marcaIntervalo, $inicioSig);
        $saida = substr_replace($saida, str_pad($intervalo, strlen($marcaIntervalo)), $posMarca, strlen($marcaIntervalo));

        $cms = $assinar(substr($saida, 0, $b) . substr($saida, $c));
        $hex = bin2hex($cms);

        if (strlen($hex) > $reserva * 2) {
            throw new RuntimeException('A assinatura (' . strlen($cms) . ' bytes) não coube no espaço reservado ('
                . $reserva . '). Aumente SIGNATURE_PADES_RESERVE_BYTES.');
        }

        return substr_replace($saida, str_pad($hex, $reserva * 2, '0'), $b + 1, $reserva * 2);
    }

    // =========================================================================
    // xref
    // =========================================================================

    /**
     * Mapa número → posição de todos os objetos (a revisão mais nova vence),
     * o trailer mais novo e a posição da última xref.
     *
     * @return array{0: array<int, int>, 1: array<string, mixed>, 2: int}
     */
    private function xref(string $pdf): array
    {
        $pos = strrpos($pdf, 'startxref');

        if ($pos === false || !preg_match('/startxref\s+(\d+)/', $pdf, $m, 0, $pos)) {
            throw new RuntimeException('PDF sem startxref.');
        }

        $startxref = (int) $m[1];
        $offsets = [];
        $trailer = null;
        $visitados = [];
        $atual = $startxref;

        while ($atual !== null && !isset($visitados[$atual])) {
            $visitados[$atual] = true;

            if (substr($pdf, $atual, 4) !== 'xref') {
                throw new RuntimeException('PDF com xref stream (compactado): o lacre só lê tabela xref clássica.');
            }

            $p = $atual + 4;

            while (preg_match('/\G\s*(\d+)\s+(\d+)[ \t]*\r?\n?/', $pdf, $sub, 0, $p)) {
                $p += strlen($sub[0]);
                $primeiro = (int) $sub[1];

                for ($i = 0; $i < (int) $sub[2]; $i++) {
                    if (!preg_match('/\G\s*(\d{10})\s+(\d{5})\s+([nf])/', $pdf, $e, 0, $p)) {
                        throw new RuntimeException('Tabela xref ilegível.');
                    }

                    $p += strlen($e[0]);
                    $num = $primeiro + $i;

                    if ($e[3] === 'n' && !isset($offsets[$num])) {
                        $offsets[$num] = (int) $e[1];
                    }
                }
            }

            $t = strpos($pdf, 'trailer', $p);

            if ($t === false) {
                throw new RuntimeException('PDF sem trailer.');
            }

            [$valor] = $this->parse($pdf, $t + 7);
            $dict = $valor[1] ?? [];

            if (isset($dict['/XRefStm'])) {
                throw new RuntimeException('PDF híbrido (xref stream): o lacre só lê tabela xref clássica.');
            }

            $trailer ??= $dict;
            $atual = isset($dict['/Prev']) ? (int) $this->rawValue($dict['/Prev']) : null;
        }

        return [$offsets, $trailer ?? [], $startxref];
    }

    /** @param  array<int, array{0: int, 1: int}>  $posicoes */
    private function xrefTable(array $posicoes): string
    {
        $saida = "0 1\n0000000000 65535 f\r\n";
        $grupo = [];
        $anterior = null;

        $fecha = function () use (&$grupo, &$saida) {
            if ($grupo === []) {
                return;
            }

            $saida .= array_key_first($grupo) . ' ' . count($grupo) . "\n";

            foreach ($grupo as [$offset, $gen]) {
                $saida .= sprintf("%010d %05d n\r\n", $offset, $gen);
            }

            $grupo = [];
        };

        foreach ($posicoes as $num => $posicao) {
            if ($anterior !== null && $num !== $anterior + 1) {
                $fecha();
            }

            $grupo[$num] = $posicao;
            $anterior = $num;
        }

        $fecha();

        return $saida;
    }

    // =========================================================================
    // Objetos
    // =========================================================================

    /** @param  array<int, int>  $offsets */
    private function object(string $pdf, array $offsets, int $num): array
    {
        $offset = $offsets[$num] ?? throw new RuntimeException("Objeto {$num} não está na tabela xref.");

        if (!preg_match('/\G\s*(\d+)\s+(\d+)\s+obj/', $pdf, $m, 0, $offset) || (int) $m[1] !== $num) {
            throw new RuntimeException("Objeto {$num} não está onde a tabela xref diz.");
        }

        return $this->parse($pdf, $offset + strlen($m[0]))[0];
    }

    /** @return array<string, mixed> */
    private function dict(array $valor): array
    {
        if ($valor[0] !== 'dict') {
            throw new RuntimeException('Era esperado um dicionário no PDF.');
        }

        return $valor[1];
    }

    /**
     * A primeira página, descendo pelos /Kids da árvore.
     *
     * @param  array<int, int>  $offsets
     * @param  array<string, mixed>  $catalogo
     * @return array{0: string, 1: int, 2: int}
     */
    private function firstPage(string $pdf, array $offsets, array $catalogo): array
    {
        $ref = $catalogo['/Pages'] ?? null;

        for ($nivel = 0; $nivel < 32 && ($ref[0] ?? null) === 'ref'; $nivel++) {
            $no = $this->dict($this->object($pdf, $offsets, $ref[1]));
            $tipo = isset($no['/Type']) ? $this->rawValue($no['/Type']) : null;

            if ($tipo === '/Page') {
                return $ref;
            }

            $filhos = $no['/Kids'] ?? null;

            if (($filhos[0] ?? null) === 'ref') {
                $filhos = $this->object($pdf, $offsets, $filhos[1]);
            }

            $ref = $filhos[1][0] ?? null;
        }

        throw new RuntimeException('PDF sem página.');
    }

    // =========================================================================
    // Sintaxe — o suficiente para catálogo, página e formulário
    // =========================================================================

    /**
     * Lê um valor a partir de $p. Devolve [valor, posição depois dele].
     *
     * Valores: ['dict', [chave => valor]], ['array', [...]], ['ref', num, gen]
     * e ['raw', texto] — nome, número, string, booleano e null são guardados
     * como estão no arquivo e regravados iguais.
     *
     * @return array{0: array, 1: int}
     */
    private function parse(string $pdf, int $p): array
    {
        $p = $this->skip($pdf, $p);
        $c = $pdf[$p] ?? '';

        if ($c === '<' && ($pdf[$p + 1] ?? '') === '<') {
            $p += 2;
            $itens = [];

            while (true) {
                $p = $this->skip($pdf, $p);

                if (substr($pdf, $p, 2) === '>>') {
                    return [['dict', $itens], $p + 2];
                }

                [$chave, $p] = $this->parse($pdf, $p);

                if ($chave[0] !== 'raw' || !str_starts_with($chave[1], '/')) {
                    throw new RuntimeException('Dicionário do PDF ilegível.');
                }

                [$valor, $p] = $this->parse($pdf, $p);
                $itens[$chave[1]] = $valor;
            }
        }

        if ($c === '[') {
            $p++;
            $itens = [];

            while (true) {
                $p = $this->skip($pdf, $p);

                if (($pdf[$p] ?? '') === ']') {
                    return [['array', $itens], $p + 1];
                }

                if ($p >= strlen($pdf)) {
                    throw new RuntimeException('Array do PDF sem fim.');
                }

                [$valor, $p] = $this->parse($pdf, $p);
                $itens[] = $valor;
            }
        }

        if ($c === '<') {
            $fim = strpos($pdf, '>', $p);

            return [['raw', substr($pdf, $p, $fim - $p + 1)], $fim + 1];
        }

        if ($c === '(') {
            $fim = $this->literalEnd($pdf, $p);

            return [['raw', substr($pdf, $p, $fim - $p)], $fim];
        }

        if ($c === '/') {
            preg_match('/\G\/[^\s\/\[\]<>(){}%]*/', $pdf, $m, 0, $p);

            return [['raw', $m[0]], $p + strlen($m[0])];
        }

        // "n g R" é referência; o resto é número, true, false ou null.
        if (preg_match('/\G(\d+)\s+(\d+)\s+R(?![A-Za-z0-9])/', $pdf, $m, 0, $p)) {
            return [['ref', (int) $m[1], (int) $m[2]], $p + strlen($m[0])];
        }

        if (preg_match('/\G[^\s\/\[\]<>(){}%]+/', $pdf, $m, 0, $p)) {
            return [['raw', $m[0]], $p + strlen($m[0])];
        }

        throw new RuntimeException('Sintaxe do PDF ilegível na posição ' . $p . '.');
    }

    private function skip(string $pdf, int $p): int
    {
        $n = strlen($pdf);

        while ($p < $n) {
            $c = $pdf[$p];

            if ($c === '%') {
                while ($p < $n && $pdf[$p] !== "\n" && $pdf[$p] !== "\r") {
                    $p++;
                }
            } elseif (str_contains(" \t\r\n\f\0", $c)) {
                $p++;
            } else {
                break;
            }
        }

        return $p;
    }

    /** Fim de uma string literal (com parênteses aninhados e escapes). */
    private function literalEnd(string $pdf, int $p): int
    {
        $nivel = 0;
        $n = strlen($pdf);

        for ($i = $p; $i < $n; $i++) {
            $c = $pdf[$i];

            if ($c === '\\') {
                $i++;
            } elseif ($c === '(') {
                $nivel++;
            } elseif ($c === ')' && --$nivel === 0) {
                return $i + 1;
            }
        }

        throw new RuntimeException('String do PDF sem fim.');
    }

    private function serialize(array $valor): string
    {
        return match ($valor[0]) {
            'dict' => '<<' . implode('', array_map(
                fn(string $chave, array $v) => ' ' . $chave . ' ' . $this->serialize($v),
                array_keys($valor[1]),
                $valor[1],
            )) . ' >>',
            'array' => '[' . implode(' ', array_map(fn(array $v) => $this->serialize($v), $valor[1])) . ']',
            'ref' => $valor[1] . ' ' . $valor[2] . ' R',
            default => $valor[1],
        };
    }

    private function rawValue(array $valor): string
    {
        return $valor[0] === 'raw' ? $valor[1] : '';
    }

    /** String de texto do PDF: ASCII como literal; o resto em UTF-16BE com BOM. */
    private function text(string $texto): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $texto)) {
            return '(' . strtr($texto, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']) . ')';
        }

        return '<FEFF' . strtoupper(bin2hex(mb_convert_encoding($texto, 'UTF-16BE', 'UTF-8'))) . '>';
    }
}
