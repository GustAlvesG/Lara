<?php

namespace Tests\Feature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * A liberação por QR Code — a regra central do módulo.
 *
 * O tablet não é pareado e não guarda token de longa duração. Cada documento
 * exige um QR novo, aquele QR vale uma leitura só, e a sessão que nasce dele
 * morre junto com o atendimento.
 *
 * O que este teste protege, caso a caso:
 *
 *  - o token NUNCA está em claro no banco;
 *  - a segunda leitura é recusada — inclusive em outro aparelho;
 *  - regerar invalida o anterior;
 *  - cancelar derruba o QR e a sessão;
 *  - o prazo curto é obrigatório.
 *
 * Sem `RefreshDatabase` e sem tocar no model `User` — ver CreatesSignatureSchema.
 */
class SignatureQrTokenTest extends TestCase
{
    use CreatesSignatureSchema;

    private SignatureRequestService $requests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();

        Storage::fake(config('signature.disk'));

        $this->requests = app(SignatureRequestService::class);
    }

    /** Documento pronto para assinar, com o signatário na fila. */
    private function documentoLiberavel(): SignatureDocument
    {
        $documento = $this->criaDocumentoDeAssinatura();

        return app(SignatureDocumentService::class)->freeze($documento);
    }

    private function signatarioDe(SignatureDocument $documento): SignatureSigner
    {
        return $documento->signers()->first();
    }

    public function test_token_nunca_e_gravado_em_claro(): void
    {
        $documento = $this->documentoLiberavel();

        $liberacao = $this->requests->issue($this->signatarioDe($documento));

        $linha = DB::table('signature_requests')->where('id', $liberacao['request']->id)->first();

        $this->assertSame(hash('sha256', $liberacao['token']), $linha->token_hash);
        $this->assertStringNotContainsString($liberacao['token'], json_encode($linha));
        $this->assertSame(64, strlen($liberacao['token']));
    }

    public function test_conteudo_do_qr_tem_formato_proprio(): void
    {
        $documento = $this->documentoLiberavel();

        $liberacao = $this->requests->issue($this->signatarioDe($documento));

        $this->assertStringStartsWith('LARA-SIGN:v1:', $liberacao['payload']);
        $this->assertSame(
            $liberacao['token'],
            SignatureRequest::tokenFromQrPayload($liberacao['payload']),
        );
    }

    /**
     * O leitor do tablet não pode navegar para uma URL que apareceu num QR
     * qualquer — é o que separa "ler um código deste sistema" de "abrir o que
     * estiver no papel".
     */
    public function test_qr_de_outra_coisa_e_ignorado(): void
    {
        $this->assertNull(SignatureRequest::tokenFromQrPayload('https://exemplo.com/phishing'));
        $this->assertNull(SignatureRequest::tokenFromQrPayload('LARA-SIGN:v1:curto'));
        $this->assertNull(SignatureRequest::tokenFromQrPayload('LARA-SIGN:v2:' . str_repeat('a', 64)));
        $this->assertNull(SignatureRequest::tokenFromQrPayload(null));
    }

    public function test_primeira_leitura_abre_a_sessao(): void
    {
        $documento = $this->documentoLiberavel();
        $liberacao = $this->requests->issue($this->signatarioDe($documento));

        $sessao = $this->requests->consume($liberacao['token'], '10.0.0.15', 'Tablet/1.0');

        $this->assertSame(SignatureRequest::STATUS_CONSUMED, $sessao['request']->status);
        $this->assertSame('10.0.0.15', $sessao['request']->consumed_ip);
        $this->assertNotNull($sessao['request']->consumed_at);
        $this->assertNotNull($sessao['request']->session_expires_at);

        // O cookie também é guardado só como hash.
        $this->assertSame(
            hash('sha256', $sessao['session_token']),
            $sessao['request']->session_hash,
        );
    }

    /**
     * O caso que motiva o desenho inteiro: alguém fotografa o QR na tela do
     * atendente e tenta abrir no próprio aparelho.
     */
    public function test_segunda_leitura_do_mesmo_qr_e_bloqueada_em_qualquer_aparelho(): void
    {
        $documento = $this->documentoLiberavel();
        $liberacao = $this->requests->issue($this->signatarioDe($documento));

        $this->requests->consume($liberacao['token'], '10.0.0.15', 'Tablet do balcão');

        $this->expectException(\App\Exceptions\SignatureSessionException::class);
        $this->expectExceptionMessage('já foi usado');

        $this->requests->consume($liberacao['token'], '200.1.2.3', 'Celular de outra pessoa');
    }

    public function test_releitura_bloqueada_vira_evento_de_auditoria(): void
    {
        $documento = $this->documentoLiberavel();
        $liberacao = $this->requests->issue($this->signatarioDe($documento));

        $this->requests->consume($liberacao['token'], '10.0.0.15');

        try {
            $this->requests->consume($liberacao['token'], '200.1.2.3');
        } catch (\App\Exceptions\SignatureSessionException) {
            // esperado
        }

        $evento = SignatureAuditEvent::where('signature_document_id', $documento->id)
            ->where('event', SignatureAuditEvent::EVENT_QR_REUSE_BLOCKED)
            ->first();

        $this->assertNotNull($evento, 'A releitura precisa ficar registrada.');
        $this->assertSame('200.1.2.3', $evento->ip);
        $this->assertSame(SignatureAuditEvent::ACTOR_KIOSK, $evento->actor_type);
    }

    public function test_qr_expirado_nao_abre_sessao(): void
    {
        $documento = $this->documentoLiberavel();
        $liberacao = $this->requests->issue($this->signatarioDe($documento));

        $this->travel((int) config('signature.qr_ttl_seconds') + 1)->seconds();

        try {
            $this->requests->consume($liberacao['token']);
            $this->fail('Um QR expirado não pode abrir sessão.');
        } catch (\App\Exceptions\SignatureSessionException $e) {
            $this->assertSame(410, $e->status);
        }

        $this->assertSame(SignatureRequest::STATUS_EXPIRED, $liberacao['request']->fresh()->status);
    }

    public function test_regerar_invalida_o_qr_anterior(): void
    {
        $documento = $this->documentoLiberavel();
        $signatario = $this->signatarioDe($documento);

        $antigo = $this->requests->issue($signatario);
        $novo = $this->requests->issue($signatario);

        $this->assertSame(SignatureRequest::STATUS_SUPERSEDED, $antigo['request']->fresh()->status);

        try {
            $this->requests->consume($antigo['token']);
            $this->fail('O QR antigo não pode mais valer.');
        } catch (\App\Exceptions\SignatureSessionException $e) {
            $this->assertStringContainsString('substituído', $e->getMessage());
        }

        // O novo continua valendo.
        $sessao = $this->requests->consume($novo['token']);
        $this->assertSame(SignatureRequest::STATUS_CONSUMED, $sessao['request']->status);
    }

    public function test_liberacao_cancelada_nao_abre_sessao(): void
    {
        $documento = $this->documentoLiberavel();
        $liberacao = $this->requests->issue($this->signatarioDe($documento));

        $this->requests->cancel($liberacao['request'], userId: 7);

        $this->expectException(\App\Exceptions\SignatureSessionException::class);
        $this->expectExceptionMessage('cancelado');

        $this->requests->consume($liberacao['token']);
    }

    public function test_token_inexistente_responde_de_forma_generica(): void
    {
        try {
            $this->requests->consume(str_repeat('z', 64));
            $this->fail('Token inexistente deveria ser recusado.');
        } catch (\App\Exceptions\SignatureSessionException $e) {
            $this->assertSame(404, $e->status);
            // Sem dizer se o token nunca existiu ou se é de outro documento.
            $this->assertStringContainsString('inválido', $e->getMessage());
        }
    }

    public function test_documento_em_rascunho_nao_pode_ser_liberado(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->expectException(SignatureDocumentLockedException::class);
        $this->expectExceptionMessage('Congele o documento');

        $this->requests->issue($this->signatarioDe($documento));
    }

    /**
     * Fila em ordem: a testemunha não assina antes de quem ela testemunha.
     */
    public function test_segundo_signatario_so_e_liberado_depois_do_primeiro(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $testemunha = SignatureSigner::create([
            'signature_document_id' => $documento->id,
            'name' => 'João Testemunha',
            'cpf' => '98765432100',
            'role' => SignatureSigner::ROLE_WITNESS,
            'position' => 2,
        ]);

        app(SignatureDocumentService::class)->freeze($documento);

        $this->expectException(SignatureDocumentLockedException::class);
        $this->expectExceptionMessage('Antes dele assina');

        $this->requests->issue($testemunha->fresh());
    }

    public function test_faixa_de_ip_restringe_o_consumo_quando_configurada(): void
    {
        config(['signature.allowed_ips' => ['192.168.10.0/24']]);

        $documento = $this->documentoLiberavel();
        $liberacao = $this->requests->issue($this->signatarioDe($documento));

        try {
            $this->requests->consume($liberacao['token'], '200.1.2.3');
            $this->fail('IP de fora da faixa deveria ser recusado.');
        } catch (\App\Exceptions\SignatureSessionException $e) {
            $this->assertSame(403, $e->status);
        }

        $this->assertDatabaseHas('signature_audit_events', [
            'signature_document_id' => $documento->id,
            'event' => SignatureAuditEvent::EVENT_QR_IP_BLOCKED,
        ]);

        // Dentro da faixa, passa — e o token continua valendo, porque a recusa
        // por IP não consome a liberação.
        $sessao = $this->requests->consume($liberacao['token'], '192.168.10.44');
        $this->assertSame(SignatureRequest::STATUS_CONSUMED, $sessao['request']->status);
    }

    public function test_sem_faixa_configurada_qualquer_ip_consome(): void
    {
        config(['signature.allowed_ips' => []]);

        $documento = $this->documentoLiberavel();
        $liberacao = $this->requests->issue($this->signatarioDe($documento));

        $sessao = $this->requests->consume($liberacao['token'], '200.1.2.3');

        $this->assertSame(SignatureRequest::STATUS_CONSUMED, $sessao['request']->status);
    }

    public function test_comando_expira_qr_nao_lido_e_sessao_parada(): void
    {
        $documento = $this->documentoLiberavel();
        $naoLido = $this->requests->issue($this->signatarioDe($documento));

        $outro = $this->documentoLiberavel();
        $lido = $this->requests->issue($this->signatarioDe($outro));
        $this->requests->consume($lido['token'], '10.0.0.15');

        $this->travel((int) config('signature.session_ttl_minutes') + 1)->minutes();

        $this->artisan('signature:expire')->assertSuccessful();

        $this->assertSame(SignatureRequest::STATUS_EXPIRED, $naoLido['request']->fresh()->status);
        $this->assertSame(SignatureRequest::STATUS_EXPIRED, $lido['request']->fresh()->status);
    }

    public function test_comando_expira_documento_congelado_e_esquecido(): void
    {
        $documento = $this->documentoLiberavel();

        $this->travel((int) config('signature.document_ttl_hours') + 1)->hours();

        $this->artisan('signature:expire')->assertSuccessful();

        $documento->refresh();

        $this->assertSame(SignatureDocument::STATUS_EXPIRED, $documento->status);
        $this->assertSame(SignatureSigner::STATUS_EXPIRED, $documento->signers()->first()->status);
    }
}
