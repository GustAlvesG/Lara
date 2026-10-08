<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Mail\SignatureIdentityCodeMail;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Conferência de identidade por CÓDIGO ENVIADO POR E-MAIL.
 *
 * No tablet, o servidor manda um código de 6 números ao e-mail do signatário;
 * a pessoa o digita. O código só existe no e-mail (o banco guarda HMAC), tem
 * prazo, número de envios e as mesmas tentativas da conferência por CPF.
 */
class SignatureIdentityEmailCodeTest extends TestCase
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
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array{document: SignatureDocument, cookie: string} */
    private function sessaoAberta(): array
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura(
            ['template' => $this->criaModeloDeAssinatura(['identity_check' => SignatureTemplate::IDENTITY_EMAIL])],
            ['email' => 'maria@example.com'],
        ));

        $liberacao = app(SignatureRequestService::class)->issue($documento->signers()->first());

        $resposta = $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload($liberacao['token']),
        ]);

        return [
            'document' => $documento,
            'cookie' => $resposta->getCookie(EnsureSignatureKioskSession::COOKIE)->getValue(),
        ];
    }

    private function tablet(string $cookie): self
    {
        return $this->withCredentials()->withCookie(EnsureSignatureKioskSession::COOKIE, $cookie);
    }

    /** O código sai só no e-mail: é de lá que o teste o tira, como a pessoa. */
    private function codigoDoUltimoEmail(): string
    {
        preg_match('/>(\d{6})</', Mail::sent(SignatureIdentityCodeMail::class)->last()->render(), $m);

        return $m[1];
    }

    public function test_opcao_aparece_no_modelo(): void
    {
        $this->assertSame('Código enviado por e-mail', SignatureTemplate::IDENTITY_CHECKS[SignatureTemplate::IDENTITY_EMAIL]);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->get(route('signature-templates.create'))
            ->assertOk()
            ->assertSee('Código enviado por e-mail');
    }

    public function test_codigo_vai_ao_email_e_confirma_a_identidade(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        // O tablet sabe o modo, nunca o endereço.
        $this->tablet($cookie)->getJson(route('quiosque.session'))
            ->assertOk()
            ->assertJsonPath('rules.identity_check', 'email')
            ->assertDontSee('maria@example.com');

        $this->tablet($cookie)->postJson(route('quiosque.identity-code', $documento))
            ->assertOk()
            ->assertJsonPath('sends_left', 2)
            ->assertDontSee('maria@example.com');

        Mail::assertSent(SignatureIdentityCodeMail::class, fn($mail) => $mail->hasTo('maria@example.com'));

        $codigo = $this->codigoDoUltimoEmail();
        $liberacao = SignatureRequest::sole();

        // O banco não guarda o código, nem em sha256 puro.
        $this->assertNotSame(hash('sha256', $codigo), $liberacao->identity_code_hash);
        $this->assertStringNotContainsString($codigo, json_encode(SignatureAuditEvent::all()->pluck('payload')));

        $this->tablet($cookie)->postJson(route('quiosque.identity', $documento), ['codigo' => $codigo])->assertOk();

        $this->assertNotNull($liberacao->fresh()->identity_confirmed_at);
        $this->assertNull($liberacao->fresh()->identity_code_hash);

        $evento = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_IDENTITY_CODE_SENT)->sole();
        $this->assertSame('m***a@example.com', $evento->payload['email']);
    }

    public function test_cpf_nao_serve_no_lugar_do_codigo_e_codigo_errado_gasta_tentativa(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $this->tablet($cookie)->postJson(route('quiosque.identity-code', $documento))->assertOk();
        $codigo = $this->codigoDoUltimoEmail();
        $errado = $codigo === '000000' ? '111111' : '000000';

        $this->tablet($cookie)->postJson(route('quiosque.identity', $documento), ['cpf' => '1234'])->assertStatus(422);
        $this->tablet($cookie)->postJson(route('quiosque.identity', $documento), ['codigo' => $errado])->assertStatus(422);

        $this->assertSame(2, SignatureRequest::sole()->identity_attempts);
        $this->assertNull(SignatureRequest::sole()->identity_confirmed_at);
    }

    public function test_codigo_vencido_nao_vale(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $this->tablet($cookie)->postJson(route('quiosque.identity-code', $documento))->assertOk();
        $codigo = $this->codigoDoUltimoEmail();

        SignatureRequest::sole()->forceFill(['identity_code_expires_at' => now()->subSecond()])->save();

        $this->tablet($cookie)->postJson(route('quiosque.identity', $documento), ['codigo' => $codigo])->assertStatus(422);
    }

    public function test_reenvio_espera_um_minuto_troca_o_codigo_e_tem_teto(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $this->tablet($cookie)->postJson(route('quiosque.identity-code', $documento))->assertOk();
        $primeiro = $this->codigoDoUltimoEmail();

        // Logo em seguida: espera.
        $this->tablet($cookie)->postJson(route('quiosque.identity-code', $documento))->assertStatus(429);

        Carbon::setTestNow(now()->addSeconds(61));
        $this->tablet($cookie)->postJson(route('quiosque.identity-code', $documento))->assertOk();
        $segundo = $this->codigoDoUltimoEmail();

        Carbon::setTestNow(now()->addSeconds(61));
        $this->tablet($cookie)->postJson(route('quiosque.identity-code', $documento))->assertOk()->assertJsonPath('sends_left', 0);

        Carbon::setTestNow(now()->addSeconds(61));
        $this->tablet($cookie)->postJson(route('quiosque.identity-code', $documento))->assertStatus(429);

        Mail::assertSent(SignatureIdentityCodeMail::class, 3);

        // O código anterior deixou de valer quando o novo saiu.
        if ($primeiro !== $segundo) {
            $this->tablet($cookie)->postJson(route('quiosque.identity', $documento), ['codigo' => $primeiro])->assertStatus(422);
        }

        $this->tablet($cookie)->postJson(route('quiosque.identity', $documento), ['codigo' => $this->codigoDoUltimoEmail()])->assertOk();
    }

    public function test_falha_no_smtp_nao_conta_envio(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP fora do ar'));

        $this->tablet($cookie)->postJson(route('quiosque.identity-code', $documento))->assertStatus(503);

        $this->assertSame(0, SignatureRequest::sole()->identity_code_sends);
        $this->assertNull(SignatureRequest::sole()->identity_code_hash);
    }

    public function test_modelo_de_cpf_nao_envia_codigo(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura([], ['email' => 'maria@example.com']));
        $liberacao = app(SignatureRequestService::class)->issue($documento->signers()->first());
        $cookie = $this->postJson(route('quiosque.consume'), ['payload' => SignatureRequest::qrPayload($liberacao['token'])])
            ->getCookie(EnsureSignatureKioskSession::COOKIE)->getValue();

        $this->tablet($cookie)->postJson(route('quiosque.identity-code', $documento))->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_signatario_sem_email_trava_o_congelamento(): void
    {
        $documento = $this->criaDocumentoDeAssinatura([
            'template' => $this->criaModeloDeAssinatura(['identity_check' => SignatureTemplate::IDENTITY_EMAIL]),
        ]);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.freeze', $documento))
            ->assertSessionHas('error', fn($m) => str_contains($m, 'Informe o e-mail de: Maria de Souza'));

        $this->assertSame(SignatureDocument::STATUS_DRAFT, $documento->fresh()->status);
    }

    public function test_manifesto_diz_como_a_identidade_foi_conferida(): void
    {
        ['document' => $documento] = $this->sessaoAberta();

        $documento->signers()->first()->evidence()->create([
            'signature_path' => 'x.png',
            'accepted' => true,
            'server_signed_at' => now(),
        ]);

        $html = app(SignatureDocumentRenderer::class)->html($documento->fresh(['template', 'signers']), SignatureDocumentRenderer::MODE_FINAL);

        $this->assertStringContainsString('Conferência de identidade: Código enviado por e-mail (m***a@example.com)', $html);
    }
}
