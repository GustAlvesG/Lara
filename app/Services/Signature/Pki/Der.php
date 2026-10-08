<?php

namespace App\Services\Signature\Pki;

/**
 * Escritor DER mínimo — o par do leitor `Govbr\Asn1`.
 *
 * Serve a duas montagens que a extensão OpenSSL do PHP não faz: o pedido de
 * carimbo de tempo (RFC 3161) e a inclusão do carimbo como atributo NÃO
 * assinado no CMS do lacre. Só comprimento definido (DER), que é o que um
 * pedido e um CMS bem formados levam.
 */
final class Der
{
    /** Um elemento: tag + comprimento + conteúdo. */
    public static function tlv(int $tag, string $conteudo): string
    {
        return chr($tag) . self::length(strlen($conteudo)) . $conteudo;
    }

    public static function sequence(string ...$filhos): string
    {
        return self::tlv(0x30, implode('', $filhos));
    }

    public static function set(string ...$filhos): string
    {
        return self::tlv(0x31, implode('', $filhos));
    }

    public static function octetString(string $bytes): string
    {
        return self::tlv(0x04, $bytes);
    }

    public static function null(): string
    {
        return "\x05\x00";
    }

    public static function boolean(bool $valor): string
    {
        return self::tlv(0x01, $valor ? "\xFF" : "\x00");
    }

    /** INTEGER não negativo a partir dos bytes big-endian. */
    public static function unsignedInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\0");

        if ($bytes === '' || (ord($bytes[0]) & 0x80)) {
            // Zero à frente: sem ele, o primeiro bit faria o número negativo.
            $bytes = "\0" . $bytes;
        }

        return self::tlv(0x02, $bytes);
    }

    public static function integer(int $valor): string
    {
        $bytes = '';

        do {
            $bytes = chr($valor & 0xFF) . $bytes;
            $valor >>= 8;
        } while ($valor > 0);

        return self::unsignedInteger($bytes);
    }

    /** OID a partir da notação de pontos. */
    public static function oid(string $pontos): string
    {
        $partes = array_map('intval', explode('.', $pontos));
        $bytes = chr($partes[0] * 40 + $partes[1]);

        foreach (array_slice($partes, 2) as $parte) {
            $pedaco = chr($parte & 0x7F);
            $parte >>= 7;

            while ($parte > 0) {
                $pedaco = chr(0x80 | ($parte & 0x7F)) . $pedaco;
                $parte >>= 7;
            }

            $bytes .= $pedaco;
        }

        return self::tlv(0x06, $bytes);
    }

    private static function length(int $n): string
    {
        if ($n < 0x80) {
            return chr($n);
        }

        $bytes = '';

        while ($n > 0) {
            $bytes = chr($n & 0xFF) . $bytes;
            $n >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
