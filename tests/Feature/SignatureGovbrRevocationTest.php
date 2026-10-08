<?php

namespace Tests\Feature;

use App\Models\SignatureDocument;
use App\Models\SignatureGovbrCheck;
use App\Services\Signature\Pki\Certificates;
use App\Services\Signature\SignatureDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsGovbrSignedPdf;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * A conferência do PDF assinado, na parte que fala com a rede e na que aceita
 * certificado ICP-Brasil:
 *
 *  - revogação pela LCR que o certificado declara, assinada pela AC de cima,
 *    guardada até vencer (Pki\RevocationChecker e Pki\PkiRepository);
 *  - certificado ICP-Brasil = assinatura qualificada, na mesma aba;
 *  - AC intermediária baixada pelo AIA, quando a assinatura não a traz;
 *  - hora pelo /M do PDF quando o CMS não traz signingTime (PAdES).
 *
 * Nenhuma chamada sai de verdade: `Http::preventStrayRequests()`, e a LCR e
 * as ACs são de teste (BuildsGovbrSignedPdf).
 */
class SignatureGovbrRevocationTest extends TestCase
{
    use BuildsGovbrSignedPdf;
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    private const LCR = 'http://lcr.teste/ac.crl';

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

        config([
            'signature.pki.network' => true,
            // As LCR reais do gov.br ficam de fora do comando nos testes.
            'signature.pki.crl_urls' => [],
        ]);

        Http::preventStrayRequests();
    }

    private function documentoCongelado(): SignatureDocument
    {
        return app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());
    }

    /** @return array{cert: string, key: string} */
    private function certificadoComLcr(?array $ac = null, string $cpf = '12345678909'): array
    {
        return $this->govbrCertificado($ac ?? $this->ac, $cpf, 'MARIA DE SOUZA', 365, ['crlDistributionPoints = URI:' . self::LCR]);
    }

    private function envia(SignatureDocument $documento, string $bytes): SignatureGovbrCheck
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.govbr.store', $documento), [
                'documento' => UploadedFile::fake()->createWithContent('assinado.pdf', $bytes),
            ]);

        return SignatureGovbrCheck::latest('id')->firstOrFail();
    }

    private function assinaOriginal(SignatureDocument $documento, array $certificado, bool $semAtributos = false, array $cadeia = []): string
    {
        return $this->govbrAssina(
            Storage::disk(config('signature.disk'))->get($documento->original_path),
            $certificado,
            $semAtributos,
            $cadeia,
        );
    }

    /** @return array{key: string, label: string, ok: ?bool, detail: string}|null */
    private function linha(SignatureGovbrCheck $conferencia, string $chave): ?array
    {
        return collect($conferencia->signatures()[0]['checks'])->firstWhere('key', $chave);
    }

    // ---------------------------------------------------------------- revogação

    public function test_certificado_fora_da_lista_passa(): void
    {
        $outro = $this->certificadoComLcr(null, '52998224725');
        Http::fake([self::LCR => Http::response($this->govbrLcr($this->ac, [$outro['cert']]))]);

        $documento = $this->documentoCongelado();
        $conferencia = $this->envia($documento, $this->assinaOriginal($documento, $this->certificadoComLcr()));

        $this->assertTrue($this->linha($conferencia, 'revogacao')['ok']);
        $this->assertTrue($conferencia->valid);
    }

    public function test_certificado_revogado_reprova(): void
    {
        $certificado = $this->certificadoComLcr();
        Http::fake([self::LCR => Http::response($this->govbrLcr($this->ac, [$certificado['cert']]))]);

        $documento = $this->documentoCongelado();
        $conferencia = $this->envia($documento, $this->assinaOriginal($documento, $certificado));

        $revogacao = $this->linha($conferencia, 'revogacao');
        $this->assertFalse($revogacao['ok']);
        $this->assertStringContainsString('REVOGADO', $revogacao['detail']);
        $this->assertFalse($conferencia->valid);
        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $documento->fresh()->status);
    }

    public function test_lista_fora_do_ar_nao_reprova(): void
    {
        Http::fake([self::LCR => Http::response('', 503)]);

        $documento = $this->documentoCongelado();
        $conferencia = $this->envia($documento, $this->assinaOriginal($documento, $this->certificadoComLcr()));

        $revogacao = $this->linha($conferencia, 'revogacao');
        $this->assertNull($revogacao['ok']);
        $this->assertStringContainsString('Não foi possível obter', $revogacao['detail']);
        $this->assertTrue($conferencia->valid);
    }

    public function test_lista_fora_do_ar_reprova_quando_a_instalacao_exige(): void
    {
        config(['signature.pki.revocation_required' => true]);
        Http::fake([self::LCR => Http::response('', 503)]);

        $documento = $this->documentoCongelado();
        $conferencia = $this->envia($documento, $this->assinaOriginal($documento, $this->certificadoComLcr()));

        $this->assertFalse($this->linha($conferencia, 'revogacao')['ok']);
        $this->assertFalse($conferencia->valid);
    }

    public function test_lista_assinada_por_outra_ac_nao_vale(): void
    {
        $impostora = $this->govbrAc('AC Impostora');
        Http::fake([self::LCR => Http::response($this->govbrLcr($impostora))]);

        $documento = $this->documentoCongelado();
        $conferencia = $this->envia($documento, $this->assinaOriginal($documento, $this->certificadoComLcr()));

        $this->assertNull($this->linha($conferencia, 'revogacao')['ok']);
    }

    public function test_lista_vencida_nao_vale(): void
    {
        Http::fake([self::LCR => Http::response($this->govbrLcr($this->ac, [], -60, 7200))]);

        $documento = $this->documentoCongelado();
        $conferencia = $this->envia($documento, $this->assinaOriginal($documento, $this->certificadoComLcr()));

        $revogacao = $this->linha($conferencia, 'revogacao');
        $this->assertNull($revogacao['ok']);
        $this->assertStringContainsString('desatualizada', $revogacao['detail']);
    }

    public function test_lista_em_vigor_fica_guardada_e_nao_e_baixada_de_novo(): void
    {
        Http::fake([self::LCR => Http::response($this->govbrLcr($this->ac))]);

        $primeiro = $this->documentoCongelado();
        $this->envia($primeiro, $this->assinaOriginal($primeiro, $this->certificadoComLcr()));

        $segundo = $this->documentoCongelado();
        $conferencia = $this->envia($segundo, $this->assinaOriginal($segundo, $this->certificadoComLcr()));

        $this->assertTrue($this->linha($conferencia, 'revogacao')['ok']);
        Http::assertSentCount(1);
    }

    public function test_comando_renova_as_listas_ja_consultadas(): void
    {
        Http::fake([self::LCR => Http::response($this->govbrLcr($this->ac))]);

        $documento = $this->documentoCongelado();
        $this->envia($documento, $this->assinaOriginal($documento, $this->certificadoComLcr()));

        $this->artisan('signature:crl', ['--forcar' => true])
            ->expectsOutputToContain('1 de 1 listas em vigor')
            ->assertSuccessful();

        Http::assertSentCount(2);
    }

    public function test_sem_rede_o_comando_nao_faz_nada(): void
    {
        config(['signature.pki.network' => false]);

        $this->artisan('signature:crl')->assertSuccessful();

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------- ICP-Brasil

    public function test_certificado_icp_brasil_vale_como_assinatura_qualificada(): void
    {
        $raizIcp = $this->govbrAc('AC Raiz ICP de Teste');
        $this->icpConfiaEm($raizIcp);

        $documento = $this->documentoCongelado();
        $conferencia = $this->envia($documento, $this->assinaOriginal($documento, $this->govbrCertificado($raizIcp, '12345678909')));

        $this->assertTrue($conferencia->valid);
        $this->assertSame('icp-brasil', $conferencia->signatures()[0]['kind']);
        $this->assertStringContainsString('ICP-Brasil', $this->linha($conferencia, 'cadeia')['detail']);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.show', [$documento, 'aba' => 'govbr']))
            ->assertOk()
            ->assertSee('Certificado ICP-Brasil — assinatura eletrônica qualificada');
    }

    public function test_assinatura_do_gov_br_segue_avancada(): void
    {
        $documento = $this->documentoCongelado();
        $conferencia = $this->envia($documento, $this->assinaOriginal($documento, $this->govbrCertificado($this->ac, '12345678909')));

        $this->assertSame('govbr', $conferencia->signatures()[0]['kind']);
    }

    public function test_ac_intermediaria_que_falta_e_baixada_pelo_aia(): void
    {
        $raizIcp = $this->govbrAc('AC Raiz ICP de Teste');
        $this->icpConfiaEm($raizIcp);
        $intermediaria = $this->govbrAcIntermediaria($raizIcp);

        Http::fake(['http://ac.teste/intermediaria.p7b' => Http::response(Certificates::pemToDer($intermediaria['cert']))]);

        $certificado = $this->govbrCertificado($intermediaria, '12345678909', 'MARIA DE SOUZA', 365, [
            'authorityInfoAccess = caIssuers;URI:http://ac.teste/intermediaria.p7b',
        ]);

        $documento = $this->documentoCongelado();
        $conferencia = $this->envia($documento, $this->assinaOriginal($documento, $certificado));

        $this->assertTrue($conferencia->valid, json_encode($conferencia->signatures()[0]['checks']));
        $this->assertSame('icp-brasil', $conferencia->signatures()[0]['kind']);
    }

    public function test_ac_intermediaria_que_falta_sem_rede_reprova_a_cadeia(): void
    {
        config(['signature.pki.network' => false]);

        $raizIcp = $this->govbrAc('AC Raiz ICP de Teste');
        $this->icpConfiaEm($raizIcp);
        $intermediaria = $this->govbrAcIntermediaria($raizIcp);

        $certificado = $this->govbrCertificado($intermediaria, '12345678909', 'MARIA DE SOUZA', 365, [
            'authorityInfoAccess = caIssuers;URI:http://ac.teste/intermediaria.p7b',
        ]);

        $documento = $this->documentoCongelado();
        $conferencia = $this->envia($documento, $this->assinaOriginal($documento, $certificado));

        $this->assertFalse($this->linha($conferencia, 'cadeia')['ok']);
        Http::assertNothingSent();
    }

    public function test_ac_intermediaria_embutida_na_assinatura_dispensa_a_rede(): void
    {
        config(['signature.pki.network' => false]);

        $raizIcp = $this->govbrAc('AC Raiz ICP de Teste');
        $this->icpConfiaEm($raizIcp);
        $intermediaria = $this->govbrAcIntermediaria($raizIcp);

        $documento = $this->documentoCongelado();
        $conferencia = $this->envia($documento, $this->assinaOriginal(
            $documento,
            $this->govbrCertificado($intermediaria, '12345678909'),
            false,
            [$intermediaria['cert']],
        ));

        $this->assertTrue($conferencia->valid);
    }

    public function test_sem_signing_time_a_hora_vem_do_m_do_pdf(): void
    {
        $documento = $this->documentoCongelado();
        $conferencia = $this->envia($documento, $this->assinaOriginal($documento, $this->govbrCertificado($this->ac, '12345678909'), true));

        $data = $this->linha($conferencia, 'data');
        $this->assertTrue($data['ok']);
        $this->assertStringContainsString('programa de assinatura', $data['detail']);
        $this->assertNotNull($conferencia->signatures()[0]['signed_at']);
        $this->assertTrue($conferencia->valid);
    }
}
