<?php

namespace Tests\Feature;

use App\Models\SignatureDocument;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Arquivar documento (`hidden_at`): só tira da lista padrão. Não muda o status
 * nem toca no `archived_at` (cópia no FTP).
 */
class SignatureDocumentHideTest extends TestCase
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

    public function test_arquivar_tira_da_lista_e_o_filtro_mostra_so_os_arquivados(): void
    {
        $arquivado = $this->criaDocumentoDeAssinatura([
            'title' => 'Termo esquecido',
            'status' => SignatureDocument::STATUS_AWAITING_SIGNATURE,
        ]);
        $this->criaDocumentoDeAssinatura(['title' => 'Termo em dia']);

        $usuario = $this->usuarioComPermissoes(['assinatura.documentos']);

        $this->actingAs($usuario)->post(route('signature-documents.hide', $arquivado))
            ->assertRedirect(route('signature-documents.index'));
        // O aviso de sucesso cita o título: consome o flash antes de olhar a lista.
        $this->actingAs($usuario)->get(route('signature-documents.index'))
            ->assertSee('arquivado');

        $arquivado->refresh();
        $this->assertTrue($arquivado->isHidden());
        $this->assertSame(1, $arquivado->hidden_by);
        // Só exibição: a situação e a cópia no FTP não mudam.
        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $arquivado->status);
        $this->assertNull($arquivado->archived_at);

        $this->actingAs($usuario)->get(route('signature-documents.index'))
            ->assertOk()
            ->assertSee('Termo em dia')
            ->assertDontSee('Termo esquecido');

        $this->actingAs($usuario)->get(route('signature-documents.index', ['arquivados' => 1]))
            ->assertOk()
            ->assertSee('Termo esquecido')
            ->assertDontSee('Termo em dia');

        $this->actingAs($usuario)->get(route('signature-documents.show', $arquivado))
            ->assertOk()
            ->assertSee('Desarquivar');
    }

    public function test_desarquivar_devolve_a_lista(): void
    {
        $documento = $this->criaDocumentoDeAssinatura(['title' => 'Volta para a lista']);
        $documento->forceFill(['hidden_at' => now(), 'hidden_by' => 1, 'hidden_by_name' => 'Fulano'])->save();

        $usuario = $this->usuarioComPermissoes(['assinatura.documentos']);

        $this->actingAs($usuario)->delete(route('signature-documents.unhide', $documento))
            ->assertRedirect(route('signature-documents.show', $documento));

        $documento->refresh();
        $this->assertFalse($documento->isHidden());
        $this->assertNull($documento->hidden_by_name);

        $this->actingAs($usuario)->get(route('signature-documents.index'))
            ->assertSee('Volta para a lista');
    }

    public function test_quem_so_consulta_nao_arquiva(): void
    {
        $documento = $this->criaDocumentoDeAssinatura(['title' => 'Só leitura']);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.consultar']))
            ->post(route('signature-documents.hide', $documento))
            ->assertForbidden();

        $this->assertFalse($documento->refresh()->isHidden());
    }
}
