<?php

namespace App\Services\Signature\Pki;

use App\Services\Signature\Govbr\Asn1;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Pede um carimbo de tempo (RFC 3161) a uma Autoridade de Carimbo do Tempo
 * (ACT) — no lacre, sobre a assinatura do clube.
 *
 * O carimbo prova que a assinatura existia naquela hora, dita por um terceiro
 * credenciado, e não pelo relógio do servidor. É o que mantém o lacre
 * verificável depois que o certificado do clube vencer.
 *
 * Confere o que volta antes de entregar: status concedido, o resumo carimbado
 * é o que foi pedido, o nonce é o deste pedido e a assinatura do carimbo
 * confere com o conteúdo. A cadeia da ACT até a raiz quem confere é o
 * validador de quem abre o PDF.
 */
class TimestampClient
{
    private const OID_SHA256 = '2.16.840.1.101.3.4.2.1';

    /**
     * O TimeStampToken (ContentInfo, DER) para o resumo SHA-256 dado.
     *
     * @throws RuntimeException  ACT fora do ar, recusa, ou resposta que não confere
     */
    public function stamp(string $sha256): string
    {
        $url = (string) config('signature.pades.tsa_url');

        if ($url === '') {
            throw new RuntimeException('Carimbo de tempo sem ACT configurada (SIGNATURE_TSA_URL).');
        }

        $nonce = random_bytes(8);
        $politica = (string) config('signature.pades.tsa_policy');

        $pedido = Der::sequence(
            Der::integer(1),
            Der::sequence(Der::sequence(Der::oid(self::OID_SHA256), Der::null()), Der::octetString($sha256)),
            $politica !== '' ? Der::oid($politica) : '',
            Der::unsignedInteger($nonce),
            // Com o certificado da ACT dentro do carimbo: o validador precisa dele.
            Der::boolean(true),
        );

        $http = Http::timeout((int) config('signature.pades.tsa_timeout_seconds', 30))
            ->withBody($pedido, 'application/timestamp-query')
            ->withHeaders(['Accept' => 'application/timestamp-reply']);

        if ((string) config('signature.pades.tsa_user') !== '') {
            $http = $http->withBasicAuth((string) config('signature.pades.tsa_user'), (string) config('signature.pades.tsa_password'));
        }

        try {
            $resposta = $http->post($url);
        } catch (Throwable $e) {
            throw new RuntimeException('A ACT não respondeu: ' . $e->getMessage(), 0, $e);
        }

        if (!$resposta->successful()) {
            throw new RuntimeException('A ACT recusou o pedido de carimbo (HTTP ' . $resposta->status() . ').');
        }

        return $this->token($resposta->body(), $sha256, $nonce);
    }

    /**
     * Extrai e confere o token de uma TimeStampResp.
     *
     * @throws RuntimeException
     */
    public function token(string $resposta, string $sha256, ?string $nonce = null): string
    {
        try {
            $raiz = Asn1::node($resposta);
            $partes = Asn1::children($resposta, $raiz);
            $status = Asn1::children($resposta, $partes[0] ?? throw new RuntimeException('vazia'));
            $codigo = (int) hexdec(bin2hex(Asn1::content($resposta, $status[0])));
        } catch (Throwable) {
            throw new RuntimeException('A resposta da ACT não é um carimbo de tempo.');
        }

        // 0 = concedido; 1 = concedido com modificações.
        if (!in_array($codigo, [0, 1], true) || !isset($partes[1])) {
            throw new RuntimeException('A ACT não concedeu o carimbo (status ' . $codigo . ').');
        }

        $token = Asn1::raw($resposta, $partes[1]);
        $info = $this->tstInfo($token);

        if (!hash_equals($sha256, $info['imprint'])) {
            throw new RuntimeException('O carimbo da ACT não é do resumo enviado.');
        }

        if ($nonce !== null && $info['nonce'] !== null && ltrim($info['nonce'], "\0") !== ltrim($nonce, "\0")) {
            throw new RuntimeException('O carimbo da ACT não é deste pedido (nonce diferente).');
        }

        if (!$this->signatureMatches($token)) {
            throw new RuntimeException('A assinatura do carimbo da ACT não confere.');
        }

        return $token;
    }

    /**
     * Hora e resumo de um TimeStampToken.
     *
     * @return array{time: ?int, imprint: string, nonce: ?string}
     *
     * @throws RuntimeException
     */
    public function tstInfo(string $token): array
    {
        try {
            $raiz = Asn1::node($token);
            $signedData = Asn1::children($token, Asn1::children($token, $raiz)[1])[0];
            $encap = Asn1::children($token, $signedData)[2];                         // encapContentInfo
            $explicito = Asn1::children($token, $encap)[1];                          // [0] EXPLICIT
            $octetos = Asn1::children($token, $explicito)[0];                        // OCTET STRING
            $tst = Asn1::content($token, $octetos);

            $campos = Asn1::children($tst, Asn1::node($tst));
            // version, policy, messageImprint, serialNumber, genTime, [accuracy], [ordering], [nonce]
            $imprint = Asn1::children($tst, $campos[2]);
            $quando = Asn1::time($tst, $campos[4]);

            $nonce = null;
            foreach (array_slice($campos, 5) as $campo) {
                if ($campo['tag'] === Asn1::INTEGER) {
                    $nonce = Asn1::content($tst, $campo);
                }
            }

            return ['time' => $quando, 'imprint' => Asn1::content($tst, $imprint[1]), 'nonce' => $nonce];
        } catch (Throwable) {
            throw new RuntimeException('O carimbo de tempo está num formato que o Lara não lê.');
        }
    }

    private function signatureMatches(string $token): bool
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'tst');
        $conteudo = tempnam(sys_get_temp_dir(), 'tst');

        try {
            file_put_contents($arquivo, $token);

            while (openssl_error_string() !== false) {
            }

            // NOVERIFY: a cadeia da ACT quem confere é o validador do PDF; aqui
            // só interessa que o carimbo não foi adulterado no caminho.
            $ok = openssl_cms_verify($arquivo, OPENSSL_CMS_NOVERIFY | OPENSSL_CMS_BINARY, null, [], null, $conteudo, null, null, OPENSSL_ENCODING_DER) === true;

            while (openssl_error_string() !== false) {
            }

            return $ok;
        } finally {
            @unlink($arquivo);
            @unlink($conteudo);
        }
    }
}
