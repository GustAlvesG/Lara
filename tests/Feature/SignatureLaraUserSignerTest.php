<?php

namespace Tests\Feature;

use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Models\User;
use App\Services\Signature\Directors\DirectorAreaService;
use App\Services\Signature\LaraUserSignerService;
use App\Services\Signature\SignatureDocumentService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * Signatário "usuário do Lara" (09/11/2026): o atendente escolhe um usuário
 * do sistema; nome, CPF e contato saem do cadastro, nunca do formulário. Ele
 * assina pelo QR ou pelo link, como qualquer signatário, e conta como "de
 * fora da diretoria". A parte do modelo pode já trazer o usuário.
 */
class SignatureLaraUserSignerTest extends TestCase
{
    use CreatesSignatureSchema;
    use MigratesAccessSchema;
    use MocksSignatureUser;
    use SharesSqliteWithUserConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateAccessSchema();
        $this->shareSqliteWithUserConnection();
        $this->createSignatureSchema();

        Storage::fake(config('signature.disk'));
        Mail::fake();
    }

    private function usuario(string $nome = 'Carla do RH', array $extra = []): User
    {
        return $this->makeUser($nome, array_merge([
            'cpf' => '52998224725',
            'phone' => '11987654321',
            'status_id' => 1,
        ], $extra));
    }

    public function test_dados_vem_do_cadastro_e_nao_do_formulario(): void
    {
        $carla = $this->usuario();
        $modelo = $this->criaModeloDeAssinatura();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.store'), [
                'signature_template_id' => $modelo->id,
                'signers' => [
                    ['name' => 'Nome Inventado', 'cpf' => '11144477735', 'email' => 'x@y.z', 'lara_user' => (string) $carla->id],
                ],
            ])
            ->assertSessionHasNoErrors();

        $signer = SignatureDocument::sole()->signers()->sole();

        $this->assertSame('Carla do RH', $signer->name);
        $this->assertSame('52998224725', $signer->cpf);
        $this->assertSame($carla->email, $signer->email);
        $this->assertSame('11987654321', $signer->phone);
        $this->assertSame($carla->id, $signer->user_id);
        $this->assertTrue($signer->isLaraUser());
        $this->assertFalse($signer->isDirector());
    }

    public function test_recusa_usuario_inativo_ou_sem_cpf(): void
    {
        $inativo = $this->usuario('Inativo', ['status_id' => 2]);
        $semCpf = $this->usuario('Sem CPF', ['cpf' => null]);
        $modelo = $this->criaModeloDeAssinatura();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.store'), [
                'signature_template_id' => $modelo->id,
                'signers' => [
                    ['name' => 'A', 'cpf' => '11144477735', 'lara_user' => (string) $inativo->id],
                    ['name' => 'B', 'cpf' => '11144477735', 'lara_user' => (string) $semCpf->id],
                ],
            ])
            ->assertSessionHasErrors(['signers.0.lara_user', 'signers.1.lara_user']);

        $this->assertSame(0, SignatureDocument::count());
    }

    public function test_user_id_solto_sem_escolha_nao_vale(): void
    {
        $carla = $this->usuario();
        $modelo = $this->criaModeloDeAssinatura();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.store'), [
                'signature_template_id' => $modelo->id,
                'signers' => [['name' => 'Maria de Souza', 'cpf' => '11144477735', 'user_id' => $carla->id]],
            ])
            ->assertSessionHasNoErrors();

        $signer = SignatureDocument::sole()->signers()->sole();

        $this->assertNull($signer->user_id);
        $this->assertFalse($signer->isLaraUser());
    }

    public function test_assina_pelo_qr_do_balcao_como_qualquer_signatario(): void
    {
        $carla = $this->usuario();
        $documento = $this->criaDocumentoDeAssinatura([], app(LaraUserSignerService::class)->signerData($carla->id));
        $documento = app(SignatureDocumentService::class)->freeze($documento);

        $signer = $documento->signers()->sole();

        $this->assertTrue($signer->isLaraUser());
        $this->assertNull($signer->releaseBlockReason());
    }

    public function test_diretoria_espera_o_usuario_do_lara(): void
    {
        $carla = $this->usuario();
        $ana = $this->usuario('Ana Diretora', ['cpf' => '12345678909', 'matricula' => '777']);
        $this->joinSector($ana, User::DIRECTORS_SECTOR);

        $servico = app(DirectorAreaService::class);
        $area = $servico->createArea('Diretoria Financeira', null);
        $servico->assign($area, $ana->fresh(), \App\Models\SignatureDirectorAreaHolder::ROLE_PRIMARY);

        $documento = $this->criaDocumentoDeAssinatura([], app(LaraUserSignerService::class)->signerData($carla->id));
        SignatureSigner::create(array_merge(
            ['signature_document_id' => $documento->id, 'position' => 2],
            $servico->signerData($area->id, $ana->id),
        ));
        $documento = app(SignatureDocumentService::class)->freeze($documento->fresh());

        $this->assertSame(['Carla do RH'], $documento->pendingNonDirectors()->pluck('name')->all());
        $this->assertStringContainsString('Carla do RH', (string) $documento->signers->firstWhere('user_id', $ana->id)->releaseBlockReason(true));
    }

    public function test_busca_devolve_usuarios_ativos_com_cpf_mascarado(): void
    {
        $this->usuario();
        $this->usuario('Carlos Inativo', ['status_id' => 2, 'cpf' => '11144477735']);
        $this->usuario('Carolina Sem CPF', ['cpf' => null]);

        $resposta = $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->getJson(route('signature-documents.lara-users', ['q' => 'Car']))
            ->assertOk()
            ->json();

        $this->assertSame(['Carla do RH', 'Carolina Sem CPF'], array_column($resposta, 'name'));
        $this->assertSame(\App\Support\Cpf::mask('52998224725'), $resposta[0]['cpf_masked']);
        $this->assertNull($resposta[0]['blocked']);
        $this->assertStringContainsString('sem CPF', $resposta[1]['blocked']);
        $this->assertStringNotContainsString('52998224725', json_encode($resposta));
    }

    public function test_busca_pede_permissao_de_documento_ou_modelo(): void
    {
        $this->actingAs($this->usuarioComPermissoes([]))
            ->getJson(route('signature-documents.lara-users', ['q' => 'Car']))
            ->assertForbidden();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->getJson(route('signature-documents.lara-users', ['q' => 'Car']))
            ->assertOk();
    }

    public function test_parte_do_modelo_traz_o_usuario_e_o_formulario_ja_nasce_com_ele(): void
    {
        $carla = $this->usuario();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->post(route('signature-templates.store'), [
                'name' => 'Termo do RH',
                'body_html' => '<p>Texto.</p>[[assinatura:empresa]] [[assinatura:colaborador]]',
                'identity_check' => SignatureTemplate::IDENTITY_NONE,
                'parties' => [
                    ['key' => 'empresa', 'label' => 'Empresa', 'lara_user_id' => (string) $carla->id],
                    ['key' => 'colaborador', 'label' => 'Colaborador'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $modelo = SignatureTemplate::where('name', 'Termo do RH')->sole();

        $this->assertSame($carla->id, $modelo->declaredParties()[0]['lara_user_id']);
        $this->assertNull($modelo->declaredParties()[1]['lara_user_id']);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.create', ['template' => $modelo->id]))
            ->assertOk()
            ->assertSee('name="signers[0][lara_user]" value="' . $carla->id . '"', false)
            ->assertSee('Carla do RH');
    }

    public function test_modelo_recusa_usuario_sem_cpf_e_parte_com_area_e_usuario(): void
    {
        $semCpf = $this->usuario('Sem CPF', ['cpf' => null]);
        $carla = $this->usuario();
        $area = app(DirectorAreaService::class)->createArea('Diretoria Social', null);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->post(route('signature-templates.store'), [
                'name' => 'Termo',
                'body_html' => '<p>Texto.</p>[[assinatura:a]] [[assinatura:b]]',
                'identity_check' => SignatureTemplate::IDENTITY_NONE,
                'parties' => [
                    ['key' => 'a', 'label' => 'A', 'lara_user_id' => (string) $semCpf->id],
                    ['key' => 'b', 'label' => 'B', 'lara_user_id' => (string) $carla->id, 'director_area_id' => (string) $area->id],
                ],
            ])
            ->assertSessionHasErrors(['parties.0.lara_user_id', 'parties.1.lara_user_id']);
    }
}
