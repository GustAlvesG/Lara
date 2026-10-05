<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Models\SignatureRequest;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * O guia do usuário dentro do sistema, e o endereço da tela do tablet que ele
 * ensina.
 */
class SignatureGuideTest extends TestCase
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

    public function test_guia_abre_para_quem_alcanca_qualquer_parte_do_modulo(): void
    {
        foreach (['assinatura.documentos', 'assinatura.consultar', 'assinatura.modelos'] as $permissao) {
            $this->actingAs($this->usuarioComPermissoes([$permissao]))
                ->get(route('signature-guide.index'))
                ->assertOk()
                ->assertSee('Baixar em PDF')
                ->assertSee(route('signature-guide.content'));
        }

        $this->actingAs($this->usuarioComPermissoes([]))
            ->get(route('signature-guide.index'))
            ->assertForbidden();
    }

    public function test_item_do_menu_aparece_ao_lado_de_documentos_e_modelos(): void
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.index'))
            ->assertOk()
            ->assertSee(route('signature-guide.index'));
    }

    public function test_conteudo_do_guia_ensina_o_endereco_que_a_rota_usa(): void
    {
        $conteudo = $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-guide.content'))
            ->assertOk()
            ->assertSee('Assinatura de documentos')
            ->assertSee('<code>/assinatura/kiosk</code>', false)
            // Os exemplos de marcador saem como texto, sem o Blade interpretá-los.
            ->assertSee('[[assinatura: Contratante]]')
            ->assertSee('{{Data}}', false)
            ->getContent();

        $this->assertStringNotContainsString('/quiosque', $conteudo);
        $this->assertStringNotContainsString('@verbatim', $conteudo);
    }

    public function test_guia_sai_em_pdf(): void
    {
        $resposta = $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->get(route('signature-guide.pdf'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_tela_do_tablet_fica_em_assinatura_kiosk(): void
    {
        $this->assertSame('/assinatura/kiosk', parse_url(route('quiosque.index'), PHP_URL_PATH));

        $this->get('/assinatura/kiosk')->assertOk();

        // Um tablet configurado com o endereço antigo cai no novo.
        $this->get('/quiosque')->assertRedirect('/assinatura/kiosk');
    }

    /**
     * O cookie da sessão só viaja nas requisições da tela do tablet. Se o
     * caminho dele ficasse no endereço antigo, o tablet abriria a sessão e a
     * perderia na requisição seguinte.
     */
    public function test_cookie_da_sessao_acompanha_o_endereco_da_tela(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());
        $liberacao = app(SignatureRequestService::class)->issue($documento->nextSigner());

        $cookie = $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload($liberacao['token']),
        ])->assertOk()->getCookie(EnsureSignatureKioskSession::COOKIE);

        $this->assertSame('/assinatura/kiosk', $cookie->getPath());
    }
}
