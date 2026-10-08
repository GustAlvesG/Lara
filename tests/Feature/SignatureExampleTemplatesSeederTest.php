<?php

namespace Tests\Feature;

use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureDocumentRenderer;
use Database\Seeders\SignatureExampleTemplatesSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * Os modelos de exemplo para teste manual: criados uma vez só, cada um
 * montando um documento sem marcador sobrando, e nunca em produção.
 */
class SignatureExampleTemplatesSeederTest extends TestCase
{
    use CreatesSignatureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        Storage::fake(config('signature.disk'));
    }

    public function test_cria_os_modelos_uma_vez_so(): void
    {
        $this->seed(SignatureExampleTemplatesSeeder::class);
        $this->seed(SignatureExampleTemplatesSeeder::class);

        $this->assertSame(7, SignatureTemplate::where('name', 'like', '[Exemplo]%')->count());
    }

    public function test_cada_modelo_monta_um_documento_sem_marcador_sobrando(): void
    {
        $this->seed(SignatureExampleTemplatesSeeder::class);

        foreach (SignatureTemplate::all() as $modelo) {
            $documento = $this->criaDocumentoDeAssinatura(['template' => $modelo]);

            if ($modelo->declaredParties()) {
                $documento->signers()->first()->forceFill(['party' => 'contratante'])->save();
                $documento->signers()->create(['name' => 'Joao', 'cpf' => '52998224725', 'position' => 2, 'party' => 'contratado']);
            }

            $html = app(SignatureDocumentRenderer::class)->html($documento->fresh(['template', 'signers']));

            $this->assertDoesNotMatchRegularExpression('/\[\[[a-z]/', $html, $modelo->name);
        }
    }

    public function test_recursos_de_cada_exemplo(): void
    {
        $this->seed(SignatureExampleTemplatesSeeder::class);

        $modelo = fn(string $n) => SignatureTemplate::where('name', 'like', "[Exemplo] {$n}.%")->firstOrFail();

        $this->assertNotEmpty($modelo('2')->signerFields());
        $this->assertNotEmpty($modelo('2')->automaticFields());
        $this->assertCount(3, $modelo('3')->declaredAttachments());
        $this->assertTrue($modelo('4')->requires_initials);
        $this->assertSame(SignatureTemplate::IDENTITY_EMAIL, $modelo('5')->identity_check);

        // O do gov.br precisa poder ir para o gov.br: sem perguntas e sem visto.
        $this->assertSame([], $modelo('6')->signerFields());
        $this->assertFalse($modelo('6')->requires_initials);
    }

    public function test_nao_roda_em_producao(): void
    {
        $this->app['env'] = 'production';

        // Direto, e não por db:seed — que em produção pararia pedindo confirmação.
        app(SignatureExampleTemplatesSeeder::class)->run();

        $this->assertSame(0, SignatureTemplate::count());
    }
}
