<?php

namespace App\Services\Signature\Pki;

/**
 * Confere se algum certificado de uma cadeia foi revogado, pela Lista de
 * Certificados Revogados (LCR) que cada um declara.
 *
 * Três respostas, como as demais conferências:
 *
 *  - `false` — algum certificado está na lista. Reprova, **qualquer que seja a
 *    data da revogação**: a hora da assinatura é declarada (pelo serviço do
 *    gov.br ou pelo programa de quem assinou), não vem de um carimbo de tempo
 *    independente, e não prova que a assinatura veio antes da revogação.
 *    Quem teve o certificado revogado assina de novo.
 *  - `true` — todos os certificados com LCR declarada foram conferidos numa
 *    lista em vigor, assinada pela AC emissora, e nenhum está nela.
 *  - `null` — não deu para conferir (lista fora do ar e sem cópia em vigor,
 *    certificado sem LCR declarada). Não aprova nem reprova — a menos que
 *    `signature.pki.revocation_required` esteja ligado, quando reprova.
 *
 * A raiz da cadeia não é conferida: ela é a âncora, guardada no repositório.
 */
class RevocationChecker
{
    public function __construct(private PkiRepository $repository)
    {
    }

    /**
     * @param  array<int, string>  $cadeia  do certificado do signatário até a raiz, em PEM
     * @return array{ok: ?bool, detail: string}
     */
    public function check(array $cadeia): array
    {
        if (!config('signature.pki.revocation', true)) {
            return $this->unverified('Consulta desligada nesta instalação (SIGNATURE_PKI_REVOCATION=false).');
        }

        $conferidos = 0;
        $emitidas = [];

        for ($i = 0; $i < count($cadeia) - 1; $i++) {
            $certificado = $cadeia[$i];
            $emissor = $cadeia[$i + 1];
            $nome = Certificates::commonName($certificado) ?? 'certificado';
            $urls = Certificates::crlUrls($certificado);

            if ($urls === []) {
                // O do signatário sem LCR declarada é o caso que importa; uma
                // intermediária sem LCR é responsabilidade da raiz, que é a âncora.
                if ($i === 0) {
                    return $this->unverified('O certificado de "' . $nome . '" não informa onde está a lista de revogação.');
                }

                continue;
            }

            $lista = null;

            foreach ($urls as $url) {
                $candidata = $this->repository->crl($url);

                if ($candidata !== null && $candidata->isSignedBy($emissor)) {
                    $lista = $candidata;
                    break;
                }
            }

            if ($lista === null) {
                return $this->unverified('Não foi possível obter a lista de revogação de "' . $nome . '" ('
                    . implode(', ', $urls) . ').');
            }

            if (!$lista->isCurrent(time())) {
                return $this->unverified('A lista de revogação de "' . $nome . '" está desatualizada (venceu em '
                    . date('d/m/Y H:i', (int) $lista->nextUpdate) . ') e não foi possível baixar a nova.');
            }

            $serie = Certificates::serial($certificado);
            $revogadoEm = $serie === null ? null : $lista->revokedAt($serie);

            if ($revogadoEm !== null) {
                return [
                    'ok' => false,
                    'detail' => 'O certificado de "' . $nome . '" foi REVOGADO em ' . date('d/m/Y H:i', $revogadoEm)
                        . '. Peça que a pessoa assine de novo, com um certificado válido.',
                ];
            }

            $conferidos++;
            $emitidas[] = $lista->thisUpdate;
        }

        if ($conferidos === 0) {
            return $this->unverified('Nenhum certificado da cadeia informa lista de revogação.');
        }

        return [
            'ok' => true,
            'detail' => ($conferidos === 1
                ? 'O certificado não está na lista de revogação da AC'
                : 'Nenhum dos ' . $conferidos . ' certificados da cadeia está na lista de revogação da sua AC')
                . ' (lista emitida em ' . date('d/m/Y H:i', min($emitidas)) . ').',
        ];
    }

    /** @return array{ok: ?bool, detail: string} */
    private function unverified(string $motivo): array
    {
        $exigida = (bool) config('signature.pki.revocation_required', false);

        return [
            'ok' => $exigida ? false : null,
            'detail' => $motivo . ($exigida
                ? ' Esta instalação exige a consulta (SIGNATURE_PKI_REVOCATION_REQUIRED): tente de novo mais tarde.'
                : ' A assinatura não foi reprovada por isso; o validador oficial (validar.iti.gov.br) confere a revogação.'),
        ];
    }
}
