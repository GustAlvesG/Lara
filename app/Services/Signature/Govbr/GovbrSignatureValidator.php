<?php

namespace App\Services\Signature\Govbr;

use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Services\Signature\Pki\Certificates;
use App\Services\Signature\Pki\PkiRepository;
use App\Services\Signature\Pki\RevocationChecker;
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
 *    assinatura, até uma raiz guardada no repositório, válido na hora em que
 *    assinou e na hora da conferência, e não pode estar revogado.
 *
 * **Duas âncoras, dois tipos de assinatura** (Lei 14.063/2020):
 *
 *  - a raiz do gov.br (`resources/certs/govbr`) — assinatura **avançada**,
 *    a do portal assinador.iti.br;
 *  - as raízes da ICP-Brasil (`resources/certs/icp-brasil`) — assinatura
 *    **qualificada**, a de quem assina com e-CPF (A1, A3 ou em nuvem) em
 *    qualquer programa que faça atualização incremental do PDF. O CPF fica no
 *    mesmo otherName. A AC intermediária que a assinatura não trouxer é
 *    buscada no endereço que o próprio certificado declara (AIA).
 *
 * O nome da classe ficou do primeiro caso; o resultado diz qual dos dois
 * (`kind`: `govbr` ou `icp-brasil`).
 *
 * O formato do gov.br foi confirmado numa amostra real na Fase 0 (ver
 * docs/funcionalidades/assinatura-eletronica.md): CMS em BER com comprimento
 * indefinido, `adbe.pkcs7.detached`, sha256 + RSA, hora no atributo assinado
 * `signingTime`, sem carimbo de tempo. Assinaturas PAdES (`ETSI.CAdES.detached`)
 * não levam `signingTime`: a hora vem do `/M` do dicionário da assinatura.
 *
 * A revogação é conferida pela LCR de cada certificado da cadeia
 * (Pki\RevocationChecker) — e é a ÚNICA parte que usa a rede. Lista fora do ar
 * não reprova (a menos que a instalação exija), mas aparece como não conferida.
 *
 * Não grava nada: quem guarda o arquivo e o resultado é o GovbrCheckService.
 */
class GovbrSignatureValidator
{
    /** CPF no certificado (leiaute ICP-Brasil, que o gov.br também usa). */
    public const OID_CPF = '2.16.76.1.3.1';

    /** De que raiz veio o certificado — e, com isso, o tipo de assinatura. */
    public const KIND_GOVBR = 'govbr';
    public const KIND_ICP_BRASIL = 'icp-brasil';

    private const OID_SUBJECT_ALT_NAME = '2.5.29.17';
    private const OID_SIGNING_TIME = '1.2.840.113549.1.9.5';

    /**
     * Folga entre o relógio do gov.br e o do Lara: uma assinatura "antes" do
     * congelamento por poucos segundos é relógio, não fraude.
     */
    private const CLOCK_SKEW_SECONDS = 300;

    public function __construct(
        private RevocationChecker $revocation,
        private PkiRepository $repository,
    ) {
    }

    /** Rótulo do tipo de assinatura, para tela e relatório. */
    public static function kindLabel(?string $kind): ?string
    {
        return match ($kind) {
            self::KIND_GOVBR => 'gov.br — assinatura eletrônica avançada',
            self::KIND_ICP_BRASIL => 'Certificado ICP-Brasil — assinatura eletrônica qualificada',
            default => null,
        };
    }

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
     * @return array<int, array{range: array{0: int, 1: int, 2: int, 3: int}, cms: ?string, m: ?int}>
     */
    private function signatures(string $pdf): array
    {
        preg_match_all('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $achados, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $tamanho = strlen($pdf);
        $assinaturas = [];

        foreach ($achados as $m) {
            $range = [(int) $m[1][0], (int) $m[2][0], (int) $m[3][0], (int) $m[4][0]];
            [, $b, $c, $d] = $range;

            // O buraco entre os dois trechos é o /Contents: <hex da assinatura>.
            $geometriaOk = $b > 0 && $c > $b + 1 && $c + $d <= $tamanho
                && $pdf[$b] === '<' && $pdf[$c - 1] === '>';

            $assinaturas[] = [
                'range' => $range,
                'cms' => $geometriaOk ? $this->cms(substr($pdf, $b + 1, $c - $b - 2)) : null,
                'm' => $this->declaredTime($pdf, $m[0][1]),
            ];
        }

        usort($assinaturas, fn(array $x, array $y) => ($x['range'][2] + $x['range'][3]) <=> ($y['range'][2] + $y['range'][3]));

        return $assinaturas;
    }

    /**
     * O `/M` do dicionário da assinatura que contém o /ByteRange em $posicao —
     * a hora que o programa de assinatura declarou. É a fonte da hora quando o
     * CMS não traz `signingTime` (PAdES, `ETSI.CAdES.detached`, proíbe o
     * atributo).
     *
     * O dicionário é o trecho entre o "obj" anterior e o "endobj" seguinte.
     */
    private function declaredTime(string $pdf, int $posicao): ?int
    {
        $inicio = strrpos(substr($pdf, max(0, $posicao - 4096), min($posicao, 4096)), ' obj');
        $inicio = $inicio === false ? max(0, $posicao - 4096) : max(0, $posicao - 4096) + $inicio;
        $fim = strpos($pdf, 'endobj', $posicao);
        $trecho = substr($pdf, $inicio, ($fim === false ? $posicao + 4096 : $fim) - $inicio);

        // O /Contents é hex e não tem parênteses; o /M é a única data do dicionário.
        if (!preg_match("/\/M\s*\(D:(\d{4})(\d{2})?(\d{2})?(\d{2})?(\d{2})?(\d{2})?([Zz+\-])?(\d{2})?'?(\d{2})?'?\)/", $trecho, $d)) {
            return null;
        }

        $data = sprintf(
            '%s-%s-%s %s:%s:%s',
            $d[1], $d[2] ?: '01', $d[3] ?: '01', $d[4] ?: '00', $d[5] ?: '00', $d[6] ?: '00',
        );

        $sinal = $d[7] ?? '';
        $fuso = in_array($sinal, ['+', '-'], true)
            ? $sinal . ($d[8] ?? '00') . ':' . (($d[9] ?? '') !== '' ? $d[9] : '00')
            : '+00:00';

        $quando = \DateTimeImmutable::createFromFormat('Y-m-d H:i:sP', $data . $fuso);

        return $quando === false ? null : $quando->getTimestamp();
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
     * @param  array{roots: array<string, array{pem: string, kind: string}>, intermediates: array<int, string>}  $confianca
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
            'kind' => null,
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

        // A hora: o atributo assinado `signingTime` (gov.br) ou, sem ele, o /M
        // do dicionário (PAdES). As duas são DECLARADAS por quem assinou.
        $quando = $this->signingTime($assinatura['cms']);
        $fonteHora = 'signingTime';

        if ($quando === null && $assinatura['m'] !== null) {
            $quando = $assinatura['m'];
            $fonteHora = 'pdf';
        }

        $saida['name'] = $this->first($info['subject']['CN'] ?? null);
        $saida['issuer'] = $this->first($info['issuer']['CN'] ?? null);
        $saida['serial'] = $info['serialNumberHex'] ?? null;
        $saida['signed_at'] = $quando === null ? null : date(DATE_ATOM, $quando);

        [$cadeiaOk, $cadeiaDetalhe, $caminho, $tipo] = $this->chain($certificado, $embutidos, $confianca, $quando ?? time());

        $saida['kind'] = $cadeiaOk ? $tipo : null;

        $checks[] = $this->check('cadeia', 'Certificado do gov.br ou da ICP-Brasil', $cadeiaOk, $cadeiaDetalhe);

        // Revogação só faz sentido com a cadeia montada: é a AC de cima que
        // assina a lista do certificado de baixo.
        if ($cadeiaOk) {
            $revogacao = $this->revocation->check($caminho);
            $checks[] = $this->check('revogacao', 'Certificado não revogado', $revogacao['ok'], $revogacao['detail']);
        }

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
            match (true) {
                $quando === null => 'A assinatura não declara a hora em que foi feita.',
                $fonteHora === 'pdf' => 'Assinada em ' . date('d/m/Y H:i:s', $quando) . ' (hora declarada no PDF pelo programa de assinatura).',
                $tipo === self::KIND_GOVBR => 'Assinada em ' . date('d/m/Y H:i:s', $quando) . ' (hora declarada pelo gov.br).',
                default => 'Assinada em ' . date('d/m/Y H:i:s', $quando) . ' (hora declarada na assinatura).',
            },
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
     * Sobe do certificado do signatário até uma raiz do repositório (gov.br ou
     * ICP-Brasil), conferindo a assinatura de cada elo com a chave do emissor
     * e a validade de cada um na HORA DA ASSINATURA e na hora da conferência —
     * a hora da assinatura é declarada por quem assinou, e não pode ser a
     * única garantia de que o certificado valia.
     *
     * Um certificado embutido no CMS, ou baixado do endereço que o próprio
     * certificado declara (AIA), pode servir de elo, mas não de âncora: a
     * âncora é só uma raiz dos arquivos do repositório. Um elo forjado com o
     * nome de uma AC não passa, porque a chave dele não confere com a de cima.
     *
     * @param  array<int, string>  $embutidos
     * @param  array{roots: array<string, array{pem: string, kind: string}>, intermediates: array<int, string>}  $confianca
     * @return array{0: bool, 1: string, 2: array<int, string>, 3: ?string}  ok, detalhe, cadeia (do signatário à raiz), tipo
     */
    private function chain(string $certificado, array $embutidos, array $confianca, int $quando): array
    {
        if ($confianca['roots'] === []) {
            return [false, 'Nenhuma raiz de confiança instalada no Lara (resources/certs).', [], null];
        }

        $candidatos = array_merge(array_column($confianca['roots'], 'pem'), $confianca['intermediates'], $embutidos);
        $atual = $certificado;
        $caminho = [];
        $agora = time();

        for ($nivel = 0; $nivel < 8; $nivel++) {
            $info = openssl_x509_parse($atual) ?: [];
            $nome = $this->first($info['subject']['CN'] ?? null) ?? 'certificado';
            $caminho[] = $atual;

            foreach (array_unique([$quando, $agora]) as $momento) {
                if ($momento < ($info['validFrom_time_t'] ?? PHP_INT_MAX) || $momento > ($info['validTo_time_t'] ?? 0)) {
                    return [false, 'O certificado "' . $nome . '" não era válido em ' . date('d/m/Y H:i', $momento) . '.', $caminho, null];
                }
            }

            $raiz = $confianca['roots'][openssl_x509_fingerprint($atual, 'sha256')] ?? null;

            if ($raiz !== null) {
                return [
                    true,
                    $raiz['kind'] === self::KIND_GOVBR
                        ? 'Cadeia até a AC Raiz do gov.br, válida na hora da assinatura.'
                        : 'Cadeia até a "' . $nome . '" (ICP-Brasil), válida na hora da assinatura.',
                    $caminho,
                    $raiz['kind'],
                ];
            }

            $emissor = $this->issuerAmong($atual, $info, $candidatos);

            // Faltou elo: a AC intermediária que a assinatura não trouxe é
            // buscada onde o próprio certificado diz (AIA). Só serve se a
            // chave conferir — e a âncora continua sendo a do repositório.
            if ($emissor === null) {
                foreach (Certificates::issuerUrls($atual) as $url) {
                    $baixados = $this->repository->issuers($url);
                    $candidatos = array_merge($candidatos, $baixados);
                    $emissor = $this->issuerAmong($atual, $info, $baixados);

                    if ($emissor !== null) {
                        break;
                    }
                }
            }

            if ($emissor === null) {
                return [false, 'O certificado não foi emitido pelo gov.br nem por uma AC da ICP-Brasil.', $caminho, null];
            }

            $atual = $emissor;
        }

        return [false, 'Cadeia de certificados longa demais.', $caminho, null];
    }

    /**
     * O certificado de AC, entre os candidatos, que emitiu $atual: nome do
     * emissor igual, é AC, e a assinatura confere com a chave dele.
     *
     * @param  array<string, mixed>  $info  openssl_x509_parse de $atual
     * @param  array<int, string>  $candidatos
     */
    private function issuerAmong(string $atual, array $info, array $candidatos): ?string
    {
        foreach ($candidatos as $candidato) {
            $ci = openssl_x509_parse($candidato) ?: [];

            if (($ci['subject'] ?? null) == ($info['issuer'] ?? null)
                && str_contains((string) ($ci['extensions']['basicConstraints'] ?? ''), 'CA:TRUE')
                && openssl_x509_verify($atual, $candidato) === 1) {
                return $candidato;
            }
        }

        return null;
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
     * As âncoras guardadas no repositório: a cadeia do gov.br e as raízes da
     * ICP-Brasil. Cada raiz (auto-assinada) leva o tipo de assinatura que
     * ancora; o resto do arquivo vira elo intermediário.
     *
     * @return array{roots: array<string, array{pem: string, kind: string}>, intermediates: array<int, string>}
     */
    private function trust(): array
    {
        $arquivos = [
            self::KIND_GOVBR => (string) config('signature.govbr.trust_bundle'),
            self::KIND_ICP_BRASIL => (string) config('signature.icp_brasil.trust_bundle'),
        ];

        $roots = [];
        $intermediates = [];

        foreach ($arquivos as $tipo => $arquivo) {
            $pems = $arquivo !== '' && is_file($arquivo) ? $this->pems((string) file_get_contents($arquivo)) : [];

            foreach ($pems as $pem) {
                if (Certificates::isSelfSigned($pem)) {
                    $roots[(string) openssl_x509_fingerprint($pem, 'sha256')] = ['pem' => $pem, 'kind' => $tipo];
                } else {
                    $intermediates[] = $pem;
                }
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
