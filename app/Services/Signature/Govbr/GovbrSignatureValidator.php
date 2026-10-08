<?php

namespace App\Services\Signature\Govbr;

use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Support\Cpf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Confere um PDF assinado no portal do gov.br (assinador.iti.br) contra um
 * documento do Lara.
 *
 * Responde a duas perguntas, por meios que não dependem de confiar em quem
 * devolveu o arquivo:
 *
 *  - **É este documento?** O assinador do gov.br não reescreve o PDF: ele
 *    ACRESCENTA a assinatura ao fim (atualização incremental). Então o arquivo
 *    devolvido começa, byte a byte, pelo PDF que o Lara gerou — o original, ou
 *    o final assinado no tablet. E cada assinatura tem de cobrir esse trecho
 *    inteiro, e a última, o arquivo até o fim: qualquer coisa acrescentada
 *    depois dela reprova.
 *  - **É a pessoa certa?** O certificado da assinatura traz o CPF num campo
 *    próprio (otherName 2.16.76.1.3.1, leiaute ICP-Brasil: 8 dígitos de
 *    nascimento + CPF + NIS + RG), e ele é comparado com o CPF dos
 *    signatários — nunca o nome. O certificado tem de subir, assinatura por
 *    assinatura, até a AC Raiz do gov.br guardada no repositório
 *    (`resources/certs/govbr`), válido na hora em que assinou.
 *
 * Tudo o que está aqui foi confirmado numa amostra real na Fase 0 (ver
 * docs/funcionalidades/assinatura-eletronica.md): CMS em BER com comprimento
 * indefinido, `adbe.pkcs7.detached`, sha256 + RSA, hora no atributo assinado
 * `signingTime`, sem carimbo de tempo.
 *
 * A revogação NÃO é conferida nesta versão — o resultado diz isso com todas as
 * letras, em vez de ficar calado.
 *
 * Não grava nada: quem guarda o arquivo e o resultado é o GovbrCheckService.
 */
class GovbrSignatureValidator
{
    /** CPF no certificado (leiaute ICP-Brasil, que o gov.br também usa). */
    public const OID_CPF = '2.16.76.1.3.1';

    private const OID_SUBJECT_ALT_NAME = '2.5.29.17';
    private const OID_SIGNING_TIME = '1.2.840.113549.1.9.5';

    /**
     * Folga entre o relógio do gov.br e o do Lara: uma assinatura "antes" do
     * congelamento por poucos segundos é relógio, não fraude.
     */
    private const CLOCK_SKEW_SECONDS = 300;

    public function validate(string $pdf, SignatureDocument $document): GovbrValidationResult
    {
        $checks = [];

        if (!str_starts_with($pdf, '%PDF-')) {
            $checks[] = $this->check('arquivo', 'O arquivo é um PDF', false, 'O arquivo enviado não é um PDF.');

            return new GovbrValidationResult($checks, [], null);
        }

        $base = $this->base($pdf, $document);

        $checks[] = $this->check(
            'documento',
            'É este documento',
            $base !== null,
            match ($base['tipo'] ?? null) {
                'original' => 'O arquivo começa, byte a byte, pelo PDF original deste documento.',
                'final' => 'O arquivo começa, byte a byte, pelo PDF assinado no tablet deste documento.',
                default => 'O arquivo não foi gerado a partir deste documento, ou foi alterado antes de ser assinado.',
            },
        );

        $assinaturas = $this->signatures($pdf);

        if ($assinaturas === []) {
            $checks[] = $this->check(
                'assinatura',
                'Tem assinatura digital',
                false,
                'O PDF não tem assinatura digital. Parece o documento sem assinar, ou um arquivo impresso e '
                . 'escaneado: a pessoa precisa assinar em assinador.iti.br e enviar o arquivo baixado de lá.',
            );

            return new GovbrValidationResult($checks, [], $base['tipo'] ?? null);
        }

        $checks[] = $this->check(
            'assinatura',
            'Tem assinatura digital',
            true,
            count($assinaturas) === 1 ? '1 assinatura.' : count($assinaturas) . ' assinaturas.',
        );

        $signers = $document->relationLoaded('signers') ? $document->signers : $document->signers()->get();
        $confianca = $this->trust();

        $resultado = [];
        foreach ($assinaturas as $i => $assinatura) {
            $resultado[] = $this->inspect($pdf, $assinatura, $i + 1, $base, $signers, $document, $confianca);
        }

        $ultima = end($assinaturas);
        $fim = $ultima['range'][2] + $ultima['range'][3];

        $checks[] = $this->check(
            'alteracao',
            'Nada foi alterado depois da última assinatura',
            $fim === strlen($pdf),
            $fim === strlen($pdf)
                ? 'A última assinatura cobre o arquivo até o último byte.'
                : 'Há ' . (strlen($pdf) - $fim) . ' bytes acrescentados depois da última assinatura.',
        );

        $checks[] = $this->check(
            'revogacao',
            'Certificado não revogado',
            null,
            'Não conferido nesta versão. A lista de revogação do gov.br é pública e pode ser consultada à parte.',
        );

        return new GovbrValidationResult($checks, $resultado, $base['tipo'] ?? null);
    }

    /**
     * Sobre qual PDF do Lara a assinatura foi feita: o arquivo devolvido tem de
     * COMEÇAR por ele, byte a byte.
     *
     * O arquivo guardado é conferido contra o hash gravado antes de servir de
     * referência — um arquivo de referência adulterado no disco não pode
     * aprovar nada.
     *
     * @return array{tipo: string, tamanho: int}|null
     */
    private function base(string $pdf, SignatureDocument $document): ?array
    {
        $disk = Storage::disk(config('signature.disk'));

        $candidatos = [
            'original' => [$document->original_path, $document->original_sha256],
            'final' => [$document->final_path, $document->final_sha256],
        ];

        foreach ($candidatos as $tipo => [$caminho, $hash]) {
            if (!$caminho || !$hash || !$disk->exists($caminho)) {
                continue;
            }

            $referencia = (string) $disk->get($caminho);

            if (!hash_equals($hash, hash('sha256', $referencia))) {
                Log::warning('Assinatura gov.br: PDF de referência não confere com o hash gravado.', [
                    'document_id' => $document->id,
                    'tipo' => $tipo,
                ]);

                continue;
            }

            // Igual também conta: é o caso de quem devolve o original sem
            // assinar, e a tela deve dizer "é este documento, sem assinatura".
            if (str_starts_with($pdf, $referencia)) {
                return ['tipo' => $tipo, 'tamanho' => strlen($referencia)];
            }
        }

        return null;
    }

    /**
     * As assinaturas do PDF, na ordem em que foram feitas (a revisão que cada
     * uma cobre termina mais adiante no arquivo).
     *
     * @return array<int, array{range: array{0: int, 1: int, 2: int, 3: int}, cms: ?string}>
     */
    private function signatures(string $pdf): array
    {
        preg_match_all('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $achados, PREG_SET_ORDER);

        $tamanho = strlen($pdf);
        $assinaturas = [];

        foreach ($achados as $m) {
            $range = [(int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]];
            [, $b, $c, $d] = $range;

            // O buraco entre os dois trechos é o /Contents: <hex da assinatura>.
            $geometriaOk = $b > 0 && $c > $b + 1 && $c + $d <= $tamanho
                && $pdf[$b] === '<' && $pdf[$c - 1] === '>';

            $assinaturas[] = [
                'range' => $range,
                'cms' => $geometriaOk ? $this->cms(substr($pdf, $b + 1, $c - $b - 2)) : null,
            ];
        }

        usort($assinaturas, fn(array $x, array $y) => ($x['range'][2] + $x['range'][3]) <=> ($y['range'][2] + $y['range'][3]));

        return $assinaturas;
    }

    /**
     * O CMS dentro do /Contents, sem o enchimento de zeros que o reserva.
     *
     * Não dá para simplesmente cortar os zeros do fim: em BER indefinido o
     * próprio CMS termina em 00 00. Quem diz onde ele acaba é a estrutura.
     */
    private function cms(string $hex): ?string
    {
        $limpo = (string) preg_replace('/[^0-9A-Fa-f]/', '', $hex);

        // Ímpar não é hexadecimal de bytes — e o hex2bin, aqui, viraria exceção.
        if ($limpo === '' || strlen($limpo) % 2 !== 0) {
            return null;
        }

        $bytes = (string) hex2bin($limpo);

        try {
            return Asn1::raw($bytes, Asn1::node($bytes));
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * As conferências de uma assinatura.
     *
     * @param  array{range: array{0: int, 1: int, 2: int, 3: int}, cms: ?string}  $assinatura
     * @param  array{tipo: string, tamanho: int}|null  $base
     * @param  Collection<int, SignatureSigner>  $signers
     * @param  array{roots: array<int, string>, intermediates: array<int, string>}  $confianca
     * @return array<string, mixed>
     */
    private function inspect(
        string $pdf,
        array $assinatura,
        int $ordem,
        ?array $base,
        Collection $signers,
        SignatureDocument $document,
        array $confianca,
    ): array {
        [$a, $b, $c, $d] = $assinatura['range'];
        $checks = [];

        $saida = [
            'order' => $ordem,
            'name' => null,
            'cpf' => null,
            'signer_id' => null,
            'signer_name' => null,
            'signed_at' => null,
            'issuer' => null,
            'serial' => null,
            'checks' => [],
        ];

        if ($assinatura['cms'] === null) {
            $saida['checks'][] = $this->check(
                'integridade',
                'A assinatura confere com o conteúdo',
                false,
                'A assinatura está corrompida ou num formato que o Lara não lê.',
            );

            return $saida;
        }

        $cobre = $a === 0 && $base !== null && $b >= $base['tamanho'];

        $checks[] = $this->check(
            'abrange',
            'A assinatura cobre o documento inteiro',
            $base === null ? null : $cobre,
            $base === null
                ? 'Sem o documento de referência, não há como dizer.'
                : ($cobre
                    ? 'O trecho assinado contém o documento inteiro.'
                    : 'A assinatura não cobre o documento inteiro.'),
        );

        [$integra, $certificado, $embutidos] = $this->verifyCms(substr($pdf, $a, $b) . substr($pdf, $c, $d), $assinatura['cms']);

        $checks[] = $this->check(
            'integridade',
            'A assinatura confere com o conteúdo',
            $integra,
            $integra
                ? 'O conteúdo assinado não mudou desde a assinatura.'
                : 'O conteúdo não confere com a assinatura: o arquivo foi alterado depois de assinado.',
        );

        if ($certificado === null) {
            $checks[] = $this->check('certificado', 'Certificado do signatário', false, 'A assinatura não traz o certificado de quem assinou.');
            $saida['checks'] = $checks;

            return $saida;
        }

        $info = openssl_x509_parse($certificado) ?: [];
        $quando = $this->signingTime($assinatura['cms']);

        $saida['name'] = $this->first($info['subject']['CN'] ?? null);
        $saida['issuer'] = $this->first($info['issuer']['CN'] ?? null);
        $saida['serial'] = $info['serialNumberHex'] ?? null;
        $saida['signed_at'] = $quando === null ? null : date(DATE_ATOM, $quando);

        [$cadeiaOk, $cadeiaDetalhe] = $this->chain($certificado, $embutidos, $confianca, $quando ?? time());

        $checks[] = $this->check('cadeia', 'Certificado emitido pelo gov.br', $cadeiaOk, $cadeiaDetalhe);

        $cpf = $this->cpf($certificado);
        $signer = $cpf === null ? null : $signers->first(fn(SignatureSigner $s) => Cpf::digits($s->cpf) === $cpf);

        $saida['cpf'] = $cpf === null ? null : Cpf::mask($cpf);
        $saida['signer_id'] = $signer?->id;
        $saida['signer_name'] = $signer?->name;

        $checks[] = $this->check(
            'cpf',
            'Assinada por um signatário deste documento',
            $signer !== null,
            match (true) {
                $cpf === null => 'O certificado não traz CPF.',
                $signer !== null => 'CPF ' . Cpf::mask($cpf) . ' é de ' . $signer->name . '.',
                default => 'CPF ' . Cpf::mask($cpf) . ' não é de nenhum signatário deste documento.',
            },
        );

        $congelado = $document->frozen_at?->getTimestamp();

        $checks[] = $this->check(
            'data',
            'Assinada depois de o documento ser congelado',
            $quando === null || $congelado === null ? null : $quando >= $congelado - self::CLOCK_SKEW_SECONDS,
            $quando === null
                ? 'A assinatura não declara a hora em que foi feita.'
                : 'Assinada em ' . date('d/m/Y H:i:s', $quando) . ' (hora declarada pelo gov.br).',
        );

        $saida['checks'] = $checks;

        return $saida;
    }

    /**
     * Confere a assinatura contra o conteúdo do ByteRange e devolve o
     * certificado de quem assinou e os demais embutidos.
     *
     * A cadeia NÃO é conferida aqui (NOVERIFY): o OpenSSL a conferiria na hora
     * de agora e com propósito S/MIME, e o que interessa é a validade na hora
     * da assinatura, até a raiz do gov.br — ver chain().
     *
     * @return array{0: bool, 1: ?string, 2: array<int, string>}
     */
    private function verifyCms(string $conteudo, string $cms): array
    {
        $dir = sys_get_temp_dir();
        $arquivoConteudo = tempnam($dir, 'govbr');
        $arquivoAssinatura = tempnam($dir, 'govbr');
        $arquivoSignatarios = tempnam($dir, 'govbr');

        try {
            file_put_contents($arquivoConteudo, $conteudo);
            file_put_contents($arquivoAssinatura, $cms);

            while (openssl_error_string() !== false) {
                // esvazia a fila de erros de chamadas anteriores
            }

            $ok = openssl_cms_verify(
                $arquivoConteudo,
                OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY | OPENSSL_CMS_NOVERIFY,
                $arquivoSignatarios,
                [],
                null,
                null,
                null,
                $arquivoAssinatura,
                OPENSSL_ENCODING_DER,
            ) === true;

            while (openssl_error_string() !== false) {
            }

            $embutidos = [];
            openssl_cms_read(
                "-----BEGIN CMS-----\n" . chunk_split(base64_encode($cms), 64, "\n") . "-----END CMS-----\n",
                $embutidos,
            );

            $signatario = $this->pems((string) @file_get_contents($arquivoSignatarios))[0]
                ?? $this->leafAmong($embutidos);

            return [$ok, $signatario, $embutidos];
        } finally {
            @unlink($arquivoConteudo);
            @unlink($arquivoAssinatura);
            @unlink($arquivoSignatarios);
        }
    }

    /**
     * Sobe do certificado do signatário até a raiz do gov.br, conferindo a
     * assinatura de cada elo com a chave do emissor e a validade de cada um na
     * HORA DA ASSINATURA.
     *
     * Um certificado embutido no CMS pode servir de elo, mas não de âncora: a
     * âncora é só a raiz do arquivo do repositório. Um elo forjado com o nome
     * da AC do gov.br não passa, porque a chave dele não confere com a de cima.
     *
     * @param  array<int, string>  $embutidos
     * @param  array{roots: array<int, string>, intermediates: array<int, string>}  $confianca
     * @return array{0: bool, 1: string}
     */
    private function chain(string $certificado, array $embutidos, array $confianca, int $quando): array
    {
        if ($confianca['roots'] === []) {
            return [false, 'A cadeia do gov.br não está instalada no Lara (resources/certs/govbr).'];
        }

        $raizes = array_map(fn(string $pem) => openssl_x509_fingerprint($pem, 'sha256'), $confianca['roots']);
        $candidatos = array_merge($confianca['roots'], $confianca['intermediates'], $embutidos);
        $atual = $certificado;

        for ($nivel = 0; $nivel < 6; $nivel++) {
            $info = openssl_x509_parse($atual) ?: [];
            $nome = $this->first($info['subject']['CN'] ?? null) ?? 'certificado';

            if ($quando < ($info['validFrom_time_t'] ?? PHP_INT_MAX) || $quando > ($info['validTo_time_t'] ?? 0)) {
                return [false, 'O certificado "' . $nome . '" não era válido em ' . date('d/m/Y H:i', $quando) . '.'];
            }

            if (in_array(openssl_x509_fingerprint($atual, 'sha256'), $raizes, true)) {
                return [true, 'Cadeia até a AC Raiz do gov.br, válida na hora da assinatura.'];
            }

            $emissor = null;

            foreach ($candidatos as $candidato) {
                $ci = openssl_x509_parse($candidato) ?: [];

                if (($ci['subject'] ?? null) == ($info['issuer'] ?? null)
                    && str_contains((string) ($ci['extensions']['basicConstraints'] ?? ''), 'CA:TRUE')
                    && openssl_x509_verify($atual, $candidato) === 1) {
                    $emissor = $candidato;
                    break;
                }
            }

            if ($emissor === null) {
                return [false, 'O certificado não foi emitido pela AC do gov.br.'];
            }

            $atual = $emissor;
        }

        return [false, 'Cadeia de certificados longa demais.'];
    }

    /**
     * CPF do otherName 2.16.76.1.3.1 do subjectAltName.
     *
     * O valor são dígitos no leiaute ICP-Brasil: data de nascimento (8), CPF
     * (11), NIS (11), RG (15). O CPF é o que vem nas posições 8 a 18.
     */
    private function cpf(string $certificadoPem): ?string
    {
        if (!openssl_x509_export($certificadoPem, $pem)) {
            return null;
        }

        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s/', '', $pem));

        try {
            $certificado = Asn1::node($der);
            $tbs = Asn1::children($der, $certificado)[0] ?? null;

            foreach ($tbs ? Asn1::children($der, $tbs) : [] as $campo) {
                // [3] EXPLICIT Extensions
                if ($campo['tag'] !== 0xA3) {
                    continue;
                }

                $extensoes = Asn1::children($der, $campo)[0] ?? null;

                foreach ($extensoes ? Asn1::children($der, $extensoes) : [] as $extensao) {
                    $partes = Asn1::children($der, $extensao);

                    if (Asn1::oid($der, $partes[0]) !== self::OID_SUBJECT_ALT_NAME) {
                        continue;
                    }

                    $valor = Asn1::content($der, end($partes));
                    $nomes = Asn1::node($valor);

                    foreach (Asn1::children($valor, $nomes) as $nome) {
                        // [0] otherName { type-id OID, [0] EXPLICIT valor }
                        if ($nome['tag'] !== 0xA0) {
                            continue;
                        }

                        $outro = Asn1::children($valor, $nome);

                        if (count($outro) < 2 || Asn1::oid($valor, $outro[0]) !== self::OID_CPF) {
                            continue;
                        }

                        $interno = Asn1::children($valor, $outro[1])[0] ?? null;
                        $digitos = $interno ? preg_replace('/\D/', '', Asn1::content($valor, $interno)) : '';

                        return strlen((string) $digitos) >= 19 ? substr($digitos, 8, 11) : null;
                    }
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * A hora do atributo assinado `signingTime` do primeiro SignerInfo.
     *
     * É a hora declarada pelo serviço de assinatura do gov.br, e não um
     * carimbo de tempo independente — a tela diz isso.
     */
    private function signingTime(string $cms): ?int
    {
        try {
            $raiz = Asn1::node($cms);
            $conteudo = Asn1::children($cms, $raiz)[1] ?? null;            // [0] EXPLICIT
            $signedData = $conteudo ? (Asn1::children($cms, $conteudo)[0] ?? null) : null;
            $campos = $signedData ? Asn1::children($cms, $signedData) : [];
            $signerInfos = end($campos);                                       // SET, o último campo

            if (!$signerInfos || $signerInfos['tag'] !== Asn1::SET) {
                return null;
            }

            $signerInfo = Asn1::children($cms, $signerInfos)[0] ?? null;

            foreach ($signerInfo ? Asn1::children($cms, $signerInfo) : [] as $campo) {
                // [0] IMPLICIT signedAttrs
                if ($campo['tag'] !== 0xA0) {
                    continue;
                }

                foreach (Asn1::children($cms, $campo) as $atributo) {
                    $partes = Asn1::children($cms, $atributo);

                    if (count($partes) === 2 && Asn1::oid($cms, $partes[0]) === self::OID_SIGNING_TIME) {
                        $valor = Asn1::children($cms, $partes[1])[0] ?? null;

                        return $valor ? Asn1::time($cms, $valor) : null;
                    }
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * A cadeia do gov.br guardada no repositório: raízes (auto-assinadas) e
     * intermediárias.
     *
     * @return array{roots: array<int, string>, intermediates: array<int, string>}
     */
    private function trust(): array
    {
        $arquivo = (string) config('signature.govbr.trust_bundle');
        $pems = is_file($arquivo) ? $this->pems((string) file_get_contents($arquivo)) : [];

        $roots = [];
        $intermediates = [];

        foreach ($pems as $pem) {
            $info = openssl_x509_parse($pem) ?: [];

            if (($info['subject'] ?? null) == ($info['issuer'] ?? null) && openssl_x509_verify($pem, $pem) === 1) {
                $roots[] = $pem;
            } else {
                $intermediates[] = $pem;
            }
        }

        return ['roots' => $roots, 'intermediates' => $intermediates];
    }

    /**
     * Sem a saída do OpenSSL, o signatário é o embutido que não emite nenhum
     * dos outros.
     *
     * @param  array<int, string>  $certs
     */
    private function leafAmong(array $certs): ?string
    {
        foreach ($certs as $cert) {
            $info = openssl_x509_parse($cert) ?: [];
            $emite = false;

            foreach ($certs as $outro) {
                if ($outro !== $cert && (openssl_x509_parse($outro)['issuer'] ?? null) == ($info['subject'] ?? null)) {
                    $emite = true;
                }
            }

            if (!$emite) {
                return $cert;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function pems(string $texto): array
    {
        preg_match_all('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', $texto, $m);

        return $m[0];
    }

    private function first(mixed $valor): ?string
    {
        return is_array($valor) ? ($valor[0] ?? null) : $valor;
    }

    /** @return array{key: string, label: string, ok: ?bool, detail: string} */
    private function check(string $key, string $label, ?bool $ok, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => $ok, 'detail' => $detail];
    }
}
