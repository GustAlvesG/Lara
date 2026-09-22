<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * A sessão do tablet, pelo HTTP — o que o aparelho de verdade faz.
 *
 * A trava que mais importa aqui é a de propriedade: a sessão aberta por um QR
 * vale para AQUELE documento, e para mais nada. Trocar o id na URL não é um
 * caso hipotético — é o primeiro lugar em que alguém mexe.
 *
 * Os cookies vêm e voltam pelo próprio teste porque o cookie da sessão é
 * emitido criptografado: pegá-lo da resposta e devolvê-lo em claro é o que
 * reproduz o navegador do tablet.
 */
class SignatureKioskSessionTest extends TestCase
{
    use CreatesSignatureSchema;

    private SignatureRequestService $requests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));

        $this->requests = app(SignatureRequestService::class);
    }

    private function documentoLiberado(): SignatureDocument
    {
        return app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());
    }

    /** @return array{document: SignatureDocument, token: string, request: SignatureRequest} */
    private function comQrGerado(): array
    {
        $documento = $this->documentoLiberado();
        $liberacao = $this->requests->issue($documento->signers()->first());

        return [
            'document' => $documento,
            'token' => $liberacao['token'],
            'request' => $liberacao['request'],
        ];
    }

    /** Lê o QR como o tablet leria e devolve o valor do cookie de sessão. */
    private function leQr(string $token): string
    {
        $resposta = $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload($token),
        ]);

        $resposta->assertOk();

        return $resposta->getCookie(EnsureSignatureKioskSession::COOKIE)->getValue();
    }

    /**
     * Devolve o teste com o cookie da sessão armado, como o navegador do
     * tablet o mandaria.
     *
     * `withCredentials()` é obrigatório: sem ele, `getJson`/`postJson` não
     * enviam cookie nenhum (ver MakesHttpRequests::prepareCookiesForJsonRequest),
     * e toda rota do quiosque responderia 419 por motivo errado.
     *
     * `withCookie()`, e não `withUnencryptedCookie()`, porque o cookie do
     * quiosque passa pelo EncryptCookies como qualquer outro do app.
     */
    private function comSessao(string $cookie): self
    {
        return $this->withCredentials()
            ->withCookie(EnsureSignatureKioskSession::COOKIE, $cookie);
    }

    public function test_leitura_do_qr_abre_a_sessao_e_devolve_o_documento(): void
    {
        ['token' => $token, 'document' => $documento] = $this->comQrGerado();

        $resposta = $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload($token),
        ]);

        $resposta->assertOk()
            ->assertJsonPath('document.id', $documento->id)
            ->assertJsonPath('signer.name', 'Maria de Souza')
            ->assertJsonPath('rules.identity_check', 'partial');

        $cookie = $resposta->getCookie(EnsureSignatureKioskSession::COOKIE);

        $this->assertNotNull($cookie, 'A leitura do QR precisa emitir o cookie da sessão.');
        $this->assertTrue($cookie->isHttpOnly(), 'O cookie da sessão não pode ser lido por script.');
        $this->assertSame('strict', strtolower((string) $cookie->getSameSite()));
    }

    /**
     * O CPF cadastrado nunca vai para a tela: a conferência de identidade é
     * feita no servidor. Se ele viajasse, bastaria abrir o inspetor.
     */
    public function test_payload_da_sessao_nao_carrega_cpf(): void
    {
        ['token' => $token] = $this->comQrGerado();

        $resposta = $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload($token),
        ]);

        $resposta->assertOk();
        $this->assertStringNotContainsString('12345678909', $resposta->getContent());
    }

    public function test_qr_de_outro_sistema_nao_vira_consulta(): void
    {
        $this->postJson(route('quiosque.consume'), ['payload' => 'https://exemplo.com/algo'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Este QR Code não é de assinatura do Lara.');
    }

    public function test_segunda_leitura_do_mesmo_qr_e_recusada_pelo_http(): void
    {
        ['token' => $token] = $this->comQrGerado();

        $this->postJson(route('quiosque.consume'), ['payload' => SignatureRequest::qrPayload($token)])
            ->assertOk();

        $this->postJson(route('quiosque.consume'), ['payload' => SignatureRequest::qrPayload($token)])
            ->assertStatus(409);
    }

    public function test_sem_cookie_a_rota_do_tablet_responde_419(): void
    {
        ['document' => $documento] = $this->comQrGerado();

        $this->getJson(route('quiosque.session'))->assertStatus(419);
        $this->getJson(route('quiosque.pdf', $documento))->assertStatus(419);
    }

    public function test_cookie_forjado_responde_419(): void
    {
        $this->comSessao(str_repeat('a', 64))
            ->getJson(route('quiosque.session'))
            ->assertStatus(419);
    }

    public function test_sessao_entrega_o_pdf_do_proprio_documento(): void
    {
        ['token' => $token, 'document' => $documento] = $this->comQrGerado();

        $cookie = $this->leQr($token);

        $this->comSessao($cookie)
            ->get(route('quiosque.pdf', $documento))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /**
     * A trava central: a sessão é daquele documento. Trocar o id na URL não
     * abre o documento do próximo da fila.
     */
    public function test_sessao_nao_alcanca_outro_documento(): void
    {
        ['token' => $token, 'document' => $meu] = $this->comQrGerado();
        $alheio = $this->documentoLiberado();

        $cookie = $this->leQr($token);

        $this->comSessao($cookie)
            ->getJson(route('quiosque.pdf', $alheio))
            ->assertStatus(403);

        // A tentativa fica registrada na trilha do documento DA SESSÃO — sujar
        // a trilha do documento alheio seria deixar qualquer um escrever nela.
        $this->assertDatabaseHas('signature_audit_events', [
            'signature_document_id' => $meu->id,
            'event' => SignatureAuditEvent::EVENT_ACCESS_DENIED,
        ]);

        $this->assertDatabaseMissing('signature_audit_events', [
            'signature_document_id' => $alheio->id,
            'event' => SignatureAuditEvent::EVENT_ACCESS_DENIED,
        ]);
    }

    public function test_sessao_vencida_responde_419(): void
    {
        ['token' => $token] = $this->comQrGerado();

        $cookie = $this->leQr($token);

        $this->travel((int) config('signature.session_ttl_minutes') + 1)->minutes();

        $this->comSessao($cookie)
            ->getJson(route('quiosque.session'))
            ->assertStatus(419);
    }

    /**
     * Cancelar pelo painel derruba o tablet na requisição seguinte — é assim
     * que o cancelamento vale "em tempo real" sem broadcasting.
     */
    public function test_cancelamento_pelo_atendente_derruba_a_sessao(): void
    {
        ['token' => $token, 'document' => $documento] = $this->comQrGerado();

        $cookie = $this->leQr($token);

        app(SignatureDocumentService::class)->cancel($documento->fresh(), 'Associado desistiu', userId: 7);

        $this->comSessao($cookie)
            ->getJson(route('quiosque.session'))
            ->assertStatus(409)
            ->assertJsonPath('session_ended', true);
    }

    public function test_leitura_registrada_entra_na_auditoria(): void
    {
        ['token' => $token, 'document' => $documento] = $this->comQrGerado();

        $cookie = $this->leQr($token);

        $this->comSessao($cookie)
            ->postJson(route('quiosque.viewed', $documento), [
                'read_seconds' => 42,
                'scrolled_to_end' => true,
            ])
            ->assertOk();

        $evento = SignatureAuditEvent::where('signature_document_id', $documento->id)
            ->where('event', SignatureAuditEvent::EVENT_VIEWED)
            ->first();

        $this->assertNotNull($evento);
        $this->assertSame(42, $evento->payload['segundos_de_leitura']);
        $this->assertTrue($evento->payload['rolou_ate_o_fim']);
    }

    public function test_recusa_encerra_o_documento_e_a_sessao(): void
    {
        ['token' => $token, 'document' => $documento] = $this->comQrGerado();

        $cookie = $this->leQr($token);

        $this->comSessao($cookie)
            ->postJson(route('quiosque.refuse', $documento), ['reason' => 'Não concordo com o item 3'])
            ->assertOk();

        $documento->refresh();

        $this->assertSame(SignatureDocument::STATUS_REFUSED, $documento->status);
        $this->assertSame('Não concordo com o item 3', $documento->signers()->first()->refusal_reason);

        // A sessão acabou junto: o tablet volta à tela de espera.
        $this->comSessao($cookie)
            ->getJson(route('quiosque.session'))
            ->assertStatus(419);
    }

    public function test_encerrar_derruba_a_sessao(): void
    {
        ['token' => $token] = $this->comQrGerado();

        $cookie = $this->leQr($token);

        $this->comSessao($cookie)->postJson(route('quiosque.leave'))->assertOk();

        $this->comSessao($cookie)->getJson(route('quiosque.session'))->assertStatus(419);
    }

    /**
     * A rota de consumo é a única do sistema em que um token pode ser
     * adivinhado — e é por isso que ela tem o teto mais baixo do módulo.
     */
    public function test_consumo_tem_rate_limiting(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('quiosque.consume'), [
                'payload' => SignatureRequest::qrPayload(str_repeat('z', 64)),
            ]);
        }

        $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload(str_repeat('z', 64)),
        ])->assertStatus(429);
    }
}
