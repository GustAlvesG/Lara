<?php

namespace Tests\Feature;

use App\Services\Signature\SignatureDocumentService;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * O QR Code da tela do atendente tem de ser legível em QUALQUER tema.
 *
 * Ele é lido por uma câmera, não por quem olha a tela: preto sobre branco, com
 * margem branca em volta. Com o fundo do cartão (`bg-surface`), que no tema
 * escuro vira grafite, o tablet não achava o código.
 */
class SignatureReleaseQrThemeTest extends TestCase
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

    public function test_qr_do_atendente_tem_fundo_branco_fixo_e_cores_fixas(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());

        $html = $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/class="[^"]*\bbg-white\b[^"]*"\s+data-qr>/', $html);
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*\bbg-surface\b[^"]*"\s+data-qr>/', $html);
        $this->assertStringContainsString("colorDark: '#000000'", $html);
        $this->assertStringContainsString("colorLight: '#ffffff'", $html);
    }
}
