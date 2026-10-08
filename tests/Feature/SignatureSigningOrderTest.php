<?php

namespace Tests\Feature;

use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Sem ordem obrigatória entre os signatários: no tablet, o atendente escolhe
 * quem assina agora. (A liberação fora da ordem está em SignatureQrTokenTest;
 * o gov.br, em SignatureGovbrSigningTest e SignatureGovbrInviteTest.)
 */
class SignatureSigningOrderTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
    }

    public function test_painel_do_tablet_deixa_escolher_quem_assina_e_libera_o_segundo(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $testemunha = SignatureSigner::create([
            'signature_document_id' => $documento->id,
            'name' => 'João Testemunha',
            'cpf' => '98765432100',
            'role' => SignatureSigner::ROLE_WITNESS,
            'position' => 2,
        ]);

        $documento = app(SignatureDocumentService::class)->freeze($documento);
        $atendente = $this->usuarioComPermissoes(['assinatura.documentos']);

        $this->actingAs($atendente)
            ->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertSee('Quem vai assinar agora')
            ->assertSee('data-release-signer', false)
            ->assertSee('João Testemunha');

        $this->actingAs($atendente)
            ->postJson(route('signature-documents.release', [$documento, $testemunha]))
            ->assertCreated();
    }
}
