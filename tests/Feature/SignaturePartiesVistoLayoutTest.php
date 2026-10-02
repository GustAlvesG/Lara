<?php

namespace Tests\Feature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Jobs\FinalizeSignatureDocument;
use App\Models\SignatureDocument;
use App\Models\SignatureLayout;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignaturePageGeometry;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * O que um contrato tem e um termo de uma pessoa só não tinha: partes que
 * assinam cada uma no seu lugar, visto em todas as páginas e o papel timbrado
 * da empresa.
 */
class SignaturePartiesVistoLayoutTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Queue::fake();
    }

    private function contrato(array $attributes = []): SignatureTemplate
    {
        return $this->criaModeloDeAssinatura(array_merge([
            'name' => 'Contrato de locação',
            'body_html' => "<p>As partes celebram o presente contrato.</p>\n"
                . "[[assinatura:contratante]]\n[[assinatura:contratado]]",
            'parties' => [
                ['key' => 'contratante', 'label' => 'Contratante'],
                ['key' => 'contratado', 'label' => 'Contratado'],
            ],
            'identity_check' => SignatureTemplate::IDENTITY_NONE,
            'requires_photo' => false,
        ], $attributes));
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $signers
     */
    private function documento(SignatureTemplate $modelo, ?array $signers = null): SignatureDocument
    {
        return app(SignatureDocumentService::class)->create($modelo, [], $signers ?? [
            ['name' => 'Maria de Souza', 'cpf' => '12345678909', 'party' => 'contratante'],
            ['name' => 'João Pereira', 'cpf' => '98765432100', 'party' => 'contratado'],
        ]);
    }

    private function congela(SignatureDocument $documento): SignatureDocument
    {
        return app(SignatureDocumentService::class)->freeze($documento);
    }

    /** PNG transparente com um risco no meio — uma rubrica de mentira, com margem em volta. */
    private function png(int $largura = 400, int $altura = 240): string
    {
        $imagem = imagecreatetruecolor($largura, $altura);
        imagealphablending($imagem, false);
        imagesavealpha($imagem, true);
        imagefill($imagem, 0, 0, imagecolorallocatealpha($imagem, 0, 0, 0, 127));
        imagesetthickness($imagem, 3);
        imageline($imagem, 150, 100, 230, 140, imagecolorallocatealpha($imagem, 20, 20, 20, 0));

        ob_start();
        imagepng($imagem);

        return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
    }

    /** @return array<int, array<string, mixed>> */
    private function tracos(int $pontos): array
    {
        $lista = [];

        for ($i = 0; $i < $pontos; $i++) {
            $lista[] = ['x' => $i, 'y' => $i % 20, 't' => $i * 12];
        }

        return [['points' => $lista]];
    }

    /** Abre a sessão do próximo signatário e confirma a identidade. */
    private function sessao(SignatureDocument $documento): string
    {
        $liberacao = app(SignatureRequestService::class)->issue($documento->nextSigner());

        $cookie = $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload($liberacao['token']),
        ])->assertOk()->getCookie(EnsureSignatureKioskSession::COOKIE)->getValue();

        $this->comSessao($cookie)->postJson(route('quiosque.identity', $documento), ['cpf' => '0'])->assertOk();

        return $cookie;
    }

    private function comSessao(string $cookie): self
    {
        return $this->withCredentials()->withCookie(EnsureSignatureKioskSession::COOKIE, $cookie);
    }

    /* ------------------------------------------------------------------
     | Partes
     |------------------------------------------------------------------*/

    public function test_cada_parte_assina_no_seu_lugar_com_o_seu_rotulo(): void
    {
        $documento = $this->congela($this->documento($this->contrato()));

        $html = app(SignatureDocumentRenderer::class)->html($documento);

        // Marcadores vizinhos saem lado a lado, numa tabela de layout.
        $this->assertSame(1, substr_count($html, '<table class="sig-grid">'));
        $this->assertSame(2, substr_count($html, 'class="sig-area"'));
        $this->assertStringNotContainsString('[[assinatura', $html);

        // Contratante à esquerda, Contratado à direita — a ordem do texto.
        $this->assertLessThan(strpos($html, 'João Pereira'), strpos($html, 'Maria de Souza'));
        $this->assertMatchesRegularExpression('/Maria de Souza.*?Contratante — CPF/s', $html);
        $this->assertMatchesRegularExpression('/João Pereira.*?Contratado — CPF/s', $html);
    }

    public function test_marcadores_separados_por_texto_ficam_cada_um_no_seu_ponto(): void
    {
        $modelo = $this->contrato([
            'body_html' => '[[assinatura:contratante]]<p>E, de outro lado:</p>[[assinatura:contratado]]',
        ]);

        $html = app(SignatureDocumentRenderer::class)->html($this->congela($this->documento($modelo)));

        $this->assertStringNotContainsString('sig-grid">', $html);
        $this->assertLessThan(strpos($html, 'E, de outro lado'), strpos($html, 'Maria de Souza'));
        $this->assertGreaterThan(strpos($html, 'E, de outro lado'), strpos($html, 'João Pereira'));
    }

    public function test_signatario_sem_parte_vai_para_o_marcador_generico_ou_para_o_fim(): void
    {
        $signers = [
            ['name' => 'Maria de Souza', 'cpf' => '12345678909', 'party' => 'contratante'],
            ['name' => 'João Pereira', 'cpf' => '98765432100', 'party' => 'contratado'],
            ['name' => 'Ana Testemunha', 'cpf' => '11144477735', 'role' => SignatureSigner::ROLE_WITNESS],
        ];

        $semGenerico = app(SignatureDocumentRenderer::class)
            ->html($this->congela($this->documento($this->contrato(), $signers)));

        // Sem lugar marcado, a testemunha vai para o fim — depois das partes.
        $this->assertGreaterThan(strpos($semGenerico, 'João Pereira'), strpos($semGenerico, 'Ana Testemunha'));
        $this->assertMatchesRegularExpression('/Ana Testemunha.*?Testemunha — CPF/s', $semGenerico);

        $comGenerico = app(SignatureDocumentRenderer::class)->html($this->congela($this->documento(
            $this->contrato(['body_html' => '<p>Testemunhas:</p>[[assinatura]]<p>Partes:</p>'
                . '[[assinatura:contratante]][[assinatura:contratado]]']),
            $signers,
        )));

        $this->assertLessThan(strpos($comGenerico, 'Partes:'), strpos($comGenerico, 'Ana Testemunha'));
    }

    public function test_parte_sem_signatario_barra_o_congelamento(): void
    {
        $rascunho = $this->documento($this->contrato(), [
            ['name' => 'Maria de Souza', 'cpf' => '12345678909', 'party' => 'contratante'],
            // Parte que o modelo não declara: é descartada, não gravada.
            ['name' => 'João Pereira', 'cpf' => '98765432100', 'party' => 'fiador'],
        ]);

        $this->assertNull($rascunho->signers[1]->party);

        $this->expectException(SignatureDocumentLockedException::class);
        $this->expectExceptionMessage('Falta informar quem assina como: Contratado.');

        $this->congela($rascunho);
    }

    public function test_modelo_grava_as_partes_e_declara_a_que_so_esta_no_texto(): void
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->post(route('signature-templates.store'), [
                'name' => 'Contrato',
                'body_html' => '[[assinatura:contratante]][[assinatura:fiador]]',
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'parties' => [['key' => 'contratante', 'label' => 'CONTRATANTE']],
                'requires_initials' => '1',
            ])
            ->assertSessionHasNoErrors();

        $modelo = SignatureTemplate::firstOrFail();

        $this->assertSame([
            ['key' => 'contratante', 'label' => 'CONTRATANTE'],
            ['key' => 'fiador', 'label' => 'Fiador'],
        ], $modelo->declaredParties());

        $this->assertTrue($modelo->requires_initials);
        // Marcador de parte não é campo do atendente.
        $this->assertSame([], $modelo->declaredVariables());

        // A revisão leva as partes e o visto para a versão seguinte.
        $this->assertSame($modelo->parties, $modelo->newVersion(['name' => 'Contrato v2'])->parties);
    }

    public function test_formulario_do_documento_pede_quem_assina_por_cada_parte(): void
    {
        $html = $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.create', ['template' => $this->contrato()->id]))
            ->assertOk()
            ->assertSee('Assina como')
            // Uma linha por parte, já escolhida.
            ->assertSee('name="signers[0][party]"', false)
            ->assertSee('name="signers[1][party]"', false)
            ->getContent();

        // Cada cartão leva no título a posição e a parte, e "Assina como" é o primeiro campo dele.
        $partes = array_column(SignatureTemplate::firstOrFail()->declaredParties(), 'label');

        $this->assertStringContainsString('data-signer-title>Signatário 1 - ' . $partes[0] . '</h4>', $html);
        $this->assertStringContainsString('data-signer-title>Signatário 2 - ' . $partes[1] . '</h4>', $html);
        $this->assertLessThan(
            strpos($html, 'name="signers[0][name]"'),
            strpos($html, 'name="signers[0][party]"'),
            '"Assina como" precisa vir antes do nome.',
        );

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.store'), [
                'signature_template_id' => SignatureTemplate::firstOrFail()->id,
                'signers' => [
                    ['name' => 'Maria de Souza', 'cpf' => '123.456.789-09', 'party' => 'contratado'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $signatario = SignatureSigner::firstOrFail();

        $this->assertSame('contratado', $signatario->party);
        $this->assertSame('Contratado', $signatario->capacityLabel());
    }

    /* ------------------------------------------------------------------
     | Visto
     |------------------------------------------------------------------*/

    public function test_modelo_com_visto_nao_aceita_assinatura_sem_a_rubrica(): void
    {
        $documento = $this->congela($this->documento($this->contrato(['requires_initials' => true])));
        $cookie = $this->sessao($documento);

        $assinatura = ['signature' => $this->png(), 'strokes' => $this->tracos(60), 'accepted' => true];

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), $assinatura)
            ->assertStatus(422)
            ->assertJsonPath('error', 'Faltou o visto. Faça a sua rubrica no espaço indicado.');

        // Um toque na tela não é rubrica.
        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), $assinatura + [
                'initials' => $this->png(),
                'initials_strokes' => $this->tracos(2),
            ])
            ->assertStatus(422);

        $this->assertSame(SignatureSigner::STATUS_PENDING, $documento->signers()->first()->status);
    }

    public function test_visto_e_gravado_recortado_e_o_tablet_e_avisado_da_exigencia(): void
    {
        $documento = $this->congela($this->documento($this->contrato(['requires_initials' => true])));

        $liberacao = app(SignatureRequestService::class)->issue($documento->nextSigner());

        $resposta = $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload($liberacao['token']),
        ])->assertOk();

        $this->assertTrue($resposta->json('rules.requires_initials'));
        // O tablet mostra a parte, e não o papel genérico.
        $this->assertSame('Contratante', $resposta->json('signer.role'));

        $cookie = $resposta->getCookie(EnsureSignatureKioskSession::COOKIE)->getValue();

        $this->comSessao($cookie)->postJson(route('quiosque.identity', $documento), ['cpf' => '0'])->assertOk();

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->png(),
                'strokes' => $this->tracos(60),
                'initials' => $this->png(),
                'initials_strokes' => $this->tracos(12),
                'accepted' => true,
            ])
            ->assertOk();

        $evidencia = $documento->signers()->first()->evidence;

        $this->assertNotNull($evidencia->initials_path);
        $this->assertCount(12, $evidencia->initials_strokes[0]['points']);

        // A tela de 400x240 foi recortada ao risco: é o que faz a rubrica
        // preencher a caixa do visto em vez de virar um ponto.
        [$largura, $altura] = getimagesizefromstring(
            Storage::disk(config('signature.disk'))->get($evidencia->initials_path),
        );

        $this->assertLessThan(120, $largura);
        $this->assertLessThan(80, $altura);
    }

    public function test_pdf_original_e_final_saem_com_o_visto(): void
    {
        $modelo = $this->contrato(['requires_initials' => true]);
        $documento = $this->congela($this->documento($modelo));

        $disk = Storage::disk(config('signature.disk'));

        // Original: as caixas em branco, desenhadas na página.
        $original = $disk->get($documento->original_path);

        $this->assertStringStartsWith('%PDF', $original);
        $this->assertSame($documento->original_sha256, hash('sha256', $original));

        foreach ([1, 2] as $vez) {
            $cookie = $this->sessao($documento->fresh());

            $this->comSessao($cookie)->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->png(),
                'strokes' => $this->tracos(60),
                'initials' => $this->png(),
                'initials_strokes' => $this->tracos(12),
                'accepted' => true,
            ])->assertOk();
        }

        (new FinalizeSignatureDocument($documento->id))->handle(
            app(SignatureDocumentRenderer::class),
            app(\App\Services\Signature\SignatureStateMachine::class),
            app(\App\Services\Signature\SignaturePdfSealer::class),
        );

        $documento->refresh();

        $this->assertSame(SignatureDocument::STATUS_FINALIZED, $documento->status);

        $final = $disk->get($documento->final_path);

        $this->assertStringStartsWith('%PDF', $final);

        // Duas assinaturas e dois vistos: quatro imagens a mais que o original,
        // que não tem nenhuma.
        $this->assertSame(0, substr_count($original, '/Subtype /Image'));
        $this->assertGreaterThanOrEqual(4, substr_count($final, '/Subtype /Image'));

        // O manifesto registra que o visto foi desenhado, e por quem.
        $this->assertStringContainsString(
            'Visto (rubrica) desenhado no tablet',
            app(SignatureDocumentRenderer::class)->html($documento, SignatureDocumentRenderer::MODE_FINAL),
        );
    }

    public function test_faixa_do_visto_abre_espaco_no_pe_da_pagina(): void
    {
        $sem = new SignaturePageGeometry(null, false);
        $com = new SignaturePageGeometry(null, true);

        // Sem papel timbrado e sem visto, a página é a de sempre.
        $this->assertSame([96, 78, -68, -56], [$sem->marginTop(), $sem->marginBottom(), $sem->headerTop(), $sem->footerTextBottom()]);

        $this->assertGreaterThan($sem->marginBottom(), $com->marginBottom());

        // A caixa do visto e o rótulo dela cabem entre o fim do texto e o
        // rodapé — sem cobrir a última linha nem a validação.
        $fimDoTexto = SignaturePageGeometry::PAGE_HEIGHT - $com->marginBottom();

        // O rodapé é fixo, `bottom` negativo a partir do fim do texto; 8 é a
        // borda e o respiro de cima dele.
        $inicioDoRodape = $fimDoTexto - $com->footerTextBottom() - $com->footerTextHeight() - 8;

        $this->assertGreaterThan($fimDoTexto, $com->vistoTop());
        $this->assertLessThanOrEqual(
            $inicioDoRodape,
            $com->vistoTop() + SignaturePageGeometry::VISTO_BOX_HEIGHT + 11,
        );
    }

    /* ------------------------------------------------------------------
     | Papel timbrado
     |------------------------------------------------------------------*/

    private function imagem(string $nome, int $largura, int $altura): UploadedFile
    {
        return UploadedFile::fake()->image($nome, $largura, $altura);
    }

    private function salvaPapelTimbrado(array $dados): void
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->post(route('signature-layout.update'), array_merge([
                'header_height_mm' => 20,
                'footer_height_mm' => 12,
                'align' => 'center',
            ], $dados))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('signature-layout.edit'));
    }

    public function test_papel_timbrado_entra_no_documento_congelado_depois_dele(): void
    {
        $this->salvaPapelTimbrado([
            'header_image' => $this->imagem('cabecalho.png', 1200, 200),
            'footer_image' => $this->imagem('rodape.png', 1200, 100),
            'footer_text' => 'Clube dos Funcionários da CSN · CNPJ 00.000.000/0001-00',
        ]);

        $layout = SignatureLayout::current();

        $documento = $this->congela($this->documento($this->contrato()));

        $this->assertSame($layout->id, $documento->signature_layout_id);

        $html = app(SignatureDocumentRenderer::class)->html($documento);

        // As duas imagens no lugar do cabeçalho de texto, e a linha da empresa.
        $this->assertSame(2, substr_count($html, 'class="letterhead '));
        $this->assertStringNotContainsString('<header>', $html);
        $this->assertStringContainsString('CNPJ 00.000.000/0001-00', $html);
        // A validação continua no rodapé — o papel timbrado não a substitui.
        $this->assertStringContainsString($documento->validation_code, $html);

        // 20 mm de altura: a imagem 6:1 é reduzida para caber, sem deformar.
        $cabecalho = app(SignatureDocumentRenderer::class)->geometry($documento)->headerImage();

        $this->assertSame(76, $cabecalho['height']);
        $this->assertSame(454, $cabecalho['width']);

        $this->assertStringStartsWith('%PDF', Storage::disk(config('signature.disk'))->get($documento->original_path));
    }

    public function test_trocar_o_papel_timbrado_nao_muda_documento_ja_congelado(): void
    {
        $semTimbre = $this->congela($this->documento($this->contrato()));

        $this->salvaPapelTimbrado(['header_image' => $this->imagem('v1.png', 600, 100)]);

        $comV1 = $this->congela($this->documento($this->contrato()));
        $v1 = SignatureLayout::current();

        // Só o texto muda: a imagem de antes é levada para a linha nova.
        $this->salvaPapelTimbrado(['footer_text' => 'Novo endereço']);

        $v2 = SignatureLayout::current();

        $this->assertNotSame($v1->id, $v2->id);
        $this->assertSame($v1->header_path, $v2->header_path);

        $this->salvaPapelTimbrado(['remove_header' => '1']);

        $this->assertNull(SignatureLayout::current()->header_path);
        // Removida do papel timbrado, não do disco: o documento v1 ainda a usa.
        Storage::disk(config('signature.disk'))->assertExists($v1->header_path);

        $renderer = app(SignatureDocumentRenderer::class);

        // Congelado antes de haver papel timbrado: cabeçalho padrão, para sempre.
        $this->assertNull($renderer->geometry($semTimbre->fresh())->layout);
        $this->assertStringContainsString('<header>', $renderer->html($semTimbre->fresh()));

        $this->assertSame($v1->id, $renderer->geometry($comV1->fresh())->layout->id);
        $this->assertNotNull($renderer->geometry($comV1->fresh())->headerImage());
        $this->assertStringNotContainsString('Novo endereço', $renderer->html($comV1->fresh()));
    }

    public function test_faixa_de_borda_a_borda_usa_a_largura_do_papel(): void
    {
        $this->salvaPapelTimbrado([
            'header_image' => $this->imagem('faixa.png', 1588, 200),
            'full_width' => '1',
        ]);

        $geometria = new SignaturePageGeometry(SignatureLayout::current(), false);

        $this->assertSame(SignaturePageGeometry::PAGE_WIDTH, $geometria->headerImage()['width']);
        $this->assertSame(100, $geometria->headerImage()['height']);
        // Encosta na borda de cima: a margem é a imagem mais o respiro abaixo dela.
        $this->assertSame(128, $geometria->marginTop());
        $this->assertSame(-128, $geometria->headerTop());
    }

    public function test_tela_e_exemplo_do_papel_timbrado(): void
    {
        $usuario = $this->usuarioComPermissoes(['assinatura.modelos']);

        $this->actingAs($usuario)->get(route('signature-layout.edit'))->assertOk()->assertSee('Imagem do cabeçalho');

        $this->salvaPapelTimbrado(['header_image' => $this->imagem('cabecalho.png', 600, 100)]);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->get(route('signature-layout.edit'))
            ->assertOk()
            ->assertSee('Remover esta imagem');

        $exemplo = $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->get(route('signature-layout.preview'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $exemplo->getContent());

        // Arquivo que não é imagem é recusado.
        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->post(route('signature-layout.update'), [
                'header_image' => UploadedFile::fake()->create('timbre.pdf', 10, 'application/pdf'),
                'header_height_mm' => 20,
                'footer_height_mm' => 12,
                'align' => 'left',
            ])
            ->assertSessionHasErrors('header_image');

        // Quem só atende no balcão não mexe na aparência do que é assinado.
        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-layout.edit'))
            ->assertForbidden();
    }
}
