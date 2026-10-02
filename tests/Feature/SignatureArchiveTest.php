<?php

namespace Tests\Feature;

use App\Jobs\ArchiveSignatureDocument;
use App\Jobs\FinalizeSignatureDocument;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureArchiver;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignaturePdfSealer;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * A cópia do documento assinado no servidor de arquivos (FTP).
 *
 * O disco do arquivo é falso aqui — o que se testa é ONDE o arquivo vai parar,
 * que o que chega é o PDF do hash gravado, e que uma falha do FTP não perde
 * nem trava nada.
 */
class SignatureArchiveTest extends TestCase
{
    use CreatesSignatureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Storage::fake('signature_archive');
        Queue::fake();

        config(['signature.archive.enabled' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Um documento assinado e finalizado, com o PDF final no disco do módulo. */
    private function finalizado(array $modelo = [], array $outrosSignatarios = []): SignatureDocument
    {
        $rascunho = $this->criaDocumentoDeAssinatura([
            'template' => $this->criaModeloDeAssinatura($modelo),
        ]);

        foreach ($outrosSignatarios as $i => $nome) {
            SignatureSigner::create([
                'signature_document_id' => $rascunho->id,
                'name' => $nome,
                'cpf' => '98765432100',
                'position' => $i + 2,
            ]);
        }

        $documento = app(SignatureDocumentService::class)->freeze($rascunho);
        $estados = app(SignatureStateMachine::class);

        foreach ($documento->signers()->get() as $signatario) {
            $estados->signerTo($signatario, SignatureSigner::STATUS_SIGNED, SignatureAuditEvent::EVENT_SIGNED, [
                'signed_at' => now(),
            ]);
        }

        $estados->documentTo($documento, SignatureDocument::STATUS_SIGNED, SignatureAuditEvent::EVENT_SIGNED);

        (new FinalizeSignatureDocument($documento->id))->handle(
            app(SignatureDocumentRenderer::class),
            $estados,
            app(SignaturePdfSealer::class),
        );

        return $documento->fresh();
    }

    public function test_finalizar_despacha_o_arquivamento(): void
    {
        $documento = $this->finalizado();

        Queue::assertPushed(ArchiveSignatureDocument::class, fn($job) => $job->documentId === $documento->id);
    }

    public function test_com_o_arquivamento_desligado_nada_e_despachado(): void
    {
        config(['signature.archive.enabled' => false]);

        $this->finalizado();

        Queue::assertNotPushed(ArchiveSignatureDocument::class);
    }

    public function test_copia_vai_para_a_pasta_do_modelo_do_ano_e_do_mes(): void
    {
        Carbon::setTestNow('2026-10-03 14:30:00');

        $documento = $this->finalizado(
            ['name' => 'Contrato de Locação de Espaço para Evento'],
            ['João Pereira'],
        );

        (new ArchiveSignatureDocument($documento->id))->handle(app(SignatureArchiver::class));

        $documento->refresh();

        // Sem acento e sem caractere que FTP ou Windows recusem.
        $this->assertSame(
            'Lara/DocumentosAssinados/Contrato de Locacao de Espaco para Evento/2026/10 - Outubro/'
                . '2026-10-03 - Maria de Souza e Joao Pereira - ' . $documento->validation_code . '.pdf',
            $documento->archive_path,
        );

        $this->assertNotNull($documento->archived_at);

        // O que chegou é o arquivo do hash gravado, byte a byte.
        $this->assertSame(
            $documento->final_sha256,
            hash('sha256', Storage::disk('signature_archive')->get($documento->archive_path)),
        );

        $evento = $documento->auditEvents()->where('event', SignatureAuditEvent::EVENT_ARCHIVED)->sole();

        $this->assertSame($documento->archive_path, $evento->payload['caminho']);
    }

    public function test_nome_de_pasta_e_arquivo_nao_leva_caractere_proibido_nem_cpf(): void
    {
        $documento = $this->finalizado(
            ['name' => 'Termo: uso/empréstimo de "quadra" <2026>?'],
            ['Ana', 'Beto', 'Caio'],
        );

        $caminho = app(SignatureArchiver::class)->pathFor($documento);
        $partes = explode('/', $caminho);

        $this->assertSame('Termo uso emprestimo de quadra 2026', $partes[2]);
        // Mais de dois signatários: os dois primeiros e a conta dos demais.
        $this->assertStringContainsString(' - Maria de Souza e Ana e mais 2 - ', $partes[5]);

        foreach ($partes as $parte) {
            $this->assertDoesNotMatchRegularExpression('/[\\\\:*?"<>|]/', $parte);
        }

        $this->assertStringNotContainsString('12345678909', $caminho);

        // O caminho depende só do documento: a mesma chamada, o mesmo lugar.
        $this->assertSame($caminho, app(SignatureArchiver::class)->pathFor($documento->fresh()));
    }

    public function test_job_repetido_nao_envia_duas_vezes(): void
    {
        $documento = $this->finalizado();
        $job = new ArchiveSignatureDocument($documento->id);

        $job->handle(app(SignatureArchiver::class));
        $job->handle(app(SignatureArchiver::class));

        $this->assertSame(
            1,
            $documento->auditEvents()->where('event', SignatureAuditEvent::EVENT_ARCHIVED)->count(),
        );
    }

    public function test_pdf_que_nao_confere_com_o_hash_nao_e_arquivado(): void
    {
        $documento = $this->finalizado();

        Storage::disk(config('signature.disk'))->put($documento->final_path, '%PDF adulterado');

        try {
            app(SignatureArchiver::class)->archive($documento);

            $this->fail('PDF adulterado foi arquivado.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('não confere com o hash gravado', $e->getMessage());
        }

        $this->assertNull($documento->fresh()->archived_at);
        $this->assertSame([], Storage::disk('signature_archive')->allFiles());
    }

    public function test_documento_ainda_nao_finalizado_nao_e_arquivado(): void
    {
        $congelado = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());

        $this->expectException(RuntimeException::class);

        app(SignatureArchiver::class)->archive($congelado);
    }

    public function test_falha_do_ftp_vira_evento_e_o_documento_fica_pendente(): void
    {
        $documento = $this->finalizado();

        (new ArchiveSignatureDocument($documento->id))->failed(new RuntimeException('Connection refused'));

        $evento = $documento->auditEvents()->where('event', SignatureAuditEvent::EVENT_ARCHIVE_FAILED)->sole();

        $this->assertSame('Connection refused', $evento->payload['erro']);
        // Nada se perdeu: o PDF continua onde sempre esteve, e a cópia segue pendente.
        $this->assertNull($documento->fresh()->archived_at);
        Storage::disk(config('signature.disk'))->assertExists($documento->final_path);
    }

    public function test_comando_envia_os_pendentes_e_so_eles(): void
    {
        $pendente = $this->finalizado(['name' => 'Termo A']);
        $jaArquivado = $this->finalizado(['name' => 'Termo B']);

        app(SignatureArchiver::class)->archive($jaArquivado);

        $this->artisan('signature:archive')
            ->expectsOutputToContain('1 documento(s) arquivado(s), 0 falha(s)')
            ->assertSuccessful();

        $this->assertNotNull($pendente->fresh()->archived_at);
        $this->assertCount(2, Storage::disk('signature_archive')->allFiles());

        // Desligado, o comando agendado não faz nada.
        config(['signature.archive.enabled' => false]);

        $outro = $this->finalizado(['name' => 'Termo C']);

        $this->artisan('signature:archive')->assertSuccessful();

        $this->assertNull($outro->fresh()->archived_at);
    }

    public function test_teste_de_conexao_cria_a_pasta_raiz(): void
    {
        $this->artisan('signature:archive --testar')
            ->expectsOutputToContain('Pasta do arquivo: Lara/DocumentosAssinados')
            ->assertSuccessful();

        Storage::disk('signature_archive')->assertExists('Lara/DocumentosAssinados');
    }
}
