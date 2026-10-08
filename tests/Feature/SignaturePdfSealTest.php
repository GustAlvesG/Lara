<?php

namespace Tests\Feature;

use App\Jobs\FinalizeSignatureDocument;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureEvidence;
use App\Models\SignatureSigner;
use App\Services\Signature\Govbr\Asn1;
use App\Services\Signature\Pki\Der;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignaturePdfSealer;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\BuildsGovbrSignedPdf;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * O lacre do PDF finalizado com o e-CNPJ do clube (PAdES) e o carimbo de tempo.
 *
 * O que está em jogo:
 *
 *  - o lacre entra por atualização incremental: o PDF de antes é prefixo
 *    exato do lacrado, e a assinatura do lacre cobre o arquivo até o fim;
 *  - no PDF do gov.br, as assinaturas das pessoas continuam conferindo;
 *  - o carimbo vai como atributo não assinado, sem mexer na assinatura, e só
 *    é aceito se for do resumo pedido;
 *  - ligado e mal configurado, falha alto.
 *
 * O certificado do clube, a AC e a ACT são de teste (BuildsGovbrSignedPdf).
 */
class SignaturePdfSealTest extends TestCase
{
    use BuildsGovbrSignedPdf;
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    private const ACT = 'http://act.teste/carimbo';

    /** @var array{cert: string, key: string} */
    private array $ac;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Mail::fake();
        Http::preventStrayRequests();

        $this->ac = $this->govbrAc();
        $this->govbrConfiaEm($this->ac);

        // O "e-CNPJ" do clube, num .pfx com a cadeia, como o servidor recebe.
        $clube = $this->govbrCertificado($this->ac, '00000000000', 'CLUBE TESTE:00000000000191');
        $pfx = tempnam(sys_get_temp_dir(), 'lacre');
        openssl_pkcs12_export_to_file($clube['cert'], $pfx, $clube['key'], 'segredo', ['extracerts' => [$this->ac['cert']]]);

        config([
            'signature.pades.enabled' => true,
            'signature.pades.certificate_path' => $pfx,
            'signature.pades.certificate_password' => 'segredo',
            'signature.pades.tsa_url' => null,
        ]);
    }

    // ---------------------------------------------------------------- apoio

    /**
     * Confere todas as assinaturas do PDF: cada CMS contra o trecho do
     * ByteRange (sem a cadeia — só a integridade).
     *
     * @return array<int, array{ok: bool, end: int, cms: string}>
     */
    private function assinaturas(string $pdf): array
    {
        preg_match_all('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $achados, PREG_SET_ORDER);
        $resultado = [];

        foreach ($achados as $m) {
            [, $a, $b, $c, $d] = array_map('intval', $m);
            $hex = (string) preg_replace('/[^0-9A-Fa-f]/', '', substr($pdf, $b + 1, $c - $b - 2));
            $bytes = (string) hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex);
            $cms = Asn1::raw($bytes, Asn1::node($bytes));

            $conteudo = tempnam(sys_get_temp_dir(), 't');
            $assinatura = tempnam(sys_get_temp_dir(), 't');
            file_put_contents($conteudo, substr($pdf, $a, $b) . substr($pdf, $c, $d));
            file_put_contents($assinatura, $cms);

            $ok = openssl_cms_verify($conteudo, OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY | OPENSSL_CMS_NOVERIFY,
                null, [], null, null, null, $assinatura, OPENSSL_ENCODING_DER) === true;

            @unlink($conteudo);
            @unlink($assinatura);

            $resultado[] = ['ok' => $ok, 'end' => $c + $d, 'cms' => $cms];
        }

        return $resultado;
    }

    /**
     * Uma ACT de teste: lê o resumo e o nonce do pedido e devolve um carimbo
     * assinado (CMS com o TSTInfo dentro). `$outroResumo` simula uma ACT que
     * carimba outra coisa.
     */
    private function act(bool $outroResumo = false): \Closure
    {
        $act = $this->govbrCertificado($this->ac, '00000000000', 'ACT DE TESTE');

        return function (Request $request) use ($act, $outroResumo) {
            $corpo = $request->body();
            $campos = Asn1::children($corpo, Asn1::node($corpo));
            $resumo = Asn1::content($corpo, Asn1::children($corpo, $campos[1])[1]);

            $nonce = '';
            foreach (array_slice($campos, 2) as $campo) {
                if ($campo['tag'] === Asn1::INTEGER) {
                    $nonce = Asn1::raw($corpo, $campo);
                }
            }

            $tst = Der::sequence(
                Der::integer(1),
                Der::oid('2.16.76.1.6.6'),
                Der::sequence(
                    Der::sequence(Der::oid('2.16.840.1.101.3.4.2.1'), Der::null()),
                    Der::octetString($outroResumo ? str_repeat("\0", 32) : $resumo),
                ),
                Der::integer(random_int(1, 999999)),
                Der::tlv(0x18, gmdate('YmdHis') . 'Z'),
                $nonce,
            );

            $entrada = tempnam(sys_get_temp_dir(), 'tst');
            $saida = tempnam(sys_get_temp_dir(), 'tst');
            file_put_contents($entrada, $tst);
            openssl_cms_sign($entrada, $saida, $act['cert'], $act['key'], [], OPENSSL_CMS_BINARY, OPENSSL_ENCODING_DER);
            $token = (string) file_get_contents($saida);
            @unlink($entrada);
            @unlink($saida);

            return Http::response(Der::sequence(Der::sequence(Der::integer(0)), $token), 200, [
                'Content-Type' => 'application/timestamp-reply',
            ]);
        };
    }

    /** Documento assinado no tablet, pronto para o job (como em SignatureFinalizationTest). */
    private function documentoAssinadoNoTablet(): SignatureDocument
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());
        $signatario = $documento->signers()->first();
        $caminho = 'signature/signatures/' . $documento->id . '/signer_' . $signatario->id . '.png';

        Storage::disk(config('signature.disk'))->put($caminho, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        SignatureEvidence::create([
            'signature_signer_id' => $signatario->id,
            'signature_path' => $caminho,
            'strokes' => [['points' => array_fill(0, 40, ['x' => 1, 'y' => 2, 't' => 3])]],
            'ip' => '10.0.0.15',
            'user_agent' => 'Tablet do balcão',
            'read_seconds' => 64,
            'scrolled_to_end' => true,
            'accepted' => true,
            'server_signed_at' => now(),
        ]);

        $states = app(SignatureStateMachine::class);
        $states->signerTo($signatario, SignatureSigner::STATUS_SIGNED, SignatureAuditEvent::EVENT_SIGNED, ['signed_at' => now()]);
        $states->documentTo($documento, SignatureDocument::STATUS_SIGNED, SignatureAuditEvent::EVENT_SIGNED);

        return $documento->fresh();
    }

    private function finaliza(SignatureDocument $documento): SignatureDocument
    {
        (new FinalizeSignatureDocument($documento->id))->handle(
            app(SignatureDocumentRenderer::class),
            app(SignatureStateMachine::class),
            app(SignaturePdfSealer::class),
        );

        return $documento->fresh();
    }

    // ---------------------------------------------------------------- lacre

    public function test_pdf_do_tablet_sai_lacrado(): void
    {
        $documento = $this->finaliza($this->documentoAssinadoNoTablet());
        $final = Storage::disk(config('signature.disk'))->get($documento->final_path);

        $assinaturas = $this->assinaturas($final);

        $this->assertCount(1, $assinaturas);
        $this->assertTrue($assinaturas[0]['ok']);
        $this->assertSame(strlen($final), $assinaturas[0]['end'], 'O lacre cobre o arquivo até o fim.');
        $this->assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $final);
        $this->assertSame(hash('sha256', $final), $documento->final_sha256);

        $evento = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_FINALIZED)->sole();
        $this->assertTrue($evento->payload['lacrado']);
        $this->assertFalse($evento->payload['carimbo_de_tempo']);
    }

    public function test_lacre_desligado_devolve_os_mesmos_bytes(): void
    {
        config(['signature.pades.enabled' => false]);

        $pdf = "%PDF-1.4\n% qualquer coisa\n%%EOF\n";

        $this->assertSame($pdf, app(SignaturePdfSealer::class)->seal($pdf));
    }

    public function test_lacre_sobre_pdf_ja_lacrado_mantem_o_primeiro(): void
    {
        $documento = $this->finaliza($this->documentoAssinadoNoTablet());
        $final = Storage::disk(config('signature.disk'))->get($documento->final_path);

        $duplo = app(SignaturePdfSealer::class)->seal($final, $documento);

        $this->assertStringStartsWith($final, $duplo);
        $this->assertSame([true, true], array_column($this->assinaturas($duplo), 'ok'));
    }

    public function test_pdf_do_gov_br_lacrado_mantem_a_assinatura_da_pessoa(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());
        $atendente = $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']));
        $atendente->post(route('signature-documents.govbr.prepare', $documento));

        $disco = Storage::disk(config('signature.disk'));
        $assinado = $this->govbrAssina($disco->get($documento->fresh()->original_path), $this->govbrCertificado($this->ac, '12345678909'));

        // A fila é `sync`: o envio aprovado finaliza na hora — já com o lacre.
        $atendente->post(route('signature-documents.govbr.store', $documento), [
            'documento' => UploadedFile::fake()->createWithContent('assinado.pdf', $assinado),
        ])->assertSessionHas('success');

        $documento->refresh();
        $final = $disco->get($documento->final_path);

        $this->assertSame(SignatureDocument::STATUS_FINALIZED, $documento->status);
        $this->assertStringStartsWith($assinado, $final, 'O lacre só acrescenta: o arquivo do gov.br é prefixo do final.');
        $this->assertSame([true, true], array_column($this->assinaturas($final), 'ok'));

        // O relatório também sai lacrado.
        $relatorio = $disco->get($documento->report_path);
        $this->assertSame([true], array_column($this->assinaturas($relatorio), 'ok'));

        // Quem guardou o arquivo baixado do gov.br (sem o lacre) continua com uma via válida.
        $this->post(route('signature.validate.verify', $documento->validation_code), [
            'documento' => UploadedFile::fake()->createWithContent('meu.pdf', $assinado),
        ])->assertOk()->assertViewHas('conferencia', fn(array $c) => $c['resultado'] === 'final');
    }

    // ------------------------------------------------------- carimbo de tempo

    public function test_carimbo_de_tempo_entra_como_atributo_nao_assinado(): void
    {
        config(['signature.pades.tsa_url' => self::ACT]);
        Http::fake([self::ACT => $this->act()]);

        $documento = $this->finaliza($this->documentoAssinadoNoTablet());
        $final = Storage::disk(config('signature.disk'))->get($documento->final_path);
        $assinaturas = $this->assinaturas($final);

        $this->assertTrue($assinaturas[0]['ok'], 'O carimbo não pode alterar o que a assinatura cobre.');
        $this->assertStringContainsString(Der::oid('1.2.840.113549.1.9.16.2.14'), $assinaturas[0]['cms']);

        Http::assertSent(fn(Request $r) => $r->url() === self::ACT
            && $r->hasHeader('Content-Type', 'application/timestamp-query'));

        $evento = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_FINALIZED)->sole();
        $this->assertTrue($evento->payload['carimbo_de_tempo']);
    }

    public function test_carimbo_de_outro_resumo_e_recusado(): void
    {
        config(['signature.pades.tsa_url' => self::ACT]);
        Http::fake([self::ACT => $this->act(true)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('não é do resumo enviado');

        $this->finaliza($this->documentoAssinadoNoTablet());
    }

    public function test_act_fora_do_ar_falha_alto(): void
    {
        config(['signature.pades.tsa_url' => self::ACT]);
        Http::fake([self::ACT => Http::response('', 503)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A ACT recusou');

        $this->finaliza($this->documentoAssinadoNoTablet());
    }

    // ------------------------------------------------- configuração e limites

    public function test_senha_errada_falha_alto(): void
    {
        config(['signature.pades.certificate_password' => 'errada']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('senha errada');

        app(SignaturePdfSealer::class)->seal("%PDF-1.4\n%%EOF\n");
    }

    public function test_pdf_com_xref_stream_e_recusado(): void
    {
        $pdf = "%PDF-1.5\n1 0 obj\n<< /Type /XRef /Size 2 >>\nstream\nendstream\nendobj\nstartxref\n9\n%%EOF\n";

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('xref stream');

        app(SignaturePdfSealer::class)->seal($pdf);
    }

    public function test_seal_check_confere_certificado_e_act(): void
    {
        config(['signature.pades.tsa_url' => self::ACT]);
        Http::fake([self::ACT => $this->act()]);

        $this->artisan('signature:seal-check')
            ->expectsOutputToContain('CLUBE TESTE:00000000000191')
            ->expectsOutputToContain('Carimbo de tempo OK')
            ->assertSuccessful();
    }

    public function test_seal_check_aponta_certificado_ausente(): void
    {
        config(['signature.pades.certificate_path' => null]);

        $this->artisan('signature:seal-check')
            ->expectsOutputToContain('Lacre sem certificado')
            ->assertFailed();
    }

    public function test_manifesto_avisa_do_lacre_e_do_validador_oficial(): void
    {
        $documento = $this->documentoAssinadoNoTablet();

        $html = app(SignatureDocumentRenderer::class)->html($documento, SignatureDocumentRenderer::MODE_FINAL);

        $this->assertStringContainsString('lacrado digitalmente pelo clube', $html);
        $this->assertStringContainsString('validar.iti.gov.br', $html);
    }
}
