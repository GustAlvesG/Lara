<?php

namespace Tests\Feature;

use App\Models\SignatureDocument;
use App\Services\Signature\SignatureDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * A página pública `/validar/{codigo}`.
 *
 * Ela responde a quem tem o papel na mão — sem login, porque quem chegou veio
 * do QR impresso. Por isso mostra pouco: título, data, situação e signatários
 * com CPF mascarado. Nem foto, nem traço, nem contato, nem o PDF.
 */
class SignatureValidationPageTest extends TestCase
{
    use CreatesSignatureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();

        Storage::fake(config('signature.disk'));
    }

    private function documentoCongelado(): SignatureDocument
    {
        return app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());
    }

    public function test_pagina_mostra_o_documento_sem_expor_dado_pessoal(): void
    {
        $documento = $this->documentoCongelado();

        $resposta = $this->get(route('signature.validate', $documento->validation_code));

        $resposta->assertOk()
            ->assertSee($documento->title)
            ->assertSee($documento->validation_code)
            ->assertSee('Maria de Souza')
            ->assertSee('123.***.**9-09');

        // O CPF inteiro nunca aparece numa página pública.
        $resposta->assertDontSee('12345678909');
    }

    /**
     * Código inventado e rascunho respondem a MESMA coisa: "não encontrado".
     * Distinguir os dois confirmaria a existência de documentos alheios.
     */
    public function test_codigo_inexistente_responde_nao_encontrado(): void
    {
        $this->get(route('signature.validate', 'ZZZZZZZZZZZZ'))
            ->assertOk()
            ->assertSee('Documento não encontrado');
    }

    public function test_documento_em_rascunho_nao_e_validavel(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        // Rascunho não tem código; mesmo com um inventado, a página não o acha.
        $documento->forceFill(['validation_code' => 'ABCDEFGH2345'])->save();

        $this->get(route('signature.validate', 'ABCDEFGH2345'))
            ->assertOk()
            ->assertSee('Documento não encontrado');
    }

    public function test_upload_do_pdf_correto_confere(): void
    {
        $documento = $this->documentoCongelado();

        $bytes = Storage::disk(config('signature.disk'))->get($documento->original_path);

        $resposta = $this->post(
            route('signature.validate.verify', $documento->validation_code),
            ['documento' => UploadedFile::fake()->createWithContent('documento.pdf', $bytes)],
        );

        $resposta->assertOk()
            ->assertSee('É o documento original, antes das assinaturas.')
            ->assertSee($documento->original_sha256);
    }

    public function test_upload_de_arquivo_alterado_nao_confere(): void
    {
        $documento = $this->documentoCongelado();

        $bytes = Storage::disk(config('signature.disk'))->get($documento->original_path) . '% alterado';

        $this->post(
            route('signature.validate.verify', $documento->validation_code),
            ['documento' => UploadedFile::fake()->createWithContent('documento.pdf', $bytes)],
        )
            ->assertOk()
            ->assertSee('Não confere.');
    }

    public function test_upload_que_nao_e_pdf_e_recusado(): void
    {
        $documento = $this->documentoCongelado();

        $this->post(
            route('signature.validate.verify', $documento->validation_code),
            ['documento' => UploadedFile::fake()->createWithContent('planilha.csv', 'a;b;c')],
        )
            ->assertSessionHasErrors('documento');
    }

    /**
     * O arquivo enviado para conferência não é guardado: validar não é receber
     * documento de ninguém.
     */
    public function test_arquivo_enviado_nao_e_gravado(): void
    {
        $documento = $this->documentoCongelado();

        $bytes = Storage::disk(config('signature.disk'))->get($documento->original_path);

        $antes = count(Storage::disk(config('signature.disk'))->allFiles());

        $this->post(
            route('signature.validate.verify', $documento->validation_code),
            ['documento' => UploadedFile::fake()->createWithContent('documento.pdf', $bytes)],
        )->assertOk();

        $this->assertSame($antes, count(Storage::disk(config('signature.disk'))->allFiles()));
    }

    public function test_pagina_nao_entrega_o_pdf(): void
    {
        $documento = $this->documentoCongelado();

        $html = $this->get(route('signature.validate', $documento->validation_code))->getContent();

        // Nenhum link para baixar o documento: quem tem o código tem o papel.
        $this->assertStringNotContainsString(route('signature-documents.pdf', $documento), $html);
        $this->assertStringNotContainsString('original.pdf', $html);
    }
}
