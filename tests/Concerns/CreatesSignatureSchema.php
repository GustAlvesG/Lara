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
 * dia em que uma coluna mudar. Todas rodam sem adaptação porque nenhuma delas
 * declara foreign key para `users` (o vínculo com o autor é
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
            '2026_09_22_120700_add_identity_check_to_signature_requests.php',
            '2026_09_22_120800_add_attendant_name_to_signature_documents.php',
            '2026_09_22_120900_add_copy_delivery_to_signature_signers.php',
            '2026_09_23_100000_add_manual_fallback_to_signature_tables.php',
            '2026_10_03_100000_add_signing_data_to_signature_documents.php',
            '2026_10_04_100000_add_parties_and_initials_to_signature_tables.php',
            '2026_10_04_100100_create_signature_layouts_table.php',
            '2026_10_05_100000_add_archive_to_signature_documents.php',
            '2026_10_06_100000_add_uploaded_pdf_to_signature_tables.php',
            '2026_10_08_100000_add_photo_consent_to_signature_evidences.php',
            '2026_10_09_100000_add_issuer_name_to_signature_requests.php',
            '2026_10_10_100000_create_signature_govbr_checks_table.php',
            '2026_10_10_100100_add_govbr_to_signature_documents_and_signers.php',
            '2026_10_10_100200_create_signature_govbr_invites_table.php',
            '2026_10_10_100300_remove_link_from_signature_govbr_invites.php',
            '2026_10_10_100400_add_govbr_report_to_signature_documents.php',
            '2026_10_11_100000_create_signature_attachments.php',
            '2026_10_11_100100_add_email_code_identity_check.php',
            '2026_10_11_100200_drop_location_from_signature_documents.php',
            '2026_10_11_100300_add_signer_field_keys_to_signature_documents.php',
        ];

        foreach ($migrations as $arquivo) {
            (require base_path('database/migrations/' . $arquivo))->up();
        }
    }

    /**
     * As tabelas do Spatie Permission, para os testes que passam por rota.
     *
     * Não é firula: o Gate registra um `before` do Spatie que consulta
     * `permissions` ANTES de chegar à Policy. Sem as tabelas, qualquer
     * requisição autenticada morre em "no such table: permissions" — mesmo com
     * o usuário mockado, que só é consultado depois.
     *
     * As tabelas ficam VAZIAS de propósito. Sem a permissão cadastrada, o
     * `before` desiste (PermissionDoesNotExist, que o próprio Spatie engole) e
     * a decisão volta para onde ela é de fato testada: o `can()` do usuário
     * mockado e a SignatureDocumentPolicy.
     */
    protected function createPermissionSchema(): void
    {
        (require base_path('database/migrations/2025_12_23_111918_create_permission_tables.php'))->up();
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
