<?php

namespace App\Services\Signature\Pki;

use App\Services\Signature\Govbr\Asn1;
use RuntimeException;

/**
 * Uma Lista de Certificados Revogados (LCR / CRL, RFC 5280), lida em DER.
 *
 * A extensão OpenSSL do PHP não lê LCR; o `Govbr\Asn1` lê. A do gov.br tem
 * ~3 MB e ~71 mil números de série: o mapa de séries é montado uma vez, na
 * primeira consulta, e reaproveitado.
 *
 * Só o que a conferência precisa: emissor (para a assinatura), validade
 * (thisUpdate/nextUpdate), a assinatura da própria lista e as séries
 * revogadas com a data. Extensões de entrada (motivo, LCR indireta) e LCR
 * delta ficam de fora — nem o gov.br nem a ICP-Brasil publicam LCR indireta.
 */
final class CertificateRevocationList
{
    private const ALGORITMOS = [
        '1.2.840.113549.1.1.5' => OPENSSL_ALGO_SHA1,
        '1.2.840.113549.1.1.11' => OPENSSL_ALGO_SHA256,
        '1.2.840.113549.1.1.12' => OPENSSL_ALGO_SHA384,
        '1.2.840.113549.1.1.13' => OPENSSL_ALGO_SHA512,
        '1.2.840.10045.4.3.2' => OPENSSL_ALGO_SHA256,
        '1.2.840.10045.4.3.3' => OPENSSL_ALGO_SHA384,
        '1.2.840.10045.4.3.4' => OPENSSL_ALGO_SHA512,
    ];

    /** @var array<string, int>|null série → data da revogação */
    private ?array $revogados = null;

    /** @param  array<string, mixed>  $revokedNode */
    private function __construct(
        private string $der,
        private string $tbs,
        private string $algoritmo,
        private string $assinatura,
        public readonly int $thisUpdate,
        public readonly ?int $nextUpdate,
        private ?array $revokedNode,
    ) {
    }

    /** @throws RuntimeException  bytes que não são uma LCR */
    public static function parse(string $bytes): self
    {
        if (str_contains($bytes, '-----BEGIN X509 CRL-----')) {
            $bytes = (string) base64_decode((string) preg_replace('/-----[^-]+-----|\s/', '', $bytes));
        }

        $raiz = Asn1::node($bytes);
        $partes = Asn1::children($bytes, $raiz);

        if ($raiz['tag'] !== Asn1::SEQUENCE || count($partes) !== 3 || $partes[2]['tag'] !== 0x03) {
            throw new RuntimeException('O arquivo não é uma lista de revogação.');
        }

        [$tbsNode, $algNode, $sigNode] = $partes;

        $campos = Asn1::children($bytes, $tbsNode);
        $i = 0;

        // version é opcional (v1 não traz).
        if (($campos[$i]['tag'] ?? null) === Asn1::INTEGER) {
            $i++;
        }

        $i += 2; // signature (AlgorithmIdentifier) + issuer

        $thisUpdate = isset($campos[$i]) ? Asn1::time($bytes, $campos[$i]) : null;
        $i++;

        if ($thisUpdate === null) {
            throw new RuntimeException('Lista de revogação sem data de emissão.');
        }

        $nextUpdate = null;
        if (isset($campos[$i]) && in_array($campos[$i]['tag'], [Asn1::UTC_TIME, Asn1::GENERALIZED_TIME], true)) {
            $nextUpdate = Asn1::time($bytes, $campos[$i]);
            $i++;
        }

        $revogados = isset($campos[$i]) && $campos[$i]['tag'] === Asn1::SEQUENCE ? $campos[$i] : null;

        $algoritmo = Asn1::oid($bytes, Asn1::children($bytes, $algNode)[0]);

        // BIT STRING: o primeiro byte é a contagem de bits não usados (0).
        $assinatura = substr(Asn1::content($bytes, $sigNode), 1);

        return new self($bytes, Asn1::raw($bytes, $tbsNode), $algoritmo, $assinatura, $thisUpdate, $nextUpdate, $revogados);
    }

    /** A lista foi assinada pela chave deste certificado (o emissor do certificado conferido)? */
    public function isSignedBy(string $emissorPem): bool
    {
        $algoritmo = self::ALGORITMOS[$this->algoritmo] ?? null;
        $chave = @openssl_pkey_get_public($emissorPem);

        if ($algoritmo === null || $chave === false) {
            return false;
        }

        return openssl_verify($this->tbs, $this->assinatura, $chave, $algoritmo) === 1;
    }

    public function isCurrent(int $agora): bool
    {
        return $this->thisUpdate <= $agora + 300 && ($this->nextUpdate === null || $agora <= $this->nextUpdate);
    }

    /** Data da revogação do certificado com esta série, ou null se não está na lista. */
    public function revokedAt(string $serialHex): ?int
    {
        $this->revogados ??= $this->indexar();

        return $this->revogados[Certificates::normalizeSerial($serialHex)] ?? null;
    }

    public function count(): int
    {
        $this->revogados ??= $this->indexar();

        return count($this->revogados);
    }

    /** @return array<string, int> */
    private function indexar(): array
    {
        if ($this->revokedNode === null) {
            return [];
        }

        $mapa = [];

        // Cursor, e não Asn1::children(): a lista do gov.br tem ~71 mil
        // entradas, e montar todos os nós de uma vez passa de 50 MB.
        $cursor = $this->revokedNode['contentStart'];
        $fim = $cursor + $this->revokedNode['contentLength'];

        while ($cursor < $fim) {
            $entrada = Asn1::node($this->der, $cursor);
            $cursor = $entrada['end'];

            $serieNode = Asn1::node($this->der, $entrada['contentStart']);

            if ($serieNode['end'] >= $entrada['end']) {
                continue;
            }

            $quando = Asn1::node($this->der, $serieNode['end']);
            $serie = Certificates::normalizeSerial(bin2hex(Asn1::content($this->der, $serieNode)));
            $mapa[$serie] = Asn1::time($this->der, $quando) ?? 0;
        }

        return $mapa;
    }
}
