<?php

namespace App\Services\Signature\Govbr;

use App\Exceptions\SignatureDocumentLockedException;
use App\Mail\SignatureGovbrInviteMail;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureGovbrInvite;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * O convite por e-mail para assinar pelo gov.br: o PDF a assinar e o passo a
 * passo.
 *
 * Não há link de devolução: o Lara não é acessível de fora. A pessoa devolve
 * o arquivo assinado respondendo ao e-mail — a resposta vai para o atendente
 * que enviou (`replyTo`) — ou entregando-o no clube, e o atendente o envia na
 * aba "Assinatura gov.br". Quem decide se o arquivo vale é a conferência.
 *
 * O e-mail sai NA HORA, dentro da transação: se o envio falhar, o convite não
 * fica registrado como enviado, e o atendente vê o erro no mesmo clique.
 */
class GovbrInviteService
{
    public function __construct(private SignatureStateMachine $states)
    {
    }

    /**
     * Por que este signatário não pode receber o convite agora. null = pode.
     */
    public function blockReason(SignatureSigner $signer): ?string
    {
        $document = $signer->document;

        if (!$document?->isGovbr()) {
            return 'Prepare o documento para o gov.br antes de enviar o convite.';
        }

        if ($document->status !== SignatureDocument::STATUS_AWAITING_SIGNATURE) {
            return 'Documento em ' . mb_strtolower($document->statusLabel()) . ': não há assinatura a colher.';
        }

        if ($signer->status !== SignatureSigner::STATUS_PENDING) {
            return $signer->name . ' já ' . mb_strtolower($signer->statusLabel()) . '.';
        }

        // Qualquer pendente, em qualquer ordem. O arquivo que vai no e-mail é
        // o último aprovado — com as assinaturas de quem já assinou.
        return null;
    }

    /**
     * Envia o convite e devolve o registro. O e-mail informado passa a ser o
     * do signatário — é para ele que a via final vai, depois.
     *
     * @param  ?string  $replyTo  e-mail do atendente: é para ele que a resposta, com o arquivo assinado, vai
     *
     * @throws SignatureDocumentLockedException
     */
    public function send(
        SignatureSigner $signer,
        string $email,
        ?int $userId = null,
        ?string $userName = null,
        ?string $replyTo = null,
    ): SignatureGovbrInvite {
        if ($motivo = $this->blockReason($signer)) {
            throw new SignatureDocumentLockedException($motivo);
        }

        $document = $signer->document;
        $arquivo = $document->govbrFileToSign();
        $pdf = Storage::disk(config('signature.disk'))->get($arquivo['path']);

        if ($pdf === null) {
            throw new SignatureDocumentLockedException('O PDF a assinar não foi encontrado no disco.');
        }

        $email = mb_strtolower(trim($email));
        $replyTo = filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : null;

        return DB::transaction(function () use ($signer, $document, $arquivo, $pdf, $email, $replyTo, $userId, $userName) {
            if ($signer->email !== $email) {
                $signer->forceFill(['email' => $email])->save();
            }

            $convite = SignatureGovbrInvite::create([
                'signature_signer_id' => $signer->id,
                'email' => $email,
                'sent_by' => $userId,
                'sent_by_name' => $userName,
            ]);

            $jaAssinado = $arquivo['check'] !== null;

            $mail = new SignatureGovbrInviteMail(
                $convite->setRelation('signer', $signer),
                $pdf,
                ($jaAssinado ? 'documento-assinado-' : 'documento-') . $document->validation_code . '.pdf',
                $jaAssinado,
                $replyTo !== null,
            );

            if ($replyTo !== null) {
                $mail->replyTo($replyTo, $userName);
            }

            Mail::to($email)->send($mail);

            $this->states->note($document, SignatureAuditEvent::EVENT_GOVBR_INVITE_SENT, [
                'signer' => $signer->id,
                'actor_id' => $userId,
                'payload' => [
                    'convite' => $convite->id,
                    'email' => $convite->maskedEmail(),
                    'arquivo' => $jaAssinado ? 'conferencia ' . $arquivo['check']->id : 'original',
                    'sha256_arquivo' => hash('sha256', $pdf),
                    'enviado_por' => $userName,
                ],
            ]);

            return $convite;
        });
    }
}
