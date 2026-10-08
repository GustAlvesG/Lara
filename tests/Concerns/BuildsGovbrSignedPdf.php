<?php

namespace Tests\Concerns;

use RuntimeException;

/**
 * PDFs "assinados pelo gov.br" para os testes, com uma AC de teste.
 *
 * Reproduz o que a Fase 0 encontrou na amostra real: a assinatura é
 * ACRESCENTADA ao fim do PDF (atualização incremental), `adbe.pkcs7.detached`,
 * sha256 + RSA, com `signingTime` assinado, e o CPF no otherName
 * 2.16.76.1.3.1 do certificado, no leiaute ICP-Brasil (nascimento + CPF + NIS
 * + RG, 45 dígitos).
 *
 * As amostras reais não entram no repositório: têm CPF e e-mail de verdade.
 *
 * A atualização incremental não leva tabela xref — o PDF não abriria num
 * leitor, e não precisa: o validador lê o dicionário da assinatura e os bytes,
 * que é tudo o que está em teste aqui.
 */
trait BuildsGovbrSignedPdf
{
    /** Bytes de hex reservados para o CMS, como o /Contents de um assinador real. */
    private int $govbrReserva = 16384;

    /**
     * Uma AC raiz de teste.
     *
     * @return array{cert: string, key: string}
     */
    protected function govbrAc(string $nome = 'AC Raiz de Teste'): array
    {
        $config = $this->govbrOpensslConfig();
        $chave = $this->govbrChave($config);

        $csr = openssl_csr_new(['C' => 'BR', 'O' => 'Teste', 'CN' => $nome], $chave, ['config' => $config, 'digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $chave, 3650, ['config' => $config, 'x509_extensions' => 'ac', 'digest_alg' => 'sha256'], random_int(1, PHP_INT_MAX));

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($chave, $chavePem, null, ['config' => $config]);

        return ['cert' => $certPem, 'key' => $chavePem];
    }

    /** Faz da AC a única âncora de confiança do validador — o papel da cadeia do gov.br. */
    protected function govbrConfiaEm(array $ac): void
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'govbr-ac');
        file_put_contents($arquivo, $ac['cert']);

        config(['signature.govbr.trust_bundle' => $arquivo]);
    }

    /**
     * Certificado de pessoa física emitido pela AC, com o CPF no otherName.
     *
     * @param  array{cert: string, key: string}  $ac
     * @return array{cert: string, key: string}
     */
    protected function govbrCertificado(array $ac, string $cpf, string $nome = 'MARIA DE SOUZA', int $dias = 365): array
    {
        // Leiaute ICP-Brasil: nascimento (8) + CPF (11) + NIS (11) + RG (15).
        $valor = '01011990' . $cpf . str_repeat('0', 26);
        $config = $this->govbrOpensslConfig($valor);
        $chave = $this->govbrChave($config);

        $csr = openssl_csr_new(['CN' => $nome], $chave, ['config' => $config, 'digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, $ac['cert'], $ac['key'], $dias, ['config' => $config, 'x509_extensions' => 'folha', 'digest_alg' => 'sha256'], random_int(1, PHP_INT_MAX));

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($chave, $chavePem, null, ['config' => $config]);

        return ['cert' => $certPem, 'key' => $chavePem];
    }

    /**
     * Acrescenta uma assinatura ao fim do PDF, como o assinador do gov.br.
     *
     * @param  array{cert: string, key: string}  $certificado
     */
    protected function govbrAssina(string $pdf, array $certificado): string
    {
        $antes = $pdf
            . "\n" . random_int(900, 9999) . " 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached"
            . ' /M (D:' . gmdate('YmdHis') . "Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents ";
        $depois = " >>\nendobj\n%%EOF\n";

        $b = strlen($antes);
        $c = $b + $this->govbrReserva + 2;
        $intervalo = str_pad(sprintf('%d %d %d', $b, $c, strlen($depois)), 32);
        $antes = str_replace('AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC', $intervalo, $antes);

        $dir = sys_get_temp_dir();
        $conteudo = tempnam($dir, 'govbr');
        $saida = tempnam($dir, 'govbr');
        $cert = tempnam($dir, 'govbr');
        $chave = tempnam($dir, 'govbr');

        try {
            file_put_contents($conteudo, $antes . $depois);
            file_put_contents($cert, $certificado['cert']);
            file_put_contents($chave, $certificado['key']);

            if (!openssl_cms_sign($conteudo, $saida, 'file://' . $cert, 'file://' . $chave, [],
                OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY, OPENSSL_ENCODING_DER)) {
                throw new RuntimeException('Falha ao assinar o PDF de teste: ' . openssl_error_string());
            }

            $hex = str_pad(bin2hex((string) file_get_contents($saida)), $this->govbrReserva, '0');
        } finally {
            foreach ([$conteudo, $saida, $cert, $chave] as $arquivo) {
                @unlink($arquivo);
            }
        }

        return $antes . '<' . $hex . '>' . $depois;
    }

    /** @param  string  $config  caminho do openssl.cnf */
    private function govbrChave(string $config): \OpenSSLAsymmetricKey
    {
        return openssl_pkey_new([
            'config' => $config,
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
    }

    /**
     * Um openssl.cnf com as extensões da AC e do certificado de pessoa física.
     * Sem ele, o PHP no Windows não acha configuração nenhuma.
     */
    private function govbrOpensslConfig(string $valorCpf = '0'): string
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'govbr-cnf');

        file_put_contents($arquivo, <<<CNF
            [ req ]
            distinguished_name = dn
            [ dn ]
            [ ac ]
            basicConstraints = critical,CA:TRUE
            keyUsage = critical,keyCertSign,cRLSign
            subjectKeyIdentifier = hash
            [ folha ]
            basicConstraints = CA:FALSE
            keyUsage = digitalSignature
            subjectAltName = otherName:2.16.76.1.3.1;OCTETSTRING:{$valorCpf}
            CNF);

        return $arquivo;
    }
}
