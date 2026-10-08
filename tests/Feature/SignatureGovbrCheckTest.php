<?php

namespace Tests\Feature;

use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureGovbrCheck;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsGovbrSignedPdf;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Aba "Assinatura gov.br": o atendente envia o PDF que voltou assinado pelo
 * gov.br e o Lara confere. Aqui, a CONFERÊNCIA; a conclusão da assinatura
 * (documento preparado para o gov.br) está em SignatureGovbrSigningTest.
 *
 * O que está em jogo são as duas perguntas da validação — é este documento?
 * foi a pessoa certa? — e o que cada recusa diz ao atendente. As assinaturas
 * são feitas com uma AC de teste (BuildsGovbrSignedPdf), no mesmo formato da
 * amostra real da Fase 0.
 *
 * Sem `RefreshDatabase` e com User mockado — ver CreatesSignatureSchema.
 */
class SignatureGovbrCheckTest extends TestCase
{
    use BuildsGovbrSignedPdf;
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    /** @var array{cert: string, key: string} */
    private array $ac;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));

        $this->ac = $this->govbrAc();
        $this->govbrConfiaEm($this->ac);
    }

    private function documentoCongelado(array $signerAttributes = []): SignatureDocument
    {
        return app(SignatureDocumentService::class)
            ->freeze($this->criaDocumentoDeAssinatura([], $signerAttributes));
    }

    private function original(SignatureDocument $documento): string
    {
        return Storage::disk(config('signature.disk'))->get($documento->original_path);
    }

    private function envia(SignatureDocument $documento, string $bytes, array $permissoes = ['assinatura.documentos'])
    {
        return $this->actingAs($this->usuarioComPermissoes($permissoes))
            ->post(route('signature-documents.govbr.store', $documento), [
                'documento' => UploadedFile::fake()->createWithContent('assinado.pdf', $bytes),
            ]);
    }

    /** As chaves do que reprovou, no arquivo e em cada assinatura. */
    private function reprovados(SignatureGovbrCheck $conferencia): array
    {
        $checks = $conferencia->checks();

        foreach ($conferencia->signatures() as $assinatura) {
            array_push($checks, ...$assinatura['checks']);
        }

        // Estrito: `null` é "não conferido", e não reprovado — o where() do
        // Laravel compara frouxo e misturaria os dois.
        return collect($checks)->filter(fn(array $c) => $c['ok'] === false)->pluck('key')->unique()->values()->all();
    }

    public function test_pdf_assinado_pelo_signatario_e_valido(): void
    {
        $documento = $this->documentoCongelado();
        $assinado = $this->govbrAssina($this->original($documento), $this->govbrCertificado($this->ac, '12345678909'));

        // Válido, mas o documento não foi preparado para o gov.br: confere e
        // registra, sem concluir — por isso "Atenção", e não sucesso. A
        // conclusão está em SignatureGovbrSigningTest.
        $this->envia($documento, $assinado)
            ->assertRedirect(route('signature-documents.show', [$documento, 'aba' => 'govbr']))
            ->assertSessionHas('warning');

        $conferencia = SignatureGovbrCheck::sole();

        $this->assertTrue($conferencia->valid);
        $this->assertSame('original', $conferencia->base);
        $this->assertSame([], $this->reprovados($conferencia));
        $this->assertSame('Maria de Souza', $conferencia->signatures()[0]['signer_name']);
        $this->assertSame('123.***.**9-09', $conferencia->signatures()[0]['cpf']);
        $this->assertSame(hash('sha256', $assinado), $conferencia->file_sha256);

        // O arquivo fica guardado como chegou, no disco privado.
        Storage::disk(config('signature.disk'))->assertExists($conferencia->file_path);
        $this->assertSame($assinado, Storage::disk(config('signature.disk'))->get($conferencia->file_path));

        // Na trilha, o veredito — sem nome nem CPF.
        $evento = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_GOVBR_CHECKED)->sole();
        $this->assertTrue($evento->payload['valido']);
        $this->assertSame([$documento->signers->first()->id], $evento->payload['signatarios']);
        $this->assertStringNotContainsString('12345678909', json_encode($evento->payload));

        // Sem preparar, é só conferência: nada muda de status.
        $this->assertStringContainsString('preparado', (string) $conferencia->conclusionReason());
        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $documento->fresh()->status);
        $this->assertSame(SignatureSigner::STATUS_PENDING, $documento->signers()->first()->status);
    }

    public function test_o_documento_sem_assinatura_e_recusado(): void
    {
        $documento = $this->documentoCongelado();

        $this->envia($documento, $this->original($documento))->assertSessionHas('warning');

        $conferencia = SignatureGovbrCheck::sole();

        $this->assertFalse($conferencia->valid);
        $this->assertSame(['assinatura'], $this->reprovados($conferencia));
    }

    public function test_assinatura_de_quem_nao_e_signatario_e_recusada(): void
    {
        $documento = $this->documentoCongelado();
        $assinado = $this->govbrAssina($this->original($documento), $this->govbrCertificado($this->ac, '52998224725', 'JOAO DA SILVA'));

        $this->envia($documento, $assinado);

        $conferencia = SignatureGovbrCheck::sole();

        $this->assertFalse($conferencia->valid);
        $this->assertSame(['cpf'], $this->reprovados($conferencia));
        $this->assertNull($conferencia->signatures()[0]['signer_id']);
    }

    public function test_assinatura_sobre_outro_documento_e_recusada(): void
    {
        $documento = $this->documentoCongelado();
        $outro = $this->documentoCongelado();

        $assinado = $this->govbrAssina($this->original($outro), $this->govbrCertificado($this->ac, '12345678909'));

        $this->envia($documento, $assinado);

        $conferencia = SignatureGovbrCheck::sole();

        $this->assertFalse($conferencia->valid);
        $this->assertNull($conferencia->base);
        $this->assertSame(['documento'], $this->reprovados($conferencia));
    }

    public function test_conteudo_alterado_depois_de_assinar_e_recusado(): void
    {
        $documento = $this->documentoCongelado();
        $assinado = $this->govbrAssina($this->original($documento), $this->govbrCertificado($this->ac, '12345678909'));

        // Um byte trocado dentro do trecho assinado — na data do dicionário da assinatura.
        $posicao = strpos($assinado, '/M (D:') + 8;
        $assinado[$posicao] = $assinado[$posicao] === '1' ? '2' : '1';

        $this->envia($documento, $assinado);

        $this->assertContains('integridade', $this->reprovados(SignatureGovbrCheck::sole()));
    }

    public function test_bytes_acrescentados_depois_da_assinatura_sao_recusados(): void
    {
        $documento = $this->documentoCongelado();
        $assinado = $this->govbrAssina($this->original($documento), $this->govbrCertificado($this->ac, '12345678909'));

        $this->envia($documento, $assinado . "\n% acrescentado depois\n");

        $this->assertSame(['alteracao'], $this->reprovados(SignatureGovbrCheck::sole()));
    }

    public function test_certificado_de_outra_ac_e_recusado(): void
    {
        $documento = $this->documentoCongelado();
        $outraAc = $this->govbrAc('AC Que Ninguem Conhece');

        $assinado = $this->govbrAssina($this->original($documento), $this->govbrCertificado($outraAc, '12345678909'));

        $this->envia($documento, $assinado);

        $this->assertSame(['cadeia'], $this->reprovados(SignatureGovbrCheck::sole()));
    }

    public function test_dois_signatarios_assinando_em_sequencia(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();
        SignatureSigner::create([
            'signature_document_id' => $documento->id,
            'name' => 'Joao da Silva',
            'cpf' => '52998224725',
            'position' => 2,
        ]);
        $documento = app(SignatureDocumentService::class)->freeze($documento->fresh());

        // O segundo assina o arquivo que o primeiro devolveu.
        $primeira = $this->govbrAssina($this->original($documento), $this->govbrCertificado($this->ac, '12345678909'));
        $segunda = $this->govbrAssina($primeira, $this->govbrCertificado($this->ac, '52998224725', 'JOAO DA SILVA'));

        $this->envia($documento, $segunda);

        $conferencia = SignatureGovbrCheck::sole();

        $this->assertTrue($conferencia->valid, json_encode($this->reprovados($conferencia)));
        $this->assertSame(['Maria de Souza', 'Joao da Silva'], array_column($conferencia->signatures(), 'signer_name'));
    }

    public function test_assinatura_sobre_o_pdf_ja_assinado_no_tablet(): void
    {
        $documento = $this->documentoCongelado();

        // O final do tablet, simulado: um PDF qualquer com o hash gravado.
        $final = "%PDF-1.7\n% final assinado no tablet\n%%EOF\n";
        $caminho = config('signature.paths.documents') . '/' . $documento->id . '/final.pdf';
        Storage::disk(config('signature.disk'))->put($caminho, $final);
        $documento->forceFill(['final_path' => $caminho, 'final_sha256' => hash('sha256', $final)])->save();

        $this->envia($documento, $this->govbrAssina($final, $this->govbrCertificado($this->ac, '12345678909')));

        $conferencia = SignatureGovbrCheck::sole();

        $this->assertTrue($conferencia->valid);
        $this->assertSame('final', $conferencia->base);
    }

    public function test_rascunho_nao_aceita_conferencia(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->envia($documento, "%PDF-1.7\n%%EOF\n")->assertSessionHas('warning');

        $this->assertSame(0, SignatureGovbrCheck::count());
    }

    public function test_arquivo_que_nao_e_pdf_e_barrado_na_entrada(): void
    {
        $documento = $this->documentoCongelado();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.govbr.store', $documento), [
                'documento' => UploadedFile::fake()->createWithContent('assinado.txt', 'não sou um PDF'),
            ])
            ->assertSessionHasErrors('documento');

        $this->assertSame(0, SignatureGovbrCheck::count());
    }

    public function test_quem_so_consulta_ve_a_aba_mas_nao_envia(): void
    {
        $documento = $this->documentoCongelado();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.consultar']))
            ->get(route('signature-documents.show', [$documento, 'aba' => 'govbr']))
            ->assertOk()
            ->assertSee('Assinatura pelo gov.br')
            ->assertDontSee('Conferir assinatura');

        $this->envia($documento, $this->original($documento), ['assinatura.consultar'])->assertForbidden();
    }

    public function test_aba_mostra_o_resultado_sem_o_cpf_inteiro(): void
    {
        $documento = $this->documentoCongelado();
        $this->envia($documento, $this->govbrAssina($this->original($documento), $this->govbrCertificado($this->ac, '12345678909')));

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.show', [$documento, 'aba' => 'govbr']))
            ->assertOk()
            ->assertSee('Válido')
            ->assertSee('Certificado emitido pelo gov.br')
            ->assertSee('123.***.**9-09')
            ->assertDontSee('12345678909');
    }

    public function test_arquivo_enviado_so_sai_pelo_proprio_documento(): void
    {
        $documento = $this->documentoCongelado();
        $outro = $this->documentoCongelado();
        $this->envia($documento, $this->original($documento));
        $conferencia = SignatureGovbrCheck::sole();

        $usuario = $this->usuarioComPermissoes(['assinatura.consultar']);

        $this->actingAs($usuario)
            ->get(route('signature-documents.govbr.pdf', [$documento, $conferencia]))
            ->assertOk();

        $this->actingAs($usuario)
            ->get(route('signature-documents.govbr.pdf', [$outro, $conferencia]))
            ->assertNotFound();
    }

    public function test_assinatura_anterior_ao_congelamento_e_recusada(): void
    {
        $documento = $this->documentoCongelado();
        $assinado = $this->govbrAssina($this->original($documento), $this->govbrCertificado($this->ac, '12345678909'));

        // A hora da assinatura vem do CMS (relógio de quem assinou). Para ela
        // ficar ANTES do congelamento, o congelamento vai para depois — além
        // dos 5 minutos de folga de relógio.
        $documento->forceFill(['frozen_at' => now()->addHour()])->save();

        $this->envia($documento, $assinado)->assertSessionHas('warning');

        $conferencia = SignatureGovbrCheck::sole();
        $this->assertFalse($conferencia->valid);
        $this->assertSame(['data'], $this->reprovados($conferencia));
    }

    public function test_revogacao_aparece_como_nao_conferida_sem_reprovar(): void
    {
        $documento = $this->documentoCongelado();

        $this->envia($documento, $this->govbrAssina($this->original($documento), $this->govbrCertificado($this->ac, '12345678909')));

        $conferencia = SignatureGovbrCheck::sole();
        $revogacao = collect($conferencia->checks())->firstWhere('key', 'revogacao');

        $this->assertNotNull($revogacao);
        $this->assertNull($revogacao['ok']);
        $this->assertTrue($conferencia->valid);
    }
}
