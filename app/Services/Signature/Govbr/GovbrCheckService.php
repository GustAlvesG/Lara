<?php

namespace App\Services\Signature\Govbr;

use App\Exceptions\SignatureDocumentLockedException;
use App\Jobs\FinalizeSignatureDocument;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureGovbrCheck;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignatureSigningDataService;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A assinatura pelo gov.br, do lado do atendente.
 *
 * Dois atos:
 *
 *  1. **Preparar** (`prepare`): o documento passa a ser assinado pelo gov.br.
 *     A data automática entra no PDF (como faria a leitura do QR), o prazo
 *     vira o do gov.br e o tablet deixa de liberar — um PDF não carrega as
 *     duas assinaturas.
 *  2. **Conferir** (`check`): o PDF que voltou é conferido, guardado e
 *     registrado. Aprovado, e com o documento preparado, a conferência CONCLUI
 *     a assinatura de quem assinou — pela máquina de estados, como o tablet.
 *     Quando todos assinaram, o documento fecha e o arquivo dessa conferência
 *     vira o PDF final.
 *
 * O arquivo é guardado mesmo quando recusado: é o registro do que chegou e de
 * por que não serviu.
 */
class GovbrCheckService
{
    public function __construct(
        private GovbrSignatureValidator $validator,
        private SignatureStateMachine $states,
        private SignatureRequestService $requests,
        private SignatureSigningDataService $signingData,
    ) {
    }

    /**
     * Prepara o documento para ser assinado pelo gov.br.
     *
     * Pode ser repetido: renova o prazo. A data automática só muda enquanto
     * ninguém assinou (`signingDataIsOpen`), como no tablet.
     *
     * @throws SignatureDocumentLockedException
     */
    public function prepare(SignatureDocument $document, ?int $userId = null, ?string $userName = null): SignatureDocument
    {
        $document->loadMissing('template');

        if ($motivo = $document->govbrBlockReason()) {
            throw new SignatureDocumentLockedException($motivo);
        }

        $prazo = now()->addDays(max(1, (int) config('signature.govbr.ttl_days', 7)));

        DB::transaction(function () use ($document, $userId, $userName, $prazo) {
            /*
             | QR gerado e ainda vivo morre aqui: lido depois, ele abriria no
             | tablet um documento que agora é do gov.br.
             */
            $vivas = SignatureRequest::whereIn('signature_signer_id', $document->signers()->pluck('id'))
                ->whereIn('status', [SignatureRequest::STATUS_PENDING, SignatureRequest::STATUS_CONSUMED])
                ->get();

            foreach ($vivas as $liberacao) {
                $this->requests->cancel($liberacao, $userId);
            }

            // A data da assinatura entra no texto ANTES de o PDF sair para a
            // pessoa — refaz o original e o hash, com evento, como no tablet.
            $this->signingData->prepare($document, ['actor_id' => $userId]);

            $document->forceFill([
                'govbr_sent_at' => now(),
                // O vaivém pelo gov.br leva dias; o prazo do balcão é de horas.
                'expires_at' => $prazo,
            ])->save();

            $this->states->note($document, SignatureAuditEvent::EVENT_GOVBR_PREPARED, [
                'actor_id' => $userId,
                'payload' => [
                    'prazo' => $prazo->toDateTimeString(),
                    'liberacoes_canceladas' => $vivas->count(),
                    'sha256_original' => $document->fresh()->original_sha256,
                    'preparado_por' => $userName,
                ],
            ]);
        });

        return $document->fresh();
    }

    /**
     * Confere o PDF devolvido, guarda e — aprovado — conclui a assinatura.
     * Quem envia é o atendente, pela aba.
     *
     * @throws SignatureDocumentLockedException  documento ainda sem PDF para comparar
     */
    public function check(
        SignatureDocument $document,
        string $pdf,
        ?int $userId = null,
        ?string $userName = null,
    ): SignatureGovbrCheck {
        if (!$document->isFrozen() || !$document->original_path) {
            throw new SignatureDocumentLockedException(
                'Congele o documento antes: só existe PDF para assinar no gov.br depois do congelamento.'
            );
        }

        $resultado = $this->validator->validate($pdf, $document->loadMissing('signers'));
        $hash = hash('sha256', $pdf);

        // Data e começo do hash no nome: dois envios do mesmo arquivo não se
        // sobrescrevem, e o nome não carrega dado pessoal nenhum.
        $caminho = config('signature.paths.documents') . '/' . $document->id
            . '/govbr/' . now()->format('Ymd-His') . '-' . substr($hash, 0, 12) . '.pdf';

        Storage::disk(config('signature.disk'))->put($caminho, $pdf);

        $conferencia = SignatureGovbrCheck::create([
            'signature_document_id' => $document->id,
            'file_path' => $caminho,
            'file_sha256' => $hash,
            'file_bytes' => strlen($pdf),
            'valid' => $resultado->isValid(),
            'base' => $resultado->base,
            'result' => $resultado->toArray(),
            'checked_by' => $userId,
            'checked_by_name' => $userName,
        ]);

        // A trilha guarda o veredito e as CHAVES do que reprovou — nada de
        // nome nem CPF de quem assinou, que já estão no registro da conferência.
        $this->states->note($document, SignatureAuditEvent::EVENT_GOVBR_CHECKED, [
            'actor_id' => $userId,
            'payload' => [
                'conferencia' => $conferencia->id,
                'valido' => $resultado->isValid(),
                'sobre' => $resultado->base,
                'sha256' => $hash,
                'assinaturas' => count($resultado->signatures),
                'signatarios' => $resultado->signedSignerIds(),
                'reprovado_em' => $resultado->failedKeys(),
                'conferido_por' => $userName,
            ],
        ]);

        $conclusao = $resultado->isValid()
            ? $this->conclude($document->fresh(['template', 'signers']), $conferencia, $resultado, $userId, $userName)
            : ['assinaram' => [], 'fechou' => false, 'motivo' => null];

        $conferencia->forceFill(['conclusion' => $conclusao])->save();

        return $conferencia;
    }

    /**
     * Dá como assinados os signatários de um arquivo APROVADO.
     *
     * Tudo ou nada: se alguma regra não fecha, ninguém é dado como assinado e
     * o motivo vai para a tela. As regras:
     *
     *  - o documento foi preparado para o gov.br e continua aguardando;
     *  - o arquivo foi assinado sobre o ORIGINAL (o final do tablet já é de um
     *    documento fechado);
     *  - quem já assinou pelo gov.br continua no arquivo — a próxima pessoa
     *    assina o arquivo devolvido pela anterior, e é ele que vira o final.
     *
     * A ORDEM dos signatários não importa: qualquer pendente pode assinar. O
     * que importa é a cadeia — um assina depois do outro, sobre o mesmo
     * arquivo. Duas pessoas assinando o mesmo arquivo ao mesmo tempo não se
     * somam: a segunda a voltar é recusada pela regra acima e assina de novo,
     * sobre o arquivo da primeira.
     *
     * @return array{assinaram: array<int, string>, fechou: bool, motivo: ?string}
     */
    private function conclude(
        SignatureDocument $document,
        SignatureGovbrCheck $conferencia,
        GovbrValidationResult $resultado,
        ?int $userId,
        ?string $userName,
    ): array {
        $nada = fn(string $motivo) => ['assinaram' => [], 'fechou' => false, 'motivo' => $motivo];

        if ($resultado->base !== 'original') {
            return $nada('Assinado sobre o PDF final do tablet: o documento já está fechado, e a conferência fica só como registro.');
        }

        if ($motivo = $document->govbrBlockReason()) {
            return $nada($motivo);
        }

        if (!$document->isGovbr()) {
            return $nada('O documento não foi preparado para o gov.br: a conferência fica só como registro. '
                . 'Use "Preparar para o gov.br" e envie à pessoa o PDF baixado depois disso.');
        }

        /** @var Collection<int, SignatureSigner> $signers */
        $signers = $document->signers->keyBy('id');

        // Os signatários do arquivo, na ordem em que assinaram.
        $doArquivo = [];
        foreach ($resultado->signatures as $assinatura) {
            if ($assinatura['signer_id'] !== null && !in_array($assinatura['signer_id'], $doArquivo, true)) {
                $doArquivo[] = $assinatura['signer_id'];
            }
        }

        $faltando = $signers
            ->filter(fn(SignatureSigner $s) => $s->signedViaGovbr() && !in_array($s->id, $doArquivo, true))
            ->pluck('name');

        if ($faltando->isNotEmpty()) {
            return $nada('Este arquivo não traz a assinatura de ' . $faltando->join(', ', ' e ')
                . ', que já assinou. A próxima pessoa precisa assinar o arquivo já assinado pela anterior, e não o original.');
        }

        $novos = array_values(array_filter(
            $doArquivo,
            fn(int $id) => $signers[$id]->status === SignatureSigner::STATUS_PENDING,
        ));

        if ($novos === []) {
            return $nada('Nenhuma assinatura nova: quem assinou este arquivo já estava registrado.');
        }

        $assinaturaDe = fn(int $id) => collect($resultado->signatures)->firstWhere('signer_id', $id);

        $fechou = DB::transaction(function () use ($document, $conferencia, $signers, $novos, $assinaturaDe, $userId, $userName) {
            foreach ($novos as $id) {
                $assinatura = $assinaturaDe($id);

                $this->states->signerTo(
                    $signers[$id],
                    SignatureSigner::STATUS_SIGNED,
                    SignatureAuditEvent::EVENT_SIGNED,
                    [
                        // A hora declarada na assinatura do gov.br: é quando o
                        // ato aconteceu. A hora do Lara fica no evento.
                        'signed_at' => $assinatura['signed_at'] ? Carbon::parse($assinatura['signed_at']) : now(),
                        // Quem assinou por fora recebe a via final, com todas
                        // as assinaturas, se tiver e-mail.
                        'wants_copy' => $signers[$id]->email !== null,
                        'govbr_check_id' => $conferencia->id,
                    ],
                    [
                        'actor_id' => $userId,
                        'payload' => [
                            'via' => 'gov.br',
                            'conferencia' => $conferencia->id,
                            'certificado' => $assinatura['serial'],
                            'emissor' => $assinatura['issuer'],
                            'hora_declarada' => $assinatura['signed_at'],
                            'conferido_por' => $userName,
                        ],
                    ],
                );
            }

            if (!$document->fresh()->allSignersSigned()) {
                return false;
            }

            $this->states->documentTo(
                $document,
                SignatureDocument::STATUS_SIGNED,
                SignatureAuditEvent::EVENT_SIGNED,
                // O arquivo desta conferência é o que vira o PDF final.
                ['govbr_check_id' => $conferencia->id],
                ['actor_id' => $userId, 'payload' => ['via' => 'gov.br', 'conferencia' => $conferencia->id]],
            );

            return true;
        });

        // Fora da transação, como no tablet: o worker não pode pegar o job
        // antes do commit.
        if ($fechou) {
            FinalizeSignatureDocument::dispatch($document->id);
        }

        return [
            'assinaram' => array_map(fn(int $id) => $signers[$id]->name, $novos),
            'fechou' => $fechou,
            'motivo' => null,
        ];
    }
}
