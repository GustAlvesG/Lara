<?php

namespace App\Services\Signature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureLayout;
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
        private SignaturePdfStamper $stamper,
    ) {
    }

    /**
     * Cria o rascunho de um documento PRONTO, a partir do PDF enviado.
     *
     * O PDF é conferido ANTES de qualquer coisa ser gravada: é aqui que o
     * atendente ainda pode trocar o arquivo. As regras da assinatura
     * (identidade, foto, visto) moram num modelo de uso único, criado junto —
     * o resto do módulo lê as regras do modelo, e assim não precisa saber que
     * este documento não veio de um.
     *
     * @param  array<string, mixed>  $attributes  title, attachment_requirements
     * @param  array<string, mixed>  $rules       identity_check, requires_photo, requires_initials
     * @param  array<int, array<string, mixed>>  $signers
     *
     * @throws \App\Exceptions\UnreadablePdfException
     */
    public function createFromUpload(
        string $pdf,
        array $attributes,
        array $rules,
        array $signers,
        ?int $userId = null,
        ?string $userName = null,
    ): SignatureDocument {
        $paginas = $this->stamper->inspect($pdf);
        $hash = hash('sha256', $pdf);

        $document = DB::transaction(function () use ($attributes, $rules, $signers, $userId, $userName) {
            $template = SignatureTemplate::create([
                'name' => $attributes['title'],
                'description' => 'Documento enviado pronto, em PDF.',
                'body_html' => '',
                'variables' => [],
                'identity_check' => $rules['identity_check'],
                'requires_photo' => (bool) ($rules['requires_photo'] ?? false),
                'requires_initials' => (bool) ($rules['requires_initials'] ?? false),
                'single_use' => true,
                // Fora da lista de modelos e da escolha do atendente.
                'active' => false,
                'created_by' => $userId,
            ]);

            return $this->create($template, $attributes, $signers, $userId, $userName);
        });

        $caminho = config('signature.paths.documents') . '/' . $document->id . '/enviado.pdf';

        Storage::disk(config('signature.disk'))->put($caminho, $pdf);

        $document->forceFill(['source_path' => $caminho, 'source_sha256' => $hash])->save();

        $this->states->note($document, SignatureAuditEvent::EVENT_SOURCE_UPLOADED, [
            'actor_id' => $userId,
            'payload' => ['sha256' => $hash, 'paginas' => $paginas, 'bytes' => strlen($pdf)],
        ]);

        return $document->fresh();
    }

    /**
     * Onde cada pessoa assina no PDF enviado — o ponto que o atendente marcou
     * na tela. Sem ponto, a pessoa assina na folha de assinaturas do fim.
     *
     * A posição é fração da página (0 a 1), a partir de cima e da esquerda.
     *
     * @param  array<int|string, array{page?: mixed, x?: mixed, y?: mixed}|null>  $positions  id do signatário => posição
     *
     * @throws SignatureDocumentLockedException
     */
    public function setSignaturePositions(SignatureDocument $document, array $positions): SignatureDocument
    {
        if ($motivo = $document->editBlockReason()) {
            throw new SignatureDocumentLockedException($motivo);
        }

        foreach ($document->signers()->get() as $signer) {
            $posicao = $positions[$signer->id] ?? null;

            $signer->forceFill([
                'signature_position' => is_array($posicao) && isset($posicao['page'], $posicao['x'], $posicao['y'])
                    ? [
                        'page' => max(1, (int) $posicao['page']),
                        'x' => round(min(1, max(0, (float) $posicao['x'])), 5),
                        'y' => round(min(1, max(0, (float) $posicao['y'])), 5),
                    ]
                    : null,
            ])->save();
        }

        return $document->fresh();
    }

    /**
     * Cria o documento em rascunho, já com os signatários.
     *
     * @param  array<string, mixed>  $attributes  title, data, attachment_requirements
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
                'title' => $this->titleFor($template, $attributes['title'] ?? null, $signers),
                'data' => $attributes['data'] ?? [],
                'attachment_requirements' => $attributes['attachment_requirements'] ?? [],
                'created_by' => $userId,
                // Retrato do nome: o manifesto precisa dizer quem atendeu, e
                // `users` vive noutra conexão — ver a migration que criou a
                // coluna.
                'created_by_name' => $userName,
            ]);

            $this->syncSigners($document, $signers);

            $this->states->note($document, SignatureAuditEvent::EVENT_CREATED, [
                'actor_id' => $userId,
                'payload' => [
                    'modelo' => $template->name,
                    'versao' => $template->version,
                    'signatarios' => count($signers),
                    'gerado_por' => $userName,
                ],
            ]);

            return $document->fresh();
        });
    }

    /**
     * O título de um documento novo.
     *
     * Por padrão é "Modelo - Primeiro signatário": numa lista de vinte
     * documentos do mesmo modelo, o título igual em todos não distingue nenhum.
     * "Por padrão" é enquanto o atendente não escreveu outro — título em
     * branco ou igual ao nome do modelo, que é como o formulário o traz.
     * Título digitado é respeitado como está.
     *
     * Documento enviado pronto (modelo de uso único) fica de fora: ali o
     * título é sempre digitado, e o modelo só existe para guardar as regras.
     *
     * Só na criação. Na correção do rascunho o título já está na tela, com o
     * nome, e quem troca o signatário troca o título se quiser.
     *
     * @param  array<int, array<string, mixed>>  $signers
     */
    private function titleFor(SignatureTemplate $template, ?string $title, array $signers): string
    {
        $title = trim((string) $title);

        if ($template->single_use) {
            return $title !== '' ? $title : $template->name;
        }

        if ($title !== '' && $title !== trim($template->name)) {
            return $title;
        }

        $primeiro = trim((string) (array_values($signers)[0]['name'] ?? ''));

        if ($primeiro === '') {
            return $template->name;
        }

        // A coluna tem 200 caracteres; quem cede é o nome do modelo, não o da pessoa.
        $sufixo = ' - ' . mb_substr($primeiro, 0, 80);

        return mb_substr($template->name, 0, 200 - mb_strlen($sufixo)) . $sufixo;
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
                'attachment_requirements' => $attributes['attachment_requirements'] ?? $document->attachment_requirements,
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

        // Parte declarada no modelo e sem ninguém para assinar por ela: o
        // contrato sairia com o lugar do Contratado em branco, e "assinado".
        $semSignatario = collect($document->template->declaredParties())
            ->reject(fn(array $parte) => $document->signers->contains('party', $parte['key']))
            ->pluck('label');

        if ($semSignatario->isNotEmpty()) {
            throw new SignatureDocumentLockedException(
                'Falta informar quem assina como: ' . $semSignatario->join(', ') . '.'
            );
        }

        // Conferência por código no e-mail: sem e-mail, o tablet não teria
        // para onde mandar o código, e a pessoa não conseguiria assinar.
        if ($document->template->identity_check === SignatureTemplate::IDENTITY_EMAIL) {
            $semEmail = $document->signers->filter(fn($s) => !$s->email)->pluck('name');

            if ($semEmail->isNotEmpty()) {
                throw new SignatureDocumentLockedException(
                    'Este modelo confere a identidade por código enviado por e-mail. Informe o e-mail de: '
                        . $semEmail->join(', ') . '.'
                );
            }
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
            // O papel timbrado vigente AGORA fica preso ao documento: o PDF
            // final, montado depois, tem de sair com a mesma cara do original.
            // Documento enviado pronto não leva papel timbrado: ele já é a
            // arte de quem o enviou.
            'signature_layout_id' => $document->isUploaded() ? null : SignatureLayout::current()?->id,
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

        // O rótulo da parte é copiado do modelo: é ele que sai impresso sob o
        // nome. Parte que o modelo não declara é ignorada, e não gravada.
        $partes = collect($document->template->declaredParties())->pluck('label', 'key');

        foreach ($signers as $signer) {
            $parte = $partes->has($signer['party'] ?? null) ? $signer['party'] : null;

            SignatureSigner::create([
                'signature_document_id' => $document->id,
                'name' => trim((string) ($signer['name'] ?? '')),
                'cpf' => Cpf::digits($signer['cpf'] ?? ''),
                'member_id' => $signer['member_id'] ?? null,
                'email' => $signer['email'] ?? null,
                'phone' => $signer['phone'] ?? null,
                'role' => $signer['role'] ?? SignatureSigner::ROLE_SIGNER,
                'party' => $parte,
                'party_label' => $parte !== null ? $partes[$parte] : null,
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
