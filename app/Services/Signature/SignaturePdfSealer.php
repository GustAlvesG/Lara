<?php

namespace App\Services\Signature;

use App\Models\SignatureDocument;
use App\Services\Signature\Govbr\Asn1;
use App\Services\Signature\Pki\Certificates;
use App\Services\Signature\Pki\Der;
use App\Services\Signature\Pki\PdfSignatureWriter;
use App\Services\Signature\Pki\TimestampClient;
use RuntimeException;

/**
 * Lacre do PDF com o certificado A1 do clube (e-CNPJ ICP-Brasil) — PAdES,
 * `adbe.pkcs7.detached`, com carimbo de tempo de uma ACT quando configurada.
 *
 * O lacre NÃO assina pela pessoa. O valor jurídico da assinatura dela vem das
 * evidências (tablet, assinatura avançada) ou do certificado dela (gov.br ou
 * ICP-Brasil). O lacre prova outra coisa: que o ARQUIVO não mudou depois de
 * emitido pelo clube, e quando — e isso qualquer pessoa confere em
 * validar.iti.gov.br, sem acesso ao Lara.
 *
 * Entra por atualização incremental (Pki\PdfSignatureWriter): no PDF do
 * gov.br, as assinaturas das pessoas continuam válidas, e o lacre vira mais
 * uma.
 *
 * Ligar `signature.pades.enabled` sem certificado utilizável (ausente, senha
 * errada, vencido) ou com a ACT fora do ar FALHA ALTO: um lacre que
 * silenciosamente não acontece é pior que lacre nenhum, porque alguém passa a
 * contar com ele. A finalização tenta de novo (FinalizeSignatureDocument).
 */
class SignaturePdfSealer
{
    private const OID_TIMESTAMP_TOKEN = '1.2.840.113549.1.9.16.2.14';

    public function __construct(
        private PdfSignatureWriter $writer,
        private TimestampClient $timestamps,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('signature.pades.enabled', false);
    }

    public function timestampsEnabled(): bool
    {
        return $this->isEnabled() && (string) config('signature.pades.tsa_url') !== '';
    }

    /**
     * Devolve os bytes a gravar: lacrados, ou os mesmos, com o lacre desligado.
     *
     * @throws RuntimeException  lacre ligado e impossível de aplicar
     */
    public function seal(string $pdf, ?SignatureDocument $document = null): string
    {
        if (!$this->isEnabled()) {
            return $pdf;
        }

        $certificado = $this->certificate();

        $motivo = (string) config('signature.pades.reason', 'Lacre do documento emitido pelo Lara');
        if ($document?->validation_code) {
            $motivo .= ' — código ' . $document->validation_code;
        }

        return $this->writer->sign(
            $pdf,
            fn(string $conteudo) => $this->cms($conteudo, $certificado),
            [
                'name' => Certificates::commonName($certificado['cert']),
                'reason' => $motivo,
                'reserve' => (int) config('signature.pades.reserve_bytes', 32768),
            ],
        );
    }

    /**
     * O certificado do lacre, conferido: arquivo existe, senha abre, está
     * dentro da validade.
     *
     * @return array{cert: string, key: \OpenSSLAsymmetricKey, chain: array<int, string>}
     *
     * @throws RuntimeException
     */
    public function certificate(): array
    {
        $caminho = (string) config('signature.pades.certificate_path');

        if ($caminho === '') {
            throw new RuntimeException('Lacre sem certificado: informe o caminho do .pfx em SIGNATURE_PADES_CERTIFICATE.');
        }

        if (!is_file($caminho) || !is_readable($caminho)) {
            throw new RuntimeException('Certificado do lacre não encontrado ou ilegível: ' . $caminho . '.');
        }

        $partes = [];

        while (openssl_error_string() !== false) {
        }

        if (!openssl_pkcs12_read((string) file_get_contents($caminho), $partes, (string) config('signature.pades.certificate_password'))) {
            $erros = '';
            while (($erro = openssl_error_string()) !== false) {
                $erros .= ' ' . $erro;
            }

            /*
             | Muitos .pfx A1 de certificadoras vêm cifrados com RC2/3DES. O
             | OpenSSL 3 não abre isso sem o provedor "legacy", e o erro
             | pareceria senha errada. A saída é converter o arquivo uma vez.
             */
            if (stripos($erros, 'unsupported') !== false || stripos($erros, 'RC2') !== false) {
                throw new RuntimeException('O .pfx usa criptografia antiga (RC2/3DES), que o OpenSSL 3 não abre. '
                    . 'Converta uma vez: openssl pkcs12 -legacy -in original.pfx -nodes -out tmp.pem && '
                    . 'openssl pkcs12 -export -in tmp.pem -out clube.pfx -certpbe AES-256-CBC -keypbe AES-256-CBC -macalg sha256 '
                    . '&& apague o tmp.pem (ver docs/funcionalidades/assinatura-eletronica.md).');
            }

            throw new RuntimeException('Não foi possível abrir o certificado do lacre: senha errada ou arquivo que não é .pfx/.p12.');
        }

        $info = openssl_x509_parse($partes['cert']) ?: [];
        $agora = time();

        if ($agora < ($info['validFrom_time_t'] ?? PHP_INT_MAX) || $agora > ($info['validTo_time_t'] ?? 0)) {
            throw new RuntimeException('O certificado do lacre está fora da validade (vence/venceu em '
                . date('d/m/Y', (int) ($info['validTo_time_t'] ?? 0)) . ').');
        }

        $chave = openssl_pkey_get_private($partes['pkey']);

        if ($chave === false) {
            throw new RuntimeException('O certificado do lacre não traz a chave privada.');
        }

        return [
            'cert' => $partes['cert'],
            'key' => $chave,
            'chain' => array_values($partes['extracerts'] ?? []),
        ];
    }

    /**
     * CMS destacado dos bytes cobertos — com a cadeia do certificado, para o
     * validador não precisar buscá-la, e com o carimbo de tempo, se houver ACT.
     *
     * @param  array{cert: string, key: \OpenSSLAsymmetricKey, chain: array<int, string>}  $certificado
     */
    private function cms(string $conteudo, array $certificado): string
    {
        $dir = sys_get_temp_dir();
        $entrada = tempnam($dir, 'lacre');
        $saida = tempnam($dir, 'lacre');
        $cadeia = tempnam($dir, 'lacre');

        try {
            file_put_contents($entrada, $conteudo);
            file_put_contents($cadeia, implode("\n", $certificado['chain']));

            while (openssl_error_string() !== false) {
            }

            $ok = openssl_cms_sign(
                $entrada,
                $saida,
                $certificado['cert'],
                $certificado['key'],
                [],
                OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY,
                OPENSSL_ENCODING_DER,
                $certificado['chain'] === [] ? null : $cadeia,
            );

            if (!$ok) {
                throw new RuntimeException('Falha ao assinar o lacre: ' . (openssl_error_string() ?: 'erro do OpenSSL'));
            }

            $cms = (string) file_get_contents($saida);
        } finally {
            @unlink($entrada);
            @unlink($saida);
            @unlink($cadeia);
        }

        return $this->timestampsEnabled() ? $this->withTimestamp($cms) : $cms;
    }

    /**
     * Acrescenta o carimbo de tempo como atributo NÃO assinado do SignerInfo
     * (id-aa-signatureTimeStampToken), carimbando o valor da assinatura — é o
     * que o PAdES e a RFC 3161 pedem. A assinatura não muda: atributos não
     * assinados ficam fora do que ela cobre.
     */
    private function withTimestamp(string $cms): string
    {
        $raiz = Asn1::node($cms);
        [$oid, $explicito] = Asn1::children($cms, $raiz);
        $signedData = Asn1::children($cms, $explicito)[0];
        $campos = Asn1::children($cms, $signedData);
        $signerInfos = end($campos);
        $signerInfo = Asn1::children($cms, $signerInfos)[0];
        $partes = Asn1::children($cms, $signerInfo);

        $assinatura = null;
        foreach ($partes as $parte) {
            if ($parte['tag'] === Asn1::OCTET_STRING) {
                $assinatura = Asn1::content($cms, $parte);
            }
        }

        if ($assinatura === null) {
            throw new RuntimeException('CMS do lacre sem o valor da assinatura.');
        }

        $token = $this->timestamps->stamp(hash('sha256', $assinatura, true));

        $atributo = Der::sequence(Der::oid(self::OID_TIMESTAMP_TOKEN), Der::set($token));
        $novoSignerInfo = Der::tlv(0x30, Asn1::content($cms, $signerInfo) . Der::tlv(0xA1, $atributo));

        $antes = '';
        foreach (array_slice($campos, 0, -1) as $campo) {
            $antes .= Asn1::raw($cms, $campo);
        }

        return Der::sequence(
            Asn1::raw($cms, $oid),
            Der::tlv(0xA0, Der::sequence($antes, Der::set($novoSignerInfo))),
        );
    }
}
