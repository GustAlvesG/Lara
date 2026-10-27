<?php

namespace App\Services\Signature\Contracts;

use App\Models\SignatureContract;
use App\Models\SignatureDocument;
use App\Models\SignatureTemplate;
use App\Services\Signature\Directors\DirectorAreaService;
use App\Services\Signature\Directors\DirectorException;
use App\Services\Signature\SignatureAttachmentService;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureFieldTypes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Transforma o contrato aprovado num documento de assinatura em RASCUNHO
 * (decisão do usuário, 27/10/2026: "vem sem congelar").
 *
 * O texto vai para um modelo de USO ÚNICO, como o PDF enviado pronto: o resto
 * do módulo lê regras e texto do modelo e não precisa saber que este
 * documento veio de um contrato. Cada signatário ganha a sua parte (`p1`,
 * `p2`…), com o rótulo que sai impresso sob o nome; o fecho usa a data
 * AUTOMÁTICA da assinatura; o visto vai em todas as páginas.
 *
 * Diretor que não responde mais pela área (mandato encerrado entre a
 * redação e a aprovação) não trava a aprovação: fica de fora com aviso, e o
 * congelamento recusa até alguém ser escolhido para aquela parte.
 */
class ContractDocumentGenerator
{
    public function __construct(
        private ContractRenderer $renderer,
        private SignatureDocumentService $documents,
        private DirectorAreaService $directors,
        private SignatureAttachmentService $attachments,
    ) {
    }

    /**
     * @return array{document: SignatureDocument, warnings: array<int, string>}
     */
    public function generate(SignatureContract $contract, ?int $userId = null, ?string $userName = null): array
    {
        $content = $contract->content ?? [];
        $avisos = [];

        $signatarios = $this->renderer->documentSigners($content);

        $documento = DB::transaction(function () use ($contract, $content, $signatarios, &$avisos) {
            $modelo = SignatureTemplate::create([
                'name' => mb_substr($contract->number . ' — ' . $contract->title, 0, 150),
                'description' => 'Contrato redigido em Assinaturas → Contratos (contrato #' . $contract->id . ').',
                'body_html' => $this->renderer->html($content, $contract->number, ContractRenderer::MODE_DOCUMENT),
                'variables' => [[
                    'key' => ContractRenderer::SIGNING_DATE_KEY,
                    'label' => 'Data da assinatura',
                    'type' => SignatureFieldTypes::DATE_SIGNING_LONG,
                    'required' => false,
                ]],
                'parties' => array_map(fn(array $s) => ['key' => $s['key'], 'label' => $s['label']], $signatarios),
                'requires_photo' => true,
                'requires_initials' => true,
                'identity_check' => array_key_exists((string) config('signature.contracts.identity_check'), SignatureTemplate::IDENTITY_CHECKS)
                    ? config('signature.contracts.identity_check')
                    : SignatureTemplate::IDENTITY_FULL,
                'single_use' => true,
                // Fora da lista de modelos e da escolha do atendente.
                'active' => false,
                'created_by' => $contract->created_by,
            ]);

            $lista = [];

            foreach ($signatarios as $signer) {
                if ($signer['source'] === 'director') {
                    try {
                        $lista[] = $this->directors->signerData((int) $signer['area_id'], (int) $signer['user_id'])
                            + ['party' => $signer['key']];
                    } catch (DirectorException $e) {
                        $avisos[] = $signer['label'] . ': ' . $e->getMessage()
                            . ' Escolha quem assina por essa parte no documento antes de congelar.';
                    }

                    continue;
                }

                $lista[] = [
                    'name' => $signer['name'],
                    'cpf' => $signer['cpf'],
                    'email' => $signer['email'] ?: null,
                    'phone' => $signer['phone'] ?: null,
                    'party' => $signer['key'],
                ];
            }

            // O título de documento é o que a lista de Documentos mostra.
            $titulo = $contract->number . ' — ' . $contract->title
                . ($contract->counterpartyName() ? ' — ' . $contract->counterpartyName() : '');

            // Quem redigiu é quem conduz a assinatura daqui em diante.
            return $this->documents->create(
                $modelo,
                ['title' => mb_substr($titulo, 0, 200)],
                $lista,
                $contract->created_by,
                $contract->created_by_name,
            );
        });

        $avisos = array_merge($avisos, $this->copyAttachments($contract, $documento, $userId, $userName));

        return ['document' => $documento, 'warnings' => $avisos];
    }

    /**
     * Os anexos do contrato seguem para o documento de assinatura (decisão do
     * usuário): mesmo arquivo, hash próprio no módulo de assinatura.
     *
     * @return array<int, string> avisos de anexo que não foi copiado
     */
    private function copyAttachments(SignatureContract $contract, SignatureDocument $documento, ?int $userId, ?string $userName): array
    {
        $disk = Storage::disk(config('signature.disk'));
        $avisos = [];

        foreach ($contract->attachments()->get() as $anexo) {
            $temporario = tempnam(sys_get_temp_dir(), 'ctanexo');

            try {
                if (!$disk->exists($anexo->path)) {
                    throw new \RuntimeException('arquivo não encontrado no disco');
                }

                file_put_contents($temporario, $disk->get($anexo->path));

                $this->attachments->store(
                    $documento,
                    new UploadedFile($temporario, $anexo->original_name, $anexo->mime, null, true),
                    null,
                    $anexo->label,
                    $userId,
                    $userName,
                );
            } catch (\Throwable $e) {
                Log::warning('Contrato: anexo não copiado para o documento de assinatura.', [
                    'contrato' => $contract->id,
                    'anexo' => $anexo->id,
                    'erro' => $e->getMessage(),
                ]);

                $avisos[] = 'O anexo "' . $anexo->label . '" não foi copiado para o documento: envie-o na tela do documento.';
            } finally {
                @unlink($temporario);
            }
        }

        return $avisos;
    }
}
