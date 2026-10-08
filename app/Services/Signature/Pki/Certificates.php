<?php

namespace App\Services\Signature\Pki;

/**
 * Utilidades de certificado X.509 que se repetem entre a conferência, a
 * revogação e o lacre: PEM ↔ DER, leitura de arquivos e pacotes PKCS#7, e os
 * endereços que o próprio certificado declara (LCR e emissor).
 */
final class Certificates
{
    /** @return array<int, string> os certificados PEM de um texto */
    public static function pems(string $texto): array
    {
        preg_match_all('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', $texto, $m);

        return $m[0];
    }

    public static function derToPem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END CERTIFICATE-----\n";
    }

    public static function pemToDer(string $pem): string
    {
        return (string) base64_decode((string) preg_replace('/-----[^-]+-----|\s/', '', $pem));
    }

    /**
     * Os certificados de um arquivo baixado ou guardado, venha como vier: PEM
     * (um ou vários), DER de um certificado só, ou pacote PKCS#7 (.p7b/.p7c,
     * em DER ou PEM) — o formato em que o gov.br e muitas ACs publicam a
     * cadeia.
     *
     * @return array<int, string> PEM
     */
    public static function fromAnyFormat(string $bytes): array
    {
        if (str_contains($bytes, '-----BEGIN CERTIFICATE-----')) {
            return self::pems($bytes);
        }

        if (str_contains($bytes, '-----BEGIN PKCS7-----')) {
            return self::fromPkcs7Pem($bytes);
        }

        $pem = self::derToPem($bytes);

        if (@openssl_x509_read($pem) !== false) {
            return [$pem];
        }

        return self::fromPkcs7Pem(
            "-----BEGIN PKCS7-----\n" . chunk_split(base64_encode($bytes), 64, "\n") . "-----END PKCS7-----\n"
        );
    }

    /** Endereços HTTP das listas de revogação (extensão CRL Distribution Points). */
    public static function crlUrls(string $pem): array
    {
        $texto = (string) ((openssl_x509_parse($pem) ?: [])['extensions']['crlDistributionPoints'] ?? '');
        preg_match_all('#URI:(https?://\S+)#i', $texto, $m);

        return array_values(array_unique($m[1]));
    }

    /** Endereços HTTP do certificado do emissor (extensão Authority Information Access). */
    public static function issuerUrls(string $pem): array
    {
        $texto = (string) ((openssl_x509_parse($pem) ?: [])['extensions']['authorityInfoAccess'] ?? '');
        preg_match_all('#CA Issuers - URI:(https?://\S+)#i', $texto, $m);

        return array_values(array_unique($m[1]));
    }

    /** Número de série em hexadecimal maiúsculo, sem zeros à esquerda. */
    public static function serial(string $pem): ?string
    {
        $hex = (openssl_x509_parse($pem) ?: [])['serialNumberHex'] ?? null;

        return $hex === null ? null : self::normalizeSerial($hex);
    }

    public static function normalizeSerial(string $hex): string
    {
        $limpo = ltrim(strtoupper($hex), '0');

        return $limpo === '' ? '0' : $limpo;
    }

    public static function commonName(string $pem, string $campo = 'subject'): ?string
    {
        $valor = (openssl_x509_parse($pem) ?: [])[$campo]['CN'] ?? null;

        return is_array($valor) ? ($valor[0] ?? null) : $valor;
    }

    public static function isSelfSigned(string $pem): bool
    {
        $info = openssl_x509_parse($pem) ?: [];

        return ($info['subject'] ?? null) == ($info['issuer'] ?? null) && openssl_x509_verify($pem, $pem) === 1;
    }

    /** @return array<int, string> */
    private static function fromPkcs7Pem(string $pem): array
    {
        $certs = [];

        return @openssl_pkcs7_read($pem, $certs) ? array_values($certs) : [];
    }
}
