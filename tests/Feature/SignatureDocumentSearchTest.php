<?php

namespace Tests\Feature;

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
