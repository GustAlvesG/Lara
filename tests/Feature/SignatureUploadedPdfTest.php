<?php

namespace Tests\Feature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Exceptions\UnreadablePdfException;
use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Jobs\FinalizeSignatureDocument;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureArchiver;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignaturePdfSealer;
use App\Services\Signature\SignaturePdfStamper;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Documento PRONTO, enviado em PDF: entra na íntegra, e o sistema só carimba
 * por cima.
 *
 * O que se protege aqui: o arquivo enviado não é alterado nem perdido; um PDF
 * que o sistema não consegue aproveitar é recusado no envio, e não no balcão;
 * e ninguém fica sem lugar para assinar.
 */
class SignatureUploadedPdfTest extends TestCase
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

    /** Um PDF de duas páginas, com imagem — o tipo de documento que um modelo não reproduz. */
    private function pdf(): string
    {
        $imagem = imagecreatetruecolor(240, 80);
        imagefill($imagem, 0, 0, imagecolorallocate($imagem, 138, 21, 56));
        ob_start();
        imagepng($imagem);
        $logo = base64_encode((string) ob_get_clean());

        return app('dompdf.wrapper')->loadHTML(
            '<img src="data:image/png;base64,' . $logo . '"><h1>Contrato pronto</h1><p>Primeira página.</p>'
            . '<div style="page-break-before:always"></div><p>Segunda página.</p>'
            . '<p>______________________________<br>Contratante</p>',
        )->setPaper('a4')->output();
    }

    /** @return array<int, array<string, mixed>> */
    private function signers(): array
    {
        return [
            ['name' => 'Maria de Souza', 'cpf' => '12345678909'],
            ['name' => 'João Pereira', 'cpf' => '98765432100'],
        ];
    }

    private function enviado(array $regras = []): SignatureDocument
    {
        return app(SignatureDocumentService::class)->createFromUpload(
            $this->pdf(),
            ['title' => 'Contrato de patrocínio'],
            array_merge(['identity_check' => SignatureTemplate::IDENTITY_NONE], $regras),
            $this->signers(),
        );
    }

    private function paginas(string $pdf): int
    {
        return app(SignaturePdfStamper::class)->inspect($pdf);
    }

    private function png(): string
    {
        $imagem = imagecreatetruecolor(400, 240);
        imagealphablending($imagem, false);
        imagesavealpha($imagem, true);
        imagefill($imagem, 0, 0, imagecolorallocatealpha($imagem, 0, 0, 0, 127));
        imagesetthickness($imagem, 3);
        imageline($imagem, 150, 100, 230, 140, imagecolorallocatealpha($imagem, 20, 20, 20, 0));

        ob_start();
        imagepng($imagem);

        return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
    }

    /** O próximo signatário abre o documento no tablet, confirma e assina. */
    private function assina(SignatureDocument $documento): void
    {
        $liberacao = app(SignatureRequestService::class)->issue($documento->fresh()->nextSigner());

        $cookie = $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload($liberacao['token']),
        ])->assertOk()->getCookie(EnsureSignatureKioskSession::COOKIE)->getValue();

        $sessao = fn() => $this->withCredentials()->withCookie(EnsureSignatureKioskSession::COOKIE, $cookie);

        $sessao()->postJson(route('quiosque.identity', $documento), ['cpf' => '0'])->assertOk();

        $pontos = [];

        for ($i = 0; $i < 60; $i++) {
            $pontos[] = ['x' => $i, 'y' => $i % 20, 't' => $i * 12];
        }

        $sessao()->postJson(route('quiosque.sign', $documento), [
            'signature' => $this->png(),
            'strokes' => [['points' => $pontos]],
            'initials' => $this->png(),
            'initials_strokes' => [['points' => $pontos]],
            'accepted' => true,
        ])->assertOk();
    }

    public function test_envio_cria_o_rascunho_e_guarda_o_arquivo_como_veio(): void
    {
        $pdf = $this->pdf();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.upload.store'), [
                'title' => 'Contrato de patrocínio',
                'file' => UploadedFile::fake()->createWithContent('contrato.pdf', $pdf),
                'identity_check' => SignatureTemplate::IDENTITY_FULL,
                'requires_photo' => '0',
                'requires_initials' => '1',
                'signers' => [['name' => 'Maria de Souza', 'cpf' => '123.456.789-09']],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $documento = SignatureDocument::firstOrFail();

        $this->assertTrue($documento->isUploaded());
        $this->assertSame(SignatureDocument::STATUS_DRAFT, $documento->status);

        // Byte a byte o que foi enviado — e o hash dele fica gravado e na trilha.
        $this->assertSame($pdf, Storage::disk(config('signature.disk'))->get($documento->source_path));
        $this->assertSame(hash('sha256', $pdf), $documento->source_sha256);

        $evento = $documento->auditEvents()->where('event', SignatureAuditEvent::EVENT_SOURCE_UPLOADED)->sole();

        $this->assertSame(2, $evento->payload['paginas']);

        // As regras da assinatura moram num modelo de uso único, que não é modelo de ninguém.
        $this->assertTrue($documento->template->single_use);
        $this->assertTrue($documento->template->requires_initials);
        $this->assertSame(SignatureTemplate::IDENTITY_FULL, $documento->template->identity_check);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->get(route('signature-templates.index'))
            ->assertOk()
            ->assertDontSee('Contrato de patrocínio');

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.upload'))
            ->assertOk()
            // Os passos do envio: o arquivo e as regras vêm antes do resto.
            ->assertSeeInOrder(['data-step="Arquivo e regras"', 'data-step="Documento"', 'data-step="Signatários"'], false);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.create'))
            ->assertOk()
            ->assertSee('Enviar documento pronto (PDF)')
            ->assertDontSee('Contrato de patrocínio');
    }

    public function test_arquivo_que_o_sistema_nao_aproveita_e_recusado_no_envio(): void
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.upload.store'), [
                'title' => 'Contrato',
                // Passa por PDF pelo cabeçalho, mas não tem estrutura de PDF.
                'file' => UploadedFile::fake()->createWithContent('contrato.pdf', "%PDF-1.4\nisto não é um documento"),
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'signers' => [['name' => 'Maria de Souza', 'cpf' => '123.456.789-09']],
            ])
            ->assertSessionHasErrors('file');

        // Recusado ANTES de gravar: nem documento, nem modelo de uso único órfão.
        $this->assertSame(0, SignatureDocument::count());
        $this->assertSame(0, SignatureTemplate::count());

        $this->expectException(UnreadablePdfException::class);

        app(SignaturePdfStamper::class)->inspect('nem de longe um PDF');
    }

    public function test_telas_do_envio_e_do_documento_enviado(): void
    {
        $usuario = $this->usuarioComPermissoes(['assinatura.documentos']);

        $this->actingAs($usuario)->get(route('signature-documents.upload'))
            ->assertOk()
            ->assertSee('Documento em PDF')
            ->assertSee('Visto em todas as páginas');

        $documento = $this->enviado();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertSee('Lugar das assinaturas')
            ->assertSee('PDF enviado');

        // A tela de marcar os lugares busca o PDF como foi enviado.
        $resposta = $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.pdf', [$documento, 'versao' => 'enviado']))
            ->assertOk();

        $this->assertSame($documento->source_sha256, hash('sha256', $resposta->streamedContent()));
    }

    public function test_lugar_da_assinatura_e_gravado_como_fracao_da_pagina(): void
    {
        $documento = $this->enviado();
        [$maria, $joao] = $documento->signers->all();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->postJson(route('signature-documents.positions', $documento), [
                'positions' => [
                    $maria->id => ['page' => 2, 'x' => 0.31, 'y' => 0.2],
                    $joao->id => null,
                ],
            ])
            ->assertOk();

        $this->assertSame(['page' => 2, 'x' => 0.31, 'y' => 0.2], $maria->fresh()->signature_position);
        $this->assertNull($joao->fresh()->signature_position);

        // Fora da página não é posição.
        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->postJson(route('signature-documents.positions', $documento), [
                'positions' => [$maria->id => ['page' => 1, 'x' => 1.4, 'y' => 0.2]],
            ])
            ->assertStatus(422);

        // Depois de congelado, o lugar não muda mais.
        app(SignatureDocumentService::class)->freeze($documento);

        $this->expectException(SignatureDocumentLockedException::class);

        app(SignatureDocumentService::class)->setSignaturePositions($documento->fresh(), []);
    }

    public function test_documento_e_assinado_por_cima_do_pdf_enviado(): void
    {
        $documento = $this->enviado(['requires_initials' => true]);
        [$maria, $joao] = $documento->signers->all();

        // Maria assina na linha da página 2; João, sem lugar, na folha de assinaturas.
        app(SignatureDocumentService::class)->setSignaturePositions($documento, [
            $maria->id => ['page' => 2, 'x' => 0.3, 'y' => 0.2],
        ]);

        $documento = app(SignatureDocumentService::class)->freeze($documento->fresh());
        $disk = Storage::disk(config('signature.disk'));

        $original = $disk->get($documento->original_path);

        // As duas páginas enviadas e a folha de assinaturas do João.
        $this->assertSame(3, $this->paginas($original));
        $this->assertSame($documento->original_sha256, hash('sha256', $original));
        // Documento pronto não leva papel timbrado: ele já é a arte de quem enviou.
        $this->assertNull($documento->signature_layout_id);

        $this->assina($documento);
        $this->assina($documento);

        (new FinalizeSignatureDocument($documento->id))->handle(
            app(SignatureDocumentRenderer::class),
            app(SignatureStateMachine::class),
            app(SignaturePdfSealer::class),
        );

        $documento->refresh();

        $this->assertSame(SignatureDocument::STATUS_FINALIZED, $documento->status);

        $final = $disk->get($documento->final_path);

        // As mesmas três páginas, mais o manifesto ao fim.
        $this->assertGreaterThan(3, $this->paginas($final));
        // A imagem do documento enviado, mais duas assinaturas e dois vistos.
        $this->assertGreaterThan(substr_count($original, '/Subtype /Image'), substr_count($final, '/Subtype /Image'));

        // O arquivo enviado continua lá, intocado.
        $this->assertSame($documento->source_sha256, hash('sha256', $disk->get($documento->source_path)));

        // O manifesto diz que o documento veio pronto e cita o hash do que foi enviado.
        $manifesto = app(SignatureDocumentRenderer::class)->html($documento, SignatureDocumentRenderer::MODE_FINAL);

        $this->assertStringContainsString('Documento enviado pronto, em PDF', $manifesto);
        $this->assertStringContainsString($documento->source_sha256, $manifesto);

        // No arquivo de rede, vai para a pasta dos avulsos — e não para uma pasta só dele.
        $this->assertStringContainsString(
            '/Documentos avulsos/',
            app(SignatureArchiver::class)->pathFor($documento),
        );
    }

    public function test_sem_lugar_marcado_todos_assinam_na_folha_de_assinaturas(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->enviado());

        $original = Storage::disk(config('signature.disk'))->get($documento->original_path);

        $this->assertSame(3, $this->paginas($original));
    }

    public function test_lugar_numa_pagina_que_o_pdf_nao_tem_cai_na_folha_de_assinaturas(): void
    {
        $documento = $this->enviado();

        app(SignatureDocumentService::class)->setSignaturePositions($documento, [
            $documento->signers[0]->id => ['page' => 9, 'x' => 0.5, 'y' => 0.5],
            $documento->signers[1]->id => ['page' => 1, 'x' => 0.5, 'y' => 0.5],
        ]);

        $documento = app(SignatureDocumentService::class)->freeze($documento->fresh());

        // Ninguém fica sem lugar: a folha de assinaturas existe para a página 9.
        $this->assertSame(
            3,
            $this->paginas(Storage::disk(config('signature.disk'))->get($documento->original_path)),
        );
    }
}
