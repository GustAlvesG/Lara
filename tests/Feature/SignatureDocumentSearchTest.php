<?php

namespace Tests\Feature;

use App\Models\SignatureContract;
use App\Models\SignatureDocument;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Busca e filtros da lista de documentos (SignatureDocument::scopeSearch) e as
 * abas Ativos/Inativos da lista de modelos.
 */
class SignatureDocumentSearchTest extends TestCase
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

    private function lista(array $query)
    {
        return $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.index', $query))
            ->assertOk();
    }

    public function test_cada_palavra_pode_vir_de_um_campo_diferente(): void
    {
        $this->criaDocumentoDeAssinatura(['title' => 'Termo da piscina'], ['name' => 'Maria de Souza']);
        $this->criaDocumentoDeAssinatura(['title' => 'Termo da academia'], ['name' => 'Maria Pereira']);
        $this->criaDocumentoDeAssinatura(['title' => 'Ficha da piscina'], ['name' => 'João Lima']);

        $this->lista(['q' => 'maria piscina'])
            ->assertSee('Termo da piscina')
            ->assertDontSee('Termo da academia')
            ->assertDontSee('Ficha da piscina');
    }

    public function test_acha_por_pedaco_do_cpf_com_ou_sem_pontuacao(): void
    {
        $this->criaDocumentoDeAssinatura(['title' => 'Do CPF certo'], ['cpf' => '52998224725']);
        $this->criaDocumentoDeAssinatura(['title' => 'De outro CPF'], ['cpf' => '12345678909']);

        $this->lista(['q' => '982.247'])->assertSee('Do CPF certo')->assertDontSee('De outro CPF');
        $this->lista(['q' => '529.982.247-25'])->assertSee('Do CPF certo')->assertDontSee('De outro CPF');
    }

    public function test_acha_por_email_modelo_codigo_e_quem_gerou(): void
    {
        $modelo = $this->criaModeloDeAssinatura(['name' => 'Contrato de locação do salão']);
        $this->criaDocumentoDeAssinatura(['title' => 'Do salão', 'template' => $modelo]);
        $this->criaDocumentoDeAssinatura(['title' => 'Com e-mail'], ['email' => 'fulano@exemplo.com']);
        $this->criaDocumentoDeAssinatura(['title' => 'Com código', 'validation_code' => 'ABCD1234WXYZ']);
        $this->criaDocumentoDeAssinatura(['title' => 'Da Joana', 'created_by_name' => 'Joana Atendente']);

        $this->lista(['q' => 'locação'])->assertSee('Do salão')->assertDontSee('Com e-mail');
        $this->lista(['q' => 'fulano@'])->assertSee('Com e-mail')->assertDontSee('Do salão');
        $this->lista(['q' => '1234wx'])->assertSee('Com código')->assertDontSee('Do salão');
        $this->lista(['q' => 'joana'])->assertSee('Da Joana')->assertDontSee('Com código');
    }

    public function test_filtra_por_modelo_em_todas_as_versoes_e_avulso(): void
    {
        $v1 = $this->criaModeloDeAssinatura(['name' => 'Ficha de sócio']);
        $v2 = $this->criaModeloDeAssinatura(['name' => 'Ficha de sócio', 'root_id' => $v1->root_id, 'version' => 2]);
        $avulso = $this->criaModeloDeAssinatura(['name' => 'PDF enviado', 'single_use' => true]);

        $this->criaDocumentoDeAssinatura(['title' => 'Ficha antiga', 'template' => $v1]);
        $this->criaDocumentoDeAssinatura(['title' => 'Ficha nova', 'template' => $v2]);
        $this->criaDocumentoDeAssinatura(['title' => 'Arquivo pronto', 'template' => $avulso]);

        $this->lista(['modelo' => $v1->root_id])
            ->assertSee('Ficha antiga')->assertSee('Ficha nova')->assertDontSee('Arquivo pronto');
        $this->lista(['modelo' => 'avulso'])
            ->assertSee('Arquivo pronto')->assertDontSee('Ficha antiga');
    }

    /** Documento nascido de contrato, como o ContractDocumentGenerator deixa. */
    private function documentoDeContrato(string $numero, string $contratada, string $cnpj): SignatureDocument
    {
        $modelo = $this->criaModeloDeAssinatura(['name' => $numero, 'single_use' => true]);
        $documento = $this->criaDocumentoDeAssinatura(['title' => $numero . ' — Serviços', 'template' => $modelo]);

        SignatureContract::create([
            'number' => $numero,
            'title' => 'Serviços',
            'status' => SignatureContract::STATUS_APPROVED,
            'signature_document_id' => $documento->id,
            'content' => ['parties' => [['role' => 'contratada', 'name' => $contratada, 'document' => $cnpj]]],
        ]);

        return $documento;
    }

    public function test_acha_contrato_pelo_numero_em_qualquer_grafia_e_pela_contratada(): void
    {
        $this->documentoDeContrato('CTL - 3/26', 'Construções Alfa LTDA', '04758575000129');
        $this->documentoDeContrato('CTL - 13/26', 'Beta Serviços', '11222333000181');
        $this->criaDocumentoDeAssinatura(['title' => 'Termo comum']);

        foreach (['ctl3/26', 'CTL-3/26', 'ctl 3', 'CTL - 3/2026'] as $busca) {
            $this->lista(['q' => $busca])->assertSee('CTL - 3/26')->assertDontSee('CTL - 13/26')->assertDontSee('Termo comum');
        }

        $this->lista(['q' => 'Construções'])->assertSee('CTL - 3/26')->assertDontSee('CTL - 13/26');
        $this->lista(['q' => '04.758.575/0001-29'])->assertSee('CTL - 3/26')->assertDontSee('CTL - 13/26');
        $this->lista(['q' => 'ctl 3 alfa'])->assertSee('CTL - 3/26');
        $this->lista(['q' => 'ctl 3 beta'])->assertDontSee('CTL - 3/26');
    }

    public function test_filtro_contratos_nao_se_mistura_com_pdf_enviado_pronto(): void
    {
        $this->documentoDeContrato('CTL - 3/26', 'Alfa', '04758575000129');
        $avulso = $this->criaModeloDeAssinatura(['name' => 'PDF enviado', 'single_use' => true]);
        $this->criaDocumentoDeAssinatura(['title' => 'Arquivo pronto', 'template' => $avulso]);

        $this->lista(['modelo' => 'contratos'])->assertSee('CTL - 3/26 — Serviços')->assertDontSee('Arquivo pronto');
        $this->lista(['modelo' => 'avulso'])->assertSee('Arquivo pronto')->assertDontSee('CTL - 3/26 — Serviços');
    }

    public function test_lista_de_contratos_acha_numero_e_contratada_com_acento(): void
    {
        SignatureContract::create(['number' => 'CTL - 3/26', 'title' => 'Primeiro', 'sector_id' => 1, 'content' => ['parties' => [['name' => 'Construções Alfa', 'document' => '04758575000129']]]]);
        SignatureContract::create(['number' => 'CTL - 13/26', 'title' => 'Segundo', 'sector_id' => 1, 'content' => ['parties' => [['name' => 'Beta', 'document' => '11222333000181']]]]);

        $this->assertSame(['Primeiro'], SignatureContract::search('ctl3/26')->pluck('title')->all());
        $this->assertSame(['Primeiro'], SignatureContract::search('Construções')->pluck('title')->all());
        $this->assertSame(['Primeiro'], SignatureContract::search('04.758.575/0001-29')->pluck('title')->all());
    }

    public function test_filtra_por_periodo_e_por_quem_gerou(): void
    {
        $velho = $this->criaDocumentoDeAssinatura(['title' => 'Do mês passado', 'created_by' => 99]);
        SignatureDocument::whereKey($velho->id)->update(['created_at' => now()->subMonth()]);
        $this->criaDocumentoDeAssinatura(['title' => 'De hoje, meu', 'created_by' => 1]);
        $this->criaDocumentoDeAssinatura(['title' => 'De hoje, de outro', 'created_by' => 99]);

        $this->lista(['de' => now()->subDay()->toDateString(), 'ate' => now()->toDateString()])
            ->assertSee('De hoje, meu')->assertDontSee('Do mês passado');

        // O usuário de teste tem id 1.
        $this->lista(['meus' => 1])
            ->assertSee('De hoje, meu')->assertDontSee('De hoje, de outro');

        // Data inválida não filtra nem quebra.
        $this->lista(['de' => '2026-13-45'])->assertSee('Do mês passado');
    }

    public function test_modelos_inativos_ficam_numa_aba_propria(): void
    {
        $this->criaModeloDeAssinatura(['name' => 'Modelo em uso']);
        $this->criaModeloDeAssinatura(['name' => 'Modelo aposentado', 'active' => false]);

        $usuario = $this->usuarioComPermissoes(['assinatura.modelos']);

        $this->actingAs($usuario)->get(route('signature-templates.index'))
            ->assertOk()
            ->assertSee('Modelo em uso')
            ->assertDontSee('Modelo aposentado');

        $this->actingAs($usuario)->get(route('signature-templates.index', ['aba' => 'inativos']))
            ->assertOk()
            ->assertSee('Modelo aposentado')
            ->assertDontSee('Modelo em uso');
    }
}
