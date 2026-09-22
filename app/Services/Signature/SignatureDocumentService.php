<?php

namespace App\Services\Signature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Support\Cpf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * O ciclo do documento no lado do ATENDENTE: criar, corrigir enquanto é
 * rascunho, congelar e cancelar.
 *
 * O congelamento é o ato central do módulo. A partir dele:
 *
 *  - o texto vira `body_snapshot` e não olha mais para o modelo;
 *  - o PDF é gerado e o `original_sha256` gravado;
 *  - o documento ganha código de validação pública;
 *  - nada mais pode ser editado.
 *
 * Antes dele, o documento é rascunho e muda à vontade. É a mesma fronteira do
 * "documento congelado na assinatura" dos contratos de freelancer, adiantada
 * para o momento em que o atendente confere a tela com a pessoa na frente —
 * aqui o que a pessoa lê no tablet PRECISA ser byte a byte o que foi hasheado.
 */
class SignatureDocumentService
{
    /**
     * Alfabeto do código de validação: sem 0/O e 1/I/L, que ninguém consegue
     * ditar por telefone sem errar.
     */
    private const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const CODE_LENGTH = 12;

    public function __construct(
        private SignatureStateMachine $states,
        private SignatureDocumentRenderer $renderer,
    ) {
    }

    /**
     * Cria o documento em rascunho, já com os signatários.
     *
     * @param  array<string, mixed>  $attributes  title, data, location
     * @param  array<int, array<string, mixed>>  $signers
     */
    public function create(
        SignatureTemplate $template,
        array $attributes,
        array $signers,
        ?int $userId = null,
        ?string $userName = null,
    ): SignatureDocument {
        return DB::transaction(function () use ($template, $attributes, $signers, $userId, $userName) {
            $document = SignatureDocument::create([
                // A versão do modelo é fixada AGORA: publicar uma revisão
                // enquanto o atendente preenche não pode trocar o texto embaixo
                // dele.
                'signature_template_id' => $template->id,
                'template_version' => $template->version,
                'title' => $attributes['title'] ?? $template->name,
                'data' => $attributes['data'] ?? [],
                'location' => $attributes['location'] ?? config('signature.location'),
                'created_by' => $userId,
                // Retrato do nome: o manifesto precisa dizer quem atendeu, e
                // `users` vive noutra conexão — ver a migration que criou a
                // coluna.
                'created_by_name' => $userName,
            ]);

            $this->syncSigners($document, $signers);

            $this->states->note($document, SignatureAuditEvent::EVENT_CREATED, [
                'payload' => [
                    'modelo' => $template->name,
                    'versao' => $template->version,
                    'signatarios' => count($signers),
                ],
            ]);

            return $document->fresh();
        });
    }

    /**
     * Corrige um documento ainda em rascunho.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $signers
     *
     * @throws SignatureDocumentLockedException
     */
    public function update(
        SignatureDocument $document,
        array $attributes,
        array $signers,
    ): SignatureDocument {
        if ($motivo = $document->editBlockReason()) {
            throw new SignatureDocumentLockedException($motivo);
        }

        return DB::transaction(function () use ($document, $attributes, $signers) {
            $document->forceFill([
                'title' => $attributes['title'] ?? $document->title,
                'data' => $attributes['data'] ?? $document->data,
                'location' => $attributes['location'] ?? $document->location,
            ])->save();

            $this->syncSigners($document, $signers);

            return $document->fresh();
        });
    }

    /**
     * Congela o documento: gera o PDF, grava o hash e libera para assinatura.
     *
     * @throws SignatureDocumentLockedException
     */
    public function freeze(SignatureDocument $document, ?int $userId = null): SignatureDocument
    {
        if ($motivo = $document->editBlockReason()) {
            throw new SignatureDocumentLockedException($motivo);
        }

        $document->load(['template', 'signers']);

        if ($document->signers->isEmpty()) {
            throw new SignatureDocumentLockedException(
                'Documento sem signatário: informe quem vai assinar antes de congelar.'
            );
        }

        $faltando = $this->renderer->missingVariables($document->template, $document->data);

        if ($faltando !== []) {
            throw new SignatureDocumentLockedException(
                'Faltam dados obrigatórios do modelo: ' . implode(', ', $faltando) . '.'
            );
        }

        /*
         | O corpo é resolvido ANTES do PDF e gravado: é dele que o PDF final
         | será re-renderizado meses depois, quando o modelo já tiver outras
         | versões e o cadastro já tiver mudado.
         */
        $document->forceFill([
            'body_snapshot' => $this->renderer->body($document->template, $document->data),
            'validation_code' => $this->generateValidationCode(),
        ])->save();

        $bytes = $this->renderer->pdf($document->fresh(['template', 'signers']));

        $path = config('signature.paths.documents') . '/' . $document->id . '/original.pdf';

        Storage::disk(config('signature.disk'))->put($path, $bytes);

        return $this->states->documentTo(
            $document,
            SignatureDocument::STATUS_AWAITING_SIGNATURE,
            SignatureAuditEvent::EVENT_FROZEN,
            [
                'original_path' => $path,
                'original_sha256' => hash('sha256', $bytes),
                'frozen_at' => now(),
                'expires_at' => now()->addHours((int) config('signature.document_ttl_hours', 24)),
            ],
            [
                'actor_id' => $userId,
                'payload' => [
                    'codigo_validacao' => $document->validation_code,
                    'bytes' => strlen($bytes),
                ],
            ],
        );
    }

    /**
     * Cancela o documento. Os signatários pendentes caem junto — e, com eles,
     * qualquer sessão de tablet aberta, que passa a não achar mais o
     * documento em estado de assinar.
     *
     * @throws SignatureDocumentLockedException
     */
    public function cancel(
        SignatureDocument $document,
        ?string $reason = null,
        ?int $userId = null,
    ): SignatureDocument {
        if (!$this->states->canDocumentGoTo($document->status, SignatureDocument::STATUS_CANCELED)) {
            throw new SignatureDocumentLockedException(
                'Documento em ' . mb_strtolower($document->statusLabel()) . ': não pode ser cancelado.'
            );
        }

        return DB::transaction(function () use ($document, $reason, $userId) {
            foreach ($document->signers()->where('status', SignatureSigner::STATUS_PENDING)->get() as $signer) {
                $this->states->signerTo(
                    $signer,
                    SignatureSigner::STATUS_CANCELED,
                    SignatureAuditEvent::EVENT_CANCELED,
                    [],
                    ['actor_id' => $userId],
                );
            }

            return $this->states->documentTo(
                $document,
                SignatureDocument::STATUS_CANCELED,
                SignatureAuditEvent::EVENT_CANCELED,
                ['canceled_reason' => $reason],
                ['actor_id' => $userId, 'payload' => ['motivo' => $reason]],
            );
        });
    }

    /**
     * Regrava a lista de signatários de um rascunho.
     *
     * Apaga e recria em vez de casar linha a linha: num rascunho não há nada
     * pendurado nos signatários (nem solicitação, nem evidência), e casar por
     * posição criaria o risco silencioso de trocar o CPF de um signatário
     * mantendo o resto.
     *
     * @param  array<int, array<string, mixed>>  $signers
     */
    private function syncSigners(SignatureDocument $document, array $signers): void
    {
        if ($signers === []) {
            return;
        }

        $document->signers()->delete();

        $posicao = 1;

        foreach ($signers as $signer) {
            SignatureSigner::create([
                'signature_document_id' => $document->id,
                'name' => trim((string) ($signer['name'] ?? '')),
                'cpf' => Cpf::digits($signer['cpf'] ?? ''),
                'member_id' => $signer['member_id'] ?? null,
                'email' => $signer['email'] ?? null,
                'phone' => $signer['phone'] ?? null,
                'role' => $signer['role'] ?? SignatureSigner::ROLE_SIGNER,
                'position' => $posicao++,
            ]);
        }
    }

    /**
     * Código público de validação, único.
     *
     * O laço existe porque a unicidade é do banco: 31^12 torna a colisão
     * improvável, não impossível, e um código repetido apontaria a validação
     * de um documento para outro.
     */
    private function generateValidationCode(): string
    {
        do {
            $codigo = '';

            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $codigo .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (SignatureDocument::where('validation_code', $codigo)->exists());

        return $codigo;
    }
}
