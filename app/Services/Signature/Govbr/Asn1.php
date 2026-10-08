<?php

namespace App\Services\Signature\Govbr;

use RuntimeException;

/**
 * Leitor BER mínimo — só o que a validação da assinatura do gov.br precisa.
 *
 * Existe porque a extensão OpenSSL do PHP não entrega dois dados de que
 * precisamos: o CPF (fica num `otherName` do subjectAltName, que o
 * `openssl_x509_parse` mostra como "<unsupported>") e a hora gravada nos
 * atributos assinados do CMS.
 *
 * É BER, e não só DER, de propósito: o gov.br grava o CMS com comprimento
 * INDEFINIDO (0x80, terminado por 00 00). Um leitor DER estrito lê só os dois
 * primeiros bytes e desiste — foi o que a Fase 0 encontrou na amostra real.
 *
 * Um nó é um array: tag (o byte de identificação), constructed, start (onde
 * começa o cabeçalho), contentStart, contentLength (sem o EOC, quando
 * indefinido) e end (primeiro byte depois do elemento).
 *
 * @phpstan-type Node array{tag: int, constructed: bool, start: int, contentStart: int, contentLength: int, end: int}
 */
final class Asn1
{
    public const SEQUENCE = 0x30;
    public const SET = 0x31;
    public const INTEGER = 0x02;
    public const OCTET_STRING = 0x04;
    public const OID = 0x06;
    public const UTC_TIME = 0x17;
    public const GENERALIZED_TIME = 0x18;

    /** Profundidade máxima: um CMS de verdade não passa de uns 15 níveis. */
    private const MAX_DEPTH = 64;

    /**
     * O elemento que começa em $offset.
     *
     * @return Node
     *
     * @throws RuntimeException  bytes que não formam um elemento BER
     */
    public static function node(string $bytes, int $offset = 0, int $depth = 0): array
    {
        $total = strlen($bytes);

        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException('Estrutura ASN.1 aninhada demais.');
        }

        if ($offset + 2 > $total) {
            throw new RuntimeException('Estrutura ASN.1 truncada.');
        }

        $tag = ord($bytes[$offset]);
        $p = $offset + 1;

        // Tag de número alto (0x1f): os bytes seguintes continuam o número.
        // Não aparece em certificado nem em CMS, mas não pode desalinhar a leitura.
        if (($tag & 0x1f) === 0x1f) {
            while ($p < $total && (ord($bytes[$p]) & 0x80)) {
                $p++;
            }
            $p++;
        }

        if ($p >= $total) {
            throw new RuntimeException('Estrutura ASN.1 truncada.');
        }

        $first = ord($bytes[$p++]);
        $constructed = (bool) ($tag & 0x20);

        if ($first === 0x80) {
            if (!$constructed) {
                throw new RuntimeException('Comprimento indefinido em elemento primitivo.');
            }

            // Comprimento indefinido: os filhos vão até o marcador 00 00.
            $cursor = $p;
            while (true) {
                if ($cursor + 2 > $total) {
                    throw new RuntimeException('Estrutura ASN.1 sem o marcador de fim.');
                }
                if ($bytes[$cursor] === "\0" && $bytes[$cursor + 1] === "\0") {
                    break;
                }
                $cursor = self::node($bytes, $cursor, $depth + 1)['end'];
            }

            return [
                'tag' => $tag,
                'constructed' => true,
                'start' => $offset,
                'contentStart' => $p,
                'contentLength' => $cursor - $p,
                'end' => $cursor + 2,
            ];
        }

        if ($first < 0x80) {
            $length = $first;
        } else {
            $n = $first & 0x7f;

            if ($n > 4 || $p + $n > $total) {
                throw new RuntimeException('Comprimento ASN.1 inválido.');
            }

            $length = 0;
            for ($i = 0; $i < $n; $i++) {
                $length = ($length << 8) | ord($bytes[$p + $i]);
            }
            $p += $n;
        }

        if ($p + $length > $total) {
            throw new RuntimeException('Estrutura ASN.1 truncada.');
        }

        return [
            'tag' => $tag,
            'constructed' => $constructed,
            'start' => $offset,
            'contentStart' => $p,
            'contentLength' => $length,
            'end' => $p + $length,
        ];
    }

    /**
     * Os filhos de um elemento construído.
     *
     * @param  Node  $node
     * @return array<int, Node>
     */
    public static function children(string $bytes, array $node): array
    {
        if (!$node['constructed']) {
            return [];
        }

        $filhos = [];
        $cursor = $node['contentStart'];
        $fim = $node['contentStart'] + $node['contentLength'];

        while ($cursor < $fim) {
            $filho = self::node($bytes, $cursor);
            $filhos[] = $filho;
            $cursor = $filho['end'];
        }

        return $filhos;
    }

    /** @param  Node  $node */
    public static function content(string $bytes, array $node): string
    {
        return substr($bytes, $node['contentStart'], $node['contentLength']);
    }

    /** O elemento inteiro, com cabeçalho — para comprimento indefinido, com o EOC. */
    public static function raw(string $bytes, array $node): string
    {
        return substr($bytes, $node['start'], $node['end'] - $node['start']);
    }

    /**
     * OID em notação de pontos.
     *
     * @param  Node  $node
     */
    public static function oid(string $bytes, array $node): string
    {
        $conteudo = self::content($bytes, $node);

        if ($conteudo === '') {
            return '';
        }

        $primeiro = ord($conteudo[0]);
        $partes = [intdiv($primeiro, 40), $primeiro % 40];
        $valor = 0;

        for ($i = 1, $n = strlen($conteudo); $i < $n; $i++) {
            $b = ord($conteudo[$i]);
            $valor = ($valor << 7) | ($b & 0x7f);

            if (!($b & 0x80)) {
                $partes[] = $valor;
                $valor = 0;
            }
        }

        return implode('.', $partes);
    }

    /**
     * UTCTime ou GeneralizedTime em timestamp Unix (UTC). Null para o que não
     * for um dos dois, ou vier num formato que não se lê com segurança.
     *
     * @param  Node  $node
     */
    public static function time(string $bytes, array $node): ?int
    {
        $texto = self::content($bytes, $node);

        $formato = match ($node['tag']) {
            self::UTC_TIME => 'ymdHis',
            self::GENERALIZED_TIME => 'YmdHis',
            default => null,
        };

        if ($formato === null || !preg_match('/^(\d{12}|\d{14})Z$/', $texto, $m)) {
            return null;
        }

        $data = \DateTimeImmutable::createFromFormat('!' . $formato, $m[1], new \DateTimeZone('UTC'));

        return $data === false ? null : $data->getTimestamp();
    }
}
