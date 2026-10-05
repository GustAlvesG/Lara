<?php

namespace Tests\Feature;

use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureDocumentService;
use Database\Seeders\SignatureTemplateSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * Os três modelos que acompanham o módulo.
 *
 * O teste existe por um motivo específico: o seeder passa o texto pelo
 * HtmlSanitizer, e a allow-list PADRÃO dele não aceita título nem lista. Se a
 * liberação extra deste módulo (`EXTRA_HTML_TAGS`) sumir num refactor, as
 * cláusulas numeradas dos contratos viram parágrafo corrido — e ninguém
 * perceberia até um contrato sair impresso errado.
 */
class SignatureTemplateSeederTest extends TestCase
{
    use CreatesSignatureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();

        Storage::fake(config('signature.disk'));
    }

    public function test_seeder_cria_os_tres_modelos(): void
    {
        $this->seed(SignatureTemplateSeeder::class);

        $this->assertSame(3, SignatureTemplate::count());

        foreach (SignatureTemplate::all() as $modelo) {
            $this->assertTrue($modelo->active);
            $this->assertSame(1, $modelo->version);
            // A primeira versão aponta para si mesma.
            $this->assertSame($modelo->id, $modelo->root_id);
            $this->assertStringContainsString('[[assinatura]]', $modelo->body_html);
        }
    }

    /** Rodar de novo não duplica nem sobrescreve. */
    public function test_seeder_e_idempotente(): void
    {
        $this->seed(SignatureTemplateSeeder::class);

        $modelo = SignatureTemplate::first();
        $modelo->newVersion(['body_html' => '<p>Texto revisado pelo jurídico.</p>[[assinatura]]'], userId: 1);

        $this->seed(SignatureTemplateSeeder::class);

        // Continuam três modelos (o revisado tem duas versões).
        $this->assertSame(3, SignatureTemplate::distinct('root_id')->count('root_id'));
        $this->assertStringContainsString(
            'revisado pelo jurídico',
            SignatureTemplate::where('root_id', $modelo->root_id)->orderByDesc('version')->first()->body_html,
        );
    }

    /**
     * A estrutura do texto precisa sobreviver ao saneamento: cláusula numerada
     * é lista, e cabeçalho de seção é título.
     */
    public function test_saneamento_preserva_listas_titulos_e_tabelas(): void
    {
        $this->seed(SignatureTemplateSeeder::class);

        $contrato = SignatureTemplate::where('name', 'like', 'Contrato de Locação%')->first();

        $this->assertStringContainsString('<ol>', $contrato->body_html);
        $this->assertStringContainsString('<li>', $contrato->body_html);
        $this->assertStringContainsString('<h3>', $contrato->body_html);
        $this->assertStringContainsString('<table>', $contrato->body_html);
    }

    /**
     * O que interessa no fim: os modelos geram documento congelável, com as
     * variáveis substituídas e sem marcador sobrando.
     */
    public function test_modelos_geram_documento_congelavel(): void
    {
        $this->seed(SignatureTemplateSeeder::class);

        $modelo = SignatureTemplate::where('name', 'like', 'Termo de Responsabilidade%')->first();

        $documento = $this->criaDocumentoDeAssinatura([
            'template' => $modelo,
            'data' => ['espaco' => 'Piscina adulto', 'validade' => 'de 12 meses'],
        ]);

        $congelado = app(SignatureDocumentService::class)->freeze($documento);

        $this->assertStringContainsString('Piscina adulto', $congelado->body_snapshot);
        $this->assertStringNotContainsString('[[espaco]]', $congelado->body_snapshot);
        $this->assertSame(64, strlen($congelado->original_sha256));

        Storage::disk(config('signature.disk'))->assertExists($congelado->original_path);
    }

    /** Variável obrigatória em branco continua barrando o congelamento. */
    public function test_modelo_com_variavel_obrigatoria_exige_preenchimento(): void
    {
        $this->seed(SignatureTemplateSeeder::class);

        $modelo = SignatureTemplate::where('name', 'like', 'Contrato de Locação%')->first();

        $documento = $this->criaDocumentoDeAssinatura(['template' => $modelo, 'data' => []]);

        $this->expectException(\App\Exceptions\SignatureDocumentLockedException::class);
        $this->expectExceptionMessage('Espaço locado');

        app(SignatureDocumentService::class)->freeze($documento);
    }
}
