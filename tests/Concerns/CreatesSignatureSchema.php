<?php

namespace Tests\Concerns;

use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;

/**
 * Cria as tabelas do módulo de assinatura no SQLite da suíte.
 *
 * Sem `RefreshDatabase` pelo mesmo motivo já registrado em
 * {@see CreatesFreelancerPixSchema} e {@see CreatesCotacaoSchema}: a cadeia
 * completa de migrations não roda hoje, e várias delas dependem de `users`,
 * cuja model fixa a conexão `mysql`.
 *
 * As migrations aplicadas são AS DE VERDADE, lidas do arquivo — e não uma
 * cópia do schema. É de propósito: uma cópia deixaria de acusar divergência no
 * dia em que uma coluna mudar. As seis rodam sem adaptação porque nenhuma
 * delas declara foreign key para `users` (o vínculo com o autor é
 * `unsignedBigInteger` solto).
 *
 * A ordem importa: `signature_documents` tem FK para `signature_templates`,
 * `signature_signers` para documentos, e requests/evidences para signatários.
 */
trait CreatesSignatureSchema
{
    protected function createSignatureSchema(): void
    {
        $migrations = [
            '2026_09_22_120000_create_signature_templates_table.php',
            '2026_09_22_120100_create_signature_documents_table.php',
            '2026_09_22_120200_create_signature_signers_table.php',
            '2026_09_22_120300_create_signature_requests_table.php',
            '2026_09_22_120400_create_signature_evidences_table.php',
            '2026_09_22_120500_create_signature_audit_events_table.php',
        ];

        foreach ($migrations as $arquivo) {
            (require base_path('database/migrations/' . $arquivo))->up();
        }
    }

    /**
     * Um modelo de documento mínimo, já ativo.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function criaModeloDeAssinatura(array $attributes = []): SignatureTemplate
    {
        return SignatureTemplate::create(array_merge([
            'name' => 'Termo de responsabilidade',
            'body_html' => '<p>Declaro estar ciente das regras de uso.</p>[[assinatura]]',
            'variables' => [],
            'created_by' => 1,
        ], $attributes));
    }

    /**
     * Documento em rascunho, com um signatário pendente.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $signerAttributes
     */
    protected function criaDocumentoDeAssinatura(
        array $attributes = [],
        array $signerAttributes = [],
    ): SignatureDocument {
        $modelo = $attributes['template'] ?? $this->criaModeloDeAssinatura();
        unset($attributes['template']);

        $documento = SignatureDocument::create(array_merge([
            'signature_template_id' => $modelo->id,
            'template_version' => $modelo->version,
            'title' => 'Termo de responsabilidade — Piscina',
            'data' => [],
            'created_by' => 1,
        ], $attributes));

        SignatureSigner::create(array_merge([
            'signature_document_id' => $documento->id,
            'name' => 'Maria de Souza',
            'cpf' => '12345678909',
            'position' => 1,
        ], $signerAttributes));

        return $documento->fresh();
    }
}
